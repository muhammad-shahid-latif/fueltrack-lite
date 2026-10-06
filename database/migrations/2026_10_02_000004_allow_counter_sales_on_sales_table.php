<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales now cover counter items (engine oil etc.) as well as meter readings,
     * so the reading link becomes optional and "liters" becomes "quantity".
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->renameColumn('liters', 'quantity');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('meter_reading_id')->nullable()->change();
            $table->foreignId('recorded_by')->nullable()->after('amount')->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable()->after('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn('notes');
            $table->renameColumn('quantity', 'liters');
        });
    }
};
