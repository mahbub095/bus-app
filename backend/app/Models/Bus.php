<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    use HasFactory;

    protected $fillable = ['operator_name', 'coach_number', 'coach_type', 'total_seats'];

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
