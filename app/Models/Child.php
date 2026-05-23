<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Child extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'birth_date',
        'age',
        'gender',
        'country_of_birth',
        'country_of_residence',
        'diagnosis',
        'condition_notes',
        'school_notes',
        'therapy_notes',
        'medical_notes',
        'communication_notes',
        'behavior_notes',
        'general_notes',
        'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function memories()
    {
        return $this->hasMany(ChildMemory::class);
    }

    public function documents()
    {
        return $this->hasMany(ChildDocument::class);
    }

    public function chatAttachments()
    {
        return $this->hasMany(ChatAttachment::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
