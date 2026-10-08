<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\SuperAdminModel;

class AuthController extends BaseController
{
    public function login()
    {
        session()->destroy();
        return view('auth/login', ['patientLogin' => false]);
    }

    public function patientLogin()
    {
        session()->destroy();
        return view('auth/login', ['patientLogin' => true]);
    }

    public function attempt()
    {
        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        // Check super_admins table first
        $superAdmin = (new SuperAdminModel())->where('email', $email)->first();
        if ($superAdmin && password_verify($password, $superAdmin['password'])) {
            session()->set([
                'user_id'   => $superAdmin['id'],
                'user_name' => 'Super Admin',
                'user_role' => 'superadmin',
                'user_email'=> $superAdmin['email'],
            ]);
            return redirect()->to(site_url('dashboard'));
        }

        // Check regular users table
        $user = (new UserModel())->findByEmail($email);
        if ($user && $user['role'] === 'patient') {
            return redirect()->back()
                ->with('error', 'Patient accounts cannot use the admin login. Use the patient portal login.')
                ->withInput();
        }

        if ($user && (int) $user['is_active'] === 1 && password_verify($password, $user['password'])) {
            session()->set([
                'user_id'   => $user['id'],
                'user_name' => $user['name'],
                'user_role' => $user['role'],
                'user_email'=> $user['email'],
            ]);

            return redirect()->to(
                $user['role'] === 'patient'
                    ? site_url('patient-portal')
                    : site_url('dashboard')
            );
        }

        return redirect()->back()->with('error', 'Invalid email or password.')->withInput();
    }

    public function attemptPatient()
    {
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');
        $user = (new UserModel())->findByEmail($email);

        if ($user && $user['role'] === 'patient'
            && (int) $user['is_active'] === 1
            && password_verify($password, $user['password'])) {
            session()->set([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'user_role' => 'patient',
                'user_email' => $user['email'],
            ]);

            return redirect()->to(site_url('patient-portal'));
        }

        return redirect()->back()
            ->with('error', 'Invalid patient email or password.')
            ->withInput();
    }

    public function logout()
    {
        return view('auth/logout');
    }

    public function doLogout()
    {
        session()->destroy();
        return redirect()->to(site_url('login'))->with('success', 'You have been signed out.');
    }
}
