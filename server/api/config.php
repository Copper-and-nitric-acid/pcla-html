<?php
/**
 * PCLA API 默认配置
 *
 * 不要把真实口令写进这个文件！在 api/ 下新建 config.local.php：
 *
 *   <?php
 *   return [
 *       'admin_token' => '你的后台口令',
 *       'ip_salt' => '一串随机字符',
 *   ];
 *
 * config.local.php 已被 .gitignore 忽略。也可以用环境变量
 * PCLA_ADMIN_TOKEN / PCLA_IP_SALT / PCLA_DB_PATH 覆盖。
 */

declare(strict_types=1);

$config = [
    'admin_token' => getenv('PCLA_ADMIN_TOKEN') ?: 'change-me-please',
    'ip_salt' => getenv('PCLA_IP_SALT') ?: 'change-me-salt',
    'db_path' => getenv('PCLA_DB_PATH') ?: __DIR__ . '/../storage/pcla.sqlite',

    // 同一个 IP 在这个时间窗口内重复点击，只记一次下载
    'download_dedupe_seconds' => 20,

    // 反馈频率限制：10 分钟内最多 3 条
    'feedback_window_minutes' => 10,
    'feedback_max' => 3,

    // 允许跨域调用 API 的站点（前后端同域时可以留空）
    'allowed_origins' => [
        'https://pcla.localhost.cc',
        'https://pcla.pntr.dev',
        'https://copper-and-nitric-acid.github.io',
        'http://localhost:8123',
        'http://127.0.0.1:8123',
    ],

    // 下载中转目标（白名单，避免开放重定向）
    'download_targets' => [
        'github' => 'https://github.com/Copper-and-nitric/plain-craft-launcher-for-android',
        'gitee' => 'https://gitee.com/copper-and-nitric-acid/plain-craft-launcher-for-android/releases',
    ],
];

$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $config = array_replace($config, $local);
    }
}

define('APP_NAME', 'PCLA API');
define('APP_TIMEZONE', 'Asia/Shanghai');
define('ADMIN_TOKEN', (string)$config['admin_token']);
define('IP_SALT', (string)$config['ip_salt']);
define('DB_PATH', (string)$config['db_path']);
define('DOWNLOAD_DEDUPE_SECONDS', (int)$config['download_dedupe_seconds']);
define('FEEDBACK_WINDOW_MINUTES', (int)$config['feedback_window_minutes']);
define('FEEDBACK_MAX', (int)$config['feedback_max']);
define('ALLOWED_ORIGINS', (array)$config['allowed_origins']);
define('DOWNLOAD_TARGETS', (array)$config['download_targets']);
