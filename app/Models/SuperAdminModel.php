<?php

namespace App\Models;

use CodeIgniter\Model;

class SuperAdminModel extends Model
{
    protected $table      = 'super_admins';
    protected $primaryKey = 'id';
    protected $allowedFields = ['email', 'password'];
    protected $useTimestamps = true;
}
