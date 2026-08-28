<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row credential store for the external Entrance Group blast platform.
 * Legacy `entrance_auths`: `entrance_{one,two,three}_data` hold a JSON blob of
 * OAuth tokens per entrance (userIds 4055 / 4479 / 4480), `entrance_userIds`
 * is the JSON list of configured ids, and `message_{one,two,three}` cache the
 * selected message group per entrance.
 */
class EntranceAuth extends Model
{
    protected $table = 'entrance_auths';

    protected $fillable = [
        'entrance_one_data', 'entrance_two_data', 'entrance_three_data',
        'entrance_userIds', 'message_one', 'message_two', 'message_three',
    ];

    /** Maps a legacy entrance userId to its data column. */
    public const SLOTS = [
        4055 => 'entrance_one_data',
        4479 => 'entrance_two_data',
        4480 => 'entrance_three_data',
    ];

    public const MESSAGE_SLOTS = [
        4055 => 'message_one',
        4479 => 'message_two',
        4480 => 'message_three',
    ];

    public static function singleton(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
