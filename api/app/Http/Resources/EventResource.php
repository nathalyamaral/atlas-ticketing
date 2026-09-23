<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'starts_at' => $this->starts_at?->toISOString(),
            'sales_start_at' => $this->sales_start_at?->toISOString(),
            'sales_end_at' => $this->sales_end_at?->toISOString(),
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
