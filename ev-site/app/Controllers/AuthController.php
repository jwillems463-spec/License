<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthController
{
    /** POST /api/auth/login */
    public function login(Request $req): void
    {
        $email = (string) $req->input('email', '');
        $password = (string) $req->input('password', '');
        if ($email === '' || $password === '') {
            throw new HttpException(422, 'Email and password are required.');
        }
        $user = Auth::attempt($email, $password);
        Response::json(['data' => $user, 'csrf_token' => Session::csrfToken()]);
    }

    /** POST /api/auth/logout */
    public function logout(Request $req): void
    {
        if (Auth::user()) {
            Audit::log('logout', 'user', Auth::user()['id'], 'Signed out');
        }
        Auth::logout();
        Response::json(['data' => ['ok' => true], 'csrf_token' => Session::csrfToken()]);
    }

    /** GET /api/auth/me */
    public function me(Request $req): void
    {
        Response::json(['data' => Auth::user(), 'csrf_token' => Session::csrfToken()]);
    }

    /** POST /api/auth/password — change own password */
    public function changePassword(Request $req): void
    {
        $user = Auth::user();
        $current = (string) $req->input('current_password', '');
        $new = (string) $req->input('new_password', '');

        $row = Database::one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!$row || !password_verify($current, $row['password_hash'])) {
            throw new HttpException(422, 'Current password is incorrect.', ['current_password' => 'Current password is incorrect.']);
        }
        if ($msg = self::passwordProblem($new)) {
            throw new HttpException(422, $msg, ['new_password' => $msg]);
        }
        if (password_verify($new, $row['password_hash'])) {
            throw new HttpException(422, 'New password must differ from the current one.', ['new_password' => 'Choose a different password.']);
        }
        Database::update('users', $user['id'], [
            'password_hash'        => password_hash($new, PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);
        session_regenerate_id(true);
        Audit::log('password', 'user', $user['id'], 'Changed own password');
        Response::json(['data' => ['ok' => true]]);
    }

    public static function passwordProblem(string $pw): ?string
    {
        if (strlen($pw) < 10) {
            return 'Password must be at least 10 characters.';
        }
        if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
            return 'Password must contain letters and numbers.';
        }
        if (strlen($pw) > 200) {
            return 'Password is too long.';
        }
        return null;
    }
}
