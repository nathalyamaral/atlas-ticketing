<?php

namespace App\Http\Resources;

use App\Enums\SeatStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAvailable =
            $this->status === SeatStatus::AVAILABLE
            || (
                $this->status === SeatStatus::RESERVED
                && $this->reserved_until?->isPast()
            );

        return [
            'id' => $this->id,
            'sector' => $this->sector,
            'row' => $this->row_label,
            'number' => $this->number,

            'status' => $isAvailable
                ? SeatStatus::AVAILABLE->value
                : $this->status->value,
        ];
    }
}
