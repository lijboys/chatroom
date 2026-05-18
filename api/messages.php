<?php
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? '';

function mb_substr_fallback($str, $start, $length = null) {
  return function_exists('mb_substr') ? mb_substr($str, $start, $length, 'UTF-8') : substr($str, $start, $length);
}

function geoLookup($ip) {
  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
    return null;
  }
  $url = 'http://ip-api.com/json/' . $ip . '?lang=zh-CN&fields=status,country,regionName,city,isp';
  $ctx = stream_context_create(['http' => ['timeout' => 2, 'header' => "User-Agent: ChatRoom\r\n"]]);
  $raw = @file_get_contents($url, false, $ctx);
  if (!$raw) return null;
  $j = json_decode($raw, true);
  if (!is_array($j) || ($j['status'] ?? '') !== 'success') return null;
  $parts = array_filter([$j['country'] ?? '', $j['regionName'] ?? '', $j['city'] ?? '']);
  $isp = $j['isp'] ?? '';
  $text = implode(' ', $parts);
  if ($isp) $text .= ' [' . $isp . ']';
  return $text ?: null;
}

if ($action === 'send') {
  $user = requireUser($db);
  $roomId = intval($_POST['room_id'] ?? 0);
  $content = trim($_POST['content'] ?? '');
  $replyTo = intval($_POST['reply_to'] ?? 0);

  if (!$roomId || !$content) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
  }

  $room = $db->querySingle("SELECT * FROM rooms WHERE id = $roomId AND status = 'active'", true);
  if (!$room) {
    echo json_encode(['success' => false, 'message' => '房间不存在或已关闭']);
    exit;
  }

  $isAnonymous = $room['type'] === 'anonymous' ? 1 : 0;
  $cleanContent = substr($content, 0, 2000);

  $stmt = $db->prepare("INSERT INTO messages (room_id, user_id, content, is_anonymous, reply_to_message_id) VALUES (?, ?, ?, ?, ?)");
  $stmt->bindValue(1, $roomId, SQLITE3_INTEGER);
  $stmt->bindValue(2, $user['id'], SQLITE3_INTEGER);
  $stmt->bindValue(3, $cleanContent, SQLITE3_TEXT);
  $stmt->bindValue(4, $isAnonymous, SQLITE3_INTEGER);
  $stmt->bindValue(5, $replyTo ?: null, $replyTo ? SQLITE3_INTEGER : SQLITE3_NULL);
  $stmt->execute();

  $msgId = $db->lastInsertRowID();

  $msg = [
    'id' => $msgId,
    'room_id' => $roomId,
    'user_id' => $isAnonymous ? null : $user['id'],
    'content' => $cleanContent,
    'is_anonymous' => $isAnonymous,
    'nickname' => $isAnonymous ? '匿名用户' : $user['nickname'],
    'username' => $isAnonymous ? 'anonymous' : $user['username'],
    'created_at' => date('Y-m-d H:i:s'),
    'reply_to_message_id' => $replyTo ?: null,
  ];

  echo json_encode(['success' => true, 'message' => $msg]);
}

