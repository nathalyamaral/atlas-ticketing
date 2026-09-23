<?php

namespace Tests\Feature\Phase3;

use App\Contracts\NotificationProvider;
use App\Enums\EventStatus;
use App\Enums\NotificationStatus;
use App\Enums\SeatStatus;
use App\Enums\UserRole;
use App\Exceptions\NotificationProviderException;
use App\Jobs\SendTicketNotification;
use App\Models\Event;
use App\Models\NotificationDelivery;
use App\Models\OutboxEvent;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_dispatches_notification_job_and_marks_event_published(): void
    {
        $outbox = $this->confirmedPurchaseOutbox();
        Queue::fake();

        $this->artisan('outbox:dispatch')->assertSuccessful();

        Queue::assertPushedOn('notifications', SendTicketNotification::class);
        $outbox->refresh();
        $this->assertSame('published', $outbox->status->value);
        $this->assertNotNull($outbox->published_at);
    }

    public function test_notification_job_is_idempotent_and_never_sends_cpf(): void
    {
        $outbox = $this->confirmedPurchaseOutbox();
        $provider = new RecordingProvider();
        $job = new SendTicketNotification($outbox->id);

        $job->handle($provider);
        $job->handle($provider);

        $this->assertCount(1, $provider->calls);
        $this->assertStringNotContainsString('12345678901', json_encode($provider->calls));

        $delivery = NotificationDelivery::query()->where('outbox_event_id', $outbox->id)->firstOrFail();
        $this->assertSame(NotificationStatus::SENT, $delivery->status);
        $this->assertSame(202, $delivery->provider_status_code);
        $this->assertSame(1, $delivery->attempts);
    }

    public function test_provider_failure_is_observable_and_failed_callback_marks_delivery_failed(): void
    {
        $outbox = $this->confirmedPurchaseOutbox();
        $provider = new RecordingProvider(new NotificationProviderException('HTTP 500', 500));
        $job = new SendTicketNotification($outbox->id);

        try {
            $job->handle($provider);
            $this->fail('Expected provider exception.');
        } catch (NotificationProviderException $exception) {
            $this->assertSame(500, $exception->statusCode);
            $job->failed($exception);
        }

        $delivery = NotificationDelivery::query()->where('outbox_event_id', $outbox->id)->firstOrFail();
        $this->assertSame(NotificationStatus::FAILED, $delivery->status);
        $this->assertSame(500, $delivery->provider_status_code);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->last_error);
    }


    public function test_failed_notification_can_be_requeued_without_resetting_attempt_history(): void
    {
        $outbox = $this->confirmedPurchaseOutbox();
        $delivery = NotificationDelivery::query()
            ->where('outbox_event_id', $outbox->id)
            ->firstOrFail();

        $delivery->update([
            'status' => NotificationStatus::FAILED->value,
            'attempts' => 5,
            'last_error' => 'provider unavailable',
        ]);

        Queue::fake();

        $this->artisan('notifications:retry-failed', ['--id' => $delivery->id])
            ->assertSuccessful();

        Queue::assertPushedOn('notifications', SendTicketNotification::class);
        $delivery->refresh();
        $this->assertSame(NotificationStatus::PENDING, $delivery->status);
        $this->assertSame(5, $delivery->attempts);
        $this->assertNull($delivery->last_error);
    }

    public function test_notification_job_has_bounded_retries_and_backoff(): void
    {
        $job = new SendTicketNotification(123);

        $this->assertSame(5, $job->tries);
        $this->assertSame(10, $job->timeout);
        $this->assertSame([5, 15, 30, 60], $job->backoff());
    }

    private function confirmedPurchaseOutbox(): OutboxEvent
    {
        $organizer = User::query()->create([
            'name' => 'Organizer', 'email' => uniqid('org').'@atlas.test',
            'password' => 'Password123!', 'role' => UserRole::ORGANIZER,
        ]);
        $buyer = User::query()->create([
            'name' => 'Buyer', 'email' => uniqid('buyer').'@atlas.test',
            'password' => 'Password123!', 'role' => UserRole::BUYER,
        ]);
        $event = Event::query()->create([
            'organizer_id' => $organizer->id,
            'name' => 'Notification Event', 'location' => 'Campo Grande - MS',
            'starts_at' => now()->addDay(), 'sales_start_at' => now()->subMinute(),
            'sales_end_at' => now()->addHour(), 'status' => EventStatus::PUBLISHED,
        ]);
        $seat = Seat::query()->create([
            'event_id' => $event->id, 'sector' => 'A', 'row_label' => 'A',
            'number' => '1', 'status' => SeatStatus::AVAILABLE,
        ]);

        Sanctum::actingAs($buyer);
        $reservationId = $this->postJson("/api/events/{$event->id}/reservations", ['seat_ids' => [$seat->id]])
            ->assertCreated()->json('data.id');
        $this->withHeader('Idempotency-Key', uniqid('confirm-'))
            ->postJson("/api/reservations/{$reservationId}/confirm", ['cpf' => '12345678901'])
            ->assertCreated();

        return OutboxEvent::query()->latest('id')->firstOrFail();
    }
}

class RecordingProvider implements NotificationProvider
{
    public array $calls = [];

    public function __construct(private readonly ?\Throwable $exception = null)
    {
    }

    public function send(string $idempotencyKey, array $payload): int
    {
        $this->calls[] = ['idempotency_key' => $idempotencyKey, 'payload' => $payload];
        if ($this->exception) throw $this->exception;
        return 202;
    }
}
