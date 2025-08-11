<?php
require_once __DIR__.'/config.php';

$jwt = require_bearer();
$user_id = (int)$jwt['user_id'];
require_active_membership($user_id);

// versión: latest (por simplicidad tomamos la última activa)
$st = db()->query("SELECT version, cuerpo FROM templates WHERE estado='activo' ORDER BY updated_at DESC LIMIT 1");
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'no_template']); exit; }

echo json_encode(['ok'=>true, 'version'=>$row['version'], 'cuerpo'=>$row['cuerpo']]);
