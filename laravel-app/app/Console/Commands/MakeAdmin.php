<?php

namespace App\Console\Commands;

use App\Auth\FirebaseUserProvider;
use App\Auth\StaffUser;
use App\Services\Firebase\FirebaseAdmin;
use App\Services\Firebase\FirebaseException;
use App\Services\Firebase\FirestoreClient;
use App\Services\User\UserService;
use Illuminate\Console\Command;

/**
 * Create the first dashboard administrator (there is no SQL users table to
 * seed any more). If the email already has a Firebase login, that account is
 * made an admin and its password is left alone.
 */
class MakeAdmin extends Command
{
    protected $signature = 'rehab:make-admin {email} {name}';

    protected $description = 'Create (or promote) a RehabPlus dashboard administrator in Firebase';

    public function handle(FirebaseAdmin $admin, FirestoreClient $db, UserService $users): int
    {
        $email = strtolower(trim($this->argument('email')));
        $name = trim($this->argument('name'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
            $this->error('Give a valid email and a name, e.g. rehab:make-admin admin@clinic.com "Clinic Admin"');

            return self::FAILURE;
        }

        try {
            $uid = $admin->uidForEmail($email);

            if ($uid) {
                $existing = $db->get("users/{$uid}");
                if (($existing['role'] ?? null) === 'patient') {
                    $this->error('That email belongs to a patient account; use a different email for staff.');

                    return self::FAILURE;
                }

                $db->commit([$db->mergeWrite("users/{$uid}", [
                    'role' => StaffUser::ADMIN, 'name' => $name, 'email' => $email, 'active' => true,
                ])]);
                $admin->updateUser($uid, ['disabled' => false]);
                FirebaseUserProvider::forget($uid);
                $this->info("{$email} already had a Firebase login; it is now an administrator (password unchanged).");

                return self::SUCCESS;
            }

            $password = (string) $this->secret('Password for the new admin (min '.FirebaseAdmin::MIN_PASSWORD.' characters)');
            if (mb_strlen($password) < FirebaseAdmin::MIN_PASSWORD || $password !== $this->secret('Repeat the password')) {
                $this->error('Passwords must match and be at least '.FirebaseAdmin::MIN_PASSWORD.' characters.');

                return self::FAILURE;
            }

            $users->create(['email' => $email, 'name' => $name, 'password' => $password, 'role' => StaffUser::ADMIN]);
        } catch (FirebaseException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Administrator {$email} created. Sign in to the dashboard with it.");

        return self::SUCCESS;
    }
}