elseif ($action === 'poll') {
  $user = requireUser($db);
  $roomId = intval($_GET['room_id'] ?? 0);
  $afterId = intval($_GET['after_id'] ?? 0);

  $room = $db->querySingle("SELECT * FROM rooms WHERE id = $roomId", true);
  if (!$room) {
    echo json_encode(['success' => false, 'message' => '房间不存在']);
    exit;
  }

  // 更新用户活跃时间 + Geo定位
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  // 先查缓存
  $existingGeo = $db->querySingle("SELECT geo_text FROM users WHERE id = {$user['id']}");
  $geoText = $existingGeo;
  if (!$geoText) {
    $geoText = geoLookup($ip);
    if ($geoText) {
      $db->exec("UPDATE users SET geo_text = '$geoText' WHERE id = {$user['id']}");
    }
  }
  $db->exec("UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE id = {$user['id']}");

  // 获取新消息（连带回复预览）
  $msgs = $db->query("
    SELECT m.*, u.nickname, u.username,
      r.content as reply_content, r.user_id as reply_user_id, ru.nickname as reply_nickname
    FROM messages m
    LEFT JOIN users u ON m.user_id = u.id
    LEFT JOIN messages r ON m.reply_to_message_id = r.id
    LEFT JOIN users ru ON r.user_id = ru.id
    WHERE m.room_id = $roomId AND m.id > $afterId AND m.recalled = 0
    ORDER BY m.id ASC
  ");

  $messages = [];
  while ($m = $msgs->fetchArray(SQLITE3_ASSOC)) {
    $isAnon = $room['type'] === 'anonymous' || $m['is_anonymous'];
    $msg = [
      'id' => intval($m['id']),
      'room_id' => intval($m['room_id']),
      'user_id' => $isAnon ? null : intval($m['user_id']),
      'content' => $m['content'],
      'is_anonymous' => $isAnon ? 1 : 0,
      'nickname' => $isAnon ? '匿名用户' : ($m['nickname'] ?? '用户'),
      'username' => $isAnon ? 'anonymous' : $m['username'],
      'created_at' => $m['created_at'],
      'reply_to_message_id' => $m['reply_to_message_id'] ? intval($m['reply_to_message_id']) : null,
    ];
    // 附带回复预览
    if ($msg['reply_to_message_id'] && $m['reply_content']) {
      $isReplyAnon = $room['type'] === 'anonymous';
      $msg['reply_preview'] = [
        'content' => mb_substr($m['reply_content'], 0, 80),
        'nickname' => $isReplyAnon ? '匿名用户' : ($m['reply_nickname'] ?? '用户'),
      ];
    }
    $messages[] = $msg;
  }

  // 获取撤回消息
  $recalled = $db->query("SELECT id FROM messages WHERE room_id = $roomId AND id > $afterId AND recalled = 1");
  $recalledIds = [];
  while ($r = $recalled->fetchArray(SQLITE3_ASSOC)) {
    $recalledIds[] = intval($r['id']);
  }

  // 在线用户列表（带Geo）
  $onlineUsers = $db->query("
    SELECT id, nickname, role, geo_text FROM users
    WHERE last_active > datetime('now', '-30 seconds') AND id != {$user['id']}
    ORDER BY nickname
  ");
  $online = [];
  while ($u = $onlineUsers->fetchArray(SQLITE3_ASSOC)) {
    $online[] = [
      'userId' => intval($u['id']),
      'nickname' => $u['nickname'],
      'role' => $u['role'],
      'geo' => $u['geo_text'] ?? '',
    ];
  }

  // 公告
  $anns = $db->query("SELECT * FROM announcements ORDER BY priority DESC, created_at DESC LIMIT 10");
  $announcements = [];
  while ($a = $anns->fetchArray(SQLITE3_ASSOC)) $announcements[] = $a;

  echo json_encode([
    'success' => true,
    'messages' => $messages,
    'recalled' => $recalledIds,
    'online_users' => $online,
    'announcements' => $announcements
  ]);
}

elseif ($action === 'load_more') {
  $user = requireUser($db);
  $roomId = intval($_GET['room_id'] ?? 0);
  $beforeId = intval($_GET['before_id'] ?? 0);

  $room = $db->querySingle("SELECT * FROM rooms WHERE id = $roomId", true);
  if (!$room) { echo json_encode(['success' => false]); exit; }

  $msgs = $db->query("
    SELECT m.*, u.nickname, u.username,
      r.content as reply_content, ru.nickname as reply_nickname
    FROM messages m
    LEFT JOIN users u ON m.user_id = u.id
    LEFT JOIN messages r ON m.reply_to_message_id = r.id
    LEFT JOIN users ru ON r.user_id = ru.id
    WHERE m.room_id = $roomId AND m.id < $beforeId AND m.recalled = 0
    ORDER BY m.id DESC LIMIT 50
  ");

  $messages = [];
  while ($m = $msgs->fetchArray(SQLITE3_ASSOC)) {
    $isAnon = $room['type'] === 'anonymous' || $m['is_anonymous'];
    $msg = [
      'id' => intval($m['id']),
      'room_id' => intval($m['room_id']),
      'user_id' => $isAnon ? null : intval($m['user_id']),
      'content' => $m['content'],
      'is_anonymous' => $isAnon ? 1 : 0,
      'nickname' => $isAnon ? '匿名用户' : ($m['nickname'] ?? '用户'),
      'username' => $isAnon ? 'anonymous' : $m['username'],
      'created_at' => $m['created_at'],
      'reply_to_message_id' => $m['reply_to_message_id'] ? intval($m['reply_to_message_id']) : null,
    ];
    if ($msg['reply_to_message_id'] && $m['reply_content']) {
      $isReplyAnon = $room['type'] === 'anonymous';
      $msg['reply_preview'] = [
        'content' => mb_substr($m['reply_content'], 0, 80),
        'nickname' => $isReplyAnon ? '匿名用户' : ($m['reply_nickname'] ?? '用户'),
      ];
    }
    $messages[] = $msg;
  }

  echo json_encode(['success' => true, 'messages' => array_reverse($messages)]);
}

elseif ($action === 'recall') {
  $user = requireUser($db);
  $messageId = intval($_POST['message_id'] ?? 0);
  $roomId = intval($_POST['room_id'] ?? 0);

  $msg = $db->querySingle("SELECT * FROM messages WHERE id = $messageId", true);
  if (!$msg) { echo json_encode(['success' => false, 'message' => '消息不存在']); exit; }
  if ($msg['user_id'] != $user['id'] && $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => '无权撤回']); exit;
  }

  $db->exec("UPDATE messages SET recalled = 1, content = '消息已撤回' WHERE id = $messageId");
  echo json_encode(['success' => true, 'message_id' => $messageId]);
}

else {
  echo json_encode(['success' => false, 'message' => '未知操作']);
}
?>
