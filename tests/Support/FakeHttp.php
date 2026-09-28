<?php

declare(strict_types=1);

namespace Quoyer\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Quoyer\QuoyerClient;
use Throwable;

/**
 * A Quoyer client wired to queued responses, recording every request sent
 * and every retry sleep.
 */
final class FakeHttp
{
    /** @var list<array{request: RequestInterface}> */
    public array $history = [];

    /** @var list<int> Milliseconds slept between attempts. */
    public array $sleeps = [];

    private MockHandler $mock;

    public function __construct(Response|Throwable ...$queue)
    {
        $this->mock = new MockHandler($queue);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function client(array $options = []): QuoyerClient
    {
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        return new QuoyerClient(array_merge([
            'api_key' => 'quoy_sandbox_abc123.secretsecretsecret',
            'base_url' => 'https://quoyer.example',
            'sleep' => function (int $milliseconds): void {
                $this->sleeps[] = $milliseconds;
            },
        ], $options), new Client(['handler' => $stack]));
    }

    public function request(int $index = 0): RequestInterface
    {
        return $this->history[$index]['request'];
    }

    public function sentCount(): int
    {
        return count($this->history);
    }

    /**
     * The decoded JSON body of a sent request.
     *
     * @return array<string, mixed>
     */
    public function body(int $index = 0): array
    {
        return json_decode((string) $this->request($index)->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The query parameters of a sent request.
     *
     * @return array<string, string>
     */
    public function query(int $index = 0): array
    {
        parse_str($this->request($index)->getUri()->getQuery(), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    /**
     * `METHOD /path` of a sent request, relative to /api/v1.
     */
    public function route(int $index = 0): string
    {
        $request = $this->request($index);

        return $request->getMethod().' '.substr($request->getUri()->getPath(), strlen('/api/v1'));
    }
}
