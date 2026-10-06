<?php

namespace App\Services;

use App\Enums\VerificationStatus;

class VerificationScorer
{
    /**
     * @param  list<array{source_type: string, relationship: string, publisher?: string, published_at?: ?string}>  $evidence
     * @return array{status: string, score: ?int, supporting_official_count: int, supporting_independent_count: int, contradicting_count: int}
     */
    public function scoreClaim(array $evidence, bool $searchPerformed): array
    {
        $supportingOfficial = $this->uniquePublisherCount($evidence, 'official', 'supports');
        $supportingIndependent = $this->uniquePublisherCount($evidence, 'independent', 'supports');
        $decisiveEvidence = array_values(array_filter($evidence, fn (array $item): bool => in_array($item['source_type'], ['official', 'independent', 'institutional'], true)));
        $contradicting = $this->uniquePublisherCount($decisiveEvidence, null, 'contradicts');
        $discoveredSupporting = $this->uniquePublisherCount($evidence, 'institutional', 'supports');
        $officialContradicting = $this->uniquePublisherCount($evidence, 'official', 'contradicts');
        $supporting = $supportingOfficial + $supportingIndependent;

        if (! $searchPerformed) {
            $status = VerificationStatus::InsufficientEvidence;
            $score = null;
        } else {
            $status = $this->status(
                $supportingOfficial,
                $supportingIndependent,
                $contradicting,
                $officialContradicting,
            );
            $score = $this->score($evidence, $supportingOfficial, $supportingIndependent, $contradicting);
            if ($discoveredSupporting > 0 && in_array($status, [VerificationStatus::Unverified, VerificationStatus::Contradicted], true)) {
                $status = VerificationStatus::PartiallySupported;
            }
            $score = min(100, $score + min(20, $discoveredSupporting * 10));
        }

        return [
            'status' => $status->value,
            'score' => $score,
            'supporting_official_count' => $supportingOfficial,
            'supporting_independent_count' => $supportingIndependent,
            'contradicting_count' => $contradicting,
        ];
    }

    /** @param list<array{source_type: string, relationship: string, publisher?: string, published_at?: ?string}> $evidence */
    public function aggregate(array $claimResults, bool $searchPerformed): array
    {
        $factualResults = array_values(array_filter(
            $claimResults,
            fn (array $claim): bool => ($claim['classification'] ?? 'factual') === 'factual',
        ));

        if ($factualResults === [] || ! $searchPerformed) {
            return ['status' => VerificationStatus::InsufficientEvidence->value, 'score' => null];
        }

        $statuses = array_column($factualResults, 'verification_status');
        $scores = array_values(array_filter(array_column($factualResults, 'evidence_score'), 'is_int'));

        if (in_array(VerificationStatus::ConflictingOfficialAccounts->value, $statuses, true)) {
            $status = VerificationStatus::ConflictingOfficialAccounts;
        } elseif (count(array_filter($statuses, fn (string $value): bool => $value === VerificationStatus::Contradicted->value)) === count($statuses)) {
            $status = VerificationStatus::Contradicted;
        } elseif (count(array_filter($statuses, fn (string $value): bool => $value === VerificationStatus::Verified->value)) === count($statuses)) {
            $status = VerificationStatus::Verified;
        } elseif (count(array_filter($statuses, fn (string $value): bool => $value === VerificationStatus::Unverified->value)) === count($statuses)) {
            $status = VerificationStatus::Unverified;
        } elseif (count(array_filter($statuses, fn (string $value): bool => in_array($value, [
            VerificationStatus::Verified->value,
            VerificationStatus::StronglySupported->value,
        ], true))) === count($statuses)) {
            $status = VerificationStatus::StronglySupported;
        } else {
            $status = VerificationStatus::PartiallySupported;
        }

        return [
            'status' => $status->value,
            'score' => $scores === [] ? null : (int) round(array_sum($scores) / count($scores)),
        ];
    }

    /** @param list<array{source_type: string, relationship: string, publisher?: string}> $evidence */
    private function status(
        int $supportingOfficial,
        int $supportingIndependent,
        int $contradicting,
        int $officialContradicting,
    ): VerificationStatus {
        if ($supportingOfficial > 0 && $officialContradicting > 0) {
            return VerificationStatus::ConflictingOfficialAccounts;
        }

        if ($supportingOfficial === 0 && $supportingIndependent === 0 && $contradicting >= 2) {
            return VerificationStatus::Contradicted;
        }

        if ($contradicting > 0 && $supportingOfficial === 0 && $supportingIndependent === 0) {
            return VerificationStatus::Contradicted;
        }

        if ($contradicting === 0 && $supportingOfficial >= 2 && $supportingIndependent >= 1) {
            return VerificationStatus::Verified;
        }

        if ($contradicting === 0 && ($supportingOfficial >= 1 || $supportingIndependent >= 2)) {
            return VerificationStatus::StronglySupported;
        }

        if ($supportingOfficial > 0 || $supportingIndependent > 0) {
            return VerificationStatus::PartiallySupported;
        }

        return VerificationStatus::Unverified;
    }

    /** @param list<array{source_type: string, relationship: string, publisher?: string, published_at?: ?string}> $evidence */
    private function score(array $evidence, int $official, int $independent, int $contradicting): int
    {
        $publishers = array_values(array_unique(array_filter(array_map(
            fn (array $item): string => strtolower((string) ($item['publisher'] ?? '')),
            $evidence,
        ))));
        $datedSources = count(array_filter($evidence, fn (array $item): bool => ! empty($item['published_at'])));
        $officialPoints = min(40, $official * 20);
        $independentPoints = min(20, $independent * 10);
        $diversityPoints = min(20, max(0, count($publishers) - 1) * 5);
        $contextPoints = min(20, $datedSources * 5);
        $contradictionPenalty = min(50, $contradicting * 15);

        return max(0, min(100, $officialPoints + $independentPoints + $diversityPoints + $contextPoints - $contradictionPenalty));
    }

    /** @param list<array{source_type: string, relationship: string, publisher?: string}> $evidence */
    private function uniquePublisherCount(array $evidence, ?string $sourceType, string $relationship): int
    {
        $publishers = [];

        foreach ($evidence as $item) {
            if ($item['relationship'] !== $relationship || ($sourceType !== null && $item['source_type'] !== $sourceType)) {
                continue;
            }

            $publisher = strtolower((string) ($item['publisher'] ?? ''));

            if ($publisher !== '') {
                $publishers[$publisher] = true;
            }
        }

        return count($publishers);
    }
}
