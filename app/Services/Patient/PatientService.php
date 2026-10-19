<?php

namespace App\Services\Patient;

use App\Models\ExerciseRecordModel;
use App\Models\PatientModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class PatientService
{
    private PatientModel $patients;
    private ExerciseRecordModel $records;

    public function __construct()
    {
        $this->patients = new PatientModel();
        $this->records = new ExerciseRecordModel();
    }

    public function find(int $id): ?array
    {
        return $this->patients->find($id);
    }

    public function search(string $search, string $condition): array
    {
        $query = $this->patients->orderBy('name', 'ASC');

        if ($search !== '') {
            $query->groupStart()
                ->like('name', $search)
                ->orLike('condition', $search)
                ->groupEnd();
        }

        if ($condition !== '') {
            $query->where('condition', $condition);
        }

        $data = $query->findAll();
        $conditions = $this->patients
            ->select('condition')
            ->distinct()
            ->orderBy('condition', 'ASC')
            ->findColumn('condition');

        return [
            'data' => $data,
            'conditions' => $conditions,
            'search' => $search,
            'selectedCondition' => $condition,
        ];
    }

    public function profile(array $patient): array
    {
        $records = $this->records
            ->where('patient_id', $patient['id'])
            ->orderBy('recorded_at', 'DESC')
            ->findAll();

        $trend = $this->records->db->query(
            "
            SELECT
                DATE(recorded_at) AS day,
                ROUND(AVG(pain_level), 1) AS avg_pain,
                ROUND(SUM(sets_completed) / NULLIF(SUM(sets_prescribed), 0) * 100, 1) AS compliance_rate
            FROM exercise_records
            WHERE patient_id = ?
            GROUP BY day
            ORDER BY day DESC
            LIMIT 14
            ",
            [$patient['id']]
        )->getResultArray();

        return [
            'patient' => $patient,
            'records' => $records,
            'trend' => array_reverse($trend),
        ] + $this->progress($records);
    }

    /**
     * Compliance % and average pain for a patient's exercise records.
     */
    public function progress(array $records): array
    {
        if ($records === []) {
            return ['compliance' => 0, 'avgPain' => 0];
        }

        $prescribed = array_sum(array_column($records, 'sets_prescribed'));
        $completed = array_sum(array_column($records, 'sets_completed'));

        return [
            'compliance' => $prescribed ? round(($completed / $prescribed) * 100, 1) : 0,
            'avgPain' => round(array_sum(array_column($records, 'pain_level')) / count($records), 1),
        ];
    }

    public function create(array $data, ?UploadedFile $avatar): void
    {
        $data += $this->storeAvatar($avatar);
        $this->patients->insert($data);
        $this->clearStatsCache();
    }

    public function update(int $id, array $data, ?UploadedFile $avatar): void
    {
        $data += $this->storeAvatar($avatar);
        $this->patients->update($id, $data);
        $this->clearStatsCache();
    }

    public function delete(int $id): bool
    {
        if (! $this->patients->find($id)) {
            return false;
        }

        $this->patients->delete($id);
        $this->clearStatsCache();

        return true;
    }

    /**
     * Move an uploaded profile picture into public/uploads/avatars and resize it to 200x200.
     * Returns ['avatar' => filename], or [] when no valid file was uploaded.
     */
    private function storeAvatar(?UploadedFile $file): array
    {
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return [];
        }

        $uploadPath = FCPATH . 'uploads/avatars';
        if (! is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $newName = $file->getRandomName();
        $file->move($uploadPath, $newName);

        try {
            \Config\Services::image()
                ->withFile($uploadPath . '/' . $newName)
                ->resize(200, 200, true)
                ->save($uploadPath . '/' . $newName);
        } catch (\Throwable $e) {
            // Keep the uploaded image even if resizing fails
        }

        return ['avatar' => $newName];
    }

    private function clearStatsCache(): void
    {
        \Config\Services::cache()->delete('patient_stats');
    }
}
