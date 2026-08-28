<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy `salesman_activities` (`button_text` is JSON, `create_date` a string).
 */
class SalesmanActivity extends Model
{
    protected $table = 'salesman_activities';

    protected $fillable = ['user_id', 'client_id', 'button_text', 'status', 'create_date'];

    protected function casts(): array
    {
        return [
            'button_text' => 'array',
        ];
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
