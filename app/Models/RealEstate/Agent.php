<?php

namespace App\Models\RealEstate;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A real-estate agent — the Real Estate sub-product's user, on the `agent`
 * guard. Legacy `agents`.
 */
class Agent extends Authenticatable
{
    protected $table = 'agents';

    protected $fillable = [
        'company_id', 'role', 'name', 'image', 'email', 'second_email',
        'password', 'confirm_password', 'address_one', 'address_two',
        'city', 'state', 'postal_code', 'office_number', 'mobile_number',
    ];

    protected $hidden = ['password', 'confirm_password'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(AgentOrder::class, 'agent_id');
    }
}
