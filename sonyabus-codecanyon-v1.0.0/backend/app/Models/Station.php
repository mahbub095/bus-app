<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function departureRoutes()
    {
        return $this->hasMany(Route::class, 'departure_station_id');
    }

    public function arrivalRoutes()
    {
        return $this->hasMany(Route::class, 'arrival_station_id');
    }
}
