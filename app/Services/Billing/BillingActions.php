<?php

namespace App\Services\Billing;

use Illuminate\Support\Carbon;

/**
 * The exact Authorize.Net actions staff checked on the Edit Plan panel, with
 * exactly the amounts/counts/dates they typed. Ported from
 * PlanChangeService::normalizeBillingActions — the mutual-exclusion + required
 * field rules are enforced in the constructor.
 */
class BillingActions
{
    public readonly bool $chargeToday;

    public readonly ?float $chargeTodayAmount;

    public readonly bool $updateExistingSubscription;

    public readonly ?float $updateExistingAmount;

    public readonly bool $cancelSubscription;

    public readonly bool $createNewSubscription;

    public readonly ?float $newSubscriptionAmount;

    public readonly ?int $newSubscriptionOccurrences;

    public readonly ?Carbon $newSubscriptionStartDate;

    public readonly bool $useNewCard;

    public readonly ?string $newCardDataDescriptor;

    public readonly ?string $newCardDataValue;

    public readonly ?string $newCardLast4;

    public readonly ?string $newCardExpMonth;

    public readonly ?string $newCardExpYear;

    public function __construct(array $a)
    {
        $this->chargeToday = ! empty($a['charge_today']);
        $this->updateExistingSubscription = ! empty($a['update_existing_subscription']);
        $this->cancelSubscription = ! empty($a['cancel_subscription']);
        $this->createNewSubscription = ! empty($a['create_new_subscription']);

        if ($this->updateExistingSubscription && $this->cancelSubscription) {
            throw new \RuntimeException('Pick one: either update or cancel the existing subscription, not both.');
        }
        if ($this->updateExistingSubscription && $this->createNewSubscription) {
            throw new \RuntimeException('Pick one: either update the existing subscription or create a new one, not both.');
        }
        if ($this->chargeToday && empty($a['charge_today_amount'])) {
            throw new \RuntimeException('Enter an amount to charge today.');
        }
        if ($this->updateExistingSubscription && empty($a['update_existing_amount'])) {
            throw new \RuntimeException('Enter the new amount for the existing subscription.');
        }
        if ($this->createNewSubscription) {
            foreach (['new_subscription_amount' => 'the amount', 'new_subscription_occurrences' => 'how many occurrences', 'new_subscription_start_date' => 'a start date'] as $key => $label) {
                if (empty($a[$key])) {
                    throw new \RuntimeException("Enter {$label} for the new subscription.");
                }
            }
        }

        $this->chargeTodayAmount = $this->chargeToday ? round((float) $a['charge_today_amount'], 2) : null;
        $this->updateExistingAmount = $this->updateExistingSubscription ? round((float) $a['update_existing_amount'], 2) : null;
        $this->newSubscriptionAmount = $this->createNewSubscription ? round((float) $a['new_subscription_amount'], 2) : null;
        $this->newSubscriptionOccurrences = $this->createNewSubscription ? (int) $a['new_subscription_occurrences'] : null;
        $this->newSubscriptionStartDate = $this->createNewSubscription ? Carbon::parse($a['new_subscription_start_date']) : null;

        $useNewCard = ! empty($a['use_new_card']) && ($this->chargeToday || $this->createNewSubscription);

        if ($useNewCard) {
            if (empty($a['new_card_data_descriptor']) || empty($a['new_card_data_value'])) {
                throw new \RuntimeException('New card details were not tokenized — please re-enter the card and try again.');
            }
            if (empty($a['new_card_last4']) || empty($a['new_card_exp_month']) || empty($a['new_card_exp_year'])) {
                throw new \RuntimeException('New card details are incomplete — please re-enter the card and try again.');
            }
        }

        $this->useNewCard = $useNewCard;
        $this->newCardDataDescriptor = $useNewCard ? $a['new_card_data_descriptor'] : null;
        $this->newCardDataValue = $useNewCard ? $a['new_card_data_value'] : null;
        $this->newCardLast4 = $useNewCard ? (string) $a['new_card_last4'] : null;
        $this->newCardExpMonth = $useNewCard ? (string) $a['new_card_exp_month'] : null;
        $this->newCardExpYear = $useNewCard ? (string) $a['new_card_exp_year'] : null;
    }

    public function anyBillingAction(): bool
    {
        return $this->chargeToday || $this->updateExistingSubscription
            || $this->cancelSubscription || $this->createNewSubscription;
    }
}
