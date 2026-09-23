<?php

namespace Tests\Feature\Phase3;

use App\Enums\EventStatus;
use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_cancel_purchase_and_seat_becomes_available_again(): void
    {
        $organizer = $this->user(UserRole::ORGANIZER, 'org@atlas.test');
        $buyer = $this->user(UserRole::BUYER, 'buyer@atlas.test');
        $event = Event::query()->create([
            'organizer_id' => $organizer->id, 'name' => 'Cancel Event',
            'location' => 'Campo Grande - MS', 'starts_at' => now()->addDay(),
            'sales_start_at' => now()->subMinute(), 'sales_end_at' => now()->addHour(),
            'status' => EventStatus::PUBLISHED,
        ]);
        $seat = Seat::query()->create([
            'event_id' => $event->id, 'sector' => 'A', 'row_label' => 'A',
            'number' => '1', 'status' => SeatStatus::AVAILABLE,
        ]);

        Sanctum::actingAs($buyer);
        $reservationId = $this->postJson("/api/events/{$event->id}/reservations", ['seat_ids' => [$seat->id]])
            ->assertCreated()->json('data.id');
        $orderId = $this->withHeader('Idempotency-Key', 'cancel-test')
            ->postJson("/api/reservations/{$reservationId}/confirm", ['cpf' => '12345678901'])
            ->assertCreated()->json('data.id');

        $this->postJson("/api/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('seats', [
            'id' => $seat->id, 'status' => 'available', 'order_id' => null,
        ]);
        $this->assertDatabaseHas('tickets', [
            'order_id' => $orderId, 'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.cancelled', 'entity_id' => $orderId,
        ]);

        $this->postJson("/api/orders/{$orderId}/cancel")->assertOk();
    }


    public function test_other_buyer_cannot_view_or_cancel_purchase(): void
    {
        $organizer = $this->user(UserRole::ORGANIZER, 'org-owner-test@atlas.test');
        $owner = $this->user(UserRole::BUYER, 'owner-order@atlas.test');
        $other = $this->user(UserRole::BUYER, 'other-order@atlas.test');
        $event = Event::query()->create([
            'organizer_id' => $organizer->id, 'name' => 'Ownership Event',
            'location' => 'Campo Grande - MS', 'starts_at' => now()->addDay(),
            'sales_start_at' => now()->subMinute(), 'sales_end_at' => now()->addHour(),
            'status' => EventStatus::PUBLISHED,
        ]);
        $seat = Seat::query()->create([
            'event_id' => $event->id, 'sector' => 'A', 'row_label' => 'A',
            'number' => '1', 'status' => SeatStatus::AVAILABLE,
        ]);

        Sanctum::actingAs($owner);
        $reservationId = $this->postJson("/api/events/{$event->id}/reservations", ['seat_ids' => [$seat->id]])
            ->assertCreated()->json('data.id');
        $orderId = $this->withHeader('Idempotency-Key', 'ownership-test')
            ->postJson("/api/reservations/{$reservationId}/confirm", ['cpf' => '12345678901'])
            ->assertCreated()->json('data.id');

        Sanctum::actingAs($other);
        $this->getJson("/api/orders/{$orderId}")->assertForbidden();
        $this->postJson("/api/orders/{$orderId}/cancel")->assertForbidden();
    }

    private function user(UserRole $role, string $email): User
    {
        return User::query()->create([
            'name' => $role->value, 'email' => $email,
            'password' => 'Password123!', 'role' => $role,
        ]);
    }
}
