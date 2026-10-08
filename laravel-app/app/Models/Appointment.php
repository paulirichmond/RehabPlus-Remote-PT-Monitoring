<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $table = 'appointments';

    protected $fillable = [
        'patient',
        'therapist',
        'patient_condition',
        'contact',
        'date',
        'time',
        'session',
        'notes',
        'status',
        'completed_at',
    ];
}
