<?php

namespace App\Policies;

use App\Models\FailedPaymentCase;
use App\Models\User;

/**
 * Ported from the legacy policy. Delegates to `User::hasPermission()` (which
 * already handles the `role_id IS NULL` superadmin bypass and the array-cast
 * `roles.permissions`), keeping the same `failed-payments.*` permission map.
 */
class FailedPaymentCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('failed-payments.view');
    }

    public function view(User $user, FailedPaymentCase $failedPaymentCase): bool
    {
        return $user->hasPermission('failed-payments.view-details')
            || $user->hasPermission('failed-payments.view');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('failed-payments.export');
    }

    public function reports(User $user): bool
    {
        return $user->hasPermission('failed-payments.reports');
    }

    public function update(User $user, FailedPaymentCase $failedPaymentCase): bool
    {
        return $user->hasPermission('failed-payments.update');
    }

    public function assign(User $user, FailedPaymentCase $failedPaymentCase): bool
    {
        return $user->hasPermission('failed-payments.assign');
    }

    public function notes(User $user, FailedPaymentCase $failedPaymentCase): bool
    {
        return $user->hasPermission('failed-payments.notes');
    }

    public function controlRetry(User $user, FailedPaymentCase $failedPaymentCase): bool
    {
        return $user->hasPermission('failed-payments.retry-control');
    }

    public function simulateRetry(User $user, FailedPaymentCase $failedPaymentCase): bool
    {
        return $user->hasPermission('failed-payments.dry-run');
    }

    public function communications(User $user, FailedPaymentCase $failedPaymentCase): bool
    {
        return $user->hasPermission('failed-payments.communications');
    }
}
