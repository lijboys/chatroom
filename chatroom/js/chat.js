let currentRoomId = null;
let currentRoomType = null;
let user = null;
let rooms = [];
let lastMsgId = 0;
let lastMsgDate = null;
let pollTimer = null;
let unreadCounts = {};
let notificationSound = null;
let loadedMessageIds = new Set();
let replyToMessage = null;
let pollInFlight = false;
let pollQueued = false;

try {
  notificationSound = new (window.AudioContext || window.webkitAudioContext)();
} catch(e) { notificationSound = null; }

function playNotificationSound() {
  if (!notificationSound || localStorage.getItem('soundEnabled') === 'false') return;
  try {
    const ctx = notificationSound;
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.value = 660;
    osc.type = 'sine';
    gain.gain.setValueAtTime(0.1, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.15);
  } catch(e) {}
}

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }

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

function toggleOnlinePanel() {
  const list = document.getElementById('onlineUsers');
  const btn = document.querySelector('.online-users-toggle');
  if (!list || !btn) return;
  const collapsed = list.classList.toggle('collapsed');
  btn.textContent = collapsed ? '+' : '−';
}

function formatTime(dateStr) {
  const d = new Date(dateStr.replace(' ', 'T'));
  return d.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
}

function formatDate(dateStr) {
  const d = new Date(dateStr.replace(' ', 'T'));
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const yesterday = new Date(today - 86400000);
  const dDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  if (dDate.getTime() === today.getTime()) return '今天';
  if (dDate.getTime() === yesterday.getTime()) return '昨天';
  return d.toLocaleDateString('zh-CN', { month: 'long', day: 'numeric' });
}

function scrollToBottom() {
  const container = document.getElementById('messagesContainer');
  container.scrollTop = container.scrollHeight;
}

function isNearBottom() {
  const container = document.getElementById('messagesContainer');
  return container.scrollHeight - container.scrollTop - container.clientHeight < 100;
}

function showSkeleton() { document.getElementById('skeletonLoading')?.classList.remove('hidden'); }
function hideSkeleton() { document.getElementById('skeletonLoading')?.classList.add('hidden'); }

function updateScrollBtn() {
  const btn = document.getElementById('scrollBottomBtn');
  if (!btn) return;
  const container = document.getElementById('messagesContainer');
  btn.classList.toggle('show', container.scrollHeight - container.scrollTop - container.clientHeight > 300);
}

async function init() {
  const meRes = await fetch('/api/auth.php?action=me');
  const meData = await meRes.json();
  if (!meData.success) { window.location.href = '/login.php'; return; }

  user = meData.user;
  document.getElementById('userNickname').textContent = user.nickname;
  if (user.pending_nickname) {
    document.getElementById('userNickname').innerHTML += ' <small style="color:#f39c12">(审核中)</small>';
  }
  document.getElementById('userRole').textContent = user.role === 'admin' ? '管理员' : '用户';
  if (user.role === 'admin') document.getElementById('adminBtn').style.display = 'block';

  await loadRooms();
  startPolling();
}

function startPolling() { if (pollTimer) clearInterval(pollTimer); pollTimer = setInterval(pollMessages, 2000); }

async function pollMessages() {
  if (!currentRoomId || pollInFlight) { if (pollInFlight) pollQueued = true; return; }
  pollInFlight = true;
  try {
    const res = await fetch(`/api/messages.php?action=poll&room_id=${currentRoomId}&after_id=${lastMsgId}`);
    const data = await res.json();
    if (!data.success) return;

    if (data.messages && data.messages.length > 0) {
      const nearBottom = isNearBottom();
      data.messages.forEach(msg => {
        loadedMessageIds.add(msg.id);
        renderMessage(msg);
        lastMsgId = Math.max(lastMsgId, msg.id);
      });
      if (nearBottom) scrollToBottom();
      playNotificationSound();
    }

    if (data.recalled && data.recalled.length > 0) {
      data.recalled.forEach(id => {
        const el = document.querySelector(`[data-msg-id="${id}"]`);
        if (el) {
          el.querySelector('.msg-text').textContent = '消息已撤回';
          el.classList.add('recalled');
          el.querySelector('.msg-recall-btn')?.remove();
          el.querySelector('.msg-reply-preview')?.remove();
        }
      });
    }

    if (data.online_users) renderOnlineUsers(data.online_users);
    if (data.announcements && data.announcements.length > 0 && !document.getElementById('announcementBar')) {
      const bar = document.createElement('div');
      bar.id = 'announcementBar';
      bar.className = 'announcement-bar';
      bar.innerHTML = data.announcements.map(a =>
        `<div class="announcement-item"><span class="announcement-icon">📢</span><span class="announcement-title">${escapeHtml(a.title)}</span><span class="announcement-content">${escapeHtml(a.content)}</span></div>`
      ).join('');
      document.getElementById('app').prepend(bar);
    }
  } catch(e) {} finally {
    pollInFlight = false;
    if (pollQueued) { pollQueued = false; pollMessages(); }
  }
}

