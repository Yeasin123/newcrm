<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per plan-change attempt. Legacy `plan_change_logs` — the audit trail
 * for the Edit Plan "Billing Actions" flow.
 *
 * status: pending → completed | failed | failed_partial
 */
class PlanChangeLog extends Model
{
    protected $table = 'plan_change_logs';

    protected $fillable = [
        'client_id', 'changed_by_user_id',
        'old_package_id', 'old_payment_structure', 'old_installment', 'old_term_months',
        'new_term_months', 'new_package_id', 'new_payment_structure', 'new_installment_type',
        'amount_already_paid', 'new_total_price', 'remaining_or_credit',
        'immediate_charge_amount', 'new_installment_amount', 'new_installment_occurrences',
        'old_subscription_id', 'old_cancel_result', 'new_subscription_id', 'new_arb_start_date',
        'overpaid_amount', 'surplus_amount', 'surplus_skip_months',
        'status', 'failure_stage', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'amount_already_paid' => 'decimal:2',
            'new_total_price' => 'decimal:2',
            'remaining_or_credit' => 'decimal:2',
            'immediate_charge_amount' => 'decimal:2',
            'new_installment_amount' => 'decimal:2',
            'overpaid_amount' => 'decimal:2',
            'surplus_amount' => 'decimal:2',
            'new_arb_start_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
