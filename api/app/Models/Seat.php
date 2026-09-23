<?php

namespace App\Models;

use App\Enums\SeatStatus;
use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    protected $fillable = [
        'event_id',
        'reservation_id',
        'order_id',
        'sector',
        'row_label',
        'number',
        'status',
        'reserved_until',
    ];

    protected function casts(): array
    {
        return [
            'status' => SeatStatus::class,
            'reserved_until' => 'datetime',
        ];
    }

    public function event() { return $this->belongsTo(Event::class); }
    public function reservation() { return $this->belongsTo(Reservation::class); }
    public function order() { return $this->belongsTo(Order::class); }
}
