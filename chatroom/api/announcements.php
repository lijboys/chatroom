<?php
require_once __DIR__ . '/helpers.php';

$anns = $db->query("
  SELECT a.*, u.nickname as creator_name
  FROM announcements a
  LEFT JOIN users u ON a.created_by = u.id
  ORDER BY a.priority DESC, a.created_at DESC
  LIMIT 10
");
$list = [];
while ($a = $anns->fetchArray(SQLITE3_ASSOC)) $list[] = $a;
echo json_encode(['success' => true, 'announcements' => $list]);
?>
