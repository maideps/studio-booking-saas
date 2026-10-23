<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use RuntimeException;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

/**
 * Stands in for stripe-php's HTTP client, so StripeGateway is tested without
 * a network (D-004). It answers each request with a JSON fixture from
 * tests/Fixtures/Stripe and records what was sent.
 *
 * The fixtures follow the shape of Stripe's API reference for Checkout
 * Sessions and Subscriptions; they were written by hand, not captured from a
 * live account. Refresh them when upgrading stripe/stripe-php.
 */
final class RecordedStripeHttpClient implements ClientInterface
{
    /** @var list<array{method: string, url: string, params: array<mixed>}> */
    public array $requests = [];

    /**
     * @param  array<string, string>  $responses  "METHOD path" => fixture file name
     */
    private function __construct(private readonly array $responses) {}

    /**
     * Installs the client for every stripe-php request until uninstall().
     *
     * @param  array<string, string>  $responses
     */
    public static function install(array $responses): self
    {
        $client = new self($responses);
        ApiRequestor::setHttpClient($client);

        return $client;
    }

    /**
     * Puts back stripe-php's own client (its default).
     */
    public static function uninstall(): void
    {
        ApiRequestor::setHttpClient(CurlClient::instance());
    }

    /**
     * @param  array<string>  $headers
     * @param  array<mixed>  $params
     * @return array{0: string, 1: int, 2: array<string, string>}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $path = (string) parse_url($absUrl, PHP_URL_PATH);
        $this->requests[] = ['method' => strtoupper($method), 'url' => $absUrl, 'params' => $params];

        $fixture = $this->responses[strtoupper($method).' '.$path]
            ?? throw new RuntimeException("No recorded Stripe response for {$method} {$path}.");

        $body = file_get_contents(base_path("tests/Fixtures/Stripe/{$fixture}"));

        if ($body === false) {
            throw new RuntimeException("Missing Stripe fixture {$fixture}.");
        }

        return [$body, 200, ['request-id' => 'req_fixture']];
    }
}
