<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand-new table (Phase 12 — billing controls). A tiny key/value store so
 * the dry-run <-> live billing switch and its companion flags can be
 * flipped from the admin UI instead of editing `.env`. Read into
 * `config('billing.*')` on every request by BillingSettingsService::apply().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
