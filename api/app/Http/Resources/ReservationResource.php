<?php

namespace App\Http\Resources;

use App\Enums\ReservationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;

        if (
            $status === ReservationStatus::ACTIVE
            && $this->expires_at->isPast()
        ) {
            $status = ReservationStatus::EXPIRED;
        }

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'status' => $status->value,

            'expires_at' =>
                $this->expires_at->toISOString(),

            'seats' => $this->whenLoaded(
                'items',
                fn () => $this->items
                    ->map(fn ($item) => [
                        'id' => $item->seat->id,
                        'sector' => $item->seat->sector,
                        'row' => $item->seat->row_label,
                        'number' => $item->seat->number,
                    ])
                    ->values()
            ),

            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }
}
