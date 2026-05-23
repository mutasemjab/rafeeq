<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialist extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'display_name',
        'slug',
        'title',
        'specialty',
        'bio',
        'years_of_experience',
        'city',
        'country',
        'consultation_fee',
        'currency',
        'rating_avg',
        'reviews_count',
        'avatar',
        'whatsapp_join_mode',
        'whatsapp_number',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active'        => 'bool',
        'metadata'         => 'array',
        'consultation_fee' => 'float',
        'rating_avg'       => 'float',
    ];

    public function availabilities()
    {
        return $this->hasMany(SpecialistAvailability::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function reviews()
    {
        return $this->hasMany(SpecialistReview::class);
    }
}
