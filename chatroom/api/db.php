<?php
// 数据库初始化
$dbPath = __DIR__ . '/../data/chatroom.db';
$db = new SQLite3($dbPath);
$db->exec('PRAGMA journal_mode=WAL');
$db->exec('PRAGMA foreign_keys=ON');

// 建表
$db->exec("
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    nickname TEXT NOT NULL,
    role TEXT DEFAULT 'user',
    status TEXT DEFAULT 'active',
    pending_nickname TEXT,
    session_id TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_active DATETIME
  );

  CREATE TABLE IF NOT EXISTS invite_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    max_uses INTEGER DEFAULT 1,
    used_count INTEGER DEFAULT 0,
    expires_at DATETIME,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    type TEXT DEFAULT 'normal',
    status TEXT DEFAULT 'active',
    pending INTEGER DEFAULT 0,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL,
    user_id INTEGER,
    content TEXT NOT NULL,
    is_anonymous INTEGER DEFAULT 0,
    recalled INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS nickname_changes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    old_nickname TEXT NOT NULL,
    new_nickname TEXT NOT NULL,
    status TEXT DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME
  );

  CREATE TABLE IF NOT EXISTS announcements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    priority INTEGER DEFAULT 0,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT
  );
");

// 迁移：添加新列
foreach ([
  ['messages', 'reply_to_message_id', 'INTEGER DEFAULT NULL'],
  ['rooms', 'room_password_hash', 'TEXT DEFAULT NULL'],
  ['users', 'geo_text', 'TEXT DEFAULT NULL'],
] as $col) {
  $exists = $db->querySingle("SELECT COUNT(*) FROM pragma_table_info('{$col[0]}') WHERE name='{$col[1]}'");
  if (!$exists) {
    $db->exec("ALTER TABLE {$col[0]} ADD COLUMN {$col[1]} {$col[2]}");
  }
}

// 创建默认管理员
$admin = $db->querySingle("SELECT COUNT(*) as c FROM users WHERE role='admin'");
if (!$admin) {
  $hash = password_hash('admin123', PASSWORD_DEFAULT);
  $db->exec("INSERT INTO users (username, password, nickname, role) VALUES ('admin', '$hash', '管理员', 'admin')");
}

// 创建默认房间
$lobby = $db->querySingle("SELECT COUNT(*) FROM rooms WHERE name='大厅'");
if (!$lobby) {
  $db->exec("INSERT INTO rooms (name, description, type, created_by) VALUES ('大厅', '公共聊天大厅', 'normal', 1)");
}
$tree = $db->querySingle("SELECT COUNT(*) FROM rooms WHERE name='匿名树洞'");
if (!$tree) {
  $db->exec("INSERT INTO rooms (name, description, type, created_by) VALUES ('匿名树洞', '匿名倾诉空间', 'anonymous', 1)");
}

// 默认设置
$db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('invite_only', 'false')");
$db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('cf_site_key', '')");
$db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('cf_secret_key', '')");
?>
