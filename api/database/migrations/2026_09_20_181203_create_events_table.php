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
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organizer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('name', 150);
            $table->string('location', 180);

            $table->dateTime('starts_at');
            $table->dateTime('sales_start_at');
            $table->dateTime('sales_end_at')->nullable();

            $table->string('status', 20)
                ->default('draft');

            $table->timestamps();

            $table->index(
                ['organizer_id', 'created_at'],
                'events_organizer_created_index'
            );

            $table->index(
                ['status', 'starts_at'],
                'events_status_starts_index'
            );

            $table->index(
                ['location', 'starts_at'],
                'events_location_starts_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
