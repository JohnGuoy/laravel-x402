# laravel-x402

[![Latest Version on Packagist](https://img.shields.io/packagist/v/johnguoy/laravel-x402.svg?style=flat-square)](https://packagist.org/packages/johnguoy/laravel-x402)
[![Tests](https://img.shields.io/github/actions/workflow/status/johnguoy/laravel-x402/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/johnguoy/laravel-x402/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/johnguoy/laravel-x402.svg?style=flat-square)](https://packagist.org/packages/johnguoy/laravel-x402)
[![License](https://img.shields.io/packagist/l/johnguoy/laravel-x402.svg?style=flat-square)](LICENSE.md)

**laravel-x402** is a Laravel SDK for the [x402 V2](https://x402.org) HTTP payment protocol. Protect any API route with a single middleware line and start collecting **USDC micropayments** from AI agents and developer clients — no accounts, no API keys, no subscriptions.

```
Client                  Laravel (you)               Facilitator (Coinbase, etc.)
  |                          |                               |
  |── GET /api/data ─────────▶|                               |
  |                          |── no PAYMENT-SIGNATURE header  |
  |◀── 402 PAYMENT-REQUIRED ──|                               |
  |   (Base64 JSON in header)|                               |
  |                          |                               |
  | [client signs payment]   |                               |
  |                          |                               |
  |── GET /api/data ─────────▶|                               |
  |   PAYMENT-SIGNATURE: ... |── POST /verify ───────────────▶|
  |                          |◀── {isValid: true} ────────────|
  |                          |── POST /settle ───────────────▶|
  |                          |◀── {success, txHash} ──────────|
  |◀── 200 + PAYMENT-RESPONSE─|                               |
```

## Requirements

| Dependency | Version                       |
|---|-------------------------------|
| PHP | ^8.2                          |
| Laravel | ^11.0 \| ^12.0 \| ^13.0       |
| BCMath extension | enabled (standard in PHP 8.x) |

## Installation

```bash
composer require johnguoy/laravel-x402
```

Laravel's package auto-discovery will register the service provider and `X402` facade automatically.

### Publish the config file

```bash
php artisan vendor:publish --tag=x402-config
```

This creates `config/x402.php` in your application where you configure your wallet, networks, and facilitators.

## Configuration

Open `config/x402.php` (or set environment variables in `.env`):

```php
// config/x402.php

return [

    // Which facilitator to use by default
    'default_facilitator' => env('X402_FACILITATOR', 'coinbase'),

    // Facilitator endpoints
    'facilitators' => [
        'coinbase'   => ['url' => env('X402_COINBASE_URL',   'https://facilitator.cdp.coinbase.com'), 'timeout' => 10],
        'cloudflare' => ['url' => env('X402_CLOUDFLARE_URL', 'https://x402.cloudflare.com'),          'timeout' => 10],
        'stellar'    => ['url' => env('X402_STELLAR_URL',    'https://facilitator.stellar.org'),       'timeout' => 10],
        'dexter'     => ['url' => env('X402_DEXTER_URL',     'https://facilitator.dexter.cash'),       'timeout' => 10],
        'mogami'     => ['url' => env('X402_MOGAMI_URL',     'https://facilitator.mogami.tech'),       'timeout' => 10],
        'testnet'    => ['url' => env('X402_TESTNET_URL',    'https://x402.org/facilitator'),          'timeout' => 15],
    ],

    // Your receiving wallet address (EVM: 0x…, Solana: base58 pubkey)
    'wallet_address' => env('X402_WALLET_ADDRESS'),

    // Default network in CAIP-2 format
    'network' => env('X402_NETWORK', 'eip155:8453'), // Base Mainnet

    // Default payment scheme
    'scheme' => env('X402_SCHEME', 'exact'),

    // On-chain token contract addresses, keyed by symbol then CAIP-2 network
    'assets' => [
        'USDC' => [
            'eip155:8453'  => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', // Base Mainnet
            'eip155:84532' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e', // Base Sepolia
            'eip155:1'     => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', // Ethereum
            'eip155:137'   => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359', // Polygon
        ],
    ],

    // Token decimal places (USDC = 6, ETH = 18, SOL = 9, …)
    'decimals' => [
        'USDC' => 6, 'USDT' => 6, 'EURC' => 6,
        'DAI'  => 18, 'ETH' => 18, 'WETH' => 18, 'MATIC' => 18,
        'SOL'  => 9, 'XLM' => 7,
    ],

    'asset'               => env('X402_ASSET', 'USDC'),
    'max_timeout_seconds' => (int) env('X402_MAX_TIMEOUT_SECONDS', 60),
    'description'         => env('X402_DESCRIPTION', 'API access via x402'),
    'mime_type'           => env('X402_MIME_TYPE', 'application/json'),
];
```

### Minimal `.env` for development (testnet)

```dotenv
X402_WALLET_ADDRESS=0xYourWalletAddress
X402_NETWORK=eip155:84532
X402_FACILITATOR=testnet
```

### Minimal `.env` for production (Base Mainnet)

```dotenv
X402_WALLET_ADDRESS=0xYourWalletAddress
X402_NETWORK=eip155:8453
X402_FACILITATOR=coinbase
```

---

## Usage

### 1. Protect a route with the `x402` middleware

```php
// routes/api.php

use Illuminate\Support\Facades\Route;

// Basic: 1 cent per request, defaults from config
Route::get('/api/data', fn () => response()->json(['result' => 'secret']))
    ->middleware('x402:0.01');

// Custom price with explicit asset
Route::get('/api/premium', [ReportController::class, 'daily'])
    ->middleware('x402:0.10,USDC');

// Specify price, asset, and a non-default facilitator
Route::get('/api/pro', [ProController::class, 'data'])
    ->middleware('x402:1.00,USDC,coinbase');

// Group: same price for a whole section of your API
Route::middleware('x402:0.05')->group(function () {
    Route::get('/api/report/daily',  [ReportController::class, 'daily']);
    Route::get('/api/report/weekly', [ReportController::class, 'weekly']);
});
```

**Middleware parameter order:**
```
x402:{price},{asset},{facilitator},{network}
```
All parameters after `price` are optional and fall back to config values.

---

### 2. What clients receive

**Step 1 — request without payment (402 response):**

```http
HTTP/1.1 402 Payment Required
PAYMENT-REQUIRED: eyJzY2hlbWUiOiJleGFjdCIsIm5ldHdvcmsiOiJlaXAxNTU...
Content-Type: application/json

{
  "error": "Payment required.",
  "accepts": [{
    "scheme": "exact",
    "network": "eip155:8453",
    "maxAmountRequired": "10000",
    "resource": "https://yourapi.com/api/data",
    "description": "API access via x402",
    "mimeType": "application/json",
    "payTo": "0xYourWalletAddress",
    "maxTimeoutSeconds": 60,
    "asset": "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913",
    "extra": { "name": "USDC", "version": "2", "decimals": 6 }
  }]
}
```

**Step 2 — request with payment signature (200 response):**

```http
GET /api/data HTTP/1.1
PAYMENT-SIGNATURE: <Base64-encoded signed payment payload>

HTTP/1.1 200 OK
PAYMENT-RESPONSE: eyJzdWNjZXNzIjp0cnVlLCJ0eEhhc2giOiIweGFiYz...
Content-Type: application/json

{"result": "secret"}
```

---

### 3. Use the Facade for advanced scenarios

```php
use JohnGuoy\LaravelX402\Facades\X402;

// Resolve a facilitator and call it manually
$facilitator = X402::facilitator('coinbase');
$result = $facilitator->verify($paymentPayload, $requirements);

// Look up an asset contract address
$address = X402::assetAddress('USDC', 'eip155:8453');
// → "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913"
```

### 4. Use PaymentAmount for precision math

```php
use JohnGuoy\LaravelX402\Support\PaymentAmount;

$amount = app(PaymentAmount::class);

// Human-readable → atomic units (BCMath, no float drift)
$amount->toAtomicUnits('0.01', 'USDC');   // "10000"
$amount->toAtomicUnits('1',    'USDC');   // "1000000"
$amount->toAtomicUnits('1',    'ETH');    // "1000000000000000000"

// Atomic units → human-readable
$amount->fromAtomicUnits('10000',   'USDC');  // "0.010000"
$amount->fromAtomicUnits('1000000', 'USDC');  // "1.000000"

// Parse price strings
$amount->parsePrice('$0.01');        // ['amount' => '0.01', 'symbol' => 'USDC']
$amount->parsePrice('0.01 USDT');    // ['amount' => '0.01', 'symbol' => 'USDT']

// Register a custom token (e.g., 8 decimals)
$amount->register('MYTOKEN', 8);
```

---

## Networks & Facilitators

### Supported Networks (CAIP-2)

| Network | CAIP-2 |
|---|---|
| Base Mainnet | `eip155:8453` |
| Base Sepolia (testnet) | `eip155:84532` |
| Ethereum Mainnet | `eip155:1` |
| Polygon Mainnet | `eip155:137` |
| Avalanche C-Chain | `eip155:43114` |
| Solana Mainnet | `solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp` |
| Solana Devnet | `solana:EtWTRABZaYq6iMfeYKouRu166VU2xqa1` |

### Known Production Facilitators

| Key | URL | Notes |
|---|---|---|
| `coinbase` | `https://facilitator.cdp.coinbase.com` | KYT/OFAC checks |
| `cloudflare` | `https://x402.cloudflare.com` | Edge-native |
| `stellar` | `https://facilitator.stellar.org` | Stellar network only |
| `dexter` | `https://facilitator.dexter.cash` | Free, no account |
| `mogami` | `https://facilitator.mogami.tech` | Free, Base focused |
| `testnet` | `https://x402.org/facilitator` | Dev/testnet only |

---

## Testing

```bash
composer test
# or directly:
vendor/bin/phpunit
```

Run only unit or feature tests:

```bash
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Feature
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT — see [LICENSE.md](LICENSE.md).

## Credits

- Built on the [x402 V2 open specification](https://x402.org) — a Linux Foundation project
- Protocol co-founded by [Coinbase](https://coinbase.com) and [Cloudflare](https://cloudflare.com)
