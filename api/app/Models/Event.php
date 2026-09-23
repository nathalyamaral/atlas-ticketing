<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'organizer_id', 'name', 'location', 'starts_at',
        'sales_start_at', 'sales_end_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'status' => EventStatus::class,
        ];
    }

    public function organizer() { return $this->belongsTo(User::class, 'organizer_id'); }
    public function seats() { return $this->hasMany(Seat::class); }
    public function reservations() { return $this->hasMany(Reservation::class); }
    public function orders() { return $this->hasMany(Order::class); }
}
