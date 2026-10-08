<?php

namespace App\Models;

use CodeIgniter\Model;

class ExerciseRecordModel extends Model
{
    protected $table      = 'exercise_records';
    protected $primaryKey = 'id';
    protected $allowedFields = ['patient_id', 'exercise_name', 'sets_prescribed', 'sets_completed', 'pain_level', 'notes', 'recorded_at'];
    protected $useTimestamps = true;

    /** Returns per-patient stats: compliance_rate, avg_pain, total_sessions, recovery_score */
    public function getPatientStats(): array
    {
        return $this->db->query("
            SELECT
                p.id,
                p.name,
                p.condition,
                COUNT(er.id)                                                        AS total_sessions,
                    COALESCE(ROUND(SUM(er.sets_completed) / NULLIF(SUM(er.sets_prescribed),0) * 100, 1), 0) AS compliance_rate,
                    COALESCE(ROUND(AVG(er.pain_level), 1), 0) AS avg_pain,
                    COALESCE(ROUND((SUM(er.sets_completed) / NULLIF(SUM(er.sets_prescribed),0) * 100)
                        - (AVG(er.pain_level) * 5), 1), 0) AS recovery_score
            FROM patients p
            LEFT JOIN exercise_records er ON er.patient_id = p.id
            GROUP BY p.id, p.name, p.condition
            ORDER BY p.name
        ")->getResultArray();
    }

    /** Returns recent exercise records with patient name */
    public function getRecentRecords(int $limit = 10): array
    {
        return $this->select('exercise_records.*, patients.name AS patient_name')
                    ->join('patients', 'patients.id = exercise_records.patient_id')
                    ->orderBy('recorded_at', 'DESC')
                    ->limit($limit)
                    ->findAll();
    }
}
