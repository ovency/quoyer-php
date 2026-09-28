<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Quoyer\Tests\Laravel\TestCase;
use Quoyer\Tests\Support\FakeHttp;

uses(TestCase::class)->in('Laravel');

/**
 * A JSON response, as the API sends them.
 *
 * @param  array<array-key, mixed>  $body
 * @param  array<string, string>  $headers
 */
function jsonResponse(int $status, array $body, array $headers = []): Response
{
    return new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body, JSON_THROW_ON_ERROR));
}

function fakeHttp(Response|Throwable ...$queue): FakeHttp
{
    return new FakeHttp(...$queue);
}
