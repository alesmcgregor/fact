<?php

namespace Tests\Feature;

use App\Contracts\HostResolver;
use App\Contracts\PinnedHttpTransport;
use App\Data\TransportResponse;
use App\Exceptions\ContentFetchException;
use App\Services\Content\PublicUrlPolicy;
use App\Services\Content\YouTubeTranscriptFetcher;
use Tests\TestCase;

class YouTubeTranscriptFetcherTest extends TestCase
{
    private function fetcher(array $player, ?string $captions = null): YouTubeTranscriptFetcher
    {
        $resolver = $this->mock(HostResolver::class);
        $resolver->shouldReceive('resolve')->with('www.youtube.com')->andReturn(['93.184.216.34']);
        $transport = $this->mock(PinnedHttpTransport::class);
        $transport->shouldReceive('get')->once()->withArgs(fn ($url): bool => str_contains($url, '/watch?v=YPbNMPulcdI'))
            ->andReturn(new TransportResponse(200, [], 'var ytInitialPlayerResponse = '.json_encode($player).';'));
        if ($captions !== null) {
            $transport->shouldReceive('get')->once()->withArgs(fn ($url): bool => str_starts_with($url, 'https://www.youtube.com/api/timedtext'))
                ->andReturn(new TransportResponse(200, [], $captions));
        }

        return new YouTubeTranscriptFetcher(new PublicUrlPolicy($resolver), $resolver, $transport);
    }

    public function test_reads_native_captions_with_description_and_provenance(): void
    {
        $player = ['videoDetails' => ['title' => 'Video', 'shortDescription' => 'Context description'],
            'captions' => ['playerCaptionsTracklistRenderer' => ['captionTracks' => [['languageCode' => 'en', 'kind' => 'asr', 'baseUrl' => 'https://www.youtube.com/api/timedtext?v=YPbNMPulcdI&signature=abc']]]]];
        $content = $this->fetcher($player, '<transcript><text>Hello spoken words.</text><text>Next claim.</text></transcript>')->fetch('https://youtu.be/YPbNMPulcdI');
        $this->assertStringContainsString('Hello spoken words. Next claim.', $content->text);
        $this->assertStringContainsString('Context description', $content->text);
        $this->assertSame('automatic', $content->metadata['transcript']);
    }

    public function test_distinguishes_bot_block_from_missing_transcript(): void
    {
        $fetcher = $this->fetcher(['playabilityStatus' => ['status' => 'LOGIN_REQUIRED']]);
        $this->expectExceptionMessage('youtube_access_blocked');
        $fetcher->fetch('https://youtu.be/YPbNMPulcdI');
    }

    public function test_rejects_missing_tracks_without_treating_page_shell_as_content(): void
    {
        $fetcher = $this->fetcher(['playabilityStatus' => ['status' => 'OK'], 'videoDetails' => ['title' => 'Video']]);
        $this->expectExceptionMessage('youtube_transcript_unavailable');
        $fetcher->fetch('https://youtu.be/YPbNMPulcdI');
    }

    public function test_rejects_caption_url_credentials_before_sending_request(): void
    {
        $fetcher = $this->fetcher(['captions' => ['playerCaptionsTracklistRenderer' => ['captionTracks' => [
            ['languageCode' => 'en', 'baseUrl' => 'https://user@www.youtube.com/api/timedtext?v=YPbNMPulcdI']]]]]);
        $this->expectException(ContentFetchException::class);
        $fetcher->fetch('https://youtu.be/YPbNMPulcdI');
    }

    public function test_rejects_non_youtube_caption_hosts_before_sending_request(): void
    {
        $fetcher = $this->fetcher(['captions' => ['playerCaptionsTracklistRenderer' => ['captionTracks' => [
            ['languageCode' => 'en', 'baseUrl' => 'http://127.0.0.1/api/timedtext']]]]]);
        $this->expectExceptionMessage('youtube_transcript_unavailable');
        $fetcher->fetch('https://youtu.be/YPbNMPulcdI');
    }
}
