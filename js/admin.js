function showToast(message, type = 'info') {
  const container = document.getElementById('toastContainer');
  if (!container) return;
  const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<span class="toast-icon">${icons[type] || 'ℹ️'}</span><span>${escapeHtml(message)}</span>`;
  container.appendChild(toast);
  setTimeout(() => { toast.classList.add('toast-out'); setTimeout(() => toast.remove(), 300); }, 3000);
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

async function checkAuth() {
  const res = await fetch('/api/auth.php?action=me');
  const data = await res.json();
  if (!data.success || data.user.role !== 'admin') {
    window.location.href = '/';
    return null;
  }
  return data.user;
}

function switchAdminTab(tab, btn) {
  document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.admin-tab-content').forEach(c => c.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById(tab + 'Tab').classList.add('active');
  if (tab === 'users') loadUsers();
  if (tab === 'rooms') loadRooms();
  if (tab === 'invite') loadInviteCodes();
  if (tab === 'nickname') loadNicknameRequests();
  if (tab === 'announcements') loadAnnouncements();
  if (tab === 'settings') loadSettings();
}

function closeAdminModal(e) {
  if (e && e.target !== document.getElementById('adminModal')) return;
  document.getElementById('adminModal').classList.add('hidden');
}

async function api(action, data = {}) {
  const form = new URLSearchParams();
  Object.keys(data).forEach(k => form.append(k, data[k]));
  const res = await fetch(`/api/admin.php?action=${action}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: form
  });
  return res.json();
}

async function loadUsers() {
  const data = await api('get_users');
  if (!data.success) return;
  const tbody = document.getElementById('userTableBody');
  tbody.innerHTML = '';
  data.users.forEach(u => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${u.id}</td>
      <td>${escapeHtml(u.username)}</td>
      <td>${escapeHtml(u.nickname)}</td>
      <td>${u.role === 'admin' ? '管理员' : '用户'}</td>
      <td>${u.status === 'banned' ? '<span style="color:var(--danger)">已禁用</span>' : '<span style="color:var(--success)">正常</span>'}</td>
      <td>${u.created_at ? new Date(u.created_at.replace(' ', 'T')).toLocaleString('zh-CN') : '-'}</td>
      <td>
        ${u.id !== 1 ? `
          <button class="btn btn-sm btn-outline" onclick="toggleBan(${u.id})">${u.status === 'banned' ? '解禁' : '禁用'}</button>
          <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${u.id})">删除</button>
        ` : '-'}
      </td>
    `;
    tbody.appendChild(tr);
  });
}

async function toggleBan(id) {
  if (!confirm('确定切换用户状态？')) return;
  const data = await api('toggle_ban', { id });
  showToast(data.message, data.success ? 'success' : 'error');
  loadUsers();
}

async function deleteUser(id) {
  if (!confirm('确定删除此用户？此操作不可撤销！')) return;
  const data = await api('delete_user', { id });
  showToast(data.message, data.success ? 'success' : 'error');
  loadUsers();
}

function showCreateUser() {
  const modal = document.getElementById('adminModalContent');
  modal.innerHTML = `
    <h3>创建用户</h3>
    <div class="form-group"><label>用户名</label><input type="text" id="cuUsername" minlength="3" placeholder="3-20个字符"></div>
    <div class="form-group"><label>密码</label><input type="text" id="cuPassword" minlength="6" placeholder="至少6位"></div>
    <div class="form-group"><label>昵称</label><input type="text" id="cuNickname" placeholder="显示名称"></div>
    <div class="form-group"><label>角色</label><select id="cuRole"><option value="user">用户</option><option value="admin">管理员</option></select></div>
    <button class="btn btn-primary btn-full" onclick="createUser()">创建</button>
    <p id="cuMsg" class="error-msg"></p>
  `;
  document.getElementById('adminModal').classList.remove('hidden');
}

async function createUser() {
  const data = await api('create_user', {
    username: document.getElementById('cuUsername').value,
    password: document.getElementById('cuPassword').value,
    nickname: document.getElementById('cuNickname').value,
    role: document.getElementById('cuRole').value
  });
  document.getElementById('cuMsg').textContent = data.message;
  if (data.success) { loadUsers(); setTimeout(closeAdminModal, 1000); }
}

async function loadRooms() {
  const data = await api('get_rooms');
  if (!data.success) return;
  const tbody = document.getElementById('roomTableBody');
  tbody.innerHTML = '';
  data.rooms.forEach(r => {
    let statusText = r.status === 'active' ? '<span style="color:var(--success)">正常</span>' : 
                     (r.pending == 1 ? '<span style="color:var(--warning)">待审核</span>' : '<span style="color:var(--danger)">已删除</span>');
    const hasPassword = r.room_password_hash ? true : false;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.id}</td>
      <td>${escapeHtml(r.name)}</td>
      <td>${r.type === 'anonymous' ? '匿名' : '公开'}</td>
      <td>${escapeHtml(r.creator_name || '-')}</td>
      <td>${statusText}</td>
      <td>${hasPassword ? '<span style="color:var(--success)">🔒 已设置</span>' : '<span style="color:var(--text-secondary)">无</span>'}</td>
      <td>
        ${r.pending == 1 ? `
          <button class="btn btn-sm btn-outline" onclick="approveRoom(${r.id})">批准</button>
          <button class="btn btn-sm btn-outline-danger" onclick="rejectRoom(${r.id})">拒绝</button>
        ` : `
          <button class="btn btn-sm btn-outline" onclick="showRoomPasswordModal(${r.id})">${hasPassword ? '修改密码' : '设置密码'}</button>
          <button class="btn btn-sm btn-outline-danger" onclick="deleteRoom(${r.id})">删除</button>
        `}
      </td>
    `;
    tbody.appendChild(tr);
  });
}

