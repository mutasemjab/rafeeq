<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpecialistAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'specialist_id',
        'available_date',
        'start_time',
        'end_time',
        'slot_duration_minutes',
        'is_available',
        'capacity',
    ];

    protected $casts = [
        'available_date' => 'date',
        'is_available'   => 'bool',
    ];

    public function specialist()
    {
        return $this->belongsTo(Specialist::class);
    }
}
