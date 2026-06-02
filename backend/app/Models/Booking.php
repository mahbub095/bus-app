<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'schedule_id',
        'passenger_name',
        'passenger_phone',
        'passenger_email',
        'seat_numbers',
        'total_fare',
        'payment_method',
        'status'
    ];

    protected $casts = [
        'total_fare' => 'decimal:2'
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