async function approveRoom(id) {
  const data = await api('approve_room', { id });
  showToast(data.message, data.success ? 'success' : 'error');
  loadRooms();
}

async function rejectRoom(id) {
  const data = await api('reject_room', { id });
  showToast(data.message, data.success ? 'success' : 'error');
  loadRooms();
}

async function deleteRoom(id) {
  if (!confirm('确定删除此房间？')) return;
  const data = await api('delete_room', { id });
  showToast(data.message, data.success ? 'success' : 'error');
  loadRooms();
}

function showRoomPasswordModal(roomId) {
  const modal = document.getElementById('adminModalContent');
  modal.innerHTML = `
    <h3>🔒 房间密码设置</h3>
    <div class="form-group">
      <label>新密码（至少4位）</label>
      <input type="text" id="adminRoomPassword" minlength="4" placeholder="输入新密码">
    </div>
    <button class="btn btn-primary btn-full" onclick="setRoomPassword(${roomId})">保存密码</button>
    <button class="btn btn-outline btn-full" onclick="clearRoomPassword(${roomId})" style="margin-top:8px">清除密码</button>
    <p id="adminRoomPasswordMsg" class="error-msg"></p>
  `;
  document.getElementById('adminModal').classList.remove('hidden');
}

async function setRoomPassword(roomId) {
  const password = document.getElementById('adminRoomPassword').value.trim();
  if (!password || password.length < 4) { document.getElementById('adminRoomPasswordMsg').textContent = '密码至少4位'; return; }
  const data = await api('set_password', { room_id: roomId, password });
  document.getElementById('adminRoomPasswordMsg').textContent = data.message;
  if (data.success) { loadRooms(); setTimeout(closeAdminModal, 1000); }
}

async function clearRoomPassword(roomId) {
  if (!confirm('确定清除房间密码？')) return;
  const data = await api('set_password', { room_id: roomId, clear: '1' });
  showToast(data.message, data.success ? 'success' : 'error');
  if (data.success) loadRooms();
}

