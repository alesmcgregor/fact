<?php

namespace App\Http\Resources;

use App\Enums\AnalysisStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof AnalysisStatus ? $this->status->value : (string) $this->status;

        return [
            'id' => $this->public_id,
            'status' => $status,
            'stage' => $this->stage,
            'source_url' => $this->submitted_url,
            'locale' => $this->locale,
            'analyzed_at' => $this->completed_at?->toIso8601String(),
            'content_metadata' => $this->content_metadata,
            'research_context' => $this->research_context,
            'summary' => $this->when($status === AnalysisStatus::Completed->value, $this->summary),
            'verification_status' => $this->when($status === AnalysisStatus::Completed->value, $this->verification_status),
            'evidence_score' => $this->when($status === AnalysisStatus::Completed->value, $this->evidence_score),
            'manipulation_level' => $this->when($status === AnalysisStatus::Completed->value, $this->manipulation_level),
            'demo_mode' => (bool) $this->demo_mode,
            'failure_message' => $status === AnalysisStatus::Failed->value
                ? __('app.failure_'.$this->failure_code)
                : null,
            'report_url' => $status === AnalysisStatus::Completed->value
                ? route('reports.show', ['analysis' => $this->public_id])
                : null,
            'events' => $this->whenLoaded('events', fn (): array => $this->events
                ->map(fn ($event): array => ['stage' => $event->stage, 'state' => $event->state])
                ->all()),
            'claims' => $this->whenLoaded('claims', fn (): array => $this->claims
                ->map(fn ($claim): array => [
                    'statement' => $claim->statement,
                    'classification' => $claim->classification,
                    'verification_status' => $claim->verification_status,
                    'explanation' => $claim->explanation,
                    'evidence_score' => $claim->evidence_score,
                    'evidence' => $claim->evidence->map(fn ($evidence): array => [
                        'title' => $evidence->title,
                        'publisher' => $evidence->publisher,
                        'url' => $evidence->url,
                        'source_type' => $evidence->source_type,
                        'identity_url' => $evidence->identity_url,
                        'relationship' => $evidence->relationship,
                        'summary' => $evidence->summary,
                        'published_at' => $evidence->published_at?->toIso8601String(),
                    ])->all(),
                ])->all()),
        ];
    }
}
