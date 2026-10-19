<?php

namespace App\Helpers;

class PatientValidation
{
    public const RULES = [
        'name'            => 'required|min_length[2]|max_length[100]',
        'condition'       => 'required|min_length[2]|max_length[150]',
        'medical_summary' => 'permit_empty|max_length[1000]',
        'therapy_plan'    => 'permit_empty|max_length[1000]',
        'avatar'          => 'if_exist|is_image[avatar]|max_size[avatar,2048]',
    ];

    public const FIELDS = ['name', 'condition', 'medical_summary', 'therapy_plan'];
}
