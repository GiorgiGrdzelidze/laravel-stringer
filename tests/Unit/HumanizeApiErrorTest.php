<?php

declare(strict_types=1);

use Stringer\Laravel\Jobs\GenerateDraftJob;

it('extracts the upstream message field and prefixes the HTTP code', function () {
    $exception = new RuntimeException(
        'HTTP request returned status code 429:'."\n".'{
  "error": {
    "code": 429,
    "message": "You exceeded your current quota, please check your plan and billing details.",
    "status": "RESOURCE_EXHAUSTED"
  }
}'
    );

    $result = GenerateDraftJob::humanizeApiError($exception);

    expect($result)->toContain('429 —')
        ->and($result)->toContain('You exceeded your current quota');
});

it('tolerates Guzzle body-summary truncation (no closing quote on message)', function () {
    // Real shape from Guzzle's RequestException when the response body
    // exceeds the default truncate-at threshold (120 chars). The closing
    // `"` of "message" never appears — Guzzle inserts `(truncated...)`
    // mid-string. Reproduces the topic 21 / "429 —" notification bug.
    $exception = new RuntimeException(
        'HTTP request returned status code 429:'."\n".'{
  "error": {
    "code": 429,
    "message": "You exceeded your current quota, please check your plan and billing deta (truncated...)'
    );

    $result = GenerateDraftJob::humanizeApiError($exception);

    expect($result)->toContain('429 —')
        ->and($result)->toContain('You exceeded your current quota')
        ->and($result)->not->toContain('(truncated');
});

it('falls back to the status-code prefix when no message field is present', function () {
    $exception = new RuntimeException(
        'HTTP request returned status code 500:'."\n".'{"some":"shape we have not seen"}'
    );

    $result = GenerateDraftJob::humanizeApiError($exception);

    expect($result)->toContain('500 —');
});

it('returns the raw message verbatim when no HTTP envelope is detected', function () {
    $exception = new RuntimeException('something went wrong somewhere');

    $result = GenerateDraftJob::humanizeApiError($exception);

    expect($result)->toBe('something went wrong somewhere');
});

it('clamps the result at 280 characters with an ellipsis', function () {
    $longBody = '{"error": {"code": 429, "message": "'.str_repeat('A', 400).'"}}';
    $exception = new RuntimeException('HTTP request returned status code 429: '.$longBody);

    $result = GenerateDraftJob::humanizeApiError($exception);

    expect(mb_strlen($result))->toBeLessThanOrEqual(281)
        ->and($result)->toEndWith('…');
});
