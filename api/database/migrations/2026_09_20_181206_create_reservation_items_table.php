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
        Schema::create('reservation_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')
                ->constrained('reservations')
                ->cascadeOnDelete();

            $table->foreignId('seat_id')
                ->constrained('seats')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                ['reservation_id', 'seat_id'],
                'reservation_items_reservation_seat_unique'
            );

            $table->index(
                ['seat_id', 'reservation_id'],
                'reservation_items_seat_reservation_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_items');
    }
};
