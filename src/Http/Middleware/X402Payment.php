<?php

namespace JohnGuoy\LaravelX402\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use JohnGuoy\LaravelX402\Contracts\FacilitatorContract;
use JohnGuoy\LaravelX402\Exceptions\FacilitatorException;
use JohnGuoy\LaravelX402\Support\HttpFacilitator;
use JohnGuoy\LaravelX402\Support\PaymentAmount;
use JohnGuoy\LaravelX402\Support\PaymentRequirement;
use JohnGuoy\LaravelX402\Support\X402Manager;

/**
 * x402 V2 Payment Middleware
 *
 * Attach to any route to require an on-chain stablecoin payment before
 * the request reaches your controller.
 *
 * x402 V2 flow (per spec):
 *   1. Request arrives without PAYMENT-SIGNATURE header
 *      → return 402 with PAYMENT-REQUIRED header (Base64-encoded requirements)
 *   2. Client signs a payment and retries with PAYMENT-SIGNATURE header
 *      → middleware calls facilitator /verify
 *   3. Facilitator confirms validity
 *      → middleware calls facilitator /settle
 *   4. Settlement succeeds
 *      → pass request through; attach PAYMENT-RESPONSE header to response
 *
 * Usage in routes/api.php:
 *   Route::get('/data', fn() => ...)->middleware('x402:0.01');
 *   Route::get('/pro',  fn() => ...)->middleware('x402:0.10,USDC,coinbase');
 *
 * Middleware parameters (all optional beyond $price):
 *   $price       – human-readable price, e.g. "0.01" or "$0.01"
 *   $asset       – token symbol, default from config x402.asset  (USDC)
 *   $facilitator – facilitator key,  default from config x402.default_facilitator
 *   $network     – CAIP-2 network,   default from config x402.network
 */
class X402Payment
{
    public function __construct(
        protected X402Manager      $manager,
        protected PaymentAmount    $paymentAmount,
        protected PaymentRequirement $requirement,
    ) {}

    public function handle(
        Request $request,
        Closure $next,
        string $price       = '0.001',
        string $asset       = '',
        string $facilitator = '',
        string $network     = '',
    ): Response {
        // Resolve defaults from config when not provided via route parameter
        $asset       = $asset       ?: config('x402.asset', 'USDC');
        $network     = $network     ?: config('x402.network', 'eip155:8453');
        $facilitator = $facilitator ?: config('x402.default_facilitator', 'coinbase');

        $walletAddress = config('x402.wallet_address', '');
        $assetAddress  = config("x402.assets.".strtoupper($asset).".{$network}", '');

        $requirements = $this->requirement->build(
            request:           $request,
            price:             $price,
            assetSymbol:       $asset,
            walletAddress:     $walletAddress,
            network:           $network,
            assetAddress:      $assetAddress,
            scheme:            config('x402.scheme', 'exact'),
            description:       config('x402.description', 'API access via x402'),
            mimeType:          config('x402.mime_type', 'application/json'),
            maxTimeoutSeconds: (int) config('x402.max_timeout_seconds', 60),
        );

        // ── Step 1: No payment header → return 402 ───────────────────────
        $signatureHeader = $request->header('PAYMENT-SIGNATURE');

        if (empty($signatureHeader)) {
            return $this->respond402($requirements);
        }

        // ── Step 2: Decode and verify the payment payload ─────────────────
        $paymentPayload = $this->decodeHeader($signatureHeader);

        if ($paymentPayload === null) {
            return $this->respond402($requirements, 'Malformed PAYMENT-SIGNATURE header (invalid Base64/JSON).');
        }

        try {
            $facilitatorClient = $this->manager->facilitator($facilitator);
            $verification      = $facilitatorClient->verify($paymentPayload, $requirements[0]);
        } catch (FacilitatorException $e) {
            return $this->respond402($requirements, 'Facilitator unreachable: '.$e->getMessage());
        }

        if (! $verification['valid']) {
            return $this->respond402(
                $requirements,
                $verification['invalidReason'] ?? 'Payment verification failed.'
            );
        }

        // ── Step 3: Settle the payment ────────────────────────────────────
        try {
            $settlement = $facilitatorClient->settle($paymentPayload, $requirements[0]);
        } catch (FacilitatorException $e) {
            return $this->respond402($requirements, 'Settlement failed: '.$e->getMessage());
        }

        if (! $settlement['success']) {
            return $this->respond402(
                $requirements,
                $settlement['error'] ?? 'On-chain settlement failed.'
            );
        }

        // ── Step 4: Payment confirmed — pass through ──────────────────────
        /** @var Response $response */
        $response = $next($request);

        // Attach PAYMENT-RESPONSE header (Base64-encoded settlement receipt)
        $settlementReceipt = base64_encode(json_encode([
            'success' => true,
            'txHash'  => $settlement['txHash'],
            'network' => $network,
            'asset'   => $asset,
        ]));

        $response->headers->set('PAYMENT-RESPONSE', $settlementReceipt);

        return $response;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build an HTTP 402 response with the PAYMENT-REQUIRED header.
     *
     * @param  array<int, array<string, mixed>>  $requirements
     */
    protected function respond402(array $requirements, ?string $error = null): JsonResponse
    {
        $encoded = $this->requirement->encode($requirements);

        return response()
            ->json([
                'error'   => $error ?? 'Payment required.',
                'accepts' => $requirements,
            ], 402)
            ->header('PAYMENT-REQUIRED', $encoded);
    }

    /**
     * Decode a Base64-encoded JSON header value into an array.
     *
     * @return array<string, mixed>|null  null on decode failure
     */
    protected function decodeHeader(string $header): ?array
    {
        $decoded = base64_decode($header, strict: true);

        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) ? $payload : null;
    }
}
