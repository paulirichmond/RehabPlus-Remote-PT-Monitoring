<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\User\UserService;

class UserController extends BaseController
{
    private const ACCOUNT_FIELDS = ['name', 'email', 'password', 'role'];

    public function index()
    {
        return view('users/index', [
            'users' => (new UserModel())->findAll()
        ]);
    }

    public function create()
    {
        return view('users/form', [
            'user'   => null,
            'errors' => []
        ]);
    }

    public function store()
    {
        (new UserService())->createStaff($this->request->getPost(self::ACCOUNT_FIELDS));

        return redirect()
            ->to(site_url('users'))
            ->with('success', 'Account created successfully.');
    }

    public function createPatient()
    {
        if (! (new UserService())->createPatient($this->request->getPost(self::ACCOUNT_FIELDS))) {
            return redirect()
                ->to(site_url('users'))
                ->with('error', 'Patient account could not be created.');
        }

        return redirect()
            ->to(site_url('users'))
            ->with('success', 'Patient account created successfully.');
    }

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

    public function update($id)
    {
        $user = (new UserModel())->find($id);

        if (! $user) {
            return redirect()->to(site_url('users'));
        }

        (new UserService())->update($user, $this->request->getPost(self::ACCOUNT_FIELDS));

        return redirect()
            ->to(site_url('users'))
            ->with('success', 'User updated successfully.');
    }

    public function delete($id)
    {
        (new UserModel())->delete($id);

        return redirect()
            ->to(site_url('users#staff-roles'))
            ->with('success', 'User deleted.');
    }
}
