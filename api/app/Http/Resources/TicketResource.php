<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'qr_payload' => 'atlas-ticket:' . $this->code,
            'status' => $this->status->value,
            'version' => $this->version,

            'buyer_cpf' =>
                $this->maskedCpf(),

            'seat' => [
                'id' => $this->seat->id,
                'sector' => $this->seat->sector,
                'row' => $this->seat->row_label,
                'number' => $this->seat->number,
            ],

            'issued_at' =>
                $this->issued_at->toISOString(),
        ];
    }

    private function maskedCpf(): string
    {
        return sprintf(
            '***.***.***-%s',
            substr($this->buyer_cpf, -2)
        );
    }
}
