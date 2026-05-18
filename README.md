# 聊天室 - PHP 版

基于 PHP + SQLite 的多人聊天室，支持匿名聊天、房间管理、管理员后台。

## 功能

- 用户注册/登录
- 公开/匿名房间聊天
- 在线用户列表
- 消息撤回
- 表情发送
- 消息搜索
- 系统公告
- 深色/浅色主题切换（跟随系统）
- 管理员后台（用户管理、房间管理、邀请码、昵称审核、公告）
- 邀请码注册模式

## 部署教程

### 1. 上传到虚拟主机

把 `public/` 目录下的 **所有文件和文件夹** 上传到虚拟主机的 `public_html` 目录。

推荐的文件结构：
```
public_html/
├── .htaccess
├── index.php          ← 聊天室主页
├── login.php          ← 登录页
├── admin.php          ← 管理后台
├── api/               ← PHP 后端接口
│   ├── db.php
│   ├── helpers.php
│   ├── auth.php
│   ├── rooms.php
│   ├── messages.php
│   ├── admin.php
│   ├── announcements.php
│   └── config.php
├── css/
│   ├── style.css
│   ├── telegram.css
│   └── saas.css
├── js/
│   ├── theme.js
│   ├── chat.js
│   └── admin.js
├── data/              ← 数据库存放位置（自动创建）
│   └── .htaccess      ← 已配置禁止访问
├── demo.html          ← 主题演示
└── test_theme.html    ← 主题测试
```

### 2. 设置权限

- `data/` 目录需要写入权限（PHP 会在里面自动创建 `chatroom.db`）
  - Linux: `chmod 755 data/`（确保属主是 www 用户）
  - 如果遇到数据库无法创建，尝试 `chmod 777 data/`

### 3. PHP 要求

- PHP 7.4 或更高版本
- 需要启用 SQLite3 扩展（大多数虚拟主机默认开启）
- 不需要 MySQL/MariaDB
- 不需要 Composer 或任何第三方库

### 4. 访问

浏览器访问你的域名：

```
https://你的域名.com/
```

### 5. 管理员账号

首次访问时系统自动创建：

| 账号 | 密码 |
|------|------|
| admin | admin123 |

**建议登录后立即修改密码。**

### 6. 备份数据

数据库文件位于 `data/chatroom.db`，定期下载此文件即可备份所有聊天记录和用户数据。

### 7. 常见问题

**Q: 页面显示 500 错误？**
A: 检查 PHP 版本是否 ≥ 7.4，检查 `data/` 目录是否有写入权限。

**Q: 登录后一直跳转到登录页？**
A: 检查虚拟主机是否支持 Cookie。某些主机需要设置 `session.save_path`。

**Q: 消息发送后看不到？**
A: 检查 `data/` 目录权限，PHP 需要写入数据库文件。

**Q: 如何修改站点名称？**
A: 编辑 `index.php` 中的标题文字。

**Q: 如何关闭注册？**
A: 登录管理后台 → 系统设置 → 开启邀请码注册模式。

## 技术栈

- **后端**: PHP 7.4+ / SQLite 3
- **前端**: 原生 JavaScript (ES6+)
- **通信**: AJAX 轮询（每2秒）
- **样式**: CSS 自定义属性（主题变量）
- **数据库**: SQLite（单文件，无需配置）

## 开发

本项目纯前端 + PHP，无需构建工具。直接在本地 PHP 开发服务器运行即可：

```bash
cd public/
php -S localhost:8000
```

访问 http://localhost:8000

## 升级实时通信

当前使用 AJAX 轮询（最长2秒延迟），如需真正实时可集成 Pusher：
1. 注册 https://pusher.com （免费额度）
2. PHP 端发消息时调用 Pusher API
3. 前端 JS 订阅频道接收实时推送

## 许可

MIT
