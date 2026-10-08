<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\PatientModel;

class UserController extends BaseController
{
    // ======================================================
    // USERS PAGE
    // ======================================================

    public function index()
    {
        $model = new UserModel();

        $data = $model->findAll();

        return view('users/index', [
            'users' => $data
        ]);
    }


    // ======================================================
    // CREATE STAFF PAGE
    // ======================================================

    public function create()
    {
        return view('users/form', [
            'user'   => null,
            'errors' => []
        ]);
    }


    // ======================================================
    // STORE STAFF ACCOUNT
    // ======================================================

    public function store()
    {
        $model = new UserModel();

        $role = UserModel::normalizeRoleForStaffAccount($this->request->getPost('role'));

        $data = [

            'name' => $this->request->getPost('name'),

            'email' => $this->request->getPost('email'),

            'password' => password_hash(
                $this->request->getPost('password'),
                PASSWORD_DEFAULT
            ),

            'role' => $role,

            'is_active' => 1

        ];

        $model->insert($data);

        return redirect()
            ->to(site_url('users'))
            ->with('success', 'Account created successfully.');
    }


    // ======================================================
    // CREATE PATIENT ACCOUNT
    // ======================================================

    public function createPatient()
    {
        $model = new UserModel();

        $data = [

            'name' => $this->request->getPost('name'),

            'email' => $this->request->getPost('email'),

            'password' => password_hash(
                $this->request->getPost('password'),
                PASSWORD_DEFAULT
            ),

            // FORCE PATIENT ROLE

            'role' => 'patient',

            'is_active' => 1

        ];

        if ($model->insert($data) === false) {
            return redirect()
                ->to(site_url('users'))
                ->with('error', 'Patient account could not be created.');
        }

        $userId = $model->getInsertID();
        (new PatientModel())->insert([
            'user_id' => $userId,
            'name' => $data['name'],
            'condition' => 'Not specified',
        ]);

        return redirect()
            ->to(site_url('users'))
            ->with('success', 'Patient account created successfully.');
    }


    // ======================================================
    // EDIT USER
    // ======================================================

    public function edit($id)
    {
        $user = (new UserModel())->find($id);

        if (! $user) {

            return redirect()->to(site_url('users'));
        }

        return view('users/form', [

            'user'   => $user,
            'errors' => []

        ]);
    }

    public function show($id)
    {
        $user = (new UserModel())->find($id);

        if (! $user) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('User not found.');
        }

        return view('users/show', [
            'user' => $user
        ]);
    }


    // ======================================================
    // UPDATE USER
    // ======================================================

    public function update($id)
    {
        $model = new UserModel();
        $user = $model->find($id);

        if (! $user) {
            return redirect()->to(site_url('users'));
        }

        $data = [

            'name' => $this->request->getPost('name'),

            'email' => $this->request->getPost('email'),

        ];

        if ($user['role'] !== 'patient') {
            $data['role'] = UserModel::normalizeRoleForStaffAccount($this->request->getPost('role'));
        } else {
            $data['role'] = 'patient';
        }

        $password = $this->request->getPost('password');

        if (! empty($password)) {

            $data['password'] = password_hash(
                $password,
                PASSWORD_DEFAULT
            );
        }

        $model->update($id, $data);

        return redirect()
            ->to(site_url('users'))
            ->with('success', 'User updated successfully.');
    }


    // ======================================================
    // DELETE USER
    // ======================================================

    public function delete($id)
    {
        (new UserModel())->delete($id);

        return redirect()
            ->to(site_url('users#staff-roles'))
            ->with('success', 'User deleted.');
    }
}