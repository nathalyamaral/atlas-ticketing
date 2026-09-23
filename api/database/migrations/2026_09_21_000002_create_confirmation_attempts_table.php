<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('confirmation_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('succeeded');
            $table->string('failure_reason', 120)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'succeeded'], 'confirmation_created_success_index');
            $table->index(['reservation_id', 'created_at'], 'confirmation_reservation_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('confirmation_attempts');
    }
};
