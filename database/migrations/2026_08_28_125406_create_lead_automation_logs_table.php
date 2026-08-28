<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 — the first brand-new table in the rebuild. One row per
 * (lead, automation action); the unique key makes every automation
 * duplicate-safe (a welcome email is never sent twice).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_automation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->string('action_key', 64);
            $table->string('status', 20)->default('pending'); // pending | sent | failed | skipped
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['lead_id', 'action_key']);
            $table->index(['action_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_automation_logs');
    }
};
