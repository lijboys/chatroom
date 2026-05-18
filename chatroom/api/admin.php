<?php
require_once __DIR__ . '/helpers.php';
$user = requireAdmin($db);

$action = $_GET['action'] ?? '';

if ($action === 'get_users') {
  $users = $db->query("SELECT id, username, nickname, role, status, created_at FROM users ORDER BY id ASC");
  $list = [];
  while ($u = $users->fetchArray(SQLITE3_ASSOC)) $list[] = $u;
  echo json_encode(['success' => true, 'users' => $list]);
}

elseif ($action === 'create_user') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';
  $nickname = $_POST['nickname'] ?? '';
  $role = $_POST['role'] ?? 'user';
  if (!$username || !$password || !$nickname) {
    echo json_encode(['success' => false, 'message' => '请填写所有必填项']); exit;
  }
  $existing = $db->querySingle("SELECT id FROM users WHERE username = '$username'");
  if ($existing) { echo json_encode(['success' => false, 'message' => '用户名已存在']); exit; }
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $db->exec("INSERT INTO users (username, password, nickname, role) VALUES ('$username', '$hash', '$nickname', '$role')");
  echo json_encode(['success' => true, 'message' => '用户已创建']);
}

elseif ($action === 'toggle_ban') {
  $id = intval($_POST['id'] ?? 0);
  if ($id === 1) { echo json_encode(['success' => false, 'message' => '不能操作管理员']); exit; }
  $u = $db->querySingle("SELECT status FROM users WHERE id = $id", true);
  $new = $u['status'] === 'banned' ? 'active' : 'banned';
  $db->exec("UPDATE users SET status = '$new' WHERE id = $id");
  echo json_encode(['success' => true, 'message' => $new === 'banned' ? '已禁用' : '已启用']);
}

elseif ($action === 'delete_user') {
  $id = intval($_POST['id'] ?? 0);
  if ($id === 1) { echo json_encode(['success' => false, 'message' => '不能删除管理员']); exit; }
  $db->exec("DELETE FROM users WHERE id = $id");
  echo json_encode(['success' => true, 'message' => '用户已删除']);
}

elseif ($action === 'get_rooms') {
  $rooms = $db->query("SELECT r.id, r.name, r.description, r.type, r.status, r.pending, r.created_by, r.created_at, r.room_password_hash, u.nickname as creator_name FROM rooms r LEFT JOIN users u ON r.created_by = u.id ORDER BY r.id ASC");
  $list = [];
  while ($r = $rooms->fetchArray(SQLITE3_ASSOC)) $list[] = $r;
  echo json_encode(['success' => true, 'rooms' => $list]);
}

elseif ($action === 'approve_room') {
  $id = intval($_POST['id'] ?? 0);
  $db->exec("UPDATE rooms SET status = 'active', pending = 0 WHERE id = $id");
  echo json_encode(['success' => true, 'message' => '房间已批准']);
}

elseif ($action === 'reject_room') {
  $id = intval($_POST['id'] ?? 0);
  $db->exec("UPDATE rooms SET status = 'deleted' WHERE id = $id");
  echo json_encode(['success' => true, 'message' => '房间已拒绝']);
}

elseif ($action === 'delete_room') {
  $id = intval($_POST['id'] ?? 0);
  $db->exec("UPDATE rooms SET status = 'deleted' WHERE id = $id");
  echo json_encode(['success' => true, 'message' => '房间已删除']);
}

elseif ($action === 'set_password') {
  $roomId = intval($_POST['room_id'] ?? 0);
  $password = $_POST['password'] ?? '';
  $clear = $_POST['clear'] ?? '0';
  $room = $db->querySingle("SELECT * FROM rooms WHERE id = $roomId", true);
  if (!$room) { echo json_encode(['success' => false, 'message' => '房间不存在']); exit; }
  if ($clear === '1') {
    $db->exec("UPDATE rooms SET room_password_hash = NULL WHERE id = $roomId");
    echo json_encode(['success' => true, 'message' => '密码已清除']);
  } elseif (strlen($password) >= 4) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->exec("UPDATE rooms SET room_password_hash = '$hash' WHERE id = $roomId");
    echo json_encode(['success' => true, 'message' => '密码已设置']);
  } else {
    echo json_encode(['success' => false, 'message' => '密码至少4位']);
  }
}

elseif ($action === 'get_invite_codes') {
  $codes = $db->query("SELECT ic.*, u.nickname as creator_name FROM invite_codes ic LEFT JOIN users u ON ic.created_by = u.id ORDER BY ic.created_at DESC");
  $list = [];
  while ($c = $codes->fetchArray(SQLITE3_ASSOC)) $list[] = $c;
  echo json_encode(['success' => true, 'codes' => $list]);
}

elseif ($action === 'create_invite') {
  $maxUses = intval($_POST['maxUses'] ?? 1);
  $duration = intval($_POST['duration'] ?? 0);
  $code = strtoupper(bin2hex(random_bytes(4)));
  $expires = $duration > 0 ? date('Y-m-d H:i:s', time() + $duration * 86400) : null;
  if ($expires) {
    $db->exec("INSERT INTO invite_codes (code, max_uses, expires_at, created_by) VALUES ('$code', $maxUses, '$expires', {$user['id']})");
  } else {
    $db->exec("INSERT INTO invite_codes (code, max_uses, created_by) VALUES ('$code', $maxUses, {$user['id']})");
  }
  echo json_encode(['success' => true, 'message' => '邀请码已创建', 'code' => $code]);
}

