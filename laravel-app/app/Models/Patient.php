<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $table = 'patients';

    protected $fillable = [
        'user_id',
        'name',
        'condition',
        'medical_summary',
        'therapy_plan',
        'avatar',
        'assigned_to',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exerciseRecords()
    {
        return $this->hasMany(ExerciseRecord::class);
    }
}
