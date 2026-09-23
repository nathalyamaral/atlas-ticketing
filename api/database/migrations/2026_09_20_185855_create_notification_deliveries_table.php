<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('outbox_event_id')
                ->unique()
                ->constrained('outbox_events')
                ->cascadeOnDelete();

            $table->uuid('notification_id')
                ->unique();

            $table->string('status', 20)
                ->default('pending');

            $table->unsignedInteger('attempts')
                ->default(0);

            $table->unsignedSmallInteger('provider_status_code')
                ->nullable();

            $table->timestamp('last_attempt_at')
                ->nullable();

            $table->timestamp('sent_at')
                ->nullable();

            $table->text('last_error')
                ->nullable();

            $table->timestamps();

            $table->index(
                ['status', 'created_at'],
                'notification_status_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
