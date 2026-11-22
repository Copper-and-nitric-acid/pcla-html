<?php
/**
 * GET /api/download.php?channel=github
 * 记录一次下载请求，然后 302 到白名单里的下载地址。
 * 加 &format=json 可以只返回统计结果，不跳转（方便测试）。
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_method('GET');

$channel = strtolower(trim((string)($_GET['channel'] ?? 'github')));
if (!isset(DOWNLOAD_TARGETS[$channel])) {
    fail('未知的下载通道', 404);
}

$target = DOWNLOAD_TARGETS[$channel];
if (!filter_var($target, FILTER_VALIDATE_URL) || parse_url($target, PHP_URL_SCHEME) !== 'https') {
    fail('下载地址配置无效', 500);
}

$counted = false;
$hash = client_ip_hash();
$now = date('Y-m-d H:i:s');

try {
    $db = db();

    // 同一个 IP 在短时间内的重复点击只算一次
    $since = date('Y-m-d H:i:s', time() - DOWNLOAD_DEDUPE_SECONDS);
    $stmt = $db->prepare(
        'SELECT 1 FROM downloads WHERE ip_hash = :ip AND channel = :channel AND created_at >= :since LIMIT 1'
    );
    $stmt->execute([':ip' => $hash, ':channel' => $channel, ':since' => $since]);

    if ($stmt->fetchColumn() === false) {
        $insert = $db->prepare(
            'INSERT INTO downloads (channel, ip_hash, user_agent, referer, created_at)
             VALUES (:channel, :ip, :ua, :referer, :created_at)'
        );
        $insert->execute([
            ':channel' => $channel,
            ':ip' => $hash,
            ':ua' => client_ua(),
            ':referer' => client_referer(),
            ':created_at' => $now,
        ]);
        $counted = true;
    }
} catch (Throwable $e) {
    // 统计失败不应该挡住下载
    error_log('[PCLA API] download stats failed: ' . $e->getMessage());
}

if (($_GET['format'] ?? '') === 'json') {
    json_out([
        'ok' => true,
        'channel' => $channel,
        'target' => $target,
        'counted' => $counted,
    ]);
}

header('Location: ' . $target, true, 302);
exit;