async function loadInviteCodes() {
  const data = await api('get_invite_codes');
  if (!data.success) return;
  const tbody = document.getElementById('inviteTableBody');
  tbody.innerHTML = '';
  data.codes.forEach(c => {
    const expired = c.expires_at && new Date(c.expires_at.replace(' ', 'T')) < new Date();
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><code>${c.code}</code></td>
      <td>${c.used_count}/${c.max_uses === -1 ? '∞' : c.max_uses}</td>
      <td>${c.max_uses === -1 ? '无限' : c.max_uses}</td>
      <td>${c.expires_at ? (expired ? '<span style="color:var(--danger)">已过期</span>' : new Date(c.expires_at.replace(' ', 'T')).toLocaleDateString('zh-CN')) : '永久'}</td>
      <td>${escapeHtml(c.creator_name || '-')}</td>
      <td>${new Date(c.created_at.replace(' ', 'T')).toLocaleString('zh-CN')}</td>
      <td><button class="btn btn-sm btn-outline-danger" onclick="deleteInvite(${c.id})">删除</button></td>
    `;
    tbody.appendChild(tr);
  });
}

async function deleteInvite(id) {
  const data = await api('delete_invite', { id });
  showToast(data.message, data.success ? 'success' : 'error');
  loadInviteCodes();
}

function showCreateInvite() {
  const modal = document.getElementById('adminModalContent');
  modal.innerHTML = `
    <h3>创建邀请码</h3>
    <div class="form-group"><label>使用次数上限</label><select id="inviteMaxUses"><option value="1">单次使用</option><option value="-1">无限使用</option><option value="5">5次</option><option value="10">10次</option></select></div>
    <div class="form-group"><label>有效期（天，0为永久）</label><input type="number" id="inviteDuration" value="0" min="0" max="365"></div>
    <button class="btn btn-primary btn-full" onclick="createInvite()">生成</button>
    <p id="inviteMsg" class="error-msg"></p>
    <div id="inviteResult" style="margin-top:12px;word-break:break-all"></div>
  `;
  document.getElementById('adminModal').classList.remove('hidden');
}

async function createInvite() {
  const data = await api('create_invite', {
    maxUses: document.getElementById('inviteMaxUses').value,
    duration: document.getElementById('inviteDuration').value
  });
  if (data.success) {
    document.getElementById('inviteResult').innerHTML = `<strong>邀请码：</strong><code style="font-size:18px;color:var(--accent)">${data.code}</code>`;
    loadInviteCodes();
  } else {
    document.getElementById('inviteMsg').textContent = data.message;
  }
}

function showBatchInvite() {
  const modal = document.getElementById('adminModalContent');
  modal.innerHTML = `
    <h3>批量创建邀请码</h3>
    <div class="form-group"><label>数量（最多100个）</label><input type="number" id="batchCount" value="10" min="1" max="100"></div>
    <div class="form-group"><label>使用次数上限</label><select id="batchMaxUses"><option value="1">单次使用</option><option value="-1">无限使用</option><option value="5">5次</option><option value="10">10次</option></select></div>
    <div class="form-group"><label>有效期（天，0为永久）</label><input type="number" id="batchDuration" value="0" min="0" max="365"></div>
    <button class="btn btn-primary btn-full" onclick="batchInvite()">批量生成</button>
    <p id="batchMsg" class="error-msg"></p>
    <div id="batchResult" style="margin-top:12px"></div>
  `;
  document.getElementById('adminModal').classList.remove('hidden');
}

async function batchInvite() {
  const data = await api('batch_invite', {
    count: document.getElementById('batchCount').value,
    maxUses: document.getElementById('batchMaxUses').value,
    duration: document.getElementById('batchDuration').value
  });
  document.getElementById('batchMsg').textContent = data.message;
  if (data.success) {
    const list = data.codes.map(c => `<code style="display:block;padding:4px 0">${c}</code>`).join('');
    document.getElementById('batchResult').innerHTML = list;
    loadInviteCodes();
  }
}

async function loadNicknameRequests() {
  const data = await api('get_nickname_requests');
  if (!data.success) return;
  const tbody = document.getElementById('nicknameTableBody');
  tbody.innerHTML = '';
  if (data.requests.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-secondary)">暂无待审核请求</td></tr>';
    return;
  }
  data.requests.forEach(r => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.id}</td>
      <td>${escapeHtml(r.username)}</td>
      <td>${escapeHtml(r.old_nickname)}</td>
      <td>${escapeHtml(r.new_nickname)}</td>
      <td>${new Date(r.created_at.replace(' ', 'T')).toLocaleString('zh-CN')}</td>
      <td>
        <button class="btn btn-sm btn-outline" onclick="approveNickname(${r.id})">批准</button>
        <button class="btn btn-sm btn-outline-danger" onclick="rejectNickname(${r.id})">拒绝</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

async function approveNickname(id) {
  const data = await api('approve_nickname', { id });
  showToast(data.message, data.success ? 'success' : 'error');
  loadNicknameRequests();
}

async function rejectNickname(id) {
  const data = await api('reject_nickname', { id });
  showToast(data.message, data.success ? 'success' : 'error');
  loadNicknameRequests();
}

async function loadAnnouncements() {
  const res = await fetch('/api/announcements.php');
  const data = await res.json();
  if (!data.success) return;
  const tbody = document.getElementById('announcementTableBody');
  tbody.innerHTML = '';
  if (data.announcements.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-secondary)">暂无公告</td></tr>';
    return;
  }
  data.announcements.forEach(a => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${a.id}</td>
      <td>${escapeHtml(a.title)}</td>
      <td>${escapeHtml(a.content)}</td>
      <td>${a.priority > 0 ? '⭐ 重要' : '普通'}</td>
      <td>${escapeHtml(a.creator_name || '-')}</td>
      <td>${new Date(a.created_at.replace(' ', 'T')).toLocaleString('zh-CN')}</td>
      <td><button class="btn btn-sm btn-outline-danger" onclick="deleteAnnouncement(${a.id})">删除</button></td>
    `;
    tbody.appendChild(tr);
  });
}

