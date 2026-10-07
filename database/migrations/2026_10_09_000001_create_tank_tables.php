<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Underground tanks. A tank's level is derived from its own ledger:
     * opening stock + deliveries − nozzle sales ± adjustments (dips, transfers).
     */
    public function up(): void
    {
        Schema::create('tanks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->foreignId('fuel_product_id')->constrained()->restrictOnDelete();
            $table->decimal('capacity', 12, 2);
            $table->decimal('opening_stock', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('nozzles', function (Blueprint $table) {
            $table->foreignId('tank_id')->nullable()->after('fuel_product_id')->constrained()->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('tank_id')->nullable()->after('fuel_product_id')->constrained()->restrictOnDelete();
        });

        Schema::table('fuel_purchases', function (Blueprint $table) {
            $table->foreignId('tank_id')->nullable()->after('fuel_product_id')->constrained()->restrictOnDelete();
            // Moving weighted average cost per unit right after this delivery.
            $table->decimal('average_cost', 12, 4)->nullable()->after('amount');
        });

        // Stock corrections: dip-reading variances and tank-to-tank transfers.
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuel_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('tank_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->string('type', 20);
            $table->decimal('quantity', 12, 2);
            $table->decimal('dip_reading', 12, 2)->nullable();
            $table->decimal('book_stock', 12, 2)->nullable();
            $table->string('reference', 40)->nullable()->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['business_date', 'fuel_product_id']);
            $table->index(['tank_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');

        Schema::table('fuel_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tank_id');
            $table->dropColumn('average_cost');
        });

        Schema::table('sales', fn (Blueprint $table) => $table->dropConstrainedForeignId('tank_id'));
        Schema::table('nozzles', fn (Blueprint $table) => $table->dropConstrainedForeignId('tank_id'));

        Schema::dropIfExists('tanks');
    }
};
