<?php

namespace JohnGuoy\LaravelX402\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use JohnGuoy\LaravelX402\Exceptions\UnsupportedAssetException;
use JohnGuoy\LaravelX402\Support\PaymentAmount;
use JohnGuoy\LaravelX402\Tests\TestCase;

class PaymentAmountTest extends TestCase
{
    private PaymentAmount $amount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->amount = new PaymentAmount();
    }

    // ── toAtomicUnits ─────────────────────────────────────────────────────

    #[Test]
    public function it_converts_usdc_one_dollar_to_atomic_units(): void
    {
        $this->assertSame('1000000', $this->amount->toAtomicUnits('1', 'USDC'));
    }

    #[Test]
    public function it_converts_usdc_one_cent_to_atomic_units(): void
    {
        $this->assertSame('10000', $this->amount->toAtomicUnits('0.01', 'USDC'));
    }

    #[Test]
    public function it_converts_usdc_one_tenth_cent_to_atomic_units(): void
    {
        $this->assertSame('1000', $this->amount->toAtomicUnits('0.001', 'USDC'));
    }

    #[Test]
    public function it_converts_usdc_minimum_unit_to_atomic(): void
    {
        // 0.000001 USDC = 1 atomic unit
        $this->assertSame('1', $this->amount->toAtomicUnits('0.000001', 'USDC'));
    }

    #[Test]
    public function it_converts_usdc_large_amount_to_atomic_units(): void
    {
        $this->assertSame('100000000', $this->amount->toAtomicUnits('100', 'USDC'));
    }

    #[Test]
    public function it_converts_usdc_decimal_with_no_floating_point_error(): void
    {
        // Classic floating-point trap: 0.1 + 0.2 ≠ 0.3 in IEEE 754
        // BCMath must give us exact results
        $this->assertSame('300000', $this->amount->toAtomicUnits('0.3', 'USDC'));
        $this->assertSame('100000', $this->amount->toAtomicUnits('0.1', 'USDC'));
        $this->assertSame('200000', $this->amount->toAtomicUnits('0.2', 'USDC'));
    }

    #[Test]
    public function it_converts_eth_to_atomic_units_with_18_decimals(): void
    {
        $this->assertSame(
            '1000000000000000000',
            $this->amount->toAtomicUnits('1', 'ETH')
        );
    }

    #[Test]
    public function it_converts_sol_to_lamports_with_9_decimals(): void
    {
        $this->assertSame('1000000000', $this->amount->toAtomicUnits('1', 'SOL'));
    }

    #[Test]
    public function it_converts_usdt_same_as_usdc(): void
    {
        $this->assertSame('10000', $this->amount->toAtomicUnits('0.01', 'USDT'));
    }

    // ── fromAtomicUnits ───────────────────────────────────────────────────

    #[Test]
    public function it_converts_atomic_units_back_to_usdc(): void
    {
        $this->assertSame('1.000000', $this->amount->fromAtomicUnits('1000000', 'USDC'));
    }

    #[Test]
    public function it_converts_small_atomic_units_to_usdc(): void
    {
        $this->assertSame('0.010000', $this->amount->fromAtomicUnits('10000', 'USDC'));
    }

    #[Test]
    public function it_round_trips_usdc_amount_correctly(): void
    {
        $humanOriginal = '0.05';
        $atomic        = $this->amount->toAtomicUnits($humanOriginal, 'USDC');
        $humanBack     = $this->amount->fromAtomicUnits($atomic, 'USDC');

        // fromAtomicUnits returns 6 decimal places; compare numerically
        $this->assertEquals((float) $humanOriginal, (float) $humanBack);
    }

    // ── decimals ──────────────────────────────────────────────────────────

    #[Test]
    public function it_returns_correct_decimals_for_usdc(): void
    {
        $this->assertSame(6, $this->amount->decimals('USDC'));
    }

    #[Test]
    public function it_returns_correct_decimals_for_eth(): void
    {
        $this->assertSame(18, $this->amount->decimals('ETH'));
    }

    #[Test]
    public function it_is_case_insensitive_for_asset_symbol(): void
    {
        $this->assertSame(6, $this->amount->decimals('usdc'));
        $this->assertSame(6, $this->amount->decimals('Usdc'));
    }

    #[Test]
    public function it_throws_for_unknown_asset_symbol(): void
    {
        $this->expectException(UnsupportedAssetException::class);
        $this->amount->decimals('UNKNOWNCOIN');
    }

    // ── parsePrice ────────────────────────────────────────────────────────

    #[Test]
    public function it_parses_dollar_prefixed_price(): void
    {
        $result = $this->amount->parsePrice('$0.01');
        $this->assertSame('0.01', $result['amount']);
        $this->assertSame('USDC', $result['symbol']); // falls back to default
    }

    #[Test]
    public function it_parses_price_with_explicit_symbol(): void
    {
        $result = $this->amount->parsePrice('0.01 USDT');
        $this->assertSame('0.01', $result['amount']);
        $this->assertSame('USDT', $result['symbol']);
    }

    #[Test]
    public function it_parses_plain_numeric_price(): void
    {
        $result = $this->amount->parsePrice('0.5', 'USDC');
        $this->assertSame('0.5', $result['amount']);
        $this->assertSame('USDC', $result['symbol']);
    }

    #[Test]
    public function it_parses_dollar_prefixed_price_with_symbol(): void
    {
        $result = $this->amount->parsePrice('$1.50 USDC');
        $this->assertSame('1.50', $result['amount']);
        $this->assertSame('USDC', $result['symbol']);
    }

    // ── register (custom asset) ───────────────────────────────────────────

    #[Test]
    public function it_allows_registering_a_custom_asset(): void
    {
        $this->amount->register('MYTOKEN', 8);
        $this->assertSame(8, $this->amount->decimals('MYTOKEN'));
        $this->assertSame('100000000', $this->amount->toAtomicUnits('1', 'MYTOKEN'));
    }
}
