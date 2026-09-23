<?php

namespace Tests\Feature\Phase5;

use App\Enums\EventStatus;
use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketReissueAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_reissue_own_ticket_and_audit_records_the_change(): void
    {
        $organizer = $this->user(UserRole::ORGANIZER, 'reissue-org@atlas.test');
        $buyer = $this->user(UserRole::BUYER, 'reissue-buyer@atlas.test');
        $event = $this->event($organizer);
        $seat = Seat::query()->create([
            'event_id' => $event->id,
            'sector' => 'A',
            'row_label' => 'A',
            'number' => '1',
            'status' => SeatStatus::AVAILABLE,
        ]);

        Sanctum::actingAs($buyer);
        $reservationId = $this->postJson("/api/events/{$event->id}/reservations", [
            'seat_ids' => [$seat->id],
        ])->assertCreated()->json('data.id');

        $orderId = $this->withHeader('Idempotency-Key', 'reissue-confirm-1')
            ->postJson("/api/reservations/{$reservationId}/confirm", ['cpf' => '12345678901'])
            ->assertCreated()
            ->json('data.id');

        $ticket = Ticket::query()->where('order_id', $orderId)->firstOrFail();
        $oldCode = $ticket->code;

        $response = $this->postJson("/api/tickets/{$ticket->id}/reissue")
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->assertNotSame($oldCode, $response->json('data.code'));
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseHas('audit_logs', [
            'event_id' => $event->id,
            'actor_user_id' => $buyer->id,
            'action' => 'ticket.reissued',
            'entity_type' => 'ticket',
            'entity_id' => $ticket->id,
        ]);
    }

    public function test_other_buyer_cannot_reissue_ticket(): void
    {
        $organizer = $this->user(UserRole::ORGANIZER, 'reissue-org2@atlas.test');
        $owner = $this->user(UserRole::BUYER, 'owner@atlas.test');
        $other = $this->user(UserRole::BUYER, 'other@atlas.test');
        $event = $this->event($organizer);
        $seat = Seat::query()->create([
            'event_id' => $event->id,
            'sector' => 'A',
            'row_label' => 'A',
            'number' => '1',
            'status' => SeatStatus::AVAILABLE,
        ]);

        Sanctum::actingAs($owner);
        $reservationId = $this->postJson("/api/events/{$event->id}/reservations", [
            'seat_ids' => [$seat->id],
        ])->assertCreated()->json('data.id');
        $orderId = $this->withHeader('Idempotency-Key', 'reissue-confirm-2')
            ->postJson("/api/reservations/{$reservationId}/confirm", ['cpf' => '12345678901'])
            ->assertCreated()
            ->json('data.id');
        $ticket = Ticket::query()->where('order_id', $orderId)->firstOrFail();

        Sanctum::actingAs($other);
        $this->postJson("/api/tickets/{$ticket->id}/reissue")->assertForbidden();
    }

    private function event(User $organizer): Event
    {
        return Event::query()->create([
            'organizer_id' => $organizer->id,
            'name' => 'Reissue Event',
            'location' => 'Campo Grande - MS',
            'starts_at' => now()->addDay(),
            'sales_start_at' => now()->subMinute(),
            'sales_end_at' => now()->addHour(),
            'status' => EventStatus::PUBLISHED,
        ]);
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
