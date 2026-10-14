<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owed fuel: a credit slip is billed in full, but part of the fuel is
     * given on a later day. The owed part is not in that day's nozzle sales,
     * and is pumped later without a new bill, so the daily closing needs both.
     */
    public function up(): void
    {
        Schema::table('customer_credits', function (Blueprint $table) {
            $table->decimal('owed_quantity', 12, 2)->default(0)->after('quantity');
        });

        Schema::create('owed_fuel_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_credit_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('fuel_product_id')->constrained()->restrictOnDelete();
            $table->date('business_date')->index();
            $table->decimal('quantity', 12, 2);
            $table->decimal('amount', 14, 2);
            $table->string('vehicle_number', 30)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('daily_closings', function (Blueprint $table) {
            $table->decimal('owed_fuel_billed', 14, 2)->default(0)->after('credit_sales');
            $table->decimal('owed_fuel_given', 14, 2)->default(0)->after('owed_fuel_billed');
        });
    }

    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            $table->dropColumn(['owed_fuel_billed', 'owed_fuel_given']);
        });

        Schema::dropIfExists('owed_fuel_deliveries');

        Schema::table('customer_credits', function (Blueprint $table) {
            $table->dropColumn('owed_quantity');
        });
    }
};
