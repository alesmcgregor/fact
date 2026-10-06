<?php

namespace Tests\Feature;

use App\Contracts\AnalysisProvider;
use App\Contracts\ContentFetcher;
use App\Enums\AnalysisStatus;
use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Services\VerificationScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FactWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_in_the_selected_locale_and_arabic_direction(): void
    {
        $response = $this->withCookie('fact_locale', 'ar')->get('/');

        $response->assertOk();
        $response->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_valid_submission_creates_queued_analysis_and_dispatches_job(): void
    {
        Queue::fake([RunAnalysis::class]);

        $response = $this->withSession(['locale' => 'tr'])->postJson('/api/analyses', [
            'url' => 'https://example.org/story',
            'locale' => 'tr',
        ]);

        $response->assertAccepted();
        $response->assertJsonPath('data.status', 'queued');
        $response->assertJsonPath('data.locale', 'tr');
        $analysis = Analysis::query()->firstOrFail();
        $this->assertSame(AnalysisStatus::Queued, $analysis->status);
        $this->assertDatabaseHas('analysis_events', ['analysis_id' => $analysis->id, 'stage' => 'queued']);
        Queue::assertPushed(RunAnalysis::class, fn (RunAnalysis $job): bool => $job->analysisId === $analysis->id);
    }

    public function test_submission_rejects_non_public_url_with_localized_message(): void
    {
        $response = $this->withSession(['locale' => 'tr'])->postJson('/api/analyses', [
            'url' => 'http://127.0.0.1/admin',
            'locale' => 'tr',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors.url.0', __('app.validation_url', locale: 'tr'));
        $this->assertDatabaseCount('analyses', 0);
    }

    public function test_public_report_escapes_content_and_uses_non_sequential_identifier(): void
    {
        $analysis = Analysis::factory()->completed()->create([
            'locale' => 'es',
            'content_title' => '<script>alert(1)</script>',
            'summary' => 'A public summary.',
        ]);
        $analysis->claims()->create([
            'position' => 1,
            'statement' => '<img src=x onerror=alert(1)>',
            'classification' => 'factual',
            'verification_status' => 'unverified',
            'explanation' => 'No evidence was found.',
        ]);

        $response = $this->get(route('reports.show', ['analysis' => $analysis->public_id]));

        $response->assertOk();
        $response->assertSee('<html lang="es" dir="ltr">', false);
        $response->assertSee('Resumen de la evidencia');
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee(route('reports.show', ['analysis' => $analysis->public_id]));
        $this->assertSame(32, strlen($analysis->public_id));
    }

    public function test_expired_public_report_returns_404(): void
    {
        $analysis = Analysis::factory()->completed()->create(['expires_at' => now()->subMinute()]);

        $this->get(route('reports.show', ['analysis' => $analysis->public_id]))->assertNotFound();
    }

    public function test_unknown_public_report_identifier_returns_404(): void
    {
        $response = $this->get('/r/not-a-real-report');

        $response->assertNotFound();
    }

    public function test_demo_job_completes_without_inventing_claims_or_evidence(): void
    {
        config(['fact.analysis_driver' => 'mock']);
        $analysis = Analysis::factory()->create();

        (new RunAnalysis($analysis->id))->handle(
            $this->app->make(ContentFetcher::class),
            $this->app->make(AnalysisProvider::class),
            $this->app->make(VerificationScorer::class),
        );

        $analysis->refresh();
        $this->assertSame(AnalysisStatus::Completed, $analysis->status);
        $this->assertSame('insufficient_evidence', $analysis->verification_status);
        $this->assertSame(null, $analysis->evidence_score);
        $this->assertTrue($analysis->demo_mode);
        $this->assertDatabaseCount('claims', 0);
        $this->assertDatabaseCount('evidence', 0);
    }

    public function test_analysis_creation_is_rate_limited_after_two_requests(): void
    {
        Queue::fake([RunAnalysis::class]);
        $payload = ['url' => 'https://example.org/story', 'locale' => 'en'];

        $this->postJson('/api/analyses', $payload)->assertAccepted();
        $payload['url'] = 'https://example.org/other-story';
        $this->postJson('/api/analyses', $payload)->assertAccepted();
        $this->postJson('/api/analyses', $payload)->assertTooManyRequests();

        $this->assertDatabaseCount('analyses', 2);
    }

    public function test_language_choice_persists_in_secure_http_only_cookie_and_safe_return_path(): void
    {
        $response = $this->from('/')->post('/language', ['locale' => 'es', 'return_to' => '//evil.example']);

        $response->assertRedirect('/');
        $response->assertCookie('fact_locale', 'es');
        $this->assertSame('es', session('locale'));
    }
}
