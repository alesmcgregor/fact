<?php

namespace Tests\Unit;

use App\Services\VerificationScorer;
use Tests\TestCase;

class VerificationScorerTest extends TestCase
{
    public function test_absence_of_search_evidence_is_not_scored_as_false(): void
    {
        $result = (new VerificationScorer)->scoreClaim([], false);

        $this->assertSame('insufficient_evidence', $result['status']);
        $this->assertSame(null, $result['score']);
    }

    public function test_conflicting_official_publishers_are_reported_as_conflict(): void
    {
        $evidence = [
            ['source_type' => 'official', 'relationship' => 'supports', 'publisher' => 'Agency A'],
            ['source_type' => 'official', 'relationship' => 'contradicts', 'publisher' => 'Agency B'],
        ];

        $result = (new VerificationScorer)->scoreClaim($evidence, true);

        $this->assertSame('conflicting_official_accounts', $result['status']);
        $this->assertSame(10, $result['score']);
    }

    public function test_aggregate_ignores_opinion_claims_and_averages_fact_scores(): void
    {
        $result = (new VerificationScorer)->aggregate([
            ['classification' => 'factual', 'verification_status' => 'strongly_supported', 'evidence_score' => 72],
            ['classification' => 'opinion', 'verification_status' => 'contradicted', 'evidence_score' => 0],
        ], true);

        $this->assertSame('strongly_supported', $result['status']);
        $this->assertSame(72, $result['score']);
    }

    public function test_distinct_publisher_count_prevents_repeated_source_from_being_corroboration(): void
    {
        $evidence = [
            ['source_type' => 'independent', 'relationship' => 'supports', 'publisher' => 'Wire A'],
            ['source_type' => 'independent', 'relationship' => 'supports', 'publisher' => 'wire a'],
        ];

        $result = (new VerificationScorer)->scoreClaim($evidence, true);

        $this->assertSame(1, $result['supporting_independent_count']);
        $this->assertSame('partially_supported', $result['status']);
    }
}
