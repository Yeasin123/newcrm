<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An audit row per successful customer-portal sign-in. Legacy
 * `client_login_infos` (written by Web\LoginController::login).
 */
class ClientLoginInfo extends Model
{
    protected $table = 'client_login_infos';

    protected $fillable = ['client_id', 'ip_info', 'last_login_at'];

    protected function casts(): array
    {
        return ['last_login_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
