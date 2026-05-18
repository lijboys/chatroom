<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>聊天室管理后台</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="admin-body">
<div class="admin-container">
  <div class="admin-header">
    <h1>⚙️ 管理后台</h1>
    <div style="display:flex;gap:8px">
      <button class="btn btn-sm btn-outline" onclick="window.location.href='/'">← 返回聊天室</button>
      <button class="btn btn-sm btn-outline" onclick="handleLogout()">🚪 退出</button>
    </div>
  </div>
  <div class="admin-tabs">
    <button class="admin-tab active" onclick="switchAdminTab('users',this)">👥 用户管理</button>
    <button class="admin-tab" onclick="switchAdminTab('rooms',this)">🏠 房间管理</button>
    <button class="admin-tab" onclick="switchAdminTab('invite',this)">🔑 邀请码</button>
    <button class="admin-tab" onclick="switchAdminTab('nickname',this)">✏️ 昵称审核</button>
    <button class="admin-tab" onclick="switchAdminTab('announcements',this)">📢 公告管理</button>
    <button class="admin-tab" onclick="switchAdminTab('settings',this)">⚙️ 系统设置</button>
  </div>
  <div class="admin-content">
    <div id="usersTab" class="admin-tab-content active">
      <div class="admin-toolbar">
        <button class="btn btn-primary btn-sm" onclick="showCreateUser()">创建用户</button>
      </div>
      <table class="admin-table">
        <thead><tr><th>ID</th><th>用户名</th><th>昵称</th><th>角色</th><th>状态</th><th>注册时间</th><th>操作</th></tr></thead>
        <tbody id="userTableBody"></tbody>
      </table>
    </div>
    <div id="roomsTab" class="admin-tab-content">
      <table class="admin-table">
        <thead><tr><th>ID</th><th>名称</th><th>类型</th><th>创建者</th><th>状态</th><th>密码</th><th>操作</th></tr></thead>
        <tbody id="roomTableBody"></tbody>
      </table>
    </div>
    <div id="inviteTab" class="admin-tab-content">
      <div class="admin-toolbar">
        <button class="btn btn-primary btn-sm" onclick="showCreateInvite()">创建邀请码</button>
        <button class="btn btn-primary btn-sm" onclick="showBatchInvite()">批量创建</button>
      </div>
      <table class="admin-table">
        <thead><tr><th>邀请码</th><th>使用次数</th><th>最大次数</th><th>有效期</th><th>创建者</th><th>创建时间</th><th>操作</th></tr></thead>
        <tbody id="inviteTableBody"></tbody>
      </table>
    </div>
    <div id="nicknameTab" class="admin-tab-content">
      <table class="admin-table">
        <thead><tr><th>ID</th><th>用户</th><th>旧昵称</th><th>新昵称</th><th>申请时间</th><th>操作</th></tr></thead>
        <tbody id="nicknameTableBody"></tbody>
      </table>
    </div>
    <div id="announcementsTab" class="admin-tab-content">
      <div class="admin-toolbar">
        <button class="btn btn-primary btn-sm" onclick="showCreateAnnouncement()">发布公告</button>
      </div>
      <table class="admin-table">
        <thead><tr><th>ID</th><th>标题</th><th>内容</th><th>优先级</th><th>发布者</th><th>时间</th><th>操作</th></tr></thead>
        <tbody id="announcementTableBody"></tbody>
      </table>
    </div>
    <div id="settingsTab" class="admin-tab-content">
      <form class="settings-form" onsubmit="return saveSettings(event)">
        <div class="form-group">
          <label>🌐 邀请码注册模式</label>
          <label class="toggle">
            <input type="checkbox" id="inviteOnly">
            <span class="toggle-slider"></span>
          </label>
        </div>
        <button type="submit" class="btn btn-primary">保存设置</button>
        <p id="settingsMsg"></p>
      </form>
    </div>
  </div>
</div>

<div id="adminModal" class="modal-overlay hidden" onclick="closeAdminModal(event)">
  <div class="modal" id="adminModalContent"></div>
</div>

<div id="toastContainer" class="toast-container"></div>

<script src="js/theme.js"></script>
<script src="js/admin.js"></script>
</body>
</html>
