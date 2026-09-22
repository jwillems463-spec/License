<?php
declare(strict_types=1);

namespace App\Core;

final class Audit
{
    public static function log(string $action, string $entity, ?int $entityId, string $summary = ''): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        Database::query(
            'INSERT INTO audit_log (user_id, action, entity, entity_id, summary, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId ? (int) $userId : null, $action, $entity, $entityId, mb_substr($summary, 0, 255), client_ip()]
        );
    }
}
