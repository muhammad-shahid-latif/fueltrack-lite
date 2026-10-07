<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Debt register. Fuel given on credit is already inside the nozzle sales,
     * so a credit entry only tags that part as unpaid. Counter items sold on
     * credit create their sale and the credit entry together.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('vehicle_number', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('tank_id')->constrained()->restrictOnDelete();
        });

        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('fuel_product_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('business_date')->index();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->decimal('rate', 10, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('vehicle_number', 30)->nullable();
            $table->string('slip_number', 30)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('business_date')->index();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 20);
            $table->string('reference', 50)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        // The cash count now also covers money received from credit customers
        // and cash paid to suppliers during the day.
        Schema::table('daily_closings', function (Blueprint $table) {
            $table->decimal('customer_receipts', 14, 2)->default(0)->after('cash_expenses');
            $table->decimal('supplier_payments', 14, 2)->default(0)->after('customer_receipts');
        });
    }

    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            $table->dropColumn(['customer_receipts', 'supplier_payments']);
        });

        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('customer_credits');

        Schema::table('sales', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));

        Schema::dropIfExists('customers');
    }
};
