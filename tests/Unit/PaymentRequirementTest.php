<?php

namespace JohnGuoy\LaravelX402\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Http\Request;
use JohnGuoy\LaravelX402\Exceptions\MissingAssetAddressException;
use JohnGuoy\LaravelX402\Exceptions\MissingWalletAddressException;
use JohnGuoy\LaravelX402\Support\PaymentAmount;
use JohnGuoy\LaravelX402\Support\PaymentRequirement;
use JohnGuoy\LaravelX402\Tests\TestCase;

class PaymentRequirementTest extends TestCase
{
    private PaymentRequirement $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new PaymentRequirement(new PaymentAmount());
    }

    private function makeRequest(string $url = 'http://localhost/api/data'): Request
    {
        return Request::create($url, 'GET');
    }

    // ── build() ───────────────────────────────────────────────────────────

    #[Test]
    public function it_builds_a_valid_requirement_array(): void
    {
        $reqs = $this->builder->build(
            request:           $this->makeRequest(),
            price:             '0.01',
            assetSymbol:       'USDC',
            walletAddress:     '0xWallet',
            network:           'eip155:8453',
            assetAddress:      '0xUSDCContract',
            scheme:            'exact',
            description:       'Test',
            mimeType:          'application/json',
            maxTimeoutSeconds: 60,
        );

        $this->assertCount(1, $reqs);

        $req = $reqs[0];
        $this->assertSame('exact',           $req['scheme']);
        $this->assertSame('eip155:8453',     $req['network']);
        $this->assertSame('10000',           $req['maxAmountRequired']); // 0.01 USDC in atomic units
        $this->assertSame('0xWallet',        $req['payTo']);
        $this->assertSame('0xUSDCContract',  $req['asset']);
        $this->assertSame(60,                $req['maxTimeoutSeconds']);
        $this->assertSame('2',               $req['extra']['version']);
        $this->assertSame('USDC',            $req['extra']['name']);
        $this->assertSame(6,                 $req['extra']['decimals']);
    }

    #[Test]
    public function it_converts_dollar_prefixed_price_correctly(): void
    {
        $reqs = $this->builder->build(
            request:       $this->makeRequest(),
            price:         '$0.001',
            assetSymbol:   'USDC',
            walletAddress: '0xWallet',
            network:       'eip155:8453',
            assetAddress:  '0xContract',
        );

        // $0.001 USDC = 1000 atomic units (6 decimals)
        $this->assertSame('1000', $reqs[0]['maxAmountRequired']);
    }

    #[Test]
    public function it_includes_full_url_as_resource(): void
    {
        $reqs = $this->builder->build(
            request:       $this->makeRequest('https://api.example.com/v1/weather'),
            price:         '0.01',
            assetSymbol:   'USDC',
            walletAddress: '0xWallet',
            network:       'eip155:8453',
            assetAddress:  '0xContract',
        );

        $this->assertSame('https://api.example.com/v1/weather', $reqs[0]['resource']);
    }

    #[Test]
    public function it_throws_when_wallet_address_is_empty(): void
    {
        $this->expectException(MissingWalletAddressException::class);

        $this->builder->build(
            request:       $this->makeRequest(),
            price:         '0.01',
            assetSymbol:   'USDC',
            walletAddress: '',   // ← empty
            network:       'eip155:8453',
            assetAddress:  '0xContract',
        );
    }

    #[Test]
    public function it_throws_when_asset_address_is_empty(): void
    {
        $this->expectException(MissingAssetAddressException::class);

        $this->builder->build(
            request:       $this->makeRequest(),
            price:         '0.01',
            assetSymbol:   'USDC',
            walletAddress: '0xWallet',
            network:       'eip155:8453',
            assetAddress:  '',   // ← empty
        );
    }

    // ── encode / decode ───────────────────────────────────────────────────

    #[Test]
    public function it_base64_encodes_and_decodes_requirements(): void
    {
        $reqs = $this->builder->build(
            request:       $this->makeRequest(),
            price:         '0.01',
            assetSymbol:   'USDC',
            walletAddress: '0xWallet',
            network:       'eip155:8453',
            assetAddress:  '0xContract',
        );

        $encoded = $this->builder->encode($reqs);
        $this->assertIsString($encoded);

        // Must be valid Base64
        $this->assertNotFalse(base64_decode($encoded, strict: true));

        $decoded = $this->builder->decode($encoded);
        $this->assertEquals($reqs, $decoded);
    }

    #[Test]
    public function it_decodes_roundtrip_preserving_all_fields(): void
    {
        $reqs    = $this->builder->build(
            request:           $this->makeRequest('https://api.test/data'),
            price:             '1',
            assetSymbol:       'USDC',
            walletAddress:     '0xABC',
            network:           'eip155:137',
            assetAddress:      '0xDEF',
            scheme:            'exact',
            description:       'Premium data',
            mimeType:          'application/json',
            maxTimeoutSeconds: 120,
        );

        $decoded = $this->builder->decode($this->builder->encode($reqs));

        $this->assertSame('1000000', $decoded[0]['maxAmountRequired']);
        $this->assertSame('eip155:137', $decoded[0]['network']);
        $this->assertSame(120, $decoded[0]['maxTimeoutSeconds']);
        $this->assertSame('Premium data', $decoded[0]['description']);
    }
}
