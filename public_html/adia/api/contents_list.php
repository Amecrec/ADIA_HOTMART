<?php
require_once __DIR__.'/config.php';

$jwt = require_bearer();
$user_id = (int)$jwt['user_id'];
require_active_membership($user_id);

$nivel = $_GET['nivel'] ?? '';
$grado = $_GET['grado'] ?? '';
$campo = $_GET['campo'] ?? '';

$sql = "SELECT id, clave, titulo, descripcion FROM contents WHERE estado='activo'";
$params = [];
if ($nivel !== '') { $sql .= " AND nivel = ?";  $params[] = $nivel; }
if ($grado !== '') { $sql .= " AND grado = ?";  $params[] = $grado; }
if ($campo !== '') { $sql .= " AND campo = ?";  $params[] = $campo; }
$sql .= " ORDER BY id DESC LIMIT 500";

$st = db()->prepare($sql);
$st->execute($params);
$data = $st->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok'=>true, 'data'=>$data]);
