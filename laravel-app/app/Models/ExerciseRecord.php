<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExerciseRecord extends Model
{
    protected $table = 'exercise_records';

    protected $fillable = [
        'patient_id',
        'exercise_name',
        'sets_prescribed',
        'sets_completed',
        'pain_level',
        'notes',
        'recorded_at',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public static function patientStats(): array
    {
        return self::selectRaw('patients.id, patients.name, patients.condition, COUNT(exercise_records.id) AS total_sessions, COALESCE(ROUND(SUM(exercise_records.sets_completed) / NULLIF(SUM(exercise_records.sets_prescribed), 0) * 100, 1), 0) AS compliance_rate, COALESCE(ROUND(AVG(exercise_records.pain_level), 1), 0) AS avg_pain, COALESCE(ROUND((SUM(exercise_records.sets_completed) / NULLIF(SUM(exercise_records.sets_prescribed), 0) * 100) - (AVG(exercise_records.pain_level) * 5), 1), 0) AS recovery_score')
            ->join('patients', 'patients.id', '=', 'exercise_records.patient_id')
            ->groupBy('patients.id', 'patients.name', 'patients.condition')
            ->orderBy('patients.name')
            ->get()
            ->toArray();
    }

    public static function recentRecords(int $limit = 10): array
    {
        return self::select('exercise_records.*', 'patients.name as patient_name')
            ->join('patients', 'patients.id', '=', 'exercise_records.patient_id')
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
