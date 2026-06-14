<?php

namespace JohnGuoy\LaravelX402\Support;

use Illuminate\Http\Request;
use JohnGuoy\LaravelX402\Exceptions\MissingAssetAddressException;
use JohnGuoy\LaravelX402\Exceptions\MissingWalletAddressException;

/**
 * Builds the x402 V2 PaymentRequirements array that is Base64-encoded and
 * placed in the PAYMENT-REQUIRED response header on a 402 response.
 *
 * Spec: https://github.com/x402-foundation/x402/blob/main/specs/schemes/exact/scheme_exact.md
 */
class PaymentRequirement
{
    public function __construct(
        protected PaymentAmount $paymentAmount,
    ) {}

    /**
     * Build a PaymentRequirements array for the 402 PAYMENT-REQUIRED header.
     *
     * @param  string       $price    Human price string: "0.01", "$0.01", "0.01 USDC"
     * @param  string|null  $facilitator  Override the default facilitator key
     * @return array<int, array<string, mixed>>
     *
     * @throws MissingWalletAddressException
     * @throws MissingAssetAddressException
     */
    public function build(
        Request $request,
        string $price,
        string $assetSymbol,
        string $walletAddress,
        string $network,
        string $assetAddress,
        string $scheme = 'exact',
        string $description = 'API access via x402',
        string $mimeType = 'application/json',
        int $maxTimeoutSeconds = 60,
    ): array {
        if (empty($walletAddress)) {
            throw new MissingWalletAddressException(
                'X402_WALLET_ADDRESS is not set. Add it to your .env file.'
            );
        }

        if (empty($assetAddress)) {
            throw new MissingAssetAddressException(
                "No asset contract address configured for '{$assetSymbol}' on network '{$network}'."
            );
        }

        ['amount' => $humanAmount, 'symbol' => $resolvedSymbol] =
            $this->paymentAmount->parsePrice($price, $assetSymbol);

        $atomicAmount = $this->paymentAmount->toAtomicUnits($humanAmount, $resolvedSymbol);
        $decimals     = $this->paymentAmount->decimals($resolvedSymbol);

        return [
            [
                // ── x402 V2 required fields ──────────────────────────────
                'scheme'            => $scheme,
                'network'           => $network,        // CAIP-2 identifier
                'maxAmountRequired' => $atomicAmount,   // string, atomic units
                'resource'          => $request->fullUrl(),
                'description'       => $description,
                'mimeType'          => $mimeType,
                'payTo'             => $walletAddress,
                'maxTimeoutSeconds' => $maxTimeoutSeconds,
                'asset'             => $assetAddress,   // on-chain contract address

                // ── V2 "extra" block (token metadata for facilitator) ────
                'extra' => [
                    'name'     => $resolvedSymbol,
                    'version'  => '2',
                    'decimals' => $decimals,
                ],
            ],
        ];
    }

    /**
     * Encode the requirements array to the Base64 JSON string used in the header.
     *
     * @param  array<int, array<string, mixed>>  $requirements
     */
    public function encode(array $requirements): string
    {
        return base64_encode(json_encode($requirements, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Decode a Base64 JSON header value back to an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function decode(string $encoded): array
    {
        return json_decode(base64_decode($encoded), true) ?? [];
    }
}
