<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')
                ->unique()
                ->constrained('reservations')
                ->restrictOnDelete();

            $table->foreignId('buyer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();

            $table->string('idempotency_key', 100)
                ->nullable()
                ->unique();

            $table->string('status', 20)
                ->default('confirmed');

            $table->timestamp('confirmed_at');

            $table->timestamp('cancelled_at')
                ->nullable();

            $table->timestamps();

            $table->index(
                ['event_id', 'status', 'confirmed_at'],
                'orders_event_status_confirmed_index'
            );

            $table->index(
                ['buyer_id', 'created_at'],
                'orders_buyer_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
