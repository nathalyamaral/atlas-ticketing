<?php

namespace Tests\Feature\Phase1;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAuthorizationTest extends TestCase
{
    use RefreshDatabase;


    public function test_login_returns_token_and_invalid_credentials_are_rejected(): void
    {
        $this->user(UserRole::BUYER, 'login-buyer@atlas.test');

        $this->postJson('/api/auth/login', [
            'email' => 'login-buyer@atlas.test',
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'buyer')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);

        $this->postJson('/api/auth/login', [
            'email' => 'login-buyer@atlas.test',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_organizer_can_create_event_and_other_organizer_cannot_manage_it(): void
    {
        $owner = $this->user(UserRole::ORGANIZER, 'owner@atlas.test');
        $other = $this->user(UserRole::ORGANIZER, 'other@atlas.test');

        Sanctum::actingAs($owner);
        $eventId = $this->postJson('/api/events', [
            'name' => 'Owned Event',
            'location' => 'Campo Grande - MS',
            'starts_at' => now()->addDay()->toISOString(),
            'sales_start_at' => now()->subMinute()->toISOString(),
            'sales_end_at' => now()->addHour()->toISOString(),
            'status' => EventStatus::PUBLISHED->value,
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs($other);
        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/events/{$eventId}/seats", [
            'seats' => [['sector' => 'A', 'row_label' => 'A', 'number' => '1']],
        ])->assertForbidden();
    }

    public function test_buyer_cannot_create_event_and_organizer_cannot_list_buyer_orders(): void
    {
        $buyer = $this->user(UserRole::BUYER, 'buyer@atlas.test');
        $organizer = $this->user(UserRole::ORGANIZER, 'organizer@atlas.test');

        Sanctum::actingAs($buyer);
        $this->postJson('/api/events', [
            'name' => 'Nope',
            'location' => 'Campo Grande - MS',
            'starts_at' => now()->addDay()->toISOString(),
            'sales_start_at' => now()->subMinute()->toISOString(),
        ])->assertForbidden();

        Sanctum::actingAs($organizer);
        $this->getJson('/api/orders')->assertForbidden();
    }


    public function test_other_organizer_cannot_list_seats_and_search_is_buyer_only(): void
    {
        $owner = $this->user(UserRole::ORGANIZER, 'seat-owner@atlas.test');
        $other = $this->user(UserRole::ORGANIZER, 'seat-other@atlas.test');
        $buyer = $this->user(UserRole::BUYER, 'seat-buyer@atlas.test');

        $event = Event::query()->create([
            'organizer_id' => $owner->id,
            'name' => 'Owner Event',
            'location' => 'Campo Grande - MS',
            'starts_at' => now()->addDay(),
            'sales_start_at' => now()->subMinute(),
            'sales_end_at' => now()->addHour(),
            'status' => EventStatus::PUBLISHED,
        ]);

        Sanctum::actingAs($other);
        $this->getJson("/api/events/{$event->id}/seats")->assertForbidden();
        $this->getJson('/api/events/search?name=Owner')->assertForbidden();

        Sanctum::actingAs($buyer);
        $this->getJson("/api/events/{$event->id}/seats")->assertOk();
        $this->getJson('/api/events/search?name=Owner')->assertOk();
    }

    private function user(UserRole $role, string $email): User
    {
        return User::query()->create([
            'name' => $role->value,
            'email' => $email,
            'password' => 'Password123!',
            'role' => $role,
        ]);
    }
}
