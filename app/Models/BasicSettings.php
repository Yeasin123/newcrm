<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single-row shop / CMS settings record. Legacy `basic_settings` — a wide
 * table owned mostly by the public shop (Phase 12). Only the entrance-blast
 * knobs are declared fillable here; Phase 12 will grow the list.
 */
class BasicSettings extends Model
{
    protected $table = 'basic_settings';

    public $timestamps = false;

    protected $fillable = [
        'entrance_delay', 'entrance_split', 'lead_lock_time', 'telynx_webhook_recieve',
        // Public shop / marketing CMS (Phase 12).
        'shop_logo', 'promotion_heading', 'promotion_text_one', 'promotion_text_two',
        'promotion_text_three', 'button_text_one', 'button_text_number_one',
        'button_text_two', 'button_text_number_two', 'about_image', 'about_title',
        'about_text', 'work_title', 'work_subtitle', 'step_one', 'step_two',
        'step_three', 'icon_one', 'icon_two', 'icon_three', 'footer_number',
        'footer_text', 'monthly_bill', 'monthly_claim', 'monthly_customer',
        'choose_year',
    ];

    /** The one settings row, created on first access. */
    public static function singleton(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
