<?php

namespace JohnGuoy\LaravelX402\Tests\Unit;

use JohnGuoy\LaravelX402\Exceptions\FacilitatorException;
use JohnGuoy\LaravelX402\Exceptions\MissingAssetAddressException;
use JohnGuoy\LaravelX402\Support\HttpFacilitator;
use JohnGuoy\LaravelX402\Support\X402Manager;
use JohnGuoy\LaravelX402\Tests\TestCase;

class X402ManagerTest extends TestCase
{
    private X402Manager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new X402Manager();
    }

    // ── facilitator() ─────────────────────────────────────────────────────

    /** @test */
    public function it_resolves_the_default_facilitator(): void
    {
        $facilitator = $this->manager->facilitator(); // uses config default: 'testnet'

        $this->assertInstanceOf(HttpFacilitator::class, $facilitator);
    }

    /** @test */
    public function it_resolves_a_named_facilitator(): void
    {
        $facilitator = $this->manager->facilitator('coinbase');

        $this->assertInstanceOf(HttpFacilitator::class, $facilitator);
        $this->assertSame('https://facilitator.cdp.coinbase.com', $facilitator->getBaseUrl());
    }

    /** @test */
    public function it_returns_the_same_instance_on_second_call(): void
    {
        $first  = $this->manager->facilitator('testnet');
        $second = $this->manager->facilitator('testnet');

        $this->assertSame($first, $second);
    }

    /** @test */
    public function it_throws_for_unconfigured_facilitator(): void
    {
        $this->expectException(FacilitatorException::class);

        $this->manager->facilitator('does-not-exist');
    }

    // ── assetAddress() ────────────────────────────────────────────────────

    /** @test */
    public function it_resolves_usdc_address_on_base_mainnet(): void
    {
        $address = $this->manager->assetAddress('USDC', 'eip155:8453');

        $this->assertSame('0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', $address);
    }

    /** @test */
    public function it_resolves_usdc_address_on_base_sepolia(): void
    {
        $address = $this->manager->assetAddress('USDC', 'eip155:84532');

        $this->assertSame('0x036CbD53842c5426634e7929541eC2318f3dCF7e', $address);
    }

    /** @test */
    public function it_throws_for_unknown_asset_network_combination(): void
    {
        $this->expectException(MissingAssetAddressException::class);

        $this->manager->assetAddress('USDC', 'eip155:9999999');
    }

    /** @test */
    public function it_throws_for_unknown_asset_symbol(): void
    {
        $this->expectException(MissingAssetAddressException::class);

        $this->manager->assetAddress('UNKNOWNCOIN', 'eip155:8453');
    }
}
