<?php

namespace Tests\Unit;

use App\Contracts\HostResolver;
use App\Exceptions\InvalidPublicUrlException;
use App\Services\Content\PublicUrlPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicUrlPolicyTest extends TestCase
{
    #[DataProvider('blockedUrlProvider')]
    public function test_rejects_non_public_or_unsupported_url(string $url): void
    {
        $resolver = $this->mock(HostResolver::class);
        $resolver->shouldNotReceive('resolve');

        $this->expectException(InvalidPublicUrlException::class);
        (new PublicUrlPolicy($resolver))->parse($url);
    }

    public static function blockedUrlProvider(): array
    {
        return [
            'ftp scheme' => ['ftp://example.org/file'],
            'local ipv4' => ['http://127.0.0.1/admin'],
            'local ipv6' => ['http://[::1]/admin'],
            'decimal ipv4' => ['http://2130706433/admin'],
            'hex ipv4' => ['http://0x7f000001/admin'],
            'nonstandard port' => ['https://example.org:8443/admin'],
            'credentials in URL' => ['https://user:password@example.org/path'],
        ];
    }

    public function test_rejects_hostname_that_resolves_to_private_address(): void
    {
        $resolver = $this->mock(HostResolver::class);
        $resolver->shouldReceive('resolve')->once()->with('public.example.org')->andReturn(['10.0.0.8']);

        $this->expectException(InvalidPublicUrlException::class);
        (new PublicUrlPolicy($resolver))->resolvePublicAddresses(
            (new PublicUrlPolicy($resolver))->parse('https://public.example.org/story'),
        );
    }

    public function test_resolves_relative_redirect_without_trusting_its_destination(): void
    {
        $resolver = $this->mock(HostResolver::class);
        $policy = new PublicUrlPolicy($resolver);
        $redirect = $policy->resolveRedirect('https://example.org/story/a', '//127.0.0.1/admin');

        $this->assertSame('https://127.0.0.1/admin', $redirect);
        $this->expectException(InvalidPublicUrlException::class);
        $policy->parse($redirect);
    }
}
