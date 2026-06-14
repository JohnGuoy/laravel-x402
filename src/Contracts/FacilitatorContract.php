<?php

namespace JohnGuoy\LaravelX402\Contracts;

interface FacilitatorContract
{
    /**
     * Verify a payment payload against the declared payment requirements.
     *
     * @param  array<string, mixed>  $paymentPayload  Decoded from the PAYMENT-SIGNATURE header
     * @param  array<string, mixed>  $requirements    The PaymentRequirement sent in the 402
     * @return array{valid: bool, invalidReason: string|null}
     */
    public function verify(array $paymentPayload, array $requirements): array;

    /**
     * Settle (submit to chain) a previously verified payment.
     *
     * @param  array<string, mixed>  $paymentPayload
     * @param  array<string, mixed>  $requirements
     * @return array{success: bool, txHash: string|null, error: string|null}
     */
    public function settle(array $paymentPayload, array $requirements): array;
}
