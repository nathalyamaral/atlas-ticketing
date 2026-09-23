<?php

namespace Tests\Feature\Phase5;

use App\Enums\EventStatus;
use App\Enums\SystemAlertStatus;
use App\Enums\UserRole;
use App\Models\ConfirmationAttempt;
use App\Models\Event;
use App\Models\SystemAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase5FeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_search_filters_and_returns_503_when_search_is_disabled(): void
    {
        $organizer = $this->user(UserRole::ORGANIZER, 'org@atlas.test');
        $buyer = $this->user(UserRole::BUYER, 'buyer@atlas.test');
        Event::query()->create([
            'organizer_id' => $organizer->id,
            'name' => 'Festival Atlas 2027', 'location' => 'Campo Grande - MS',
            'starts_at' => now()->addMonth(), 'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addWeek(), 'status' => EventStatus::PUBLISHED,
        ]);

        Sanctum::actingAs($buyer);
        $this->getJson('/api/events/search?name=Festival&location=Campo')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Festival Atlas 2027');

        config(['search.available' => false]);
        $this->getJson('/api/events/search?name=Festival')->assertStatus(503);
    }

    public function test_only_event_owner_can_view_report_and_audit(): void
    {
        $owner = $this->user(UserRole::ORGANIZER, 'owner@atlas.test');
        $other = $this->user(UserRole::ORGANIZER, 'other@atlas.test');
        $event = Event::query()->create([
            'organizer_id' => $owner->id, 'name' => 'Report Event',
            'location' => 'Campo Grande - MS', 'starts_at' => now()->addMonth(),
            'sales_start_at' => now()->subDay(), 'sales_end_at' => now()->addWeek(),
            'status' => EventStatus::PUBLISHED,
        ]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/events/{$event->id}/report")
            ->assertOk()
            ->assertJsonPath('data.tickets.total', 0);
        $this->getJson("/api/events/{$event->id}/audit")->assertOk();

        Sanctum::actingAs($other);
        $this->getJson("/api/events/{$event->id}/report")->assertForbidden();
        $this->getJson("/api/events/{$event->id}/audit")->assertForbidden();
    }

    public function test_failure_rate_monitor_opens_and_resolves_alert(): void
    {
        config([
            'monitoring.confirmation_failure.window_minutes' => 5,
            'monitoring.confirmation_failure.minimum_attempts' => 10,
            'monitoring.confirmation_failure.threshold_percent' => 20,
        ]);

        $now = Carbon::parse('2026-09-21 12:00:00');
        Carbon::setTestNow($now);

        for ($i = 0; $i < 10; $i++) {
            ConfirmationAttempt::query()->create([
                'succeeded' => $i >= 3,
                'failure_reason' => $i < 3 ? 'conflict' : null,
                'duration_ms' => 10,
                'created_at' => now(),
            ]);
        }

        $this->artisan('monitor:confirmation-failures')->assertSuccessful();
        $this->assertDatabaseHas('system_alerts', [
            'type' => 'confirmation_failure_rate',
            'status' => SystemAlertStatus::OPEN->value,
        ]);

        Carbon::setTestNow($now->copy()->addMinutes(6));
        for ($i = 0; $i < 10; $i++) {
            ConfirmationAttempt::query()->create([
                'succeeded' => true, 'duration_ms' => 8, 'created_at' => now(),
            ]);
        }

        $this->artisan('monitor:confirmation-failures')->assertSuccessful();
        $alert = SystemAlert::query()->firstOrFail();
        $this->assertSame(SystemAlertStatus::RESOLVED, $alert->status);

        Carbon::setTestNow();
    }

    public function test_ops_metrics_requires_organizer(): void
    {
        $organizer = $this->user(UserRole::ORGANIZER, 'ops@atlas.test');
        $buyer = $this->user(UserRole::BUYER, 'buyer@atlas.test');

        Sanctum::actingAs($buyer);
        $this->getJson('/api/ops/metrics')->assertForbidden();

        Sanctum::actingAs($organizer);
        $this->getJson('/api/ops/metrics')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'outbox_pending', 'notification_failed', 'open_alerts',
                'confirmation_failure_rate_percent', 'generated_at',
            ]]);
    }

    private function user(UserRole $role, string $email): User
    {
        return User::query()->create([
            'name' => $role->value, 'email' => $email,
            'password' => 'Password123!', 'role' => $role,
        ]);
    }
}
