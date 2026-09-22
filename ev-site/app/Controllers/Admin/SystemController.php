<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SettingsRepository;

/**
 * Dashboard stats, site settings, audit log and image uploads.
 */
final class SystemController
{
    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /** GET /api/admin/stats */
    public function stats(Request $req): void
    {
        $counts = Database::one(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'published') AS published,
                    SUM(status = 'draft') AS drafts,
                    SUM(is_featured = 1) AS featured,
                    ROUND(AVG(CASE WHEN status='published' THEN range_km END)) AS avg_range,
                    ROUND(AVG(CASE WHEN status='published' THEN price_usd END)) AS avg_price
             FROM vehicles"
        ) ?? [];
        $byBody = Database::all("SELECT body_type AS label, COUNT(*) AS value FROM vehicles WHERE status='published' GROUP BY body_type ORDER BY value DESC");
        $byBrand = Database::all(
            "SELECT b.name AS label, COUNT(v.id) AS value FROM brands b JOIN vehicles v ON v.brand_id = b.id
             WHERE v.status='published' GROUP BY b.id, b.name ORDER BY value DESC, b.name LIMIT 10"
        );
        $recent = Database::all(
            'SELECT a.id, a.action, a.entity, a.entity_id, a.summary, a.created_at, u.name AS user_name
             FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 8'
        );
        $cast = static fn(array $rows) => array_map(static fn($r) => ['label' => $r['label'], 'value' => (int) $r['value']], $rows);

        Response::json(['data' => [
            'vehicles' => array_map(static fn($v) => $v === null ? 0 : (int) $v, $counts),
            'brands'   => (int) Database::value('SELECT COUNT(*) FROM brands'),
            'users'    => (int) Database::value('SELECT COUNT(*) FROM users WHERE is_active = 1'),
            'by_body'  => $cast($byBody),
            'by_brand' => $cast($byBrand),
            'recent'   => $recent,
        ]]);
    }

    /** GET /api/admin/settings */
    public function settings(Request $req): void
    {
        $schema = [];
        foreach (SettingsRepository::SCHEMA as $k => $def) {
            $schema[] = ['key' => $k, 'label' => $def['label'], 'max' => $def['max']];
        }
        Response::json(['data' => SettingsRepository::all(), 'schema' => $schema]);
    }

    /** PUT /api/admin/settings */
    public function saveSettings(Request $req): void
    {
        $input = $req->body();
        $values = [];
        $errors = [];
        foreach (SettingsRepository::SCHEMA as $key => $def) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $v = trim((string) $input[$key]);
            if (mb_strlen($v) > $def['max']) {
                $errors[$key] = "Must be at most {$def['max']} characters.";
                continue;
            }
            $values[$key] = $v;
        }
        if (isset($values['max_compare']) && (!ctype_digit($values['max_compare']) || $values['max_compare'] < 2 || $values['max_compare'] > 4)) {
            $errors['max_compare'] = 'Must be 2, 3 or 4.';
        }
        if (isset($values['items_per_page']) && (!ctype_digit($values['items_per_page']) || $values['items_per_page'] < 6 || $values['items_per_page'] > 48)) {
            $errors['items_per_page'] = 'Must be between 6 and 48.';
        }
        if (!empty($values['contact_email']) && !filter_var($values['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = 'Must be a valid email address.';
        }
        if (isset($values['site_name']) && $values['site_name'] === '') {
            $errors['site_name'] = 'This field is required.';
        }
        if ($errors) {
            throw new HttpException(422, 'Please correct the highlighted fields.', $errors);
        }
        SettingsRepository::save($values);
        Audit::log('update', 'settings', null, 'Updated settings: ' . implode(', ', array_keys($values)));
        Response::json(['data' => SettingsRepository::all()]);
    }

    /** GET /api/admin/audit */
    public function audit(Request $req): void
    {
        $perPage = 50;
        $page = max(1, (int) ($req->query['page'] ?? 1));
        $total = (int) Database::value('SELECT COUNT(*) FROM audit_log');
        $offset = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT a.*, u.name AS user_name, u.email AS user_email FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT $perPage OFFSET $offset"
        );
        Response::json(['data' => $rows, 'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int) ceil($total / $perPage))]]);
    }

    /** POST /api/admin/uploads (multipart, field "image") */
    public function upload(Request $req): void
    {
        $file = $_FILES['image'] ?? null;
        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            $msg = in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'The file is larger than the server allows.' : 'No image was uploaded.';
            throw new HttpException(422, $msg, ['image' => $msg]);
        }
        $max = (int) Config::get('uploads.max_bytes', 5 * 1024 * 1024);
        if ($file['size'] > $max) {
            throw new HttpException(422, 'Image must be ' . round($max / 1048576, 1) . ' MB or smaller.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new HttpException(400, 'Invalid upload.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::IMAGE_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
            throw new HttpException(422, 'Only JPG, PNG, WEBP or GIF images are allowed.');
        }

        $dir = APP_ROOT . '/uploads/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new HttpException(500, 'Upload folder is not writable.');
        }
        $name = bin2hex(random_bytes(12)) . '.' . self::IMAGE_TYPES[$mime];
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
            throw new HttpException(500, 'Could not save the uploaded file.');
        }
        @chmod("$dir/$name", 0644);
        $path = url('uploads/' . date('Y/m') . '/' . $name);
        Audit::log('upload', 'image', null, 'Uploaded ' . $path);
        Response::json(['data' => ['url' => $path]], 201);
    }
}