function showCreateAnnouncement() {
  const modal = document.getElementById('adminModalContent');
  modal.innerHTML = `
    <h3>📢 发布公告</h3>
    <div class="form-group"><label>标题</label><input type="text" id="annTitle" maxlength="100" placeholder="公告标题"></div>
    <div class="form-group"><label>内容</label><textarea id="annContent" maxlength="500" placeholder="公告内容" style="width:100%;padding:12px;background:var(--bg-input);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text-primary);font-size:14px;resize:vertical;min-height:80px"></textarea></div>
    <div class="form-group"><label>优先级</label><select id="annPriority"><option value="0">普通</option><option value="1">⭐ 重要</option></select></div>
    <button class="btn btn-primary btn-full" onclick="createAnnouncement()">发布</button>
    <p id="annMsg" class="error-msg"></p>
  `;
  document.getElementById('adminModal').classList.remove('hidden');
}

async function createAnnouncement() {
  const title = document.getElementById('annTitle').value.trim();
  const content = document.getElementById('annContent').value.trim();
  const priority = document.getElementById('annPriority').value;
  if (!title || !content) { document.getElementById('annMsg').textContent = '请填写标题和内容'; return; }
  const data = await api('create_announcement', { title, content, priority });
  document.getElementById('annMsg').textContent = data.message;
  if (data.success) { loadAnnouncements(); setTimeout(closeAdminModal, 1000); }
}

async function deleteAnnouncement(id) {
  if (!confirm('确定删除此公告？')) return;
  const data = await api('delete_announcement', { id });
  showToast(data.message, data.success ? 'success' : 'error');
  loadAnnouncements();
}

async function loadSettings() {
  const data = await api('get_settings');
  if (!data.success) return;
  document.getElementById('inviteOnly').checked = data.settings.invite_only === 'true';
}

async function saveSettings(e) {
  e.preventDefault();
  const data = await api('save_settings', {
    inviteOnly: document.getElementById('inviteOnly').checked ? 'true' : 'false'
  });
  document.getElementById('settingsMsg').textContent = data.message;
}

async function handleLogout() {
  await fetch('/api/auth.php?action=logout', { method: 'POST' });
  window.location.href = '/login.php';
}

checkAuth().then(user => {
  if (user) loadUsers();
});