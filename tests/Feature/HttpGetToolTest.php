<?php

use App\Ai\Tools\HttpGetTool;
use Illuminate\Support\Facades\Http;

it('fetches a public page and returns its body', function () {
    Http::fake(['https://api.example.com/*' => Http::response('{"status":"ok"}')]);

    $result = (new HttpGetTool)->execute(['url' => 'https://api.example.com/health']);

    expect($result)->toBe('{"status":"ok"}');
    Http::assertSentCount(1);
});

it('cuts very long responses so they cannot flood the model', function () {
    Http::fake(['*' => Http::response(str_repeat('a', 10000))]);

    $result = (new HttpGetTool)->execute(['url' => 'https://example.com']);

    expect(strlen($result))->toBeLessThanOrEqual(4003);
});

it('blocks requests to internal or non-http addresses', function (string $url) {
    Http::fake();

    expect(fn () => (new HttpGetTool)->execute(['url' => $url]))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with([
    'localhost' => 'http://localhost/admin',
    'loopback ip' => 'http://127.0.0.1/',
    'private 192' => 'http://192.168.1.10/',
    'private 10' => 'http://10.0.0.5/',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data',
    'ipv6 loopback' => 'http://[::1]/',
    'file scheme' => 'file:///etc/passwd',
    'not a url' => 'not a url',
    'internal domain' => 'http://db.internal/',
]);