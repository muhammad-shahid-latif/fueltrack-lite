<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('designation', 60);
            $table->string('phone', 20)->nullable();
            $table->date('joined_on');
            $table->decimal('monthly_salary', 12, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        // Money paid to staff during the month, recovered from their next salary.
        // Each advance is also an expense, so it reaches the cash count and P&L.
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('business_date')->index();
            $table->decimal('amount', 12, 2);
            $table->foreignId('expense_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        // One slip per employee per month. Figures are a snapshot, so a later
        // salary change never rewrites a past month.
        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('month');
            $table->decimal('basic_salary', 12, 2);
            $table->unsignedTinyInteger('days_in_month');
            $table->decimal('absent_days', 4, 1)->default(0);
            $table->decimal('absence_deduction', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('other_deduction', 12, 2)->default(0);
            $table->decimal('advance_deduction', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2);
            $table->string('status', 10)->default('pending')->index();
            $table->date('paid_on')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->foreignId('expense_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'month']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_slips');
        Schema::dropIfExists('salary_advances');
        Schema::dropIfExists('employees');
    }
};
