<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Facilitator
    |--------------------------------------------------------------------------
    |
    | The key of the facilitator to use by default. Must match a key in the
    | "facilitators" array below. You can override per-route in middleware.
    |
    */

    'default_facilitator' => env('X402_FACILITATOR', 'coinbase'),

    /*
    |--------------------------------------------------------------------------
    | Facilitators
    |--------------------------------------------------------------------------
    |
    | x402 V2 separates the server (you) from the facilitator (a third-party
    | service that verifies and settles payments on-chain on your behalf).
    |
    | Known production facilitators (June 2026):
    |   Coinbase CDP   https://facilitator.cdp.coinbase.com
    |   Cloudflare     https://x402.cloudflare.com
    |   Dexter (free)  https://facilitator.dexter.cash
    |   Mogami (free)  https://facilitator.mogami.tech
    |   PayAI          https://facilitator.payai.network
    |   Polygon        https://facilitator.polygon.technology
    |   Stellar (free) https://facilitator.stellar.org   ← Stellar network only
    |   x402.org       https://x402.org/facilitator      ← testnet / dev only
    |
    */

    'facilitators' => [

        'coinbase' => [
            'url'     => env('X402_COINBASE_URL', 'https://facilitator.cdp.coinbase.com'),
            'timeout' => (int) env('X402_COINBASE_TIMEOUT', 10),
        ],

        'cloudflare' => [
            'url'     => env('X402_CLOUDFLARE_URL', 'https://x402.cloudflare.com'),
            'timeout' => (int) env('X402_CLOUDFLARE_TIMEOUT', 10),
        ],

        'stellar' => [
            'url'     => env('X402_STELLAR_URL', 'https://facilitator.stellar.org'),
            'timeout' => (int) env('X402_STELLAR_TIMEOUT', 10),
        ],

        'dexter' => [
            'url'     => env('X402_DEXTER_URL', 'https://facilitator.dexter.cash'),
            'timeout' => (int) env('X402_DEXTER_TIMEOUT', 10),
        ],

        'mogami' => [
            'url'     => env('X402_MOGAMI_URL', 'https://facilitator.mogami.tech'),
            'timeout' => (int) env('X402_MOGAMI_TIMEOUT', 10),
        ],

        'testnet' => [
            'url'     => env('X402_TESTNET_URL', 'https://x402.org/facilitator'),
            'timeout' => (int) env('X402_TESTNET_TIMEOUT', 15),
        ],

        // Self-hosted facilitator example:
        // 'custom' => [
        //     'url'     => env('X402_CUSTOM_URL', 'https://your-facilitator.example.com'),
        //     'timeout' => 10,
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Receiving Wallet Address
    |--------------------------------------------------------------------------
    |
    | The on-chain address that receives payments.
    | For EVM networks: a 0x… Ethereum-style address.
    | For Solana: a base-58 public key.
    |
    */

    'wallet_address' => env('X402_WALLET_ADDRESS'),

    /*
    |--------------------------------------------------------------------------
    | Default Network (CAIP-2 format)
    |--------------------------------------------------------------------------
    |
    | x402 V2 mandates CAIP-2 identifiers for all network references.
    |
    | Common values:
    |   eip155:8453   = Base Mainnet           ← recommended (low fees, fast)
    |   eip155:84532  = Base Sepolia Testnet
    |   eip155:1      = Ethereum Mainnet
    |   eip155:137    = Polygon Mainnet
    |   eip155:43114  = Avalanche C-Chain
    |   solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp = Solana Mainnet
    |   solana:EtWTRABZaYq6iMfeYKouRu166VU2xqa1 = Solana Devnet
    |
    */

    'network' => env('X402_NETWORK', 'eip155:8453'),

    /*
    |--------------------------------------------------------------------------
    | Default Payment Scheme
    |--------------------------------------------------------------------------
    |
    | x402 V2 supports:
    |   exact            – fixed price per request (most common)
    |   upto             – buyer authorises a max; seller charges actual usage
    |   batch-settlement – off-chain vouchers redeemed on-chain in batches
    |
    */

    'scheme' => env('X402_SCHEME', 'exact'),

    /*
    |--------------------------------------------------------------------------
    | Token Contract Addresses
    |--------------------------------------------------------------------------
    |
    | On-chain contract addresses for each supported asset.
    | Keyed by token symbol. The address must match the network above.
    |
    | USDC has 6 decimal places on all EVM chains.
    |
    */

    'assets' => [

        // USDC — 6 decimals
        'USDC' => [
            'eip155:8453'   => env('X402_USDC_BASE',     '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913'),
            'eip155:84532'  => env('X402_USDC_BASE_SEP', '0x036CbD53842c5426634e7929541eC2318f3dCF7e'),
            'eip155:1'      => env('X402_USDC_ETH',      '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48'),
            'eip155:137'    => env('X402_USDC_POLYGON',  '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359'),
            'eip155:43114'  => env('X402_USDC_AVAX',     '0xB97EF9Ef8734C71904D8002F8b6Bc66Dd9c48a6E'),
        ],

        // USDT — 6 decimals on most EVM chains
        'USDT' => [
            'eip155:1'      => env('X402_USDT_ETH',      '0xdAC17F958D2ee523a2206206994597C13D831ec7'),
            'eip155:137'    => env('X402_USDT_POLYGON',  '0xc2132D05D31c914a87C6611C10748AEb04B58e8F'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Asset Symbol
    |--------------------------------------------------------------------------
    */

    'asset' => env('X402_ASSET', 'USDC'),

    /*
    |--------------------------------------------------------------------------
    | Token Decimal Places
    |--------------------------------------------------------------------------
    |
    | Used by PaymentAmount for atomic-unit conversion.
    | USDC and USDT are 6 decimals; ETH/WETH are 18.
    |
    */

    'decimals' => [
        'USDC'  => 6,
        'USDT'  => 6,
        'EURC'  => 6,
        'DAI'   => 18,
        'ETH'   => 18,
        'WETH'  => 18,
        'MATIC' => 18,
        'SOL'   => 9,
        'XLM'   => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds a signed PAYMENT-SIGNATURE payload remains valid before the
    | server rejects it as stale. x402 V2 recommends 60–300 seconds.
    |
    */

    'max_timeout_seconds' => (int) env('X402_MAX_TIMEOUT_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Default Description & MIME Type
    |--------------------------------------------------------------------------
    |
    | Sent inside the PAYMENT-REQUIRED header so clients/agents know what
    | they are purchasing before authorising a payment.
    |
    */

    'description' => env('X402_DESCRIPTION', 'API access via x402'),
    'mime_type'   => env('X402_MIME_TYPE', 'application/json'),

];
