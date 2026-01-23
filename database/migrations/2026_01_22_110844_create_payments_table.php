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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->onDelete('cascade');
            $table->string('transaction_id')->nullable()->unique();
            $table->string('payment_type')->nullable();
            $table->decimal('gross_amount', 10, 2);
            $table->enum('payment_status', [
                'pending',
                'settlement',
                'cancel',
                'deny',
                'expire',
                'failure'
            ])->default('pending');
            $table->text('midtrans_response')->nullable(); // JSON response dari Midtrans
            $table->string('snap_token')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('order_id');
            $table->index('transaction_id');
            $table->index('payment_status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
