<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class AuditLogService
{
    public function __construct(private ?BaseConnection $database = null)
    {
        $this->database ??= db_connect();
    }

    public function record(
        int $actorUserId,
        string $eventType,
        string $objectType,
        string|int $objectId,
        ?string $reason = null,
        array $before = [],
        array $after = [],
    ): int {
        $objectId = trim((string) $objectId);
        if ($actorUserId < 1
            || !preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $eventType)
            || !preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $objectType)
            || $objectId === '' || mb_strlen($objectId) > 120) {
            throw new RuntimeException('Data riwayat audit tidak valid.');
        }

        $beforeJson = $this->encode($before);
        $afterJson = $this->encode($after);
        $reason = $reason === null ? null : $this->safeText($reason, 500);
        $this->database->table('audit_events')->insert([
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'reason' => $reason ?: null,
            'before_values' => $beforeJson,
            'after_values' => $afterJson,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->database->insertID();
    }

    private function encode(array $values): ?string
    {
        if ($values === []) {
            return null;
        }
        $safe = $this->sanitizeArray($values, 0);
        $json = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > 12000) {
            throw new RuntimeException('Rincian perubahan terlalu besar untuk riwayat audit.');
        }
        return $json;
    }

    private function sanitizeArray(array $values, int $depth): array
    {
        if ($depth > 4) {
            return ['detail' => '[kedalaman dibatasi]'];
        }
        $safe = [];
        foreach (array_slice($values, 0, 100, true) as $key => $value) {
            $keyText = (string) $key;
            if (preg_match('/password|secret|token|credential|authorization|cookie|hash|api.?key/i', $keyText)) {
                continue;
            }
            if (is_array($value)) {
                $safe[$keyText] = $this->sanitizeArray($value, $depth + 1);
            } elseif (is_string($value)) {
                $safe[$keyText] = $this->safeText($value, 1000);
            } elseif (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $safe[$keyText] = $value;
            } else {
                $safe[$keyText] = '[nilai tidak disimpan]';
            }
        }
        return $safe;
    }

    private function safeText(string $value, int $maxLength): string
    {
        $value = preg_replace('/\b[a-f0-9]{64}\b/i', '[token disamarkan]', $value) ?? '';
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        return mb_substr(trim($value), 0, $maxLength);
    }
}
