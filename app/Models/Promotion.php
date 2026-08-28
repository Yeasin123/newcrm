<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A promo code. Legacy `promotions`.
 *
 * `amount` is the discount magnitude; `promotion_type` selects how it is
 * applied — 1 = fixed dollar amount, 2 = percentage of the pre-discount
 * total (see config('checkout.discount_type') and the legacy
 * PlanCheckoutController::priceCalculator $discountType argument).
 */
class Promotion extends Model
{
    protected $table = 'promotions';

    protected $fillable = ['name', 'amount', 'promotion_type'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'promotion_type' => 'integer',
        ];
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PromotionCondition::class, 'promotion_id');
    }
}
