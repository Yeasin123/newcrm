<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Legacy `buyer_sellers` table. */
class BuyerSeller extends Model
{
    protected $table = 'buyer_sellers';

    protected $guarded = ['id'];
}
