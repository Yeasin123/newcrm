<?php

namespace App\Services\FailedPayment;

class FailedPaymentFailureCategoryMapper
{
    public function map(?string $failureCode = null, ?string $failureReason = null, ?string $eventType = null): string
    {
        $haystack = $this->normalizeText(implode(' ', array_filter([
            $failureCode,
            $failureReason,
            $eventType,
        ])));

        if ($this->containsAny($haystack, ['insufficient funds', 'insufficient balance', 'nsf', 'not sufficient'])) {
            return 'insufficient_funds';
        }

        if ($this->containsAny($haystack, ['expired card', 'card expired', 'expiration date', 'expired'])) {
            return 'expired_card';
        }

        if ($this->containsAny($haystack, ['payment profile missing', 'profile missing', 'customer payment profile not found', 'profile not found'])) {
            return 'payment_profile_missing';
        }

        if ($this->containsAny($haystack, ['invalid payment profile', 'payment profile invalid', 'invalid profile'])) {
            return 'invalid_payment_profile';
        }

        if ($this->containsAny($haystack, ['account closed', 'closed account'])) {
            return 'account_closed';
        }

        if ($this->containsAny($haystack, ['suspected fraud', 'fraud', 'held'])) {
            return 'suspected_fraud';
        }

        if ($this->containsAny($haystack, ['processor unavailable', 'processor error', 'gateway unavailable', 'gateway error'])) {
            return 'processor_unavailable';
        }

        if ($this->containsAny($haystack, ['temporary gateway error', 'temporary error', 'timeout', 'service unavailable', 'try again'])) {
            return 'temporary_gateway_error';
        }

        if ($this->containsAny($haystack, ['configuration error', 'invalid credentials', 'authentication failed', 'merchant error'])) {
            return 'configuration_error';
        }

        if ($this->containsAny($haystack, ['duplicate transaction', 'duplicate'])) {
            return 'duplicate_transaction';
        }

        if ($this->containsAny($haystack, ['declined', 'do not honor', 'card declined'])) {
            return 'declined';
        }

        if ($this->containsAny($haystack, ['payment profile', 'customer profile', 'subscription failed', 'subscription suspended', 'subscription terminated'])) {
            return 'unknown';
        }

        return 'unknown';
    }

    public function isRetryable(string $category): bool
    {
        $retryable = array_map('strval', (array) config('failed_payments.retryable_failure_categories', []));

        return in_array($category, $retryable, true);
    }

    public function normalize(?string $category): string
    {
        $category = strtolower(trim((string) $category));

        return $category !== '' ? str_replace([' ', '-'], '_', $category) : 'unknown';
    }

    protected function normalizeText(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }

    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
