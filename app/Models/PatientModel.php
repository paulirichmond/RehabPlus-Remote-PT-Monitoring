<?php

namespace App\Models;

use CodeIgniter\Model;

class PatientModel extends Model
{
    protected $table = 'patients';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'name',
        'user_id',
        'condition',
        'medical_summary',
        'therapy_plan',
        'avatar',
        'assigned_to'
    ];

    protected $useTimestamps = true;

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[100]',
        'condition' => 'required|min_length[2]|max_length[150]',
    ];

    public function getPaginated(int $perPage = 10): array
    {
        $data = $this->paginate($perPage);
        $pager = $this->pager;

        return compact('data', 'pager');
    }
}