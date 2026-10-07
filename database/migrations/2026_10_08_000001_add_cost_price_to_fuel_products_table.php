<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cost per unit of the opening stock. Together with purchase rates it
     * gives the average cost used for profit and loss.
     */
    public function up(): void
    {
        Schema::table('fuel_products', function (Blueprint $table) {
            $table->decimal('cost_price', 10, 2)->nullable()->after('selling_price');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_products', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
