<?php

namespace JohnGuoy\LaravelX402\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Mockery;
use Mockery\MockInterface;
use JohnGuoy\LaravelX402\Contracts\FacilitatorContract;
use JohnGuoy\LaravelX402\Support\X402Manager;
use JohnGuoy\LaravelX402\Tests\TestCase;

class X402PaymentMiddlewareTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Register a test route protected by the x402 middleware and return its URL.
     */
    private function protectedRoute(string $price = '0.01', string $path = '/api/test'): string
    {
        Route::get($path, fn () => response()->json(['data' => 'secret']))->middleware("x402:{$price}");

        return $path;
    }

    /**
     * Build a fake, well-formed PAYMENT-SIGNATURE header value.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function fakeSignature(array $overrides = []): string
    {
        $payload = array_merge([
            'scheme'    => 'exact',
            'network'   => 'eip155:84532',
            'payload'   => 'fake-eip3009-authorization',
            'signature' => '0xfakesignature',
        ], $overrides);

        return base64_encode(json_encode($payload));
    }

    /**
     * Swap the X402Manager binding so it returns a mock facilitator.
     *
     * @param  array{valid: bool, invalidReason?: string|null}  $verifyResult
     * @param  array{success: bool, txHash?: string|null, error?: string|null}  $settleResult
     */
    private function mockFacilitator(array $verifyResult, array $settleResult = []): void
    {
        $facilitator = Mockery::mock(FacilitatorContract::class);

        $facilitator->shouldReceive('verify')
            ->once()
            ->andReturn(array_merge(['valid' => true, 'invalidReason' => null], $verifyResult));

        if ($verifyResult['valid'] ?? true) {
            $facilitator->shouldReceive('settle')
                ->once()
                ->andReturn(array_merge(['success' => true, 'txHash' => '0xabc123', 'error' => null], $settleResult));
        }

        $manager = Mockery::mock(X402Manager::class);
        $manager->shouldReceive('facilitator')->andReturn($facilitator);
        $manager->shouldReceive('assetAddress')->andReturn('0xUSDCContract');

        $this->app->instance(X402Manager::class, $manager);
    }

    // ── 402 — no payment header ───────────────────────────────────────────

    /** @test */
    public function it_returns_402_when_no_payment_header_is_present(): void
    {
        $route = $this->protectedRoute();

        $response = $this->getJson($route);

        $response->assertStatus(402);
        $response->assertJsonStructure(['error', 'accepts']);
        $this->assertNotEmpty($response->headers->get('PAYMENT-REQUIRED'));
    }

    /** @test */
    public function payment_required_header_is_valid_base64_json(): void
    {
        $route = $this->protectedRoute();

        $response = $this->getJson($route);

        $header  = $response->headers->get('PAYMENT-REQUIRED');
        $decoded = json_decode(base64_decode($header, strict: true), true);

        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);

        $req = $decoded[0];
        $this->assertArrayHasKey('scheme',            $req);
        $this->assertArrayHasKey('network',           $req);
        $this->assertArrayHasKey('maxAmountRequired', $req);
        $this->assertArrayHasKey('payTo',             $req);
        $this->assertArrayHasKey('asset',             $req);
        $this->assertArrayHasKey('extra',             $req);
        $this->assertSame('2', $req['extra']['version']);
    }

    /** @test */
    public function payment_required_header_contains_correct_atomic_amount(): void
    {
        $route = $this->protectedRoute('0.01');

        $response = $this->getJson($route);

        $header  = $response->headers->get('PAYMENT-REQUIRED');
        $decoded = json_decode(base64_decode($header), true);

        // 0.01 USDC with 6 decimals = 10000 atomic units
        $this->assertSame('10000', $decoded[0]['maxAmountRequired']);
    }

    /** @test */
    public function it_returns_correct_wallet_address_in_payment_requirement(): void
    {
        $route = $this->protectedRoute();

        $response = $this->getJson($route);

        $header  = $response->headers->get('PAYMENT-REQUIRED');
        $decoded = json_decode(base64_decode($header), true);

        $this->assertSame(
            config('x402.wallet_address'),
            $decoded[0]['payTo']
        );
    }

    // ── 402 — malformed signature header ─────────────────────────────────

    /** @test */
    public function it_returns_402_for_malformed_payment_signature(): void
    {
        $route = $this->protectedRoute();

        $response = $this->getJson($route, ['PAYMENT-SIGNATURE' => 'not-valid-base64!!!']);

        $response->assertStatus(402);
        $response->assertJsonFragment(['error' => 'Malformed PAYMENT-SIGNATURE header (invalid Base64/JSON).']);
    }

    // ── 402 — verification failure ────────────────────────────────────────

    /** @test */
    public function it_returns_402_when_facilitator_rejects_payment(): void
    {
        $route = $this->protectedRoute();
        $this->mockFacilitator(['valid' => false, 'invalidReason' => 'Signature mismatch']);

        $response = $this->getJson($route, ['PAYMENT-SIGNATURE' => $this->fakeSignature()]);

        $response->assertStatus(402);
        $response->assertJsonFragment(['error' => 'Signature mismatch']);
    }

    // ── 402 — settlement failure ──────────────────────────────────────────

    /** @test */
    public function it_returns_402_when_settlement_fails(): void
    {
        $route = $this->protectedRoute();
        $this->mockFacilitator(
            ['valid' => true, 'invalidReason' => null],
            ['success' => false, 'txHash' => null, 'error' => 'Insufficient funds']
        );

        $response = $this->getJson($route, ['PAYMENT-SIGNATURE' => $this->fakeSignature()]);

        $response->assertStatus(402);
        $response->assertJsonFragment(['error' => 'Insufficient funds']);
    }

    // ── 200 — successful payment ──────────────────────────────────────────

    /** @test */
    public function it_passes_request_through_after_successful_payment(): void
    {
        $route = $this->protectedRoute();
        $this->mockFacilitator(['valid' => true]);

        $response = $this->getJson($route, ['PAYMENT-SIGNATURE' => $this->fakeSignature()]);

        $response->assertStatus(200);
        $response->assertJson(['data' => 'secret']);
    }

    /** @test */
    public function it_attaches_payment_response_header_on_success(): void
    {
        $route = $this->protectedRoute();
        $this->mockFacilitator(['valid' => true]);

        $response = $this->getJson($route, ['PAYMENT-SIGNATURE' => $this->fakeSignature()]);

        $this->assertNotEmpty($response->headers->get('PAYMENT-RESPONSE'));
    }

    /** @test */
    public function payment_response_header_contains_tx_hash(): void
    {
        $route = $this->protectedRoute();
        $this->mockFacilitator(['valid' => true], ['success' => true, 'txHash' => '0xdeadbeef']);

        $response = $this->getJson($route, ['PAYMENT-SIGNATURE' => $this->fakeSignature()]);

        $header  = $response->headers->get('PAYMENT-RESPONSE');
        $decoded = json_decode(base64_decode($header), true);

        $this->assertTrue($decoded['success']);
        $this->assertSame('0xdeadbeef', $decoded['txHash']);
    }

    // ── Price precision tests ─────────────────────────────────────────────

    /** @test */
    public function middleware_correctly_converts_one_dollar_price(): void
    {
        Route::get('/api/dollar', fn () => response()->json(['ok' => true]))
            ->middleware('x402:1');

        $response = $this->getJson('/api/dollar');

        $header  = $response->headers->get('PAYMENT-REQUIRED');
        $decoded = json_decode(base64_decode($header), true);

        $this->assertSame('1000000', $decoded[0]['maxAmountRequired']);
    }

    /** @test */
    public function middleware_correctly_converts_one_tenth_cent_price(): void
    {
        Route::get('/api/micro', fn () => response()->json(['ok' => true]))
            ->middleware('x402:0.001');

        $response = $this->getJson('/api/micro');

        $header  = $response->headers->get('PAYMENT-REQUIRED');
        $decoded = json_decode(base64_decode($header), true);

        // 0.001 USDC = 1000 atomic units
        $this->assertSame('1000', $decoded[0]['maxAmountRequired']);
    }

    // ── Multiple routes ───────────────────────────────────────────────────

    /** @test */
    public function different_routes_can_have_different_prices(): void
    {
        Route::get('/api/cheap',     fn () => response()->json([]))->middleware('x402:0.001');
        Route::get('/api/expensive', fn () => response()->json([]))->middleware('x402:0.10');

        $cheap     = json_decode(base64_decode($this->getJson('/api/cheap')->headers->get('PAYMENT-REQUIRED')),     true);
        $expensive = json_decode(base64_decode($this->getJson('/api/expensive')->headers->get('PAYMENT-REQUIRED')), true);

        $this->assertSame('1000',   $cheap[0]['maxAmountRequired']);
        $this->assertSame('100000', $expensive[0]['maxAmountRequired']);
    }

    // ── ServiceProvider ───────────────────────────────────────────────────

    /** @test */
    public function service_provider_registers_payment_amount_singleton(): void
    {
        $a = $this->app->make(\JohnGuoy\LaravelX402\Support\PaymentAmount::class);
        $b = $this->app->make(\JohnGuoy\LaravelX402\Support\PaymentAmount::class);

        $this->assertSame($a, $b);
    }

    /** @test */
    public function service_provider_registers_x402_manager_singleton(): void
    {
        $a = $this->app->make(X402Manager::class);
        $b = $this->app->make(X402Manager::class);

        $this->assertSame($a, $b);
    }

    /** @test */
    public function facade_resolves_to_x402_manager(): void
    {
        $via_facade    = \JohnGuoy\LaravelX402\Facades\X402::getFacadeRoot();
        $via_container = $this->app->make(X402Manager::class);

        $this->assertSame($via_facade, $via_container);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
