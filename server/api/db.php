<?php
/**
 * SQLite 连接与自动建表。
 * 数据库默认位于 api/../storage/pcla.sqlite。
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('无法创建 storage 目录');
        }
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('storage 目录不可写，请把权限设为 775 或 777');
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    $schema = @file_get_contents(__DIR__ . '/../schema.sql');
    if ($schema === false || trim($schema) === '') {
        throw new RuntimeException('缺少 schema.sql');
    }
    $pdo->exec($schema);

    return $pdo;
}
