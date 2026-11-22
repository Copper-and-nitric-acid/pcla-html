<?php
/**
 * 所有 API 文件的公共入口：时区、CORS、安全头、异常处理。
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

date_default_timezone_set(APP_TIMEZONE);

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store, max-age=0');

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && in_array($origin, ALLOWED_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token');
    header('Access-Control-Max-Age: 600');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

set_exception_handler(static function (Throwable $e): void {
    error_log('[PCLA API] ' . $e->getMessage());
    if (!headers_sent()) {
        json_out(['ok' => false, 'error' => '服务器内部错误，请稍后再试'], 500);
    }
    exit;
});
