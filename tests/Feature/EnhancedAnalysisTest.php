<?php

namespace Tests\Feature;

use App\Contracts\AnalysisProvider;
use App\Contracts\ContentFetcher;
use App\Exceptions\AnalysisProviderException;
use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Services\VerificationScorer;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnhancedAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_reuses_youtube_aliases_without_dispatching_twice(): void
    {
        Queue::fake([RunAnalysis::class]);
        $first = $this->postJson('/api/analyses', ['url' => 'https://youtu.be/YPbNMPulcdI?t=10', 'locale' => 'tr'])->assertAccepted();
        $second = $this->postJson('/api/analyses', ['url' => 'https://www.youtube.com/watch?v=YPbNMPulcdI&utm_source=test', 'locale' => 'tr'])->assertAccepted();
        $second->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('meta.reused', true);
        $this->assertDatabaseCount('analyses', 1);
        Queue::assertPushed(RunAnalysis::class, 1);
    }

    public function test_returns_completed_report_without_spending_again(): void
    {
        Queue::fake([RunAnalysis::class]);
        $first = $this->postJson('/api/analyses', ['url' => 'https://example.org/news', 'locale' => 'en']);
        $analysis = Analysis::query()->firstOrFail();
        $analysis->update(['status' => 'completed', 'stage' => 'complete', 'completed_at' => now()]);
        $second = $this->postJson('/api/analyses', ['url' => 'https://example.org/news', 'locale' => 'en'])->assertOk();
        $second->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('meta.reused', true);
        Queue::assertPushed(RunAnalysis::class, 1);
    }

    public function test_explicit_refresh_creates_new_report_after_cooldown(): void
    {
        Queue::fake([RunAnalysis::class]);
        $first = $this->postJson('/api/analyses', ['url' => 'https://example.org/news', 'locale' => 'en']);
        Analysis::query()->firstOrFail()->update(['status' => 'completed', 'completed_at' => now()->subMinutes(6)]);
        $second = $this->postJson('/api/analyses', ['url' => 'https://example.org/news', 'locale' => 'en', 'refresh' => true])->assertAccepted();
        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('analyses', 2);
        Queue::assertPushed(RunAnalysis::class, 2);
    }

    public function test_refresh_cannot_bypass_cooldown(): void
    {
        Queue::fake([RunAnalysis::class]);
        $first = $this->postJson('/api/analyses', ['url' => 'https://example.org/news', 'locale' => 'en']);
        Analysis::query()->firstOrFail()->update(['status' => 'completed', 'completed_at' => now()]);
        $this->postJson('/api/analyses', ['url' => 'https://example.org/news', 'locale' => 'en', 'refresh' => true])
            ->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        Queue::assertPushed(RunAnalysis::class, 1);
    }

    public function test_perspective_option_uses_a_separate_cache_entry(): void
    {
        Queue::fake([RunAnalysis::class]);
        $this->postJson('/api/analyses', ['url' => 'https://example.org/news', 'locale' => 'en'])->assertAccepted();
        $this->postJson('/api/analyses', ['url' => 'https://example.org/news', 'locale' => 'en', 'include_perspectives' => true])->assertAccepted();
        $this->assertDatabaseCount('analyses', 2);
        Queue::assertPushed(RunAnalysis::class, 2);
    }

    public function test_rejects_oversized_supplied_transcript_before_queueing(): void
    {
        Queue::fake([RunAnalysis::class]);
        $this->postJson('/api/analyses', ['url' => 'https://example.org/news', 'locale' => 'en', 'transcript' => str_repeat('x', 12001)])
            ->assertUnprocessable()->assertJsonValidationErrors('transcript');
        Queue::assertNothingPushed();
    }

    public function test_supplied_transcript_job_skips_platform_fetch_and_discloses_provenance(): void
    {
        config(['fact.analysis_driver' => 'openai', 'fact.content_driver' => 'safe']);
        $analysis = Analysis::factory()->create(['locale' => 'tr', 'supplied_transcript' => 'A user supplied factual statement in a transcript.']);
        $fetcher = $this->mock(ContentFetcher::class);
        $fetcher->shouldNotReceive('fetch');
        $provider = $this->mock(AnalysisProvider::class);
        $provider->shouldReceive('analyze')->once()->withArgs(fn ($content, $locale): bool => $content->metadata['transcript'] === 'user_supplied' && $locale === 'tr')
            ->andReturn(['summary' => 'Unverified.', 'claims' => [], 'signals' => [], 'limitations' => [], 'search_performed' => true]);
        (new RunAnalysis($analysis->id))->handle($fetcher, $provider, app(VerificationScorer::class));
        $analysis->refresh();
        $this->assertSame('completed', $analysis->status->value);
        $this->assertContains(__('app.supplied_transcript_note', locale: 'tr'), $analysis->limitations);
        $this->assertSame('user_supplied', $analysis->content_metadata['transcript']);
    }

    public function test_cached_report_is_not_shared_across_locale_or_transcript_changes(): void
    {
        Queue::fake([RunAnalysis::class]);
        $this->postJson('/api/analyses', ['url' => 'https://example.org/news', 'locale' => 'en'])->assertAccepted();
        $this->postJson('/api/analyses', ['url' => 'https://example.org/news', 'locale' => 'tr'])->assertAccepted();
        $this->assertDatabaseCount('analyses', 2);
        Queue::assertPushed(RunAnalysis::class, 2);
    }

    public function test_worker_timeout_marks_analysis_failed_instead_of_leaving_it_running(): void
    {
        $analysis = Analysis::factory()->create(['status' => 'running']);
        (new RunAnalysis($analysis->id))->failed(new \RuntimeException('Worker timeout'));
        $this->assertSame('failed', $analysis->fresh()->status->value);
        $this->assertSame('analysis_unavailable', $analysis->fresh()->failure_code);
    }

    public function test_rate_limit_releases_job_and_reuses_fetched_text(): void
    {
        config(['fact.analysis_driver' => 'openai', 'fact.content_driver' => 'safe']);
        $analysis = Analysis::factory()->create(['content_title' => 'Title', 'content_excerpt' => 'Previously fetched text', 'content_metadata' => []]);
        $fetcher = $this->mock(ContentFetcher::class);
        $fetcher->shouldNotReceive('fetch');
        $provider = $this->mock(AnalysisProvider::class);
        $provider->shouldReceive('analyze')->once()->andThrow(new AnalysisProviderException('provider_rate_limited', 60));
        $queueJob = $this->mock(Job::class);
        $queueJob->shouldReceive('attempts')->andReturn(1);
        $queueJob->shouldReceive('release')->once()->with(60);
        $job = new RunAnalysis($analysis->id);
        $job->setJob($queueJob);
        $job->handle($fetcher, $provider, app(VerificationScorer::class));
        $this->assertSame('queued', $analysis->fresh()->status->value);
        $this->assertSame('Previously fetched text', $analysis->fresh()->content_excerpt);
    }
}