function renderMessage(msg) {
  const container = document.getElementById('messages');
  const dateLabel = formatDate(msg.created_at);
  if (dateLabel !== lastMsgDate) {
    const divider = document.createElement('div');
    divider.className = 'date-divider';
    divider.textContent = dateLabel;
    container.appendChild(divider);
    lastMsgDate = dateLabel;
  }

  const div = document.createElement('div');
  const isSelf = msg.user_id && msg.user_id == user.id && !msg.is_anonymous;
  div.className = `message ${isSelf ? 'self' : 'other'}`;
  div.dataset.msgId = msg.id;

  if (msg.recalled) {
    div.innerHTML = `<div class="msg-text recalled-text">消息已撤回</div>`;
  } else {
    let replyHtml = '';
    if (msg.reply_preview) {
      replyHtml = `<div class="msg-reply-preview"><span>${escapeHtml(msg.reply_preview.nickname)}: ${escapeHtml(msg.reply_preview.content)}</span></div>`;
    }
    let recallBtn = (isSelf || user.role === 'admin') ? `<button class="msg-recall-btn" onclick="event.stopPropagation();recallMessage(${msg.id}, ${msg.room_id})" title="撤回">↩</button>` : '';
    div.innerHTML = `
      ${!isSelf ? `<div class="msg-author">${escapeHtml(msg.nickname || '用户')}</div>` : ''}
      ${replyHtml}
      <div class="msg-text">${escapeHtml(msg.content)}</div>
      <div class="msg-meta">
        <span class="msg-time">${formatTime(msg.created_at)}</span>
        ${recallBtn}
      </div>
    `;
    // 点击消息回复
    div.addEventListener('click', (e) => {
      if (e.target.closest('.msg-recall-btn')) return;
      setReplyTo(msg);
    });
  }
  container.appendChild(div);
}

function renderMessageBefore(msg) {
  const container = document.getElementById('messages');
  const firstChild = container.firstChild;
  const div = document.createElement('div');
  const isSelf = msg.user_id && msg.user_id == user.id && !msg.is_anonymous;
  div.className = `message ${isSelf ? 'self' : 'other'}`;
  div.dataset.msgId = msg.id;

  if (msg.recalled) {
    div.innerHTML = `<div class="msg-text recalled-text">消息已撤回</div>`;
  } else {
    let replyHtml = '';
    if (msg.reply_preview) {
      replyHtml = `<div class="msg-reply-preview"><span>${escapeHtml(msg.reply_preview.nickname)}: ${escapeHtml(msg.reply_preview.content)}</span></div>`;
    }
    let recallBtn = (isSelf || user.role === 'admin') ? `<button class="msg-recall-btn" onclick="event.stopPropagation();recallMessage(${msg.id}, ${msg.room_id})" title="撤回">↩</button>` : '';
    div.innerHTML = `
      ${!isSelf ? `<div class="msg-author">${escapeHtml(msg.nickname || '用户')}</div>` : ''}
      ${replyHtml}
      <div class="msg-text">${escapeHtml(msg.content)}</div>
      <div class="msg-meta">
        <span class="msg-time">${formatTime(msg.created_at)}</span>
        ${recallBtn}
      </div>
    `;
    div.addEventListener('click', (e) => {
      if (e.target.closest('.msg-recall-btn')) return;
      setReplyTo(msg);
    });
  }
  container.insertBefore(div, firstChild);
}

function setReplyTo(msg) {
  replyToMessage = { id: msg.id, nickname: msg.nickname || '用户', content: msg.content };
  const box = document.getElementById('replyBox');
  const text = document.getElementById('replyText');
  if (box && text) {
    text.textContent = `${replyToMessage.nickname}: ${replyToMessage.content.substring(0, 60)}`;
    box.hidden = false;
    document.getElementById('messageInput').focus();
  }
}

