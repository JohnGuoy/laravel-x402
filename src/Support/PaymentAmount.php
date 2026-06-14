<?php

namespace JohnGuoy\LaravelX402\Support;

use JohnGuoy\LaravelX402\Exceptions\UnsupportedAssetException;

/**
 * Precision-safe conversion between human-readable amounts and on-chain
 * atomic units using PHP's BCMath extension (no floating-point drift).
 *
 * USDC has 6 decimal places on all EVM chains:
 *   1 USDC  =  1_000_000 atomic units
 *   0.01    =     10_000 atomic units
 *   0.001   =      1_000 atomic units
 */
class PaymentAmount
{
    /** @var array<string, int> */
    protected array $decimalsMap;

    public function __construct()
    {
        $this->decimalsMap = config('x402.decimals', [
            'USDC'  => 6,
            'USDT'  => 6,
            'EURC'  => 6,
            'DAI'   => 18,
            'ETH'   => 18,
            'WETH'  => 18,
            'MATIC' => 18,
            'SOL'   => 9,
            'XLM'   => 7,
        ]);
    }

    /**
     * Convert a human-readable decimal string to an on-chain atomic unit string.
     *
     * Examples (USDC, 6 decimals):
     *   "0.01"  → "10000"
     *   "1"     → "1000000"
     *   "1.5"   → "1500000"
     *
     * @throws UnsupportedAssetException
     */
    public function toAtomicUnits(string $humanAmount, string $symbol): string
    {
        $decimals   = $this->decimals($symbol);
        $multiplier = bcpow('10', (string) $decimals, 0);

        // Multiply with enough scale to capture the fractional part, then strip it
        $result = bcmul($humanAmount, $multiplier, $decimals);

        if (str_contains($result, '.')) {
            $result = explode('.', $result)[0];
        }

        return $result;
    }

    /**
     * Convert an on-chain atomic unit string back to a human-readable decimal.
     *
     * Examples (USDC, 6 decimals):
     *   "10000"   → "0.010000"
     *   "1000000" → "1.000000"
     *
     * @throws UnsupportedAssetException
     */
    public function fromAtomicUnits(string $atomicAmount, string $symbol): string
    {
        $decimals = $this->decimals($symbol);
        $divisor  = bcpow('10', (string) $decimals, 0);

        return bcdiv($atomicAmount, $divisor, $decimals);
    }

    /**
     * Return the decimal precision for the given asset symbol.
     *
     * @throws UnsupportedAssetException
     */
    public function decimals(string $symbol): int
    {
        $upper = strtoupper(trim($symbol));

        if (! array_key_exists($upper, $this->decimalsMap)) {
            throw new UnsupportedAssetException(
                "Asset '{$upper}' is not in the decimals map. "
                ."Add it to config('x402.decimals')."
            );
        }

        return $this->decimalsMap[$upper];
    }

    /**
     * Parse a price string in any of the following formats:
     *   "$0.01"       → ['amount' => '0.01', 'symbol' => $default]
     *   "0.01 USDC"   → ['amount' => '0.01', 'symbol' => 'USDC']
     *   "0.01"        → ['amount' => '0.01', 'symbol' => $default]
     *
     * @return array{amount: string, symbol: string}
     */
    public function parsePrice(string $price, string $defaultSymbol = 'USDC'): array
    {
        $price = trim($price);

        // "$0.01 USDC" or "0.01 USDC"
        if (preg_match('/^\$?([\d.]+)\s+([A-Z]{2,10})$/i', $price, $m)) {
            return ['amount' => $m[1], 'symbol' => strtoupper($m[2])];
        }

        // "$0.01" or "0.01"
        $price = ltrim($price, '$');

        return ['amount' => $price, 'symbol' => strtoupper($defaultSymbol)];
    }

    /**
     * Register or override a custom decimal count for an asset symbol.
     */
    public function register(string $symbol, int $decimals): static
    {
        $this->decimalsMap[strtoupper($symbol)] = $decimals;

        return $this;
    }
}
