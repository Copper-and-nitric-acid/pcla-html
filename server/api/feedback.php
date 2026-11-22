<?php
/**
 * POST /api/feedback.php  提交反馈（公开）
 * GET  /api/feedback.php  查看反馈（需要 X-Admin-Token）
 *
 * POST 字段：name, contact, message, website（蜜罐，留空）
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    require_admin();

    $status = (string)($_GET['status'] ?? 'all');
    $allowed = ['all', 'pending', 'approved', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        fail('status 参数无效', 400);
    }

    $db = db();
    if ($status === 'all') {
        $stmt = $db->query(
            'SELECT id, name, contact, message, status, created_at
             FROM feedback ORDER BY id DESC LIMIT 100'
        );
    } else {
        $stmt = $db->prepare(
            'SELECT id, name, contact, message, status, created_at
             FROM feedback WHERE status = :status ORDER BY id DESC LIMIT 100'
        );
        $stmt->execute([':status' => $status]);
    }

    json_out(['ok' => true, 'items' => $stmt->fetchAll()]);
}

require_method('POST');

$data = request_data();

// 蜜罐：机器人通常会填，直接假装成功，不写库
if (trim((string)($data['website'] ?? '')) !== '') {
    json_out(['ok' => true, 'id' => 0, 'message' => '已收到']);
}

$name = clean_text($data['name'] ?? '', 24);
$contact = clean_text($data['contact'] ?? '', 120);
$message = clean_text($data['message'] ?? '', 1000);

if ($name === '') {
    fail('请填写昵称', 422);
}
if (mb_strlen($message, 'UTF-8') < 5) {
    fail('留言至少 5 个字', 422);
}

$hash = client_ip_hash();
$now = date('Y-m-d H:i:s');

try {
    $db = db();

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM feedback WHERE ip_hash = :ip AND created_at >= :since'
    );
    $stmt->execute([
        ':ip' => $hash,
        ':since' => date('Y-m-d H:i:s', time() - FEEDBACK_WINDOW_MINUTES * 60),
    ]);
    if ((int)$stmt->fetchColumn() >= FEEDBACK_MAX) {
        header('Retry-After: 600');
        fail('提交太频繁了，过几分钟再来吧', 429);
    }

    $insert = $db->prepare(
        'INSERT INTO feedback (name, contact, message, status, ip_hash, user_agent, created_at)
         VALUES (:name, :contact, :message, :status, :ip, :ua, :created_at)'
    );
    $insert->execute([
        ':name' => $name,
        ':contact' => $contact,
        ':message' => $message,
        ':status' => 'pending',
        ':ip' => $hash,
        ':ua' => client_ua(),
        ':created_at' => $now,
    ]);

    json_out([
        'ok' => true,
        'id' => (int)$db->lastInsertId(),
        'message' => '留言已提交，审核后显示',
    ], 201);
} catch (Throwable $e) {
    error_log('[PCLA API] feedback failed: ' . $e->getMessage());
    fail('留言保存失败，请稍后再试', 500);
}
