<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->after('reservation_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->index(
                ['order_id', 'status'],
                'seats_order_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropIndex(
                'seats_order_status_index'
            );
            $table->dropColumn('order_id');
        });
    }
};
