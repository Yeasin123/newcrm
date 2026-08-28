<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Restricts a promo code to a set of packages. Legacy `promotion_conditions`.
 * `package_id` is a JSON-encoded string array of package ids (the legacy
 * autoApplyPromoCode matches it with `JSON_CONTAINS(package_id, ?)`).
 */
class PromotionCondition extends Model
{
    protected $table = 'promotion_conditions';

    protected $fillable = ['promotion_id', 'name', 'package_id'];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }

    /** @return array<int, string> */
    public function packageIds(): array
    {
        $decoded = json_decode((string) $this->package_id, true);

        return is_array($decoded) ? array_map('strval', $decoded) : [];
    }
}
