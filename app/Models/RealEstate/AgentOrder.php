<?php

namespace App\Models\RealEstate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A home-warranty order placed by an agent through the Real Estate order
 * wizard. Legacy `agent_orders` — the per-step payloads are stored as JSON
 * text columns (`stepproperty`, `stepbuyer`, `addondata`, `closingInfo`,
 * `buyerAgent`, `sellerAgent`).
 */
class AgentOrder extends Model
{
    protected $table = 'agent_orders';

    protected $fillable = [
        'agent_id', 'plan_name', 'address_one', 'address_two', 'name', 'email', 'phone',
        'stepproperty', 'stepbuyer', 'addondata', 'closingInfo', 'buyerAgent', 'sellerAgent',
    ];

    protected function casts(): array
    {
        return [
            'stepproperty' => 'array',
            'stepbuyer' => 'array',
            'addondata' => 'array',
            'closingInfo' => 'array',
            'buyerAgent' => 'array',
            'sellerAgent' => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
}
