<?php
require_once __DIR__.'/config.php';

$jwt = require_bearer();
$user_id = (int)$jwt['user_id'];

$mem = membership_of_user_id($user_id);
$active = $mem && $mem['active'] && strtotime($mem['expires_at']) >= time();

echo json_encode([
  'ok'=>true,
  'active'=>(bool)$active,
  'expires_at'=>$mem['expires_at'] ?? null
]);
