<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

// 会话管理（简化版，用 token + cookie）
function getSessionUserId() {
  return isset($_COOKIE['user_id']) ? intval($_COOKIE['user_id']) : 0;
}

function getSessionUser($db) {
  $id = getSessionUserId();
  if (!$id) return null;
  $token = $_COOKIE['session_token'] ?? '';
  $user = $db->querySingle("SELECT * FROM users WHERE id = $id AND session_id = '$token'", true);
  return $user ?: null;
}

function loginUser($db, $userId) {
  $token = bin2hex(random_bytes(32));
  $db->exec("UPDATE users SET session_id = '$token', last_active = CURRENT_TIMESTAMP WHERE id = $userId");
  setcookie('user_id', $userId, time() + 86400 * 7, '/', '', false, true);
  setcookie('session_token', $token, time() + 86400 * 7, '/', '', false, true);
  return $token;
}

function logoutUser($db) {
  $id = getSessionUserId();
  if ($id) $db->exec("UPDATE users SET session_id = NULL WHERE id = $id");
  setcookie('user_id', '', time() - 3600, '/');
  setcookie('session_token', '', time() - 3600, '/');
}

function requireUser($db) {
  $user = getSessionUser($db);
  if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
  }
  if ($user['status'] === 'banned') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '账户已被禁用']);
    exit;
  }
  return $user;
}

function requireAdmin($db) {
  $user = requireUser($db);
  if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '无权限']);
    exit;
  }
  return $user;
}
?>
