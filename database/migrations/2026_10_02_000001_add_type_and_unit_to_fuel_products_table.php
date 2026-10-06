<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_products', function (Blueprint $table) {
            $table->string('type', 20)->default('fuel')->after('code')->index();
            $table->string('unit', 10)->default('L')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_products', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'unit']);
        });
    }
};
