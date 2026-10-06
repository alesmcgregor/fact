<?php

namespace Tests\Unit;

use App\Contracts\HostResolver;
use App\Contracts\PinnedHttpTransport;
use App\Data\TransportResponse;
use App\Exceptions\ContentFetchException;
use App\Services\Content\ContentExtractor;
use App\Services\Content\PublicUrlPolicy;
use App\Services\Content\SafeUrlFetcher;
use Tests\TestCase;

class SafeUrlFetcherTest extends TestCase
{
    public function test_blocks_redirect_to_private_ip_before_connecting_to_it(): void
    {
        $resolver = $this->mock(HostResolver::class);
        $resolver->shouldReceive('resolve')->once()->with('example.org')->andReturn(['93.184.216.34']);
        $transport = $this->mock(PinnedHttpTransport::class);
        $transport->shouldReceive('get')->once()->with('https://example.org/story', '93.184.216.34', 2000000)
            ->andReturn(new TransportResponse(302, ['location' => 'http://127.0.0.1/admin'], ''));

        $fetcher = new SafeUrlFetcher(new PublicUrlPolicy($resolver), $transport, new ContentExtractor);

        try {
            $fetcher->fetch('https://example.org/story');
            $this->fail('Expected the private redirect to be rejected.');
        } catch (ContentFetchException $exception) {
            $this->assertSame('blocked_redirect', $exception->failureCode);
        }
    }

    public function test_stops_when_transport_reports_an_oversized_response(): void
    {
        $resolver = $this->mock(HostResolver::class);
        $resolver->shouldReceive('resolve')->once()->with('example.org')->andReturn(['93.184.216.34']);
        $transport = $this->mock(PinnedHttpTransport::class);
        $transport->shouldReceive('get')->once()->andThrow(new ContentFetchException('response_too_large'));

        $fetcher = new SafeUrlFetcher(new PublicUrlPolicy($resolver), $transport, new ContentExtractor);

        try {
            $fetcher->fetch('https://example.org/story');
            $this->fail('Expected the oversized response to be rejected.');
        } catch (ContentFetchException $exception) {
            $this->assertSame('response_too_large', $exception->failureCode);
        }
    }
}
