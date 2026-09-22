<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AuthController;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class UserController
{
    private static function schema(): array
    {
        return [
            'name'                 => ['type' => 'string', 'required' => true, 'max' => 100],
            'email'                => ['type' => 'email', 'required' => true],
            'role'                 => ['type' => 'enum', 'required' => true, 'values' => Auth::ROLES],
            'is_active'            => ['type' => 'bool', 'default' => 1],
            'must_change_password' => ['type' => 'bool', 'default' => 1],
        ];
    }

    private const COLUMNS = 'id, name, email, role, is_active, must_change_password, last_login_at, created_at';

    /** GET /api/admin/users */
    public function index(Request $req): void
    {
        $rows = array_map([$this, 'present'], Database::all('SELECT ' . self::COLUMNS . ' FROM users ORDER BY name'));
        Response::json(['data' => $rows]);
    }

    /** POST /api/admin/users */
    public function store(Request $req): void
    {
        $data = Validator::validate($req->body(), self::schema());
        $password = (string) $req->input('password', '');
        if ($msg = AuthController::passwordProblem($password)) {
            throw new HttpException(422, $msg, ['password' => $msg]);
        }
        $this->assertEmailFree($data['email']);
        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $id = Database::insert('users', $data);
        Audit::log('create', 'user', $id, "Created {$data['role']} {$data['email']}");
        Response::json(['data' => $this->find($id)], 201);
    }

    /** PUT /api/admin/users/{id} */
    public function update(Request $req): void
    {
        $id = (int) $req->param('id');
        $user = $this->find($id);
        $data = Validator::validate($req->body(), self::schema(), true);

        if (isset($data['email'])) {
            $this->assertEmailFree($data['email'], $id);
        }
        $self = Auth::user()['id'] === $id;
        if ($self && ((isset($data['role']) && $data['role'] !== 'admin') || (isset($data['is_active']) && !$data['is_active']))) {
            throw new HttpException(422, 'You cannot demote or deactivate your own account.');
        }
        if ($user['role'] === 'admin' && ((isset($data['role']) && $data['role'] !== 'admin') || (isset($data['is_active']) && !$data['is_active']))) {
            $this->assertAnotherAdmin($id);
        }

        $password = (string) $req->input('password', '');
        if ($password !== '') {
            if ($msg = AuthController::passwordProblem($password)) {
                throw new HttpException(422, $msg, ['password' => $msg]);
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        Database::update('users', $id, $data);
        Audit::log('update', 'user', $id, 'Updated user ' . ($data['email'] ?? $user['email']) . ($password !== '' ? ' (password reset)' : ''));
        Response::json(['data' => $this->find($id)]);
    }

    /** DELETE /api/admin/users/{id} */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');
        $user = $this->find($id);
        if (Auth::user()['id'] === $id) {
            throw new HttpException(422, 'You cannot delete your own account.');
        }
        if ($user['role'] === 'admin') {
            $this->assertAnotherAdmin($id);
        }
        Database::query('DELETE FROM users WHERE id = ?', [$id]);
        Audit::log('delete', 'user', $id, 'Deleted user ' . $user['email']);
        Response::json(['data' => ['ok' => true]]);
    }

    private function find(int $id): array
    {
        $u = Database::one('SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?', [$id]);
        if (!$u) {
            throw new HttpException(404, 'User not found.');
        }
        return $this->present($u);
    }

    private function present(array $u): array
    {
        $u['id'] = (int) $u['id'];
        $u['is_active'] = (bool) $u['is_active'];
        $u['must_change_password'] = (bool) $u['must_change_password'];
        return $u;
    }

    private function assertEmailFree(string $email, int $ignoreId = 0): void
    {
        if (Database::value('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $ignoreId])) {
            throw new HttpException(422, 'Please correct the highlighted fields.', ['email' => 'This email is already registered.']);
        }
    }

    private function assertAnotherAdmin(int $excludingId): void
    {
        $others = (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?", [$excludingId]);
        if ($others === 0) {
            throw new HttpException(422, 'At least one active administrator is required.');
        }
    }
}
