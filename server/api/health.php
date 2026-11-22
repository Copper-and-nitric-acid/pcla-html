<?php
/**
 * GET /api/health.php
 * 健康检查：PHP 版本、数据库连通性。
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_method('GET');

$db = db();
$ok = $db->query('SELECT 1')->fetchColumn() === 1;

json_out([
    'ok' => $ok,
    'service' => APP_NAME,
    'php' => PHP_VERSION,
    'database' => $ok ? 'ok' : 'error',
    'time' => date('c'),
]);