function cancelReply() {
  replyToMessage = null;
  const box = document.getElementById('replyBox');
  if (box) box.hidden = true;
}

function addSystemMessage(text) {
  const container = document.getElementById('messages');
  const div = document.createElement('div');
  div.className = 'message system';
  div.textContent = text;
  container.appendChild(div);
  scrollToBottom();
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

async function loadRooms() {
  const res = await fetch('/api/rooms.php?action=list');
  const data = await res.json();
  if (data.success) { rooms = data.rooms; renderRoomList(); }
}

function renderRoomList() {
  const list = document.getElementById('roomList');
  list.innerHTML = '';
  rooms.forEach(room => {
    const div = document.createElement('div');
    const icon = room.type === 'anonymous' ? '🌳' : '💬';
    div.className = `room-item ${currentRoomId === room.id ? 'active' : ''}`;
    const unread = unreadCounts[room.id] || 0;
    const lockIcon = room.has_password ? '🔒 ' : '';
    div.innerHTML = `
      <div class="room-icon">${icon}</div>
      <div class="room-info">
        <div class="room-name">${lockIcon}${escapeHtml(room.name)} ${unread > 0 ? `<span class="room-unread">${unread}</span>` : ''}</div>
        <div class="room-desc">${escapeHtml(room.description || '')}</div>
      </div>
    `;
    div.onclick = () => joinRoom(room);
    list.appendChild(div);
  });
}

function filterRooms() {
  const query = document.getElementById('roomSearch').value.toLowerCase();
  document.querySelectorAll('.room-item').forEach(el => {
    el.style.display = el.querySelector('.room-name').textContent.toLowerCase().includes(query) ? 'flex' : 'none';
  });
}

async function joinRoom(room) {
  if (currentRoomId === room.id) return;

  // 检查房间密码
  if (room.has_password) {
    const unlocked = await checkRoomPassword(room);
    if (!unlocked) return;
  }

  currentRoomId = room.id;
  currentRoomType = room.type;
  lastMsgId = 0;
  lastMsgDate = null;
  loadedMessageIds.clear();
  cancelReply();

  document.getElementById('welcomeScreen').classList.add('hidden');
  document.getElementById('chatScreen').classList.remove('hidden');
  document.getElementById('currentRoomName').textContent = room.name;
  document.getElementById('roomTypeBadge').textContent = room.type === 'anonymous' ? '匿名模式' : '公开';
  document.getElementById('roomTypeBadge').style.display = 'inline';
  document.getElementById('messages').innerHTML = '';
  showSkeleton();

  delete unreadCounts[room.id];
  renderRoomList();
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('messageInput').focus();
  await loadInitialMessages();
  hideSkeleton();
}

async function checkRoomPassword(room) {
  return new Promise((resolve) => {
    const modal = document.getElementById('modalContent');
    modal.innerHTML = `
      <h3>🔒 房间需要密码</h3>
      <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px">${escapeHtml(room.name)} 需要密码才能进入</p>
      <div class="form-group">
        <label>密码</label>
        <input type="password" id="roomPasswordInput" placeholder="输入房间密码">
      </div>
      <button class="btn btn-primary btn-full" onclick="submitRoomPassword(${room.id})">进入房间</button>
      <p id="roomPasswordMsg" class="error-msg"></p>
    `;
    document.getElementById('modalOverlay').classList.remove('hidden');
    document.getElementById('modalOverlay').dataset.passwordCheck = '1';
    document.getElementById('modalOverlay').dataset.roomId = room.id;
    window.__passwordResolve = resolve;
    document.getElementById('roomPasswordInput')?.focus();
  });
}

window.submitRoomPassword = async function(roomId) {
  const password = document.getElementById('roomPasswordInput')?.value;
  if (!password) { document.getElementById('roomPasswordMsg').textContent = '请输入密码'; return; }
  const form = new URLSearchParams();
  form.append('room_id', roomId);
  form.append('password', password);
  const res = await fetch('/api/rooms.php?action=verify_password', {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form
  });
  const data = await res.json();
  if (data.success) {
    closeModal();
    window.__passwordResolve(true);
  } else {
    document.getElementById('roomPasswordMsg').textContent = data.message;
  }
};

// closeModal 支持密码检查

async function loadInitialMessages() {
  try {
    const res = await fetch(`/api/messages.php?action=poll&room_id=${currentRoomId}&after_id=0`);
    const data = await res.json();
    if (data.success && data.messages) {
      data.messages.forEach(msg => {
        loadedMessageIds.add(msg.id);
        renderMessage(msg);
        lastMsgId = Math.max(lastMsgId, msg.id);
      });
      scrollToBottom();
    }
  } catch(e) {}
}

async function loadMoreMessages() {
  if (!currentRoomId) return;
  const firstMsg = document.querySelector('[data-msg-id]');
  if (!firstMsg) return;
  const beforeId = parseInt(firstMsg.dataset.msgId);
  try {
    const res = await fetch(`/api/messages.php?action=load_more&room_id=${currentRoomId}&before_id=${beforeId}`);
    const data = await res.json();
    if (data.success && data.messages.length > 0) {
      data.messages.forEach(msg => { loadedMessageIds.add(msg.id); renderMessageBefore(msg); });
    }
  } catch(e) {}
}

function handleInputKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

async function sendMessage() {
  const input = document.getElementById('messageInput');
  const content = input.value.trim();
  if (!content || !currentRoomId) return;

  const form = new URLSearchParams();
  form.append('room_id', currentRoomId);
  form.append('content', content);
  if (replyToMessage) form.append('reply_to', replyToMessage.id);

  await fetch('/api/messages.php?action=send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: form
  });

  input.value = '';
  input.style.height = 'auto';
  cancelReply();
}

