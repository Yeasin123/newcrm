<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Legacy `bonuses` — a single config row. `commission_double = 1` doubles
 * every salesman commission at sale time.
 */
class Bonus extends Model
{
    protected $table = 'bonuses';

    protected $fillable = [
        'bonus_one', 'bonus_one_price', 'bonus_two', 'bonus_two_price', 'commission_double',
    ];

    protected function casts(): array
    {
        return [
            'commission_double' => 'boolean',
            'bonus_one_price' => 'float',
            'bonus_two_price' => 'float',
        ];
    }
}
