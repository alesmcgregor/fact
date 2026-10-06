<?php

namespace App\Providers;

use App\Contracts\AnalysisProvider;
use App\Contracts\ContentFetcher;
use App\Contracts\HostResolver;
use App\Contracts\PinnedHttpTransport;
use App\Services\AI\MockAnalysisProvider;
use App\Services\AI\OpenAIResponsesAnalysisProvider;
use App\Services\Content\ContentExtractor;
use App\Services\Content\DnsHostResolver;
use App\Services\Content\PinnedCurlTransport;
use App\Services\Content\PublicUrlPolicy;
use App\Services\Content\SafeUrlFetcher;
use App\Services\Content\YouTubeTranscriptFetcher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HostResolver::class, DnsHostResolver::class);
        $this->app->bind(PinnedHttpTransport::class, PinnedCurlTransport::class);
        $this->app->bind(ContentFetcher::class, fn ($app): SafeUrlFetcher => new SafeUrlFetcher(
            $app->make(PublicUrlPolicy::class),
            $app->make(PinnedHttpTransport::class),
            $app->make(ContentExtractor::class),
            $app->make(YouTubeTranscriptFetcher::class),
        ));
        $this->app->bind(AnalysisProvider::class, function (): AnalysisProvider {
            return config('fact.analysis_driver') === 'openai'
                ? app(OpenAIResponsesAnalysisProvider::class)
                : app(MockAnalysisProvider::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('analysis-create', function (Request $request): Limit|array {
            $address = $request->ip() ?? 'unknown';
            $key = hash('sha256', $address.'|'.($request->userAgent() ?? 'unknown'));

            return [
                Limit::perMinute(2)->by($key),
                Limit::perHour(12)->by(hash('sha256', $address)),
                Limit::perMinute(120)->by('global'),
            ];
        });

        RateLimiter::for('analysis-read', function (Request $request): Limit {
            return Limit::perMinute(90)->by($request->ip() ?? 'unknown');
        });
    }
}