async function recallMessage(messageId, roomId) {
  const form = new URLSearchParams();
  form.append('message_id', messageId);
  form.append('room_id', roomId);
  await fetch('/api/messages.php?action=recall', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: form
  });
}

function searchMessages() {
  const query = document.getElementById('messageSearchInput').value.trim().toLowerCase();
  const container = document.getElementById('messages');
  const msgs = container.querySelectorAll('.message:not(.system)');
  container.querySelectorAll('.msg-text').forEach(el => { el.innerHTML = el.textContent; });
  if (!query) return;
  let firstMatch = null;
  msgs.forEach(msg => {
    const textEl = msg.querySelector('.msg-text');
    if (textEl && textEl.textContent.toLowerCase().includes(query)) {
      const text = textEl.textContent;
      const idx = text.toLowerCase().indexOf(query);
      if (idx !== -1) {
        textEl.innerHTML = escapeHtml(text.substring(0, idx)) +
          '<mark>' + escapeHtml(text.substring(idx, idx + query.length)) + '</mark>' +
          escapeHtml(text.substring(idx + query.length));
      }
      if (!firstMatch) firstMatch = msg;
    }
  });
  if (firstMatch) firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function renderOnlineUsers(users) {
  const container = document.getElementById('onlineUsers');
  if (!container) return;
  if (users.length === 0) {
    container.innerHTML = '<div class="online-users-empty">暂无其他用户在线</div>';
    return;
  }
  container.innerHTML = users.map(u => `
    <div class="online-user" title="${escapeHtml(u.geo || '位置未知')}">
      <span class="online-dot"></span>
      <span class="online-user-name">${escapeHtml(u.nickname)}</span>
      ${u.role === 'admin' ? '<span class="online-user-badge">管理员</span>' : ''}
      ${u.geo ? `<span class="online-user-geo">${escapeHtml(u.geo.split(' ').slice(0,2).join(' '))}</span>` : ''}
    </div>
  `).join('');
}

const EMOJIS = ['😀','😁','😂','🤣','😃','😄','😅','😆','😉','😊','😋','😎','😍','🥰','😘','😗','😙','😚','🙂','🤗','🤩','🤔','🤨','😐','😑','😶','🙄','😏','😣','😥','😮','🤐','😯','😪','😫','😴','😌','😛','😜','😝','🤤','😒','😓','😔','😕','🙃','🤑','😲','☹️','🙁','😖','😞','😟','😤','😢','😭','😦','😧','😨','😩','🤯','😬','😰','😱','🥵','🥶','😳','🤪','😵','😡','😠','🤬','👍','👎','👊','✊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','✌️','🤟','🤘','👌','❤️','🧡','💛','💚','💙','💜','🖤','💔','💕','💞','💗','💖','💘','💝','🌟','⭐','✨','🔥','💯','🎉','🎊','🎈','🎁','🏆','👑','💎'];

function toggleEmojiPicker() {
  const picker = document.getElementById('emojiPicker');
  if (!picker) return;
  picker.classList.toggle('visible');
  if (!picker.innerHTML && picker.classList.contains('visible')) {
    picker.innerHTML = EMOJIS.map(e => `<span class="emoji-item" onclick="insertEmoji('${e}')">${e}</span>`).join('');
  }
}

function insertEmoji(emoji) {
  const input = document.getElementById('messageInput');
  const start = input.selectionStart;
  const end = input.selectionEnd;
  input.value = input.value.substring(0, start) + emoji + input.value.substring(end);
  input.focus();
  input.selectionStart = input.selectionEnd = start + emoji.length;
  autoResize(input);
}

function toggleSound() {
  const enabled = localStorage.getItem('soundEnabled') !== 'false';
  localStorage.setItem('soundEnabled', enabled ? 'false' : 'true');
  const btn = document.getElementById('soundToggle');
  if (btn) btn.textContent = enabled ? '🔇' : '🔔';
}

let scrollLoading = false;
document.getElementById('messagesContainer')?.addEventListener('scroll', function() {
  updateScrollBtn();
  if (this.scrollTop < 100 && currentRoomId && !scrollLoading) {
    scrollLoading = true; loadMoreMessages(); setTimeout(() => { scrollLoading = false; }, 500);
  }
});

function showNicknameModal() {
  const modal = document.getElementById('modalContent');
  modal.innerHTML = `
    <h3>✏️ 修改昵称</h3>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px">修改昵称需要管理员审核</p>
    <div class="form-group"><label>当前昵称</label><input type="text" value="${escapeHtml(user.nickname)}" disabled></div>
    <div class="form-group"><label>新昵称</label><input type="text" id="newNickname" maxlength="20" placeholder="输入新昵称"></div>
    <button class="btn btn-primary btn-full" onclick="submitNickname()">提交申请</button>
    <p id="nicknameMsg" class="error-msg"></p>
  `;
  document.getElementById('modalOverlay').classList.remove('hidden');
}

async function submitNickname() {
  const nickname = document.getElementById('newNickname').value.trim();
  if (!nickname) return;
  const form = new URLSearchParams(); form.append('nickname', nickname);
  const res = await fetch('/api/auth.php?action=request_nickname', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form });
  const data = await res.json();
  document.getElementById('nicknameMsg').textContent = data.message;
  if (data.success) setTimeout(closeModal, 1500);
}

