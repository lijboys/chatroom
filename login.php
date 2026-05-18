<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>聊天室 - 登录</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-body">
<div class="auth-container">
  <div class="auth-card">
    <div class="auth-header">
      <h1>💬 聊天室</h1>
      <p id="authTitle">欢迎回来</p>
    </div>
    <div class="auth-tabs">
      <button class="auth-tab active" onclick="switchTab('login')">登录</button>
      <button class="auth-tab" onclick="switchTab('register')">注册</button>
    </div>
    <form id="loginForm" class="auth-form" onsubmit="return handleLogin(event)">
      <div class="form-group">
        <label>用户名</label>
        <input type="text" id="loginUsername" required placeholder="输入用户名" autocomplete="username">
      </div>
      <div class="form-group">
        <label>密码</label>
        <input type="password" id="loginPassword" required placeholder="输入密码" autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary btn-full">登录</button>
      <p id="loginError" class="error-msg"></p>
    </form>
    <form id="registerForm" class="auth-form hidden" onsubmit="return handleRegister(event)">
      <div class="form-group">
        <label>用户名</label>
        <input type="text" id="regUsername" required minlength="3" maxlength="20" placeholder="3-20个字符">
      </div>
      <div class="form-group">
        <label>密码</label>
        <input type="password" id="regPassword" required minlength="6" placeholder="至少6位密码">
      </div>
      <div class="form-group">
        <label>昵称</label>
        <input type="text" id="regNickname" required maxlength="20" placeholder="你的显示名称">
        <small class="form-hint">昵称修改需管理员审核，请谨慎填写</small>
      </div>
      <div class="form-group" id="inviteGroup" style="display:none">
        <label>邀请码</label>
        <input type="text" id="regInvite" placeholder="输入邀请码">
      </div>
      <button type="submit" class="btn btn-primary btn-full">注册</button>
      <p id="regError" class="error-msg"></p>
    </form>
  </div>
</div>
<script src="js/theme.js"></script>
<script>
let currentTab = 'login';

async function loadConfig() {
  const res = await fetch('/api/config.php');
  const data = await res.json();
  if (data.success) {
    if (data.inviteOnly) {
      document.getElementById('inviteGroup').style.display = 'block';
    }
  }
}

function switchTab(tab) {
  currentTab = tab;
  document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
  document.querySelector(`.auth-tab[onclick*="${tab}"]`).classList.add('active');
  document.getElementById('loginForm').classList.toggle('hidden', tab !== 'login');
  document.getElementById('registerForm').classList.toggle('hidden', tab !== 'register');
  document.getElementById('authTitle').textContent = tab === 'login' ? '欢迎回来' : '创建新账户';
  document.getElementById('loginError').textContent = '';
  document.getElementById('regError').textContent = '';
}

async function handleLogin(e) {
  e.preventDefault();
  const btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = '登录中...';

  const form = new URLSearchParams();
  form.append('username', document.getElementById('loginUsername').value);
  form.append('password', document.getElementById('loginPassword').value);

  const res = await fetch('/api/auth.php?action=login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: form
  });
  const data = await res.json();
  btn.disabled = false;
  btn.textContent = '登录';

  if (data.success) {
    window.location.href = '/';
  } else {
    document.getElementById('loginError').textContent = data.message;
  }
}

async function handleRegister(e) {
  e.preventDefault();
  const btn = e.target.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.textContent = '注册中...';

  const form = new URLSearchParams();
  form.append('username', document.getElementById('regUsername').value);
  form.append('password', document.getElementById('regPassword').value);
  form.append('nickname', document.getElementById('regNickname').value);
  form.append('inviteCode', document.getElementById('regInvite').value);

  const res = await fetch('/api/auth.php?action=register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: form
  });
  const data = await res.json();
  btn.disabled = false;
  btn.textContent = '注册';

  if (data.success) {
    window.location.href = '/';
  } else {
    document.getElementById('regError').textContent = data.message;
  }
}

loadConfig();
</script>
</body>
</html>
