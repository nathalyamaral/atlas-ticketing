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
        Schema::create('seats', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained('events')
                ->restrictOnDelete();

            $table->foreignId('reservation_id')
                ->nullable()
                ->constrained('reservations')
                ->nullOnDelete();

            $table->string('sector', 80)
                ->default('General');

            $table->string('row_label', 20);

            $table->string('number', 20);

            $table->string('status', 20)
                ->default('available');

            $table->timestamp('reserved_until')
                ->nullable();

            $table->timestamps();

            $table->unique(
                [
                    'event_id',
                    'sector',
                    'row_label',
                    'number',
                ],
                'seats_event_position_unique'
            );

            $table->index(
                ['event_id', 'status'],
                'seats_event_status_index'
            );

            $table->index(
                ['reservation_id', 'reserved_until'],
                'seats_reservation_expiration_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
