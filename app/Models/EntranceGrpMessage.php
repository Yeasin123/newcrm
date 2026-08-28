<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of a named entrance blast message group. Legacy
 * `entrance_grp_messages`: every line in a group shares a `unique_grp_id`
 * (a `uniqid()`), so a group is a set of rows with the same `grp_name` /
 * `unique_grp_id`.
 */
class EntranceGrpMessage extends Model
{
    protected $table = 'entrance_grp_messages';

    protected $fillable = ['grp_name', 'text', 'unique_grp_id'];
}
