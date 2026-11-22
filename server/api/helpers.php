<?php
/**
 * 公共函数：JSON 输出、IP 哈希、输入清理、管理员校验。
 */

declare(strict_types=1);

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $status = 400, array $extra = []): void
{
    json_out(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

function require_method(string $method): void
{
    $actual = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (strtoupper($actual) !== strtoupper($method)) {
        fail('Method not allowed', 405, ['allow' => strtoupper($method)]);
    }
}

function client_ip(): string
{
    // 默认只信任 REMOTE_ADDR；如果套了 CDN，请在 README 里看代理说明
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function client_ip_hash(): string
{
    return hash_hmac('sha256', client_ip(), IP_SALT);
}

/**
 * 读取 JSON body；不是 JSON 时退回表单数据。
 */
function request_data(): array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return is_array($_POST) ? $_POST : [];
}

/**
 * 去掉标签、控制字符，限制长度。适合短文本，不适合富文本。
 */
function clean_text(mixed $value, int $maxLength): string
{
    if (!is_string($value) && !is_numeric($value)) {
        return '';
    }
    $text = (string)$value;
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
    $text = strip_tags($text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return mb_substr($text, 0, $maxLength);
}

/**
 * 允许用 X-Admin-Token 或 Authorization: Bearer <token> 访问管理接口。
 */
function require_admin(): void
{
    $provided = (string)($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '');
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($provided === '' && preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        $provided = trim($m[1]);
    }

    if (ADMIN_TOKEN === 'change-me-please' || $provided === '' || !hash_equals(ADMIN_TOKEN, $provided)) {
        fail('Unauthorized', 401);
    }
}

function client_ua(): string
{
    return mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function client_referer(): string
{
    return mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255);
}