function showCreateRoom() {
  const modal = document.getElementById('modalContent');
  modal.innerHTML = `
    <h3>➕ 创建聊天室</h3>
    <p style="color:var(--text-secondary);font-size:13px;margin-bottom:16px">${user.role !== 'admin' ? '创建后需管理员审核' : '管理员可立即创建'}</p>
    <div class="form-group"><label>房间名称</label><input type="text" id="roomName" maxlength="30" placeholder="输入房间名称"></div>
    <div class="form-group"><label>房间描述（可选）</label><input type="text" id="roomDesc" maxlength="100" placeholder="简单描述这个房间"></div>
    <div class="form-group"><label>房间密码（可选）</label><input type="text" id="roomPassword" maxlength="20" placeholder="留空为公开房间"></div>
    <button class="btn btn-primary btn-full" onclick="createRoom()">创建房间</button>
    <p id="roomMsg" class="error-msg"></p>
  `;
  document.getElementById('modalOverlay').classList.remove('hidden');
}

async function createRoom() {
  const name = document.getElementById('roomName').value.trim();
  const desc = document.getElementById('roomDesc').value.trim();
  const password = document.getElementById('roomPassword').value.trim();
  if (!name) return;
  const form = new URLSearchParams();
  form.append('name', name); form.append('description', desc); form.append('password', password);
  const res = await fetch('/api/rooms.php?action=create', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form });
  const data = await res.json();
  document.getElementById('roomMsg').textContent = data.message;
  if (data.success) { await loadRooms(); setTimeout(closeModal, 1000); }
}

function closeModal(e) {
  const overlay = document.getElementById('modalOverlay');
  if (e && e.target !== overlay) return;
  if (overlay.dataset.passwordCheck === '1') {
    overlay.dataset.passwordCheck = '0';
    if (window.__passwordResolve) window.__passwordResolve(false);
  }
  overlay.classList.add('hidden');
}

async function handleLogout() {
  await fetch('/api/auth.php?action=logout', { method: 'POST' });
  window.location.href = '/login.php';
}

init();