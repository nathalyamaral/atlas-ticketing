<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'buyer_id',
        'event_id',
        'status',
        'expires_at',
        'confirmed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function items()
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function order()
    {
        return $this->hasOne(Order::class);
    }
}
