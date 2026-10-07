<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photo and CNIC. Files live on the private disk and are only served to
     * the owner, never from the public folder.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('cnic_number', 15)->nullable()->unique()->after('phone');
            $table->string('photo_path')->nullable()->after('cnic_number');
            $table->string('cnic_front_path')->nullable()->after('photo_path');
            $table->string('cnic_back_path')->nullable()->after('cnic_front_path');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['cnic_number']);
            $table->dropColumn(['cnic_number', 'photo_path', 'cnic_front_path', 'cnic_back_path']);
        });
    }
};
