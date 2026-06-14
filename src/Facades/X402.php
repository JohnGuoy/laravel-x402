<?php

namespace JohnGuoy\LaravelX402\Facades;

use Illuminate\Support\Facades\Facade;
use JohnGuoy\LaravelX402\Support\X402Manager;

/**
 * @method static \JohnGuoy\LaravelX402\Contracts\FacilitatorContract facilitator(string|null $key = null)
 * @method static string assetAddress(string $symbol, string $network)
 *
 * @see \JohnGuoy\LaravelX402\Support\X402Manager
 */
class X402 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'x402';
    }
}
