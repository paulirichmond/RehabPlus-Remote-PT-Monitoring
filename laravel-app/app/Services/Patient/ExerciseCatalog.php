<?php

namespace App\Services\Patient;

/**
 * Exercise types the patient app can track with the camera. The codes must
 * match ExerciseType in the app (lib/models/exercise_model.dart); an unknown
 * code is skipped by the app, and adding one needs an app update.
 */
class ExerciseCatalog
{
    /** code => [name, kind ('reps'|'hold'), default target, default hold seconds] */
    public const TYPES = [
        'kneeExtension' => ['Knee Extension', 'reps', 10, null],
        'straightLegRaise' => ['Straight Leg Raise', 'reps', 10, null],
        'kneeFlexion' => ['Knee Flexion', 'reps', 10, null],
        'heelSlide' => ['Heel Slides', 'reps', 10, null],
        'sitToStand' => ['Sit-to-Stand', 'reps', 10, null],
        'gluteBridge' => ['Glute Bridge', 'reps', 10, null],
        'bicepCurl' => ['Bicep Curl', 'reps', 10, null],
        'forwardRaise' => ['Forward Raise', 'reps', 10, null],
        'sideRaise' => ['Side Raise', 'reps', 10, null],
        'forwardPush' => ['Forward Push', 'reps', 10, null],
        'singleLegBalance' => ['Single-Leg Balance', 'hold', 3, 10],
        'wallSit' => ['Wall Sit', 'hold', 3, 20],
        // Second batch (app: lib/services/exercise_geometry.dart).
        'miniSquat' => ['Mini Squat', 'reps', 10, null],
        'hipAbduction' => ['Standing Hip Abduction', 'reps', 10, null],
        'hamstringCurl' => ['Standing Hamstring Curl', 'reps', 10, null],
        'marching' => ['Marching in Place', 'reps', 10, null],
        'overheadRaise' => ['Overhead Arm Raise', 'reps', 10, null],
    ];

    public const DAY_NAMES = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    public static function codes(): array
    {
        return array_keys(self::TYPES);
    }

    public static function name(?string $code): string
    {
        return self::TYPES[$code][0] ?? ($code ?: 'Unknown exercise');
    }

    public static function isHold(?string $code): bool
    {
        return (self::TYPES[$code][1] ?? null) === 'hold';
    }
}
