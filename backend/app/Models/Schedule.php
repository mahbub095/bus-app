<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = ['bus_id', 'route_id', 'departure_time', 'arrival_time', 'fare'];

    protected $casts = [
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
        'fare' => 'decimal:2'
    ];

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
