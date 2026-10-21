<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One SMS token is used in one transaction for one vehicle. The quota on
     * the SMS caps the relief; liters beyond it are sold at full price.
     */
    public function up(): void
    {
        Schema::table('relief_sales', function (Blueprint $table) {
            $table->decimal('quota_liters', 8, 2)->default(0)->after('quantity');
            $table->date('token_expires_on')->nullable()->after('token_code');
            $table->boolean('owner_verified')->default(false)->after('vehicle_number');
            $table->index('vehicle_number');
        });
    }

    public function down(): void
    {
        Schema::table('relief_sales', function (Blueprint $table) {
            $table->dropIndex(['vehicle_number']);
            $table->dropColumn(['quota_liters', 'token_expires_on', 'owner_verified']);
        });
    }
};
