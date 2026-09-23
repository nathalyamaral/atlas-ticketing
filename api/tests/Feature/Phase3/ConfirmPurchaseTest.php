<?php

namespace Tests\Feature\Phase3;

use App\Enums\EventStatus;
use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfirmPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_is_idempotent_and_creates_ticket_outbox_and_delivery_atomically(): void
    {
        [$buyer, $event, $seat] = $this->fixture();
        Sanctum::actingAs($buyer);

        $reservation = $this->postJson("/api/events/{$event->id}/reservations", [
            'seat_ids' => [$seat->id],
        ])->assertCreated()->json('data');

        $first = $this->withHeader('Idempotency-Key', 'confirm-test-001')
            ->postJson("/api/reservations/{$reservation['id']}/confirm", [
                'cpf' => '123.456.789-01',
            ])
            ->assertCreated();

        $orderId = $first->json('data.id');
        $ticketCode = $first->json('data.tickets.0.code');

        $second = $this->withHeader('Idempotency-Key', 'confirm-test-001')
            ->postJson("/api/reservations/{$reservation['id']}/confirm", [
                'cpf' => '123.456.789-01',
            ])
            ->assertOk();

        $this->assertSame($orderId, $second->json('data.id'));
        $this->assertSame($ticketCode, $second->json('data.tickets.0.code'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('outbox_events', 1);
        $this->assertDatabaseCount('notification_deliveries', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.confirmed',
            'entity_id' => $orderId,
        ]);

        $rawCpf = DB::table('tickets')->where('order_id', $orderId)->value('buyer_cpf');
        $this->assertNotSame('12345678901', $rawCpf);
        $this->assertStringNotContainsString('12345678901', (string) $rawCpf);
    }

    public function test_expired_reservation_cannot_be_confirmed(): void
    {
        [$buyer, $event, $seat] = $this->fixture();
        Sanctum::actingAs($buyer);

        $reservationId = $this->postJson("/api/events/{$event->id}/reservations", [
            'seat_ids' => [$seat->id],
        ])->assertCreated()->json('data.id');

        DB::table('reservations')->where('id', $reservationId)->update(['expires_at' => now()->subMinute()]);
        DB::table('seats')->where('id', $seat->id)->update(['reserved_until' => now()->subMinute()]);

        $this->withHeader('Idempotency-Key', 'expired-001')
            ->postJson("/api/reservations/{$reservationId}/confirm", ['cpf' => '12345678901'])
            ->assertConflict();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('confirmation_attempts', [
            'reservation_id' => $reservationId,
            'succeeded' => false,
            'failure_reason' => 'conflict',
        ]);
    }

    public function test_reclaimed_expired_seat_prevents_old_reservation_confirmation(): void
    {
        [$buyerA, $event, $seat] = $this->fixture();
        $buyerB = $this->buyer('buyer-b@atlas.test');
        Sanctum::actingAs($buyerA);

        $oldReservationId = $this->postJson("/api/events/{$event->id}/reservations", [
            'seat_ids' => [$seat->id],
        ])->assertCreated()->json('data.id');

        DB::table('reservations')->where('id', $oldReservationId)->update(['expires_at' => now()->subMinute()]);
        DB::table('seats')->where('id', $seat->id)->update(['reserved_until' => now()->subMinute()]);

        Sanctum::actingAs($buyerB);
        $this->postJson("/api/events/{$event->id}/reservations", [
            'seat_ids' => [$seat->id],
        ])->assertCreated();

        Sanctum::actingAs($buyerA);
        $this->withHeader('Idempotency-Key', 'old-reservation')
            ->postJson("/api/reservations/{$oldReservationId}/confirm", ['cpf' => '12345678901'])
            ->assertConflict();
    }

    private function fixture(): array
    {
        $organizer = User::query()->create([
            'name' => 'Organizer', 'email' => 'organizer@atlas.test',
            'password' => 'Password123!', 'role' => UserRole::ORGANIZER,
        ]);
        $buyer = $this->buyer('buyer@atlas.test');
        $event = Event::query()->create([
            'organizer_id' => $organizer->id,
            'name' => 'Flash Sale',
            'location' => 'Campo Grande - MS',
            'starts_at' => now()->addDay(),
            'sales_start_at' => now()->subMinute(),
            'sales_end_at' => now()->addHour(),
            'status' => EventStatus::PUBLISHED,
        ]);
        $seat = Seat::query()->create([
            'event_id' => $event->id,
            'sector' => 'A', 'row_label' => 'A', 'number' => '1',
            'status' => SeatStatus::AVAILABLE,
        ]);
        return [$buyer, $event, $seat];
    }

    private function buyer(string $email): User
    {
        return User::query()->create([
            'name' => 'Buyer', 'email' => $email,
            'password' => 'Password123!', 'role' => UserRole::BUYER,
        ]);
    }
}
