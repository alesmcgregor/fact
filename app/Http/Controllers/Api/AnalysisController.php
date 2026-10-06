<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnalysisRequest;
use App\Http\Resources\AnalysisResource;
use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Models\AnalysisEvent;
use App\Services\Content\AnalysisUrlNormalizer;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class AnalysisController extends Controller
{
    public function store(StoreAnalysisRequest $request, AnalysisUrlNormalizer $normalizer): JsonResponse
    {
        $data = $request->validated();
        $url = $normalizer->normalize($data['url']);
        $fingerprint = hash('sha256', json_encode([$url, $data['locale'], (bool) ($data['include_perspectives'] ?? false),
            hash('sha256', trim($data['transcript'] ?? '')), config('fact.engine_version'), config('fact.analysis_driver')]));
        try {
            [$analysis, $reused] = Cache::lock('analysis-create:'.$fingerprint, 10)->block(3, function () use ($data, $url, $fingerprint): array {
                $existing = Analysis::query()->where('request_fingerprint', $fingerprint)
                    ->where('expires_at', '>', now())
                    ->where(function ($query) use ($data): void {
                        $query->where(function ($pending): void {
                            $pending->whereIn('status', ['queued', 'running'])->where('updated_at', '>', now()->subMinutes(10));
                        })->orWhere(function ($complete) use ($data): void {
                            $complete->where('status', 'completed')->where('completed_at', '>',
                                ($data['refresh'] ?? false) ? now()->subMinutes(5) : now()->subDays(config('fact.reuse_days')));
                        });
                    })->latest('id')->first();
                if ($existing) {
                    return [$existing, true];
                }
                $analysis = Analysis::query()->create([
                    'submitted_url' => trim($data['url']), 'normalized_url' => $url, 'request_fingerprint' => $fingerprint,
                    'locale' => $data['locale'], 'include_perspectives' => $data['include_perspectives'] ?? false,
                    'supplied_transcript' => trim($data['transcript'] ?? '') ?: null,
                    'status' => AnalysisStatus::Queued, 'stage' => 'queued',
                ]);
                AnalysisEvent::query()->create([
                    'analysis_id' => $analysis->id, 'stage' => 'queued', 'state' => 'complete', 'details' => [], 'created_at' => now(),
                ]);
                try {
                    RunAnalysis::dispatch($analysis->id)->afterCommit();
                } catch (\Throwable) {
                    $analysis->forceFill(['status' => AnalysisStatus::Failed, 'stage' => 'failed',
                        'failure_code' => 'queue_unavailable', 'completed_at' => now()])->save();
                }

                return [$analysis, false];
            });
        } catch (LockTimeoutException) {
            return response()->json(['message' => __('app.error_network')], 503)->header('Retry-After', '3');
        }
        $analysis->load('events');
        if ($analysis->status === AnalysisStatus::Completed) {
            $analysis->load('claims.evidence');
        }

        return (new AnalysisResource($analysis))->additional(['meta' => ['reused' => $reused, 'refresh_cooldown_seconds' => 300]])
            ->response()->setStatusCode($analysis->status === AnalysisStatus::Completed ? 200 : 202)->header('Cache-Control', 'no-store');
    }

    public function show(Analysis $analysis): JsonResponse
    {
        abort_if($analysis->expires_at?->isPast(), 404);
        App::setLocale($analysis->locale);
        $analysis->load('events');
        if ($analysis->status === AnalysisStatus::Completed) {
            $analysis->load('claims.evidence');
        }

        return (new AnalysisResource($analysis))->response()->header('Cache-Control', 'no-store');
    }
}
