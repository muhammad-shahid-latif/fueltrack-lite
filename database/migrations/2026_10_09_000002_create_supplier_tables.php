<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supplier register. What the station owes a supplier is
     * opening balance + purchases − payments; payments settle the oldest
     * bills first, which gives every bill its paid / partial / overdue status.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('fuel_purchases', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('tank_id')->constrained()->restrictOnDelete();
            $table->date('due_date')->nullable()->after('business_date');
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->date('business_date')->index();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 20);
            $table->string('reference', 50)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');

        Schema::table('fuel_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn('due_date');
        });

        Schema::dropIfExists('suppliers');
    }
};
