<?php

namespace Tests\Feature;

use App\Exceptions\ContentFetchException;
use App\Services\Content\SocialContentFetcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SocialContentFetcherTest extends TestCase
{
    public function test_combines_title_description_and_native_transcript(): void
    {
        config(['fact.social.key' => 'test-social-key']);
        Http::preventStrayRequests();
        Http::fake([
            'api.supadata.ai/v1/metadata*' => Http::response(['title' => 'Video title', 'description' => 'Video description', 'author' => ['name' => 'Speaker'], 'duration' => 120]),
            'api.supadata.ai/v1/transcript*' => Http::response(['content' => 'Actual spoken transcript claims.', 'lang' => 'tr']),
        ]);
        $content = app(SocialContentFetcher::class)->fetch('https://www.youtube.com/watch?v=YPbNMPulcdI');
        $this->assertSame('Video title', $content->title);
        $this->assertStringContainsString('Actual spoken transcript', $content->text);
        $this->assertStringContainsString('Video description', $content->text);
        $this->assertSame('native_provider', $content->metadata['transcript']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'test-social-key') && ($request['mode'] ?? '') === 'native');
    }

    public function test_does_not_analyze_video_description_as_a_transcript(): void
    {
        config(['fact.social.key' => 'test-social-key']);
        Http::fake([
            'api.supadata.ai/v1/metadata*' => Http::response(['title' => 'Title', 'description' => 'Long description of a video but no spoken transcript.']),
            'api.supadata.ai/v1/transcript*' => Http::response([], 404),
        ]);
        $this->expectException(ContentFetchException::class);
        $this->expectExceptionMessage('youtube_transcript_unavailable');
        app(SocialContentFetcher::class)->fetch('https://youtu.be/YPbNMPulcdI');
    }

    public function test_handles_async_native_transcript_without_duplicate_submission(): void
    {
        config(['fact.social.key' => 'test-social-key']);
        Sleep::fake();
        Http::fake([
            'api.supadata.ai/v1/metadata*' => Http::response(['title' => 'Title']),
            'api.supadata.ai/v1/transcript?*' => Http::response(['jobId' => 'job-123'], 202),
            'api.supadata.ai/v1/transcript/job-123' => Http::response(['status' => 'completed', 'content' => 'Native caption text.', 'lang' => 'en']),
        ]);
        $content = app(SocialContentFetcher::class)->fetch('https://youtu.be/YPbNMPulcdI');
        $this->assertStringContainsString('Native caption text.', $content->text);
        Http::assertSentCount(3);
    }

    #[TestWith(['https://www.instagram.com/reel/abc', 'instagram'])]
    #[TestWith(['https://www.facebook.com/watch/?v=123', 'facebook'])]
    #[TestWith(['https://www.tiktok.com/@name/video/123', 'tiktok'])]
    #[TestWith(['https://x.com/name/status/123', 'x'])]
    #[TestWith(['https://www.youtube.com/watch?v=YPbNMPulcdI', 'youtube'])]
    #[TestWith(['https://youtube.com.attacker.org/video', null])]
    public function test_routes_only_supported_platform_domains(string $url, ?string $expected): void
    {
        $this->assertSame($expected, app(SocialContentFetcher::class)->platform($url));
    }

    public function test_reports_provider_failure_without_leaking_the_key(): void
    {
        config(['fact.social.key' => 'private-social-key']);
        Http::fake(['*' => Http::response([], 401)]);
        $this->expectExceptionMessage('transcript_provider_unavailable');
        app(SocialContentFetcher::class)->fetch('https://youtu.be/YPbNMPulcdI');
    }
}
