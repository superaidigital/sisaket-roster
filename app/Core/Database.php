<?php
declare(strict_types=1);
namespace App\Core;
use PDO;

final class Database {
    private static ?PDO $pdo = null;
    public static function pdo(): PDO {
        if (self::$pdo) return self::$pdo;
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']);
        return self::$pdo = new PDO($dsn, $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }
}
