<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every WhatsApp message the system tried to send, with Meta's answer.
     */
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 20); // closing, test
            $table->date('business_date')->nullable()->index();
            $table->string('recipient', 20);
            $table->string('status', 10); // sent, failed
            $table->string('message_id')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};
