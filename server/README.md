# PCLA PHP 后端

一个可以直接丢到 PHP 空间就能跑的小后端，不需要 MySQL：

- 下载中转与计数
- 下载 / 反馈统计
- 留言反馈提交、审核、删除
- 后台管理页
- SQLite 自动建库，无需手动导入

> 静态站（GitHub Pages）不能运行 PHP。这一套需要部署到支持 PHP 的主机，
> 再把前端下载按钮和表单接到对应地址。

## 环境要求

- PHP 8.0+
- 扩展：`pdo_sqlite`、`sqlite3`、`mbstring`、`json`
- `storage/` 目录可写（建议 775）

## 目录结构

```
server/
├── api/
│   ├── _bootstrap.php   # 公共入口：CORS / 安全头 / 异常
│   ├── config.php       # 管理口令、IP 盐、下载地址、跨域白名单
│   ├── db.php           # SQLite 连接与自动建表
│   ├── helpers.php      # JSON、清理、管理员校验
│   ├── health.php       # 健康检查
│   ├── stats.php        # 公开统计
│   ├── download.php     # 下载计数 + 302 跳转
│   ├── feedback.php     # 反馈提交 / 管理接口
│   └── admin.php        # 后台审核页面
├── storage/             # SQLite 数据库（需要可写）
├── schema.sql           # 表结构（自动执行）
├── nginx.example.conf
└── README.md
```

## 部署

1. 把 `api/` 和 `storage/` 上传到网站根目录（两者保持同级）。
2. 复制 `api/config.local.example.php` 为 `api/config.local.php`，填写：
   - `admin_token`：后台口令
   - `ip_salt`：一串随机字符
   - `allowed_origins`：你的前端域名（可选）

   `config.local.php` 已被 gitignore，不会被提交。
3. 给 `storage/` 写权限：
   ```bash
   chmod 775 storage
   ```
4. 打开健康检查：
   ```
   https://你的域名/api/health.php
   ```
   看到 `"ok": true` 就说明 PHP、SQLite、目录权限都正常。
5. 后台：
   ```
   https://你的域名/api/admin.php
   ```

如果使用 Apache，`api/.htaccess` 与 `storage/.htaccess` 已经配好；
Nginx 参考 `nginx.example.conf`。

## API

| 方法 | 地址 | 说明 |
| --- | --- | --- |
| GET | `/api/health.php` | 健康检查 |
| GET | `/api/stats.php` | 下载总数、今日、近 7 天、反馈数 |
| GET | `/api/download.php?channel=github` | 记录下载并 302 到 GitHub |
| GET | `/api/download.php?channel=gitee` | 记录下载并 302 到 Gitee |
| GET | `/api/download.php?channel=github&format=json` | 只记录并返回 JSON，不跳转 |
| POST | `/api/feedback.php` | 提交反馈 |
| GET | `/api/feedback.php` | 查看反馈，需要 `X-Admin-Token` |
| GET | `/api/admin.php` | 浏览器后台 |

提交反馈：

```bash
curl -X POST https://你的域名/api/feedback.php \
  -H 'Content-Type: application/json' \
  -d '{"name":"Steve","contact":"","message":"启动 1.20.1 时卡在加载界面","website":""}'
```

成功返回 `201`：

```json
{"ok":true,"id":1,"message":"留言已提交，审核后显示"}
```

查看待审核反馈：

```bash
curl https://你的域名/api/feedback.php?status=pending \
  -H 'X-Admin-Token: 你的管理口令'
```

## 前端怎么接

把下载按钮的地址从 GitHub 改成中转接口，就能统计点击：

```html
<a href="/api/download.php?channel=github">下载 Android 版</a>
```

显示下载数：

```js
fetch('/api/stats.php')
  .then(r => r.json())
  .then(({ downloads }) => {
    document.querySelector('#download-count').textContent = downloads.total;
  });
```

反馈表单：

```html
<form id="feedback-form">
  <input name="name" maxlength="24" required placeholder="昵称" />
  <input name="contact" maxlength="120" placeholder="联系方式（可选）" />
  <textarea name="message" maxlength="1000" required placeholder="遇到了什么问题？"></textarea>
  <input name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true" />
  <button type="submit">提交</button>
</form>
<script>
document.querySelector('#feedback-form').addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(event.currentTarget));
  const res = await fetch('/api/feedback.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  const json = await res.json();
  alert(json.ok ? json.message : json.error);
  if (json.ok) event.currentTarget.reset();
});
</script>
```

## 安全与限制

- 下载地址是白名单，不能通过参数跳转到任意网站。
- IP 只存 HMAC-SHA256 哈希，不保存明文。
- 反馈：蜜罐字段 + 同 IP 10 分钟最多 3 条 + 长度限制 + `strip_tags`。
- 管理接口需要 `X-Admin-Token`；后台页用会话 + CSRF。
- 默认口令是 `change-me-please`，后台会拒绝登录，部署前必须修改。
- 如果套了 Cloudflare，`REMOTE_ADDR` 可能是 Cloudflare 的 IP；需要按主机环境改用 `CF-Connecting-IP`，不要直接信任任意 `X-Forwarded-For`。
- SQLite 单机足够用；每天备份一次 `storage/pcla.sqlite` 即可。

## 本地测试

```bash
php -S 127.0.0.1:8090 -t server/api

curl http://127.0.0.1:8090/health.php
curl http://127.0.0.1:8090/stats.php
curl "http://127.0.0.1:8090/download.php?channel=github&format=json"
```

> `php -S` 不会读取 `.htaccess`，本地测试时不要用它的地址对外提供 `storage/`。
