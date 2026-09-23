<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'order_id',
        'seat_id',
        'code',
        'buyer_cpf',
        'status',
        'version',
        'issued_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'buyer_cpf' => 'encrypted',
            'status' => TicketStatus::class,
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function seat()
    {
        return $this->belongsTo(Seat::class);
    }
}
