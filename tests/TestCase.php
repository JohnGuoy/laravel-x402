<?php

namespace JohnGuoy\LaravelX402\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use JohnGuoy\LaravelX402\X402ServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [X402ServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'X402' => \JohnGuoy\LaravelX402\Facades\X402::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('x402.wallet_address',       '0xTestWallet0000000000000000000000000001');
        $app['config']->set('x402.network',              'eip155:84532');
        $app['config']->set('x402.asset',                'USDC');
        $app['config']->set('x402.scheme',               'exact');
        $app['config']->set('x402.default_facilitator',  'testnet');
        $app['config']->set('x402.max_timeout_seconds',  60);
        $app['config']->set('x402.description',          'Test API');
        $app['config']->set('x402.mime_type',            'application/json');

        $app['config']->set('x402.decimals', [
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

        $app['config']->set('x402.assets', [
            'USDC' => [
                'eip155:8453'  => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
                'eip155:84532' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
                'eip155:1'     => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
            ],
        ]);

        $app['config']->set('x402.facilitators', [
            'testnet' => [
                'url'     => 'https://x402.org/facilitator',
                'timeout' => 15,
            ],
            'coinbase' => [
                'url'     => 'https://facilitator.cdp.coinbase.com',
                'timeout' => 10,
            ],
        ]);
    }
}
