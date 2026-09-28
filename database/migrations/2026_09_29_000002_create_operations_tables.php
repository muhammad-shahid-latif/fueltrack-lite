<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nozzle_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->decimal('opening_meter', 14, 2);
            $table->decimal('closing_meter', 14, 2);
            $table->decimal('liters', 12, 2);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['business_date', 'nozzle_id']);
        });

        // One sale per meter reading. The rate is a snapshot, so later price
        // changes never rewrite historical sales.
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_reading_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('fuel_product_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->decimal('liters', 12, 2);
            $table->decimal('rate', 10, 2);
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->index(['business_date', 'fuel_product_id']);
        });

        Schema::create('fuel_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuel_product_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->decimal('quantity', 12, 2);
            $table->decimal('rate', 10, 2);
            $table->decimal('amount', 14, 2);
            $table->string('supplier_name', 100)->nullable();
            $table->string('invoice_number', 50)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['business_date', 'fuel_product_id']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->index();
            $table->string('category', 30)->index();
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        // Every figure is a snapshot taken when the day is closed, so the
        // record stays correct even if underlying data is edited later.
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->decimal('total_liters', 12, 2);
            $table->decimal('total_sales', 14, 2);
            $table->decimal('card_sales', 14, 2)->default(0);
            $table->decimal('credit_sales', 14, 2)->default(0);
            $table->decimal('cash_sales', 14, 2);
            $table->decimal('total_expenses', 14, 2);
            $table->decimal('cash_expenses', 14, 2);
            $table->decimal('opening_cash', 14, 2);
            $table->decimal('expected_cash', 14, 2);
            $table->decimal('actual_cash', 14, 2);
            $table->decimal('difference', 14, 2);
            $table->string('notes', 500)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('daily_closings');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('fuel_purchases');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('meter_readings');
    }
};
