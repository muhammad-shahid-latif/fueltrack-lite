<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 30)->unique();
            $table->decimal('selling_price', 10, 2);
            $table->decimal('opening_stock', 12, 2)->default(0);
            $table->decimal('low_stock_threshold', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pumps', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('nozzles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pump_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fuel_product_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('number');
            $table->decimal('current_meter', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pump_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nozzles');
        Schema::dropIfExists('pumps');
        Schema::dropIfExists('fuel_products');
    }
};
