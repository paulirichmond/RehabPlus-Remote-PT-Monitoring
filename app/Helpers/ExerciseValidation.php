<?php

namespace App\Helpers;

class ExerciseValidation
{
    public const RULES = [
        'exercise_name'   => 'required|max_length[150]',
        'sets_prescribed' => 'required|integer|greater_than[0]',
        'sets_completed'  => 'required|integer|greater_than_equal_to[0]',
        'pain_level'      => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[10]',
        'recorded_at'     => 'required|valid_date',
    ];

    public const FIELDS = ['exercise_name', 'sets_prescribed', 'sets_completed', 'pain_level', 'notes', 'recorded_at'];
}
