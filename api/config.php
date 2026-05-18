<?php
require_once __DIR__ . '/helpers.php';
$siteKey = $db->querySingle("SELECT value FROM settings WHERE key = 'cf_site_key'");
$inviteOnly = $db->querySingle("SELECT value FROM settings WHERE key = 'invite_only'");
echo json_encode([
  'success' => true,
  'cfSiteKey' => $siteKey ?: '',
  'inviteOnly' => $inviteOnly === 'true'
]);
?>
