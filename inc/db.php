<?php
/**
 * PDO connection. One connection per request.
 *
 * Every query in this codebase goes through prepared statements — see q()/qOne()/
 * qAll()/qVal() below, which exist so that hand-written string concatenation
 * never becomes tempting.
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements, not PDO's client-side emulation.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        if (DEBUG) {
            throw $e;
        }
        http_response_code(500);
        exit('Database connection failed.');
    }

    return $pdo;
}

/** Run a statement and return the PDOStatement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** Fetch one row, or null. */
function qOne(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Fetch all rows. */
function qAll(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** Fetch a single scalar value, or null. */
function qVal(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}
