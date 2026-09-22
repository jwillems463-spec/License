<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class BrandController
{
    private const SCHEMA = [
        'name'     => ['type' => 'string', 'required' => true, 'max' => 100],
        'slug'     => ['type' => 'string', 'max' => 120],
        'country'  => ['type' => 'string', 'max' => 80],
        'logo_url' => ['type' => 'url', 'max' => 500],
        'website'  => ['type' => 'url', 'max' => 255],
    ];

    /** GET /api/admin/brands */
    public function index(Request $req): void
    {
        $rows = Database::all(
            'SELECT b.*, COUNT(v.id) AS ev_count FROM brands b LEFT JOIN vehicles v ON v.brand_id = b.id
             GROUP BY b.id ORDER BY b.name'
        );
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['ev_count'] = (int) $r['ev_count'];
        }
        Response::json(['data' => $rows]);
    }

    /** POST /api/admin/brands */
    public function store(Request $req): void
    {
        $data = Validator::validate($req->body(), self::SCHEMA);
        $data['slug'] = slugify(($data['slug'] ?? '') ?: $data['name']);
        $this->assertUnique($data);
        $id = Database::insert('brands', $data);
        Audit::log('create', 'brand', $id, 'Created brand ' . $data['name']);
        Response::json(['data' => $this->find($id)], 201);
    }

    /** PUT /api/admin/brands/{id} */
    public function update(Request $req): void
    {
        $id = (int) $req->param('id');
        $brand = $this->find($id);
        $data = Validator::validate($req->body(), self::SCHEMA, true);
        if (array_key_exists('slug', $data) || isset($data['name'])) {
            $data['slug'] = slugify(($data['slug'] ?? '') ?: ($data['name'] ?? $brand['name']));
        }
        $this->assertUnique($data, $id);
        Database::update('brands', $id, $data);
        Audit::log('update', 'brand', $id, 'Updated brand ' . ($data['name'] ?? $brand['name']));
        Response::json(['data' => $this->find($id)]);
    }

    /** DELETE /api/admin/brands/{id} */
    public function destroy(Request $req): void
    {
        $id = (int) $req->param('id');
        $brand = $this->find($id);
        $count = (int) Database::value('SELECT COUNT(*) FROM vehicles WHERE brand_id = ?', [$id]);
        if ($count > 0) {
            throw new HttpException(409, "Cannot delete {$brand['name']}: $count EV(s) still use this brand.");
        }
        Database::query('DELETE FROM brands WHERE id = ?', [$id]);
        Audit::log('delete', 'brand', $id, 'Deleted brand ' . $brand['name']);
        Response::json(['data' => ['ok' => true]]);
    }

    private function find(int $id): array
    {
        $b = Database::one('SELECT * FROM brands WHERE id = ?', [$id]);
        if (!$b) {
            throw new HttpException(404, 'Brand not found.');
        }
        $b['id'] = (int) $b['id'];
        return $b;
    }

    private function assertUnique(array $data, int $ignoreId = 0): void
    {
        $errors = [];
        if (isset($data['name']) && Database::value('SELECT id FROM brands WHERE name = ? AND id <> ?', [$data['name'], $ignoreId])) {
            $errors['name'] = 'A brand with this name already exists.';
        }
        if (isset($data['slug']) && Database::value('SELECT id FROM brands WHERE slug = ? AND id <> ?', [$data['slug'], $ignoreId])) {
            $errors['slug'] = 'This slug is already in use.';
        }
        if ($errors) {
            throw new HttpException(422, 'Please correct the highlighted fields.', $errors);
        }
    }
}
