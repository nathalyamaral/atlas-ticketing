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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('buyer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();

            $table->string('status', 20)
                ->default('active');

            $table->timestamp('expires_at');

            $table->timestamp('confirmed_at')
                ->nullable();

            $table->timestamp('cancelled_at')
                ->nullable();

            $table->timestamps();

            $table->index(
                ['event_id', 'status', 'expires_at'],
                'reservations_event_status_expires_index'
            );

            $table->index(
                ['buyer_id', 'status', 'created_at'],
                'reservations_buyer_status_created_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
