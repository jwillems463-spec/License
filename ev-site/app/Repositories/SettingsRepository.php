<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class SettingsRepository
{
    /** Editable settings with defaults and max length. */
    public const SCHEMA = [
        'site_name'       => ['default' => 'e-carscompare', 'max' => 80, 'label' => 'Site name'],
        'site_tagline'    => ['default' => 'Find, explore and compare electric vehicles', 'max' => 160, 'label' => 'Tagline'],
        'contact_email'   => ['default' => '', 'max' => 190, 'label' => 'Contact email'],
        'currency_symbol' => ['default' => '$', 'max' => 5, 'label' => 'Currency symbol'],
        'hero_title'      => ['default' => 'Find your next electric vehicle', 'max' => 120, 'label' => 'Homepage headline'],
        'hero_subtitle'   => ['default' => '', 'max' => 300, 'label' => 'Homepage sub-headline'],
        'footer_text'     => ['default' => '', 'max' => 300, 'label' => 'Footer text'],
        'max_compare'     => ['default' => '4', 'max' => 1, 'label' => 'Max EVs in comparison (2-4)'],
        'items_per_page'  => ['default' => '12', 'max' => 2, 'label' => 'EVs per page (6-48)'],
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            $out = [];
            foreach (self::SCHEMA as $k => $def) {
                $out[$k] = $def['default'];
            }
            foreach (Database::all('SELECT setting_key, setting_value FROM settings') as $row) {
                if (array_key_exists($row['setting_key'], self::SCHEMA)) {
                    $out[$row['setting_key']] = (string) $row['setting_value'];
                }
            }
            self::$cache = $out;
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        return self::all()[$key] ?? $default;
    }

    public static function maxCompare(): int
    {
        return max(2, min(4, (int) self::get('max_compare', '4')));
    }

    public static function perPage(): int
    {
        return max(6, min(48, (int) self::get('items_per_page', '12')));
    }

    public static function save(array $values): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($values as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        self::$cache = null;
    }
}
