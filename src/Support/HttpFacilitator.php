<?php

namespace JohnGuoy\LaravelX402\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JohnGuoy\LaravelX402\Contracts\FacilitatorContract;
use JohnGuoy\LaravelX402\Exceptions\FacilitatorException;

/**
 * HTTP facilitator client.
 *
 * Calls the x402 V2 /verify and /settle endpoints on the configured
 * facilitator service (Coinbase, Cloudflare, Stellar, etc.).
 *
 * Flow per the x402 V2 spec (facilitator.md):
 *   1. Server POSTs {payment, paymentRequirements} to /verify
 *   2. Facilitator returns {isValid, invalidReason}
 *   3. If valid, server POSTs to /settle → facilitator submits on-chain
 *   4. Facilitator returns {success, txHash}
 */
class HttpFacilitator implements FacilitatorContract
{
    protected Client $http;

    public function __construct(
        protected string $baseUrl,
        protected int $timeout = 10,
    ) {
        $this->http = new Client([
            'base_uri'        => rtrim($baseUrl, '/'),
            'timeout'         => $timeout,
            'connect_timeout' => 5,
            'headers'         => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'User-Agent'   => 'laravel-x402/1.0',
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function verify(array $paymentPayload, array $requirements): array
    {
        try {
            $response = $this->http->post('/verify', [
                'json' => [
                    'payment'             => $paymentPayload,
                    'paymentRequirements' => $requirements,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return [
                'valid'         => (bool) ($body['isValid'] ?? false),
                'invalidReason' => $body['invalidReason'] ?? null,
            ];
        } catch (GuzzleException $e) {
            throw new FacilitatorException(
                "Facilitator /verify request failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function settle(array $paymentPayload, array $requirements): array
    {
        try {
            $response = $this->http->post('/settle', [
                'json' => [
                    'payment'             => $paymentPayload,
                    'paymentRequirements' => $requirements,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return [
                'success' => (bool) ($body['success'] ?? false),
                'txHash'  => $body['txHash'] ?? null,
                'error'   => $body['error'] ?? null,
            ];
        } catch (GuzzleException $e) {
            throw new FacilitatorException(
                "Facilitator /settle request failed: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
