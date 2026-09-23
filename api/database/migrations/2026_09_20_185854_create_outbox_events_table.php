<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();

            $table->uuid('event_id')
                ->unique();

            $table->string('type', 100);

            $table->string('aggregate_type', 50);

            $table->unsignedBigInteger('aggregate_id');

            $table->json('payload');

            $table->string('status', 20)
                ->default('pending');

            $table->unsignedInteger('attempts')
                ->default(0);

            $table->timestamp('available_at')
                ->useCurrent();

            $table->timestamp('published_at')
                ->nullable();

            $table->text('last_error')
                ->nullable();

            $table->timestamps();

            $table->index(
                ['status', 'available_at'],
                'outbox_status_available_index'
            );

            $table->index(
                ['aggregate_type', 'aggregate_id'],
                'outbox_aggregate_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
