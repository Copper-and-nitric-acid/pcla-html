# PCLA 官网

PCLA（Plain Craft Launcher for Android）的官方产品与下载页面。

- 纯静态站点：HTML + CSS + TypeScript（esbuild 打包）
- 页面内容：产品介绍、设备体检说明、界面预览、下载、更新日志、常见问题
- 下载按钮直接跳转到组织仓库：
  https://github.com/Copper-and-nitric/plain-craft-launcher-for-android

## 本地开发

```bash
npm install
npm run dev        # 监听并在 http://127.0.0.1:8000 提供页面
npm run build      # 输出 assets/app.js
npm run typecheck  # TypeScript 类型检查
```

`assets/app.js` 是构建产物，已随仓库提交，部署时不需要再构建。

## 部署

推送到 `main` 后，由 GitHub Pages 直接发布仓库根目录。

## PHP 后端

仓库里的 `server/` 是一套可直接部署的 PHP + SQLite 小后端：

- 下载中转计数（`/api/download.php`）
- 下载 / 反馈统计（`/api/stats.php`）
- 留言反馈与审核（`/api/feedback.php`、`/api/admin.php`）
- 无需 MySQL，首次请求自动建表

GitHub Pages 只能托管静态文件，跑不了 PHP；需要把 `server/api/` 与 `server/storage/`
上传到支持 PHP 的主机。部署步骤见 [server/README.md](server/README.md)。

## 说明

PCLA 是第三方、非官方项目，与 Mojang Studios、Microsoft 及 PCL 官方无关。
Minecraft 是 Mojang Studios 的商标。
