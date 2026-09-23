<?php

namespace Tests\Feature\Reservations;

use App\Enums\EventStatus;
use App\Enums\ReservationStatus;
use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReserveSeatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_reserve_available_seats(): void
    {
        $organizer = $this->organizer();
        $buyer = $this->buyer();

        $event = $this->openEvent($organizer);

        $seat1 = $this->seat($event, '1');
        $seat2 = $this->seat($event, '2');

        Sanctum::actingAs($buyer);

        $response = $this->postJson(
            "/api/events/{$event->id}/reservations",
            [
                'seat_ids' => [
                    $seat1->id,
                    $seat2->id,
                ],
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');

        $reservationId = $response->json('data.id');

        $this->assertDatabaseHas('seats', [
            'id' => $seat1->id,
            'status' => SeatStatus::RESERVED->value,
            'reservation_id' => $reservationId,
        ]);

        $this->assertDatabaseHas('seats', [
            'id' => $seat2->id,
            'status' => SeatStatus::RESERVED->value,
            'reservation_id' => $reservationId,
        ]);
    }

    public function test_same_seat_cannot_be_reserved_twice(): void
    {
        $organizer = $this->organizer();

        $buyerA = $this->buyer(
            'buyer-a@example.test'
        );

        $buyerB = $this->buyer(
            'buyer-b@example.test'
        );

        $event = $this->openEvent($organizer);
        $seat = $this->seat($event, '1');

        Sanctum::actingAs($buyerA);

        $this->postJson(
            "/api/events/{$event->id}/reservations",
            [
                'seat_ids' => [$seat->id],
            ]
        )->assertCreated();

        Sanctum::actingAs($buyerB);

        $this->postJson(
            "/api/events/{$event->id}/reservations",
            [
                'seat_ids' => [$seat->id],
            ]
        )
            ->assertConflict()
            ->assertJson([
                'message' =>
                    'One or more seats are unavailable.',
            ]);

        $this->assertDatabaseCount(
            'reservations',
            1
        );
    }

    public function test_multi_seat_reservation_is_all_or_nothing(): void
    {
        $organizer = $this->organizer();

        $buyerA = $this->buyer(
            'buyer-a@example.test'
        );

        $buyerB = $this->buyer(
            'buyer-b@example.test'
        );

        $event = $this->openEvent($organizer);

        $seat1 = $this->seat($event, '1');
        $seat2 = $this->seat($event, '2');

        Sanctum::actingAs($buyerA);

        $this->postJson(
            "/api/events/{$event->id}/reservations",
            [
                'seat_ids' => [$seat1->id],
            ]
        )->assertCreated();

        Sanctum::actingAs($buyerB);

        $this->postJson(
            "/api/events/{$event->id}/reservations",
            [
                'seat_ids' => [
                    $seat1->id,
                    $seat2->id,
                ],
            ]
        )->assertConflict();

        $this->assertDatabaseHas('seats', [
            'id' => $seat2->id,
            'status' => SeatStatus::AVAILABLE->value,
            'reservation_id' => null,
        ]);

        $this->assertDatabaseCount(
            'reservations',
            1
        );
    }

    public function test_expired_seat_can_be_reserved_again(): void
    {
        $organizer = $this->organizer();

        $oldBuyer = $this->buyer(
            'old@example.test'
        );

        $newBuyer = $this->buyer(
            'new@example.test'
        );

        $event = $this->openEvent($organizer);
        $seat = $this->seat($event, '1');

        $oldReservation =
            Reservation::query()->create([
                'buyer_id' => $oldBuyer->id,
                'event_id' => $event->id,
                'status' =>
                    ReservationStatus::ACTIVE->value,
                'expires_at' => now()->subMinute(),
            ]);

        $seat->update([
            'status' => SeatStatus::RESERVED->value,
            'reservation_id' =>
                $oldReservation->id,
            'reserved_until' => now()->subMinute(),
        ]);

        ReservationItem::query()->create([
            'reservation_id' =>
                $oldReservation->id,
            'seat_id' => $seat->id,
        ]);

        Sanctum::actingAs($newBuyer);

        $response = $this->postJson(
            "/api/events/{$event->id}/reservations",
            [
                'seat_ids' => [$seat->id],
            ]
        );

        $response->assertCreated();

        $newReservationId =
            $response->json('data.id');

        $seat->refresh();

        $this->assertSame(
            $newReservationId,
            $seat->reservation_id
        );

        $this->assertSame(
            SeatStatus::RESERVED,
            $seat->status
        );
    }

    public function test_seat_from_another_event_is_rejected(): void
    {
        $organizer = $this->organizer();
        $buyer = $this->buyer();

        $eventA = $this->openEvent(
            $organizer,
            'Event A'
        );

        $eventB = $this->openEvent(
            $organizer,
            'Event B'
        );

        $seatFromB = $this->seat(
            $eventB,
            '1'
        );

        Sanctum::actingAs($buyer);

        $this->postJson(
            "/api/events/{$eventA->id}/reservations",
            [
                'seat_ids' => [$seatFromB->id],
            ]
        )->assertConflict();

        $this->assertDatabaseHas('seats', [
            'id' => $seatFromB->id,
            'status' => SeatStatus::AVAILABLE->value,
        ]);
    }

    public function test_organizer_cannot_make_reservation(): void
    {
        $organizer = $this->organizer();

        $event = $this->openEvent($organizer);
        $seat = $this->seat($event, '1');

        Sanctum::actingAs($organizer);

        $this->postJson(
            "/api/events/{$event->id}/reservations",
            [
                'seat_ids' => [$seat->id],
            ]
        )->assertForbidden();
    }

    public function test_expiration_command_releases_seat(): void
    {
        $organizer = $this->organizer();
        $buyer = $this->buyer();

        $event = $this->openEvent($organizer);
        $seat = $this->seat($event, '1');

        $reservation =
            Reservation::query()->create([
                'buyer_id' => $buyer->id,
                'event_id' => $event->id,
                'status' =>
                    ReservationStatus::ACTIVE->value,
                'expires_at' => now()->subMinute(),
            ]);

        $seat->update([
            'status' => SeatStatus::RESERVED->value,
            'reservation_id' => $reservation->id,
            'reserved_until' => now()->subMinute(),
        ]);

        ReservationItem::query()->create([
            'reservation_id' => $reservation->id,
            'seat_id' => $seat->id,
        ]);

        Artisan::call('reservations:expire');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' =>
                ReservationStatus::EXPIRED->value,
        ]);

        $this->assertDatabaseHas('seats', [
            'id' => $seat->id,
            'status' => SeatStatus::AVAILABLE->value,
            'reservation_id' => null,
            'reserved_until' => null,
        ]);
    }

    private function organizer(): User
    {
        return User::factory()->create([
            'role' => UserRole::ORGANIZER->value,
        ]);
    }

    private function buyer(
        ?string $email = null
    ): User {
        return User::factory()->create([
            'email' => $email
                ?? fake()->unique()->safeEmail(),
            'role' => UserRole::BUYER->value,
        ]);
    }

    private function openEvent(
        User $organizer,
        string $name = 'Flash Sale'
    ): Event {
        return Event::query()->create([
            'organizer_id' => $organizer->id,
            'name' => $name,
            'location' => 'Campo Grande - MS',

            'sales_start_at' =>
                now()->subMinute(),

            'sales_end_at' =>
                now()->addDay(),

            'starts_at' =>
                now()->addDays(2),

            'status' =>
                EventStatus::PUBLISHED->value,
        ]);
    }

    private function seat(
        Event $event,
        string $number
    ): Seat {
        return Seat::query()->create([
            'event_id' => $event->id,
            'sector' => 'A',
            'row_label' => 'A',
            'number' => $number,
            'status' =>
                SeatStatus::AVAILABLE->value,
        ]);
    }
}
