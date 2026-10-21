<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PM Fuel Relief Scheme: the customer pays the pump price less the relief
     * per liter, and the government (State Bank) pays the relief to the
     * dealer later. The meters count the full price, so the daily closing
     * takes the relief off the cash expected in the drawer.
     */
    public function up(): void
    {
        Schema::create('relief_sales', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->index();
            $table->foreignId('fuel_product_id')->constrained()->restrictOnDelete();
            $table->string('vehicle_type', 10);
            $table->unsignedSmallInteger('tokens')->default(1);
            $table->decimal('quantity', 12, 2);
            $table->decimal('price', 12, 2);
            $table->decimal('relief_rate', 12, 2);
            $table->decimal('relief_amount', 14, 2);
            $table->string('token_code', 40)->nullable()->unique();
            $table->string('vehicle_number', 30)->nullable();
            $table->date('received_on')->nullable()->index();
            $table->string('receipt_reference', 100)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('daily_closings', function (Blueprint $table) {
            $table->decimal('relief_liters', 12, 2)->default(0)->after('owed_fuel_given');
            $table->decimal('relief_amount', 14, 2)->default(0)->after('relief_liters');
        });
    }

    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            $table->dropColumn(['relief_liters', 'relief_amount']);
        });

        Schema::dropIfExists('relief_sales');
    }
};
