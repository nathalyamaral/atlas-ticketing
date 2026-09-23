<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100);
            $table->string('status', 20)->default('open');
            $table->string('message', 255);
            $table->json('context')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status'], 'system_alert_type_status_index');
            $table->index(['status', 'triggered_at'], 'system_alert_status_triggered_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};
