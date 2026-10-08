<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'departure_station_id',
        'arrival_station_id',
        'distance',
        'duration',
        'boarding_points',
        'dropping_points',
    ];

    protected $casts = [
        'boarding_points' => 'array',
        'dropping_points' => 'array',
    ];

    public function departureStation()
    {
        return $this->belongsTo(Station::class, 'departure_station_id');
    }

    public function arrivalStation()
    {
        return $this->belongsTo(Station::class, 'arrival_station_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
