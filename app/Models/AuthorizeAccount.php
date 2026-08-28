<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A merchant Authorize.Net account. Legacy `authorize_accounts`.
 * Credential columns are stored encrypted at rest (Eloquent `encrypted` cast)
 * — the old app uses the same cast, so ciphertext is compatible as long as
 * APP_KEY matches. Multiple accounts are selected by `weight` when charging.
 */
class AuthorizeAccount extends Model
{
    protected $table = 'authorize_accounts';

    protected $fillable = [
        'name', 'environment', 'api_login_id', 'transaction_key',
        'public_client_key', 'signature_key', 'is_active', 'is_disabled',
        'is_default_charge', 'is_default_client', 'weight', 'notes',
    ];

    protected $hidden = [
        'api_login_id', 'transaction_key', 'public_client_key', 'signature_key',
    ];

    protected function casts(): array
    {
        return [
            'api_login_id' => 'encrypted',
            'transaction_key' => 'encrypted',
            'public_client_key' => 'encrypted',
            'signature_key' => 'encrypted',
            'is_active' => 'boolean',
            'is_disabled' => 'boolean',
            'is_default_charge' => 'boolean',
            'is_default_client' => 'boolean',
            'weight' => 'integer',
        ];
    }
}
