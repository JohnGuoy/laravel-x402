<?php

namespace JohnGuoy\LaravelX402\Support;

use JohnGuoy\LaravelX402\Contracts\FacilitatorContract;
use JohnGuoy\LaravelX402\Exceptions\FacilitatorException;
use JohnGuoy\LaravelX402\Exceptions\MissingAssetAddressException;

/**
 * Resolves facilitator instances and on-chain asset addresses from config.
 */
class X402Manager
{
    /** @var array<string, FacilitatorContract> */
    protected array $resolved = [];

    /**
     * Resolve and (lazily) cache a FacilitatorContract by config key.
     *
     * @throws FacilitatorException
     */
    public function facilitator(?string $key = null): FacilitatorContract
    {
        $key ??= config('x402.default_facilitator', 'coinbase');

        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $cfg = config("x402.facilitators.{$key}");

        if (empty($cfg['url'])) {
            throw new FacilitatorException(
                "Facilitator '{$key}' is not configured or missing a URL in config/x402.php."
            );
        }

        return $this->resolved[$key] = new HttpFacilitator(
            baseUrl: $cfg['url'],
            timeout: (int) ($cfg['timeout'] ?? 10),
        );
    }

    /**
     * Resolve the on-chain contract address for an asset on a given network.
     *
     * @throws MissingAssetAddressException
     */
    public function assetAddress(string $symbol, string $network): string
    {
        $upper   = strtoupper($symbol);
        $address = config("x402.assets.{$upper}.{$network}");

        if (empty($address)) {
            throw new MissingAssetAddressException(
                "No contract address for '{$upper}' on network '{$network}'. "
                ."Add it to config/x402.php under 'assets'."
            );
        }

        return $address;
    }
}
