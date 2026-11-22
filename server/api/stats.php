<?php
/**
 * GET /api/stats.php
 * 公开统计：下载中转次数、今日 / 近 7 天、已通过的反馈数量。
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_method('GET');

$db = db();

$total = (int)$db->query('SELECT COUNT(*) FROM downloads')->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(*) FROM downloads WHERE created_at >= :since');
$stmt->execute([':since' => date('Y-m-d 00:00:00')]);
$today = (int)$stmt->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(*) FROM downloads WHERE created_at >= :since');
$stmt->execute([':since' => date('Y-m-d 00:00:00', strtotime('-6 days'))]);
$last7 = (int)$stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM feedback WHERE status = 'approved'");
$approvedFeedback = (int)$stmt->fetchColumn();

json_out([
    'ok' => true,
    'downloads' => [
        'total' => $total,
        'today' => $today,
        'last7' => $last7,
    ],
    'feedback' => [
        'approved' => $approvedFeedback,
    ],
    'updated_at' => date('c'),
]);
