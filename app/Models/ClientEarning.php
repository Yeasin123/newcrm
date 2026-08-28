<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-client rolled-up totals produced by the earnings calculator.
 * Legacy `client_earnings` (`total_earn`, `total_profit`).
 */
class ClientEarning extends Model
{
    protected $table = 'client_earnings';

    protected $fillable = ['client_id', 'total_earn', 'total_profit'];

    protected function casts(): array
    {
        return [
            'total_earn' => 'float',
            'total_profit' => 'float',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
