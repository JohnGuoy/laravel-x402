# Changelog

All notable changes to `laravel-x402` will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] — 2026-06-14

### Added
- Initial release
- `x402` route middleware implementing x402 V2 server flow (verify + settle)
- `X402Payment` middleware with route-level price/asset/facilitator/network overrides
- `PaymentAmount` — BCMath precision-safe atomic unit conversion for USDC (6 dec), ETH (18 dec), SOL (9 dec), and more
- `PaymentRequirement` — x402 V2 compliant `PAYMENT-REQUIRED` header builder with Base64 encode/decode
- `HttpFacilitator` — Guzzle-based client for `/verify` and `/settle` facilitator endpoints
- `X402Manager` — lazy-cached facilitator resolution and asset address lookup from config
- `X402` Facade
- `X402ServiceProvider` with auto-discovery, config publishing (`vendor:publish --tag=x402-config`), and middleware alias
- Config support for Coinbase, Cloudflare, Stellar, Dexter, Mogami, and custom self-hosted facilitators
- PHPUnit 11/12 test suite — Unit (PaymentAmount, PaymentRequirement, X402Manager) + Feature (middleware HTTP flow)
- Full README with installation, config reference, usage examples, and Packagist publishing guide
