<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tokens are handled in the government system; FuelTrack keeps one relief
     * total per day and fuel, so the per-token vehicle type becomes optional.
     */
    public function up(): void
    {
        Schema::table('relief_sales', function (Blueprint $table) {
            $table->string('vehicle_type', 10)->nullable()->change();
            $table->unsignedSmallInteger('tokens')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('relief_sales', function (Blueprint $table) {
            $table->string('vehicle_type', 10)->nullable(false)->default('bike')->change();
            $table->unsignedSmallInteger('tokens')->default(1)->change();
        });
    }
};
