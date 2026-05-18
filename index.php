<?php
// Check if logged in, redirect to login if not
require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/helpers.php';

$user = getSessionUser($db);
if (!$user) {
  header('Location: login.php');
  exit;
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>聊天室</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div id="app">
  <aside id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-header-top">
        <h2>💬 聊天室</h2>
        <button id="soundToggle" class="btn-icon" onclick="toggleSound()" title="通知声音">🔔</button>
      </div>
      <div class="user-info">
        <span id="userNickname">加载中...</span>
        <small id="userRole"></small>
      </div>
    </div>
    <div class="sidebar-actions">
      <button class="btn btn-sm btn-outline" onclick="showNicknameModal()">✏️ 昵称</button>
      <button class="btn btn-sm btn-outline" onclick="showCreateRoom()">➕ 房间</button>
      <button class="btn btn-sm btn-outline" id="adminBtn" style="display:none" onclick="window.open('admin.php','_blank')">⚙️ 管理</button>
      <button class="btn btn-sm btn-outline-danger" onclick="handleLogout()">🚪 退出</button>
    </div>
    <div class="search-box">
      <input type="text" id="roomSearch" placeholder="搜索房间..." oninput="filterRooms()">
    </div>
    <div class="room-list" id="roomList"></div>
  </aside>
  <main id="main">
    <div id="welcomeScreen" class="welcome-screen">
      <div class="welcome-content">
        <h1>💬 欢迎来到聊天室</h1>
        <p>选择一个房间开始聊天</p>
      </div>
    </div>
    <div id="chatScreen" class="chat-screen hidden">
      <div class="chat-header">
        <button class="btn btn-sm btn-outline mobile-menu-btn" onclick="toggleSidebar()">☰</button>
        <div class="chat-header-info">
          <h3 id="currentRoomName">房间</h3>
          <div class="chat-header-meta">
            <span id="roomTypeBadge" class="badge"></span>
            <span id="onlineCount" class="online-count">0 人在线</span>
          </div>
        </div>
        <div class="chat-header-actions">
          <button class="btn-icon" onclick="toggleEmojiPicker()" title="表情">😊</button>
          <button class="btn-icon" onclick="document.getElementById('messageSearchPanel').classList.toggle('hidden')" title="搜索消息">🔍</button>
        </div>
      </div>
      <div id="messageSearchPanel" class="message-search-panel hidden">
        <div class="message-search-input-group">
          <input type="text" id="messageSearchInput" placeholder="搜索当前房间消息..." onkeydown="if(event.key==='Enter')searchMessages()">
          <button class="btn btn-sm btn-primary" onclick="searchMessages()">搜索</button>
          <button class="btn btn-sm btn-outline" onclick="document.getElementById('messageSearchPanel').classList.add('hidden')">✕</button>
        </div>
      </div>
      <div class="chat-layout">
        <div class="messages-container" id="messagesContainer">
          <div class="load-more-hint" id="loadMoreHint" onclick="loadMoreMessages()">点击加载更多消息</div>
          <div class="messages" id="messages"></div>
          <div class="skeleton-loading hidden" id="skeletonLoading">
            <div class="skeleton-message"><div class="skeleton-avatar"></div><div class="skeleton-body"><div class="skeleton-line"></div><div class="skeleton-line"></div></div></div>
            <div class="skeleton-message" style="justify-content:flex-end"><div class="skeleton-body"><div class="skeleton-line"></div><div class="skeleton-line"></div></div></div>
            <div class="skeleton-message"><div class="skeleton-avatar"></div><div class="skeleton-body"><div class="skeleton-line"></div><div class="skeleton-line"></div></div></div>
            <div class="skeleton-message"><div class="skeleton-avatar"></div><div class="skeleton-body"><div class="skeleton-line"></div><div class="skeleton-line"></div></div></div>
            <div class="skeleton-message" style="justify-content:flex-end"><div class="skeleton-body"><div class="skeleton-line"></div><div class="skeleton-line"></div></div></div>
          </div>
          <button class="scroll-bottom-btn" id="scrollBottomBtn" onclick="scrollToBottom()" title="回到底部">↓</button>
        </div>
        <div class="online-users-panel" id="onlineUsersPanel">
          <div class="online-users-header">
            <span>在线用户</span>
            <button class="online-users-toggle" onclick="toggleOnlinePanel()" title="折叠">−</button>
          </div>
          <div class="online-users-list" id="onlineUsers">
            <div class="online-users-empty">暂无其他用户</div>
          </div>
        </div>
      </div>
      <div class="message-input-area">
        <div id="emojiPicker" class="emoji-picker"></div>
        <div id="replyBox" class="reply-composer" hidden>
          <div class="reply-composer-main">
            <span class="reply-composer-label">回复</span>
            <span id="replyText" class="reply-composer-text"></span>
          </div>
          <button type="button" class="reply-composer-cancel" onclick="cancelReply()" aria-label="取消回复">✕</button>
        </div>
        <div class="input-wrapper">
          <button class="btn-icon input-emoji-btn" onclick="toggleEmojiPicker()" title="表情">😊</button>
          <textarea id="messageInput" rows="1" placeholder="输入消息..." onkeydown="handleInputKey(event)" oninput="autoResize(this)"></textarea>
          <button id="sendBtn" onclick="sendMessage()">📤</button>
        </div>
      </div>
    </div>
  </main>
</div>

<div id="modalOverlay" class="modal-overlay hidden" onclick="closeModal(event)">
  <div class="modal" id="modalContent"></div>
</div>

<div id="toastContainer" class="toast-container"></div>

<script src="js/theme.js"></script>
<script src="js/chat.js"></script>
</body>
</html>
