<?php
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? '';

if ($action === 'list') {
  $user = getSessionUser($db);
  $rooms = $db->query("SELECT id, name, description, type, status, pending, created_by, created_at, room_password_hash FROM rooms WHERE status = 'active' OR status = 'pending' ORDER BY id ASC");
  $list = [];
  while ($r = $rooms->fetchArray(SQLITE3_ASSOC)) {
    $r['has_password'] = !empty($r['room_password_hash']);
    unset($r['room_password_hash']);
    $list[] = $r;
  }
  echo json_encode(['success' => true, 'rooms' => $list]);
}

elseif ($action === 'create') {
  $user = requireUser($db);
  $name = $_POST['name'] ?? '';
  $desc = $_POST['description'] ?? '';
  $password = $_POST['password'] ?? '';
  if (!$name) { echo json_encode(['success' => false, 'message' => '请输入房间名称']); exit; }
  $pending = $user['role'] === 'admin' ? 0 : 1;
  $hash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

  $stmt = $db->prepare("INSERT INTO rooms (name, description, type, pending, created_by, room_password_hash) VALUES (?, ?, 'normal', ?, ?, ?)");
  $stmt->bindValue(1, $name, SQLITE3_TEXT);
  $stmt->bindValue(2, $desc, SQLITE3_TEXT);
  $stmt->bindValue(3, $pending, SQLITE3_INTEGER);
  $stmt->bindValue(4, $user['id'], SQLITE3_INTEGER);
  $stmt->bindValue(5, $hash, SQLITE3_NULL);
  $stmt->execute();
  echo json_encode([
    'success' => true,
    'message' => $pending ? '房间创建成功，需管理员审核' : '房间创建成功'
  ]);
}

elseif ($action === 'set_password') {
  $user = requireUser($db);
  $roomId = intval($_POST['room_id'] ?? 0);
  $password = $_POST['password'] ?? '';
  $clear = $_POST['clear'] ?? '0';

  $room = $db->querySingle("SELECT * FROM rooms WHERE id = $roomId", true);
  if (!$room) { echo json_encode(['success' => false, 'message' => '房间不存在']); exit; }
  if ($room['created_by'] != $user['id'] && $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => '无权操作']); exit;
  }

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

elseif ($action === 'verify_password') {
  $user = requireUser($db);
  $roomId = intval($_POST['room_id'] ?? 0);
  $password = $_POST['password'] ?? '';

  $room = $db->querySingle("SELECT * FROM rooms WHERE id = $roomId", true);
  if (!$room || !$room['room_password_hash']) {
    echo json_encode(['success' => false, 'message' => '房间无需密码']); exit;
  }

  if (password_verify($password, $room['room_password_hash'])) {
    $_SESSION['room_unlocked_' . $roomId] = true;
    echo json_encode(['success' => true, 'message' => '密码正确']);
  } else {
    echo json_encode(['success' => false, 'message' => '密码错误']);
  }
}

else {
  echo json_encode(['success' => false, 'message' => '未知操作']);
}
?>
