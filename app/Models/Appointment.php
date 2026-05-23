<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'child_id',
        'specialist_id',
        'payment_id',
        'appointment_type',
        'booking_reference',
        'scheduled_date',
        'start_time',
        'end_time',
        'timezone',
        'status',
        'join_url',
        'join_available_at',
        'notes',
        'canceled_reason',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_date'   => 'date',
        'join_available_at' => 'datetime',
        'completed_at'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function specialist()
    {
        return $this->belongsTo(Specialist::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function review()
    {
        return $this->hasOne(SpecialistReview::class);
    }
}
