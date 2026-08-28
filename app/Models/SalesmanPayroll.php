<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded "Mark Paid" payroll run for a salesman. Brand-new table
 * (`salesman_payrolls`) — see the Phase 4 / Salesmen-module rebuild.
 */
class SalesmanPayroll extends Model
{
    protected $table = 'salesman_payrolls';

    protected $fillable = [
        'user_id', 'period_start', 'period_end', 'pay_date',
        'sales_count', 'gross', 'spiff', 'net',
        'run_by_user_id', 'run_by_email',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'pay_date' => 'date',
            'sales_count' => 'integer',
            'gross' => 'decimal:2',
            'spiff' => 'decimal:2',
            'net' => 'decimal:2',
        ];
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function runBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by_user_id');
    }
}
