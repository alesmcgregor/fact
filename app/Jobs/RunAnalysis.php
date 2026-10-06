<?php

namespace App\Jobs;

use App\Contracts\AnalysisProvider;
use App\Contracts\ContentFetcher;
use App\Data\FetchedContent;
use App\Enums\AnalysisStatus;
use App\Exceptions\AnalysisProviderException;
use App\Exceptions\ContentFetchException;
use App\Models\Analysis;
use App\Models\AnalysisEvent;
use App\Services\VerificationScorer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunAnalysis implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 240;

    public int $tries = 4;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $analysisId) {}

    public function handle(ContentFetcher $contentFetcher, AnalysisProvider $provider, VerificationScorer $scorer): void
    {
        $analysis = Analysis::query()->find($this->analysisId);

        if ($analysis === null || $analysis->status === AnalysisStatus::Completed) {
            return;
        }

        App::setLocale($analysis->locale);
        $analysis->forceFill(['status' => AnalysisStatus::Running, 'failure_code' => null])->save();

        try {
            $this->recordStage($analysis, 'validating_url');
            $demoMode = config('fact.analysis_driver') !== 'openai';

            if ($demoMode) {
                $content = new FetchedContent(__('app.demo_title'), '', null, $analysis->submitted_url);
            } else {
                if (config('fact.content_driver') !== 'safe') {
                    throw new AnalysisProviderException('content_provider_not_configured');
                }

                $this->recordStage($analysis, 'fetching_content');
                if ($analysis->content_excerpt !== null) {
                    $content = new FetchedContent($analysis->content_title, $analysis->content_excerpt, $analysis->content_language,
                        $analysis->normalized_url, $analysis->content_metadata ?? []);
                } elseif ($analysis->supplied_transcript) {
                    $content = new FetchedContent(__('app.transcript_user_supplied'), $analysis->supplied_transcript, null, $analysis->submitted_url,
                        ['platform' => 'user_input', 'transcript' => 'user_supplied', 'truncated' => false]);
                } else {
                    $content = $contentFetcher->fetch($analysis->submitted_url);
                }
                $content = new FetchedContent($content->title, $content->text, $content->language, $content->finalUrl,
                    [...$content->metadata, 'include_perspectives' => $analysis->include_perspectives]);
                $this->recordStage($analysis, 'extracting_content');
                $analysis->forceFill([
                    'normalized_url' => $content->finalUrl,
                    'content_title' => $content->title,
                    'content_excerpt' => $content->text,
                    'content_language' => $content->language,
                    'content_metadata' => $content->metadata,
                ])->save();
            }

            $this->recordStage($analysis, 'searching_evidence');
            $result = $provider->analyze($content, $analysis->locale);
            $this->recordStage($analysis, 'evaluating_evidence');
            $claimResults = [];

            foreach (array_values($result['claims'] ?? []) as $index => $claimData) {
                if (! is_array($claimData) || trim((string) ($claimData['statement'] ?? '')) === '') {
                    continue;
                }

                $evidenceRows = is_array($claimData['evidence'] ?? null) ? $claimData['evidence'] : [];
                $scoring = $scorer->scoreClaim($evidenceRows, (bool) ($result['search_performed'] ?? false));
                $claim = $analysis->claims()->create([
                    'position' => $index + 1,
                    'statement' => $claimData['statement'],
                    'classification' => $claimData['classification'] ?? 'factual',
                    'verification_status' => $scoring['status'],
                    'explanation' => $claimData['explanation'] ?? null,
                    'evidence_score' => $scoring['score'] ?? 0,
                    'supporting_official_count' => $scoring['supporting_official_count'],
                    'supporting_independent_count' => $scoring['supporting_independent_count'],
                    'contradicting_count' => $scoring['contradicting_count'],
                ]);

                foreach ($evidenceRows as $evidence) {
                    if (! is_array($evidence) || ! filter_var($evidence['url'] ?? null, FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    $claim->evidence()->create([
                        'analysis_id' => $analysis->id,
                        'title' => $evidence['title'] ?? parse_url($evidence['url'], PHP_URL_HOST),
                        'publisher' => $evidence['publisher'] ?? parse_url($evidence['url'], PHP_URL_HOST),
                        'url' => $evidence['url'],
                        'source_type' => $evidence['source_type'] ?? 'independent',
                        'identity_url' => $evidence['identity_url'] ?? null,
                        'relationship' => $evidence['relationship'] ?? 'context',
                        'summary' => $evidence['summary'] ?? null,
                        'published_at' => $evidence['published_at'] ?? null,
                        'retrieved_at' => now(),
                    ]);
                }

                $claimResults[] = [
                    'classification' => $claimData['classification'] ?? 'factual',
                    'verification_status' => $scoring['status'],
                    'evidence_score' => $scoring['score'],
                ];
            }

            $overall = $scorer->aggregate($claimResults, (bool) ($result['search_performed'] ?? false));
            $signals = is_array($result['signals'] ?? null) ? $result['signals'] : [];
            $limitations = is_array($result['limitations'] ?? null) ? $result['limitations'] : [];

            if (! $demoMode) {
                $limitations[] = __('app.media_not_checked');
                if ($content->metadata['truncated'] ?? false) {
                    $limitations[] = __('app.truncated_content');
                }
                if (($content->metadata['transcript'] ?? '') === 'user_supplied') {
                    $limitations[] = __('app.supplied_transcript_note');
                }
                if (($content->metadata['transcript'] ?? '') === 'automatic') {
                    $limitations[] = __('app.automatic_transcript_note');
                }
            }

            $this->recordStage($analysis, 'generating_report');
            $analysis->forceFill([
                'status' => AnalysisStatus::Completed,
                'stage' => 'complete',
                'summary' => $result['summary'] ?? '',
                'research_context' => $result['research_context'] ?? [],
                'verification_status' => $overall['status'],
                'evidence_score' => $overall['score'],
                'manipulation_level' => $this->manipulationLevel($signals),
                'manipulation_signals' => array_slice($signals, 0, 8),
                'limitations' => array_values(array_unique($limitations)),
                'demo_mode' => (bool) ($result['demo_mode'] ?? $demoMode),
                'completed_at' => now(),
            ])->save();
            $this->recordStage($analysis, 'complete');
        } catch (ContentFetchException $exception) {
            if ($exception->failureCode === 'transcript_pending' && $this->job !== null && $this->attempts() < $this->tries) {
                $analysis->forceFill(['status' => AnalysisStatus::Queued, 'stage' => 'fetching_content'])->save();
                $this->release(30);

                return;
            }
            $this->fail($analysis, $exception->failureCode);
        } catch (AnalysisProviderException $exception) {
            if ($exception->getMessage() === 'provider_rate_limited' && $this->job !== null && $this->attempts() < $this->tries) {
                $analysis->forceFill(['status' => AnalysisStatus::Queued, 'stage' => 'searching_evidence'])->save();
                $this->release(max(30, min(180, $exception->getCode())));

                return;
            }
            Log::warning('FACT provider could not generate report.', ['analysis_public_id' => $analysis->public_id,
                'failure' => $exception->getMessage()]);
            $failureCode = $exception->getMessage() === 'provider_rate_limited' ? 'provider_rate_limited' : (in_array($exception->getMessage(), ['provider_not_configured', 'content_provider_not_configured'], true)
                ? 'provider_not_configured'
                : 'analysis_unavailable');
            $this->fail($analysis, $failureCode);
        } catch (Throwable $exception) {
            Log::error('FACT analysis failed.', [
                'analysis_public_id' => $analysis->public_id,
                'exception' => $exception::class,
            ]);
            $this->fail($analysis, 'analysis_unavailable');
        } finally {
            App::setLocale(config('app.locale'));
        }
    }

    private function recordStage(Analysis $analysis, string $stage): void
    {
        $analysis->forceFill(['stage' => $stage])->save();
        AnalysisEvent::query()->create([
            'analysis_id' => $analysis->id,
            'stage' => $stage,
            'state' => 'complete',
            'details' => [],
            'created_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $analysis = Analysis::query()->find($this->analysisId);
        if ($analysis !== null && $analysis->status !== AnalysisStatus::Completed) {
            $this->fail($analysis, 'analysis_unavailable');
        }
    }

    private function fail(Analysis $analysis, string $failureCode): void
    {
        $analysis->forceFill([
            'status' => AnalysisStatus::Failed,
            'stage' => 'failed',
            'failure_code' => $failureCode,
            'completed_at' => now(),
        ])->save();
        AnalysisEvent::query()->create([
            'analysis_id' => $analysis->id,
            'stage' => 'failed',
            'state' => 'failed',
            'details' => ['code' => $failureCode],
            'created_at' => now(),
        ]);
    }

    /** @param list<array{level?: string}> $signals */
    private function manipulationLevel(array $signals): ?string
    {
        if ($signals === []) {
            return null;
        }

        $levels = array_column($signals, 'level');

        if (in_array('high', $levels, true)) {
            return 'high';
        }

        if (in_array('medium', $levels, true) || count($signals) >= 2) {
            return 'medium';
        }

        return 'low';
    }
}
