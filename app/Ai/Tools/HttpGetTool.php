<?php

namespace App\Ai\Tools;

use App\Contracts\Tool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class HttpGetTool implements Tool
{
    public function name(): string
    {
        return 'http_get';
    }

    public function description(): string
    {
        return 'Fetch a public web page or JSON API with an HTTP GET request.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => ['url' => ['type' => 'string', 'description' => 'Absolute http(s) URL']],
            'required' => ['url'],
        ];
    }

    public function requiresApproval(): bool
    {
        return false;
    }

    public function execute(array $arguments): string
    {
        $url = (string) ($arguments['url'] ?? '');
        $this->assertPublicUrl($url);

        $response = Http::timeout(10)->withoutRedirecting()->get($url);

        return Str::limit($response->body(), 4000);
    }

    /**
     * First-line SSRF guard: literal localhost / private / reserved addresses.
     * Known gap: a public-looking hostname that secretly points at a private IP
     * (DNS rebinding) is not caught yet. We resolve and re-check DNS later.
     */
    private function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('Only absolute http(s) URLs are allowed.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')) {
            throw new InvalidArgumentException('Internal hosts are not allowed.');
        }

        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isPublicIp = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

        if ($isIp && ! $isPublicIp) {
            throw new InvalidArgumentException('Private or reserved IP addresses are not allowed.');
        }
    }
}