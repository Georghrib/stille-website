<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Dünne PDO-Hülle. Alle Abfragen laufen über Prepared Statements.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(?array $cfg = null): PDO
    {
        $cfg ??= [
            'host' => Env::get('DB_HOST', 'localhost'),
            'port' => Env::get('DB_PORT', '3306'),
            'name' => Env::get('DB_NAME', ''),
            'user' => Env::get('DB_USER', ''),
            'pass' => Env::get('DB_PASS', ''),
        ];
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'] ?: '3306', $cfg['name']);
        return new PDO($dsn, (string) $cfg['user'], (string) $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '" . date('P') . "'",
        ]);
    }

    public static function pdo(): PDO
    {
        return self::$pdo ??= self::connect();
    }

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** @param array<string,mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn ($c) => "`$c`", $cols)),
            implode(', ', array_map(fn ($c) => ":$c", $cols))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public static function update(string $table, int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $set = implode(', ', array_map(fn ($c) => "`$c` = :$c", array_keys($data)));
        $data['__id'] = $id;
        self::run(sprintf('UPDATE `%s` SET %s WHERE id = :__id', $table, $set), $data);
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
