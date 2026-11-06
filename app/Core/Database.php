<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Единое подключение к MySQL через PDO.
 * Все запросы — только подготовленные (prepared statements).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                config('db.host', '127.0.0.1'),
                (int) config('db.port', 3306),
                config('db.name'),
                config('db.charset', 'utf8mb4')
            );

            self::$pdo = new PDO($dsn, (string) config('db.user'), (string) config('db.pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);

            // Часовой пояс БД совпадает с PHP, чтобы NOW() и date() не расходились
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** INSERT по ассоциативному массиву. Возвращает ID новой записи. */
    public static function insert(string $table, array $data): int
    {
        self::assertIdentifier($table);
        $columns = array_keys($data);
        array_walk($columns, [self::class, 'assertIdentifier']);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', array_map(static fn ($c) => ':' . $c, $columns))
        );
        self::query($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * UPDATE по ассоциативному массиву.
     * $where — SQL-условие с именованными параметрами, например "id = :id".
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        self::assertIdentifier($table);
        $set = [];
        $params = [];
        foreach ($data as $column => $value) {
            self::assertIdentifier($column);
            $set[] = "`{$column}` = :set_{$column}";
            $params['set_' . $column] = $value;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), $where);
        return self::query($sql, array_merge($params, $whereParams))->rowCount();
    }

    /** Выполняет функцию в транзакции; при исключении — откат. */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Имена таблиц/колонок не параметризуются, поэтому проверяем их по шаблону. */
    private static function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
            throw new \InvalidArgumentException("Недопустимое имя в SQL: {$name}");
        }
    }
}
