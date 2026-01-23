<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('event_type', [
                'notification',
                'webhook',
                'manual_check',
                'error'
            ])->default('notification');
            $table->text('payload'); // JSON data
            $table->timestamps();

            // Indexes
            $table->index('order_id');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
