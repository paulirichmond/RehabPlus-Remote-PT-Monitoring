Login credentials are not stored in this repository.
The Laravel dashboard (laravel-app/) signs staff in with Firebase Auth. Create the
first administrator with `php artisan rehab:make-admin <email> "<name>"` (it asks
for the password), then add therapists and front-desk staff under Staff Accounts.

#API USAGES:
GET http://localhost:8080/api/v1/patients
Header: X-API-KEY: <ask the team for the API key>
