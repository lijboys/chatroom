<?php
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? '';

if ($action === 'login') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';
  $user = $db->querySingle("SELECT * FROM users WHERE username = '$username'", true);
  if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => '用户名或密码错误']);
    exit;
  }
  if ($user['status'] === 'banned') {
    echo json_encode(['success' => false, 'message' => '账户已被禁用']);
    exit;
  }
  $token = loginUser($db, $user['id']);
  echo json_encode([
    'success' => true,
    'message' => '登录成功',
    'user' => ['id' => $user['id'], 'username' => $user['username'], 'nickname' => $user['nickname'], 'role' => $user['role']],
    'sessionId' => $token
  ]);
}

elseif ($action === 'register') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';
  $nickname = $_POST['nickname'] ?? '';
  $inviteCode = $_POST['inviteCode'] ?? '';

  if (strlen($username) < 3 || strlen($username) > 20) {
    echo json_encode(['success' => false, 'message' => '用户名长度需在3-20个字符之间']); exit;
  }
  if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => '密码长度至少6位']); exit;
  }
  if (strlen($nickname) < 1 || strlen($nickname) > 20) {
    echo json_encode(['success' => false, 'message' => '昵称长度需在1-20个字符之间']); exit;
  }

  $existing = $db->querySingle("SELECT id FROM users WHERE username = '$username'");
  if ($existing) {
    echo json_encode(['success' => false, 'message' => '用户名已存在']); exit;
  }

  $inviteOnly = $db->querySingle("SELECT value FROM settings WHERE key = 'invite_only'");
  if ($inviteOnly === 'true') {
    if (!$inviteCode) {
      echo json_encode(['success' => false, 'message' => '请填写邀请码']); exit;
    }
    $code = $db->querySingle("SELECT * FROM invite_codes WHERE code = '$inviteCode'", true);
    if (!$code) { echo json_encode(['success' => false, 'message' => '邀请码无效']); exit; }
    if ($code['expires_at'] && strtotime($code['expires_at']) < time()) {
      echo json_encode(['success' => false, 'message' => '邀请码已过期']); exit;
    }
    if ($code['max_uses'] != -1 && $code['used_count'] >= $code['max_uses']) {
      echo json_encode(['success' => false, 'message' => '邀请码已达使用上限']); exit;
    }
    $db->exec("UPDATE invite_codes SET used_count = used_count + 1 WHERE id = {$code['id']}");
  }

  $hash = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $db->prepare("INSERT INTO users (username, password, nickname) VALUES (?, ?, ?)");
  $stmt->bindValue(1, $username, SQLITE3_TEXT);
  $stmt->bindValue(2, $hash, SQLITE3_TEXT);
  $stmt->bindValue(3, $nickname, SQLITE3_TEXT);
  $stmt->execute();
  $uid = $db->lastInsertRowID();
  $token = loginUser($db, $uid);

  echo json_encode([
    'success' => true,
    'message' => '注册成功！提示：如需修改昵称，需管理员审核。',
    'user' => ['id' => $uid, 'username' => $username, 'nickname' => $nickname, 'role' => 'user'],
    'sessionId' => $token
  ]);
}

elseif ($action === 'logout') {
  logoutUser($db);
  echo json_encode(['success' => true]);
}

elseif ($action === 'me') {
  $user = getSessionUser($db);
  if ($user) {
    echo json_encode(['success' => true, 'user' => [
      'id' => $user['id'], 'username' => $user['username'], 'nickname' => $user['nickname'],
      'role' => $user['role'], 'status' => $user['status'], 'pending_nickname' => $user['pending_nickname']
    ]]);
  } else {
    echo json_encode(['success' => false]);
  }
}

elseif ($action === 'request_nickname') {
  $user = requireUser($db);
  $nickname = $_POST['nickname'] ?? '';
  if (strlen($nickname) < 1 || strlen($nickname) > 20) {
    echo json_encode(['success' => false, 'message' => '昵称长度需在1-20个字符之间']); exit;
  }
  $old = $db->querySingle("SELECT nickname FROM users WHERE id = {$user['id']}");
  $db->exec("INSERT INTO nickname_changes (user_id, old_nickname, new_nickname) VALUES ({$user['id']}, '$old', '$nickname')");
  $db->exec("UPDATE users SET pending_nickname = '$nickname' WHERE id = {$user['id']}");
  echo json_encode(['success' => true, 'message' => '昵称修改申请已提交，请等待管理员审核']);
}

else {
  echo json_encode(['success' => false, 'message' => '未知操作']);
}
?>
