<?php
/**
 * 后台管理页 /api/admin.php
 * 用 config.php 里的 ADMIN_TOKEN 登录，审核 / 删除反馈。
 */

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; form-action \'self\'; base-uri \'none\'; frame-ancestors \'none\'');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
session_name('PCLASESSID');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $https,
]);
session_start();

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect_self(string $query = ''): void
{
    header('Location: admin.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

$setupRequired = ADMIN_TOKEN === 'change-me-please';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$setupRequired) {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'login') {
        $token = (string)($_POST['token'] ?? '');
        if ($token !== '' && hash_equals(ADMIN_TOKEN, $token)) {
            session_regenerate_id(true);
            $_SESSION['pcla_admin'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(24));
            redirect_self();
        }
        usleep(300000);
        $loginError = '口令不对';
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        redirect_self();
    } elseif (in_array($action, ['approve', 'reject', 'delete'], true) && !empty($_SESSION['pcla_admin'])) {
        $csrf = (string)($_POST['csrf'] ?? '');
        if ($csrf === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $csrf)) {
            http_response_code(400);
            exit('CSRF token mismatch');
        }
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db = db();
            if ($action === 'delete') {
                $stmt = $db->prepare('DELETE FROM feedback WHERE id = :id');
                $stmt->execute([':id' => $id]);
            } else {
                $status = $action === 'approve' ? 'approved' : 'rejected';
                $stmt = $db->prepare('UPDATE feedback SET status = :status WHERE id = :id');
                $stmt->execute([':status' => $status, ':id' => $id]);
            }
        }
        redirect_self('done=1');
    }
}

$loggedIn = !empty($_SESSION['pcla_admin']);
$csrf = (string)($_SESSION['csrf'] ?? '');
if ($loggedIn && $csrf === '') {
    $csrf = bin2hex(random_bytes(24));
    $_SESSION['csrf'] = $csrf;
}

$db = db();
$totalDownloads = (int)$db->query('SELECT COUNT(*) FROM downloads')->fetchColumn();
$todayDownloads = (int)$db->query(
    "SELECT COUNT(*) FROM downloads WHERE created_at >= '" . date('Y-m-d 00:00:00') . "'"
)->fetchColumn();
$pending = (int)$db->query("SELECT COUNT(*) FROM feedback WHERE status = 'pending'")->fetchColumn();
$approved = (int)$db->query("SELECT COUNT(*) FROM feedback WHERE status = 'approved'")->fetchColumn();

$rows = $loggedIn
    ? $db->query('SELECT id, name, contact, message, status, created_at FROM feedback ORDER BY id DESC LIMIT 200')->fetchAll()
    : [];

