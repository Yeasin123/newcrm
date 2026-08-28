<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-package price override. Legacy `custom_plan_prices`. When a row exists
 * for a package, `new_yearly` / `new_monthly` replace the catalog price.
 */
class CustomPlanPrice extends Model
{
    protected $table = 'custom_plan_prices';

    protected $fillable = [
        'package_id', 'original_yearly', 'original_monthly', 'new_yearly', 'new_monthly',
    ];

    protected function casts(): array
    {
        return [
            'original_yearly' => 'float',
            'original_monthly' => 'float',
            'new_yearly' => 'float',
            'new_monthly' => 'float',
        ];
    }
}
