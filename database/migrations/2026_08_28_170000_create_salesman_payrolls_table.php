<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand-new table (no legacy equivalent): one row per "Mark Paid" payroll
 * run for a salesman, so the Salesmen module can show a real Payroll
 * History instead of only a flipped sale_commissions.is_paid flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salesman_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date');
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('spiff', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->unsignedBigInteger('run_by_user_id')->nullable();
            $table->string('run_by_email', 190)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'pay_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salesman_payrolls');
    }
};
