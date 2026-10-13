<?php
/**
 * The admin audit trail.
 *
 * Every write in the panel goes through log_activity(), and the before/after
 * snapshots are the JSON columns the schema already had waiting for them. Two
 * reasons this is not optional: "who unpublished this?" is a question that only
 * ever gets asked after something has gone wrong, and the previous values are
 * what make the answer actionable.
 *
 * Logging must never break the write it is describing, so every failure here is
 * swallowed and logged to the PHP error log instead.
 */

declare(strict_types=1);

/**
 * @param array|null $before Row as it was, or null for a create.
 * @param array|null $after  Row as it now is, or null for a hard delete.
 */
function log_activity(
    string $module,
    string $action,
    string $entityType = '',
    ?int $entityId = null,
    ?array $before = null,
    ?array $after = null
): void {
    try {
        q(
            'INSERT INTO activity_log
                 (admin_id, module, action, entity_type, entity_id, before_json, after_json, ip_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                admin_id() ?: null,
                mb_substr($module, 0, 32),
                mb_substr($action, 0, 32),
                mb_substr($entityType, 0, 32),
                $entityId,
                $before === null ? null : json_encode(redact_secrets($before), JSON_UNESCAPED_UNICODE),
                $after  === null ? null : json_encode(redact_secrets($after), JSON_UNESCAPED_UNICODE),
                ip_hash(),
            ]
        );
    } catch (Throwable $e) {
        error_log('log_activity: ' . $e->getMessage());
    }
}

/**
 * Strip anything that must never be written to a table people browse.
 *
 * The audit trail is shown in the panel and may be exported, and an admin row's
 * before/after snapshot would otherwise put a password hash and a TOTP secret
 * in plain view of every editor.
 */
function redact_secrets(array $row): array
{
    foreach (['password_hash', 'password', 'totp_secret', 'secret'] as $k) {
        if (array_key_exists($k, $row)) {
            $row[$k] = '«redacted»';
        }
    }
    return $row;
}

/** The fields that actually changed, so a log line is readable. */
function activity_diff(?array $before, ?array $after): array
{
    if ($before === null || $after === null) {
        return [];
    }
    $out = [];
    foreach ($after as $k => $v) {
        if (!array_key_exists($k, $before)) {
            continue;
        }
        if ((string) $before[$k] !== (string) $v) {
            $out[$k] = ['from' => $before[$k], 'to' => $v];
        }
    }
    return $out;
}
