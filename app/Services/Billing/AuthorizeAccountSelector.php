<?php

namespace App\Services\Billing;

use App\Models\AuthorizeAccount;
use App\Services\Billing\Anet\ArbGateway;

/**
 * Chooses which merchant account to use. Mirrors the legacy behaviour:
 * a `is_default_charge` / `is_default_client` account wins outright;
 * otherwise pick among active accounts weighted by `weight`.
 */
class AuthorizeAccountSelector
{
    public function __construct(private readonly ArbGateway $arb) {}

    public function pickForCharge(): ?AuthorizeAccount
    {
        return AuthorizeAccount::query()->where('is_active', 1)->where('is_default_charge', 1)->first()
            ?? $this->weightedActive();
    }

    public function pickForNewClient(): ?AuthorizeAccount
    {
        return AuthorizeAccount::query()->where('is_active', 1)->where('is_default_client', 1)->first()
            ?? $this->weightedActive();
    }

    /** Weighted random pick among active accounts. */
    public function weightedActive(): ?AuthorizeAccount
    {
        $accounts = AuthorizeAccount::query()->where('is_active', 1)->get();

        if ($accounts->isEmpty()) {
            return null;
        }

        $total = (int) $accounts->sum(fn (AuthorizeAccount $a) => max(1, (int) $a->weight));
        $roll = random_int(1, $total);

        foreach ($accounts as $account) {
            $roll -= max(1, (int) $account->weight);
            if ($roll <= 0) {
                return $account;
            }
        }

        return $accounts->first();
    }

    /**
     * Probe each account for a subscription id (used when a webhook / refund
     * arrives without an obvious owning account). Ported from the legacy
     * `findAuthorizeAccountBySubscriptionId`.
     */
    public function bySubscriptionId(string $subscriptionId): ?AuthorizeAccount
    {
        foreach (AuthorizeAccount::query()->orderBy('id')->get() as $account) {
            $resp = $this->arb->getSubscription($account, $subscriptionId);

            if ($resp && data_get($resp, 'messages.resultCode') === 'Ok') {
                return $account;
            }
        }

        return null;
    }
}