?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<title>PCLA 后台</title>
<style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #08090b; color: #eaf2ff; font: 14px/1.6 "PingFang SC", "Microsoft YaHei", system-ui, sans-serif; }
    .wrap { width: min(1080px, calc(100% - 32px)); margin: 40px auto 80px; }
    h1 { margin: 0; font-size: 26px; letter-spacing: -.03em; }
    .muted { color: #8a9ab5; font-size: 12px; }
    .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
    .card { padding: 20px; border: 1px solid rgba(126,170,255,.14); border-radius: 14px; background: #0e1420; }
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 16px 0; }
    .stat { padding: 14px; border: 1px solid rgba(126,170,255,.12); border-radius: 12px; background: #0b111b; }
    .stat b { display: block; font-size: 24px; color: #c8f56d; }
    .stat small { color: #8a9ab5; }
    label { display: block; margin-bottom: 6px; color: #8a9ab5; font-size: 12px; }
    input[type=password] { width: 100%; padding: 11px 12px; border: 1px solid rgba(126,170,255,.2); border-radius: 9px; background: #080d15; color: #fff; }
    button { padding: 8px 12px; border: 1px solid rgba(126,170,255,.22); border-radius: 8px; background: #142033; color: #eaf2ff; cursor: pointer; font: inherit; }
    button:hover { background: #1a2a42; }
    button.primary { background: #c8f56d; border-color: #c8f56d; color: #0b1206; font-weight: 700; }
    button.danger { border-color: rgba(255,107,107,.4); color: #ff9a9a; }
    .login { max-width: 380px; margin: 12vh auto; }
    .error { color: #ff9a9a; font-size: 12px; }
    .warn { padding: 12px 14px; border: 1px solid rgba(255,196,107,.3); border-radius: 10px; background: rgba(255,196,107,.08); color: #ffc46b; font-size: 13px; }
    .msg { padding: 13px 15px; border-bottom: 1px solid rgba(126,170,255,.1); }
    .msg:last-child { border-bottom: 0; }
    .msg-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px; }
    .msg-head b { font-size: 13px; }
    .msg-head time { color: #6f84a4; font-size: 11px; margin-left: auto; }
    .msg-body { margin: 6px 0; color: #c7d5ea; white-space: pre-wrap; }
    .msg-contact { color: #7fa6d8; font-size: 11px; }
    .status { padding: 2px 7px; border-radius: 5px; font-size: 10px; }
    .status.pending { background: rgba(255,196,107,.12); color: #ffc46b; }
    .status.approved { background: rgba(111,240,200,.1); color: #6ff0c8; }
    .status.rejected { background: rgba(255,107,107,.1); color: #ff9a9a; }
    .actions { display: flex; gap: 6px; margin-top: 8px; }
    form.inline { display: inline; }
    @media (max-width: 640px) { .stats { grid-template-columns: 1fr 1fr; } .wrap { margin-top: 22px; } }
</style>
</head>
<body>
<div class="wrap">
<?php if ($setupRequired): ?>
    <div class="login">
        <div class="card">
            <h1>还没配置好</h1>
            <p class="warn" style="margin-top:14px">请先创建 <code>api/config.local.php</code>，把 <code>admin_token</code> 改成你自己的口令，再刷新本页。</p>
        </div>
    </div>
<?php elseif (!$loggedIn): ?>
    <div class="login">
        <div class="card">
            <h1>PCLA 后台</h1>
            <p class="muted">输入 config.php 里配置的管理口令。</p>
            <form method="post" style="margin-top:16px">
                <input type="hidden" name="action" value="login" />
                <label for="token">管理口令</label>
                <input id="token" name="token" type="password" autocomplete="current-password" required />
                <?php if (!empty($loginError)): ?><p class="error"><?= h($loginError) ?></p><?php endif; ?>
                <button class="primary" type="submit" style="margin-top:12px;width:100%">登录</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="topbar">
        <div>
            <h1>PCLA 后台</h1>
            <p class="muted">反馈审核 · 下载统计</p>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="logout" />
            <button type="submit">退出</button>
        </form>
    </div>

    <div class="stats">
        <div class="stat"><b><?= $totalDownloads ?></b><small>下载请求总数</small></div>
        <div class="stat"><b><?= $todayDownloads ?></b><small>今日下载</small></div>
        <div class="stat"><b><?= $pending ?></b><small>待审核反馈</small></div>
        <div class="stat"><b><?= $approved ?></b><small>已通过反馈</small></div>
    </div>

    <?php if (isset($_GET['done'])): ?><p class="warn" style="margin-bottom:12px">操作完成。</p><?php endif; ?>

    <div class="card" style="padding:0;overflow:hidden">
        <?php if (!$rows): ?>
            <p class="muted" style="padding:20px">还没有反馈。</p>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <div class="msg">
                <div class="msg-head">
                    <b><?= h($row['name']) ?></b>
                    <span class="status <?= h($row['status']) ?>"><?= h($row['status']) ?></span>
                    <time><?= h($row['created_at']) ?></time>
                </div>
                <?php if ($row['contact'] !== ''): ?>
                    <div class="msg-contact">联系方式：<?= h($row['contact']) ?></div>
                <?php endif; ?>
                <div class="msg-body"><?= h($row['message']) ?></div>
                <div class="actions">
                    <?php if ($row['status'] !== 'approved'): ?>
                        <form class="inline" method="post">
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>" />
                            <input type="hidden" name="action" value="approve" />
                            <button class="primary" type="submit">通过</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($row['status'] !== 'rejected'): ?>
                        <form class="inline" method="post">
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>" />
                            <input type="hidden" name="action" value="reject" />
                            <button type="submit">拒绝</button>
                        </form>
                    <?php endif; ?>
                    <form class="inline" method="post" onsubmit="return confirm('确定删除这条反馈？')">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>" />
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>" />
                        <input type="hidden" name="action" value="delete" />
                        <button class="danger" type="submit">删除</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>
</body>
</html>
