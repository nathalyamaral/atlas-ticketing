<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('seat_id')
                ->constrained('seats')
                ->restrictOnDelete();

            $table->uuid('code')
                ->unique();

            $table->text('buyer_cpf');

            $table->string('status', 20)
                ->default('active');

            $table->unsignedInteger('version')
                ->default(1);

            $table->timestamp('issued_at');

            $table->timestamp('cancelled_at')
                ->nullable();

            $table->timestamps();

            $table->unique(
                ['order_id', 'seat_id'],
                'tickets_order_seat_unique'
            );

            $table->index(
                ['seat_id', 'status'],
                'tickets_seat_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