elseif ($action === 'batch_invite') {
  $count = min(intval($_POST['count'] ?? 10), 100);
  $maxUses = intval($_POST['maxUses'] ?? 1);
  $duration = intval($_POST['duration'] ?? 0);
  $codes = [];
  for ($i = 0; $i < $count; $i++) {
    $code = strtoupper(bin2hex(random_bytes(4)));
    $expires = $duration > 0 ? date('Y-m-d H:i:s', time() + $duration * 86400) : null;
    if ($expires) {
      $db->exec("INSERT INTO invite_codes (code, max_uses, expires_at, created_by) VALUES ('$code', $maxUses, '$expires', {$user['id']})");
    } else {
      $db->exec("INSERT INTO invite_codes (code, max_uses, created_by) VALUES ('$code', $maxUses, {$user['id']})");
    }
    $codes[] = $code;
  }
  echo json_encode(['success' => true, 'message' => "已创建 $count 个邀请码", 'codes' => $codes]);
}

elseif ($action === 'delete_invite') {
  $id = intval($_POST['id'] ?? 0);
  $db->exec("DELETE FROM invite_codes WHERE id = $id");
  echo json_encode(['success' => true, 'message' => '邀请码已删除']);
}

elseif ($action === 'get_nickname_requests') {
  $reqs = $db->query("SELECT nc.*, u.username FROM nickname_changes nc LEFT JOIN users u ON nc.user_id = u.id WHERE nc.status = 'pending' ORDER BY nc.created_at DESC");
  $list = [];
  while ($r = $reqs->fetchArray(SQLITE3_ASSOC)) $list[] = $r;
  echo json_encode(['success' => true, 'requests' => $list]);
}

elseif ($action === 'approve_nickname') {
  $id = intval($_POST['id'] ?? 0);
  $change = $db->querySingle("SELECT * FROM nickname_changes WHERE id = $id", true);
  if (!$change) { echo json_encode(['success' => false, 'message' => '请求不存在']); exit; }
  $db->exec("UPDATE users SET nickname = '{$change['new_nickname']}', pending_nickname = NULL WHERE id = {$change['user_id']}");
  $db->exec("UPDATE nickname_changes SET status = 'approved', reviewed_at = CURRENT_TIMESTAMP WHERE id = $id");
  echo json_encode(['success' => true, 'message' => '已批准']);
}

elseif ($action === 'reject_nickname') {
  $id = intval($_POST['id'] ?? 0);
  $change = $db->querySingle("SELECT * FROM nickname_changes WHERE id = $id", true);
  if (!$change) { echo json_encode(['success' => false, 'message' => '请求不存在']); exit; }
  $db->exec("UPDATE users SET pending_nickname = NULL WHERE id = {$change['user_id']}");
  $db->exec("UPDATE nickname_changes SET status = 'rejected', reviewed_at = CURRENT_TIMESTAMP WHERE id = $id");
  echo json_encode(['success' => true, 'message' => '已拒绝']);
}

elseif ($action === 'create_announcement') {
  $title = $_POST['title'] ?? '';
  $content = $_POST['content'] ?? '';
  $priority = intval($_POST['priority'] ?? 0);
  if (!$title || !$content) { echo json_encode(['success' => false, 'message' => '标题和内容不能为空']); exit; }
  $db->exec("INSERT INTO announcements (title, content, priority, created_by) VALUES ('$title', '$content', $priority, {$user['id']})");
  echo json_encode(['success' => true, 'message' => '公告已发布']);
}

elseif ($action === 'delete_announcement') {
  $id = intval($_POST['id'] ?? 0);
  $db->exec("DELETE FROM announcements WHERE id = $id");
  echo json_encode(['success' => true, 'message' => '公告已删除']);
}

elseif ($action === 'get_settings') {
  $rows = $db->query("SELECT key, value FROM settings");
  $settings = [];
  while ($r = $rows->fetchArray(SQLITE3_ASSOC)) $settings[$r['key']] = $r['value'];
  echo json_encode(['success' => true, 'settings' => $settings]);
}

elseif ($action === 'save_settings') {
  if (isset($_POST['inviteOnly'])) {
    $db->exec("REPLACE INTO settings (key, value) VALUES ('invite_only', '" . ($_POST['inviteOnly'] === 'true' ? 'true' : 'false') . "')");
  }
  if (isset($_POST['cfSiteKey'])) $db->exec("REPLACE INTO settings (key, value) VALUES ('cf_site_key', '{$_POST['cfSiteKey']}')");
  if (isset($_POST['cfSecretKey'])) $db->exec("REPLACE INTO settings (key, value) VALUES ('cf_secret_key', '{$_POST['cfSecretKey']}')");
  echo json_encode(['success' => true, 'message' => '设置已保存']);
}

else {
  echo json_encode(['success' => false, 'message' => '未知操作']);
}
?>
