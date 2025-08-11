<?php
require_once __DIR__.'/config.php';

// Hotmart enviará POST con datos de la compra/renovación/cancelación.
// IMPORTANTE: Ajusta los nombres de campos según tu configuración de Hotmart.

// Leer cuerpo (JSON o x-www-form-urlencoded). Aquí intentamos ambas:
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload) $payload = $_POST; // fallback

// (Opcional pero recomendado) Validar firma/HMAC si la configuras en Hotmart
// if (!isset($_SERVER['HTTP_X_HOTMART_SIGNATURE']) || $_SERVER['HTTP_X_HOTMART_SIGNATURE'] !== hash_hmac('sha256', $raw, HOTMART_WEBHOOK_SECRET)) {
//   http_response_code(401); echo json_encode(['ok'=>false,'error'=>'invalid_signature']); exit;
// }

$email = $payload['email'] ?? null;
$evento = strtolower($payload['event'] ?? '');
// Campos personalizados capturados en el checkout:
$nombre_docente = $payload['nombre_docente'] ?? null;
$escuela       = $payload['escuela'] ?? null;
$nivel         = $payload['nivel'] ?? null;
$grado         = $payload['grado'] ?? null;

// Validaciones mínimas
if (!$email || !$evento) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_payload']); exit; }

// Upsert de usuario
$pdo = db();
$pdo->beginTransaction();
try {
  $st = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
  $st->execute([$email]);
  $user = $st->fetch(PDO::FETCH_ASSOC);

  if ($user) {
    $uid = (int)$user['id'];
    $up = $pdo->prepare("UPDATE users SET nombre_docente=COALESCE(?, nombre_docente), escuela=COALESCE(?, escuela), nivel=COALESCE(?, nivel), grado=COALESCE(?, grado) WHERE id=?");
    $up->execute([$nombre_docente, $escuela, $nivel, $grado, $uid]);
  } else {
    $ins = $pdo->prepare("INSERT INTO users (email, nombre_docente, escuela, nivel, grado, created_at) VALUES (?,?,?,?,?,NOW())");
    $ins->execute([$email, $nombre_docente, $escuela, $nivel, $grado]);
    $uid = (int)$pdo->lastInsertId();
  }

  // Actualizar membresía según evento
  // Ajusta estos mapeos a los estados reales de Hotmart (approved, refunded, chargeback, canceled, overdue, etc.)
  $active = 0;
  $expiresAt = null;

  switch ($evento) {
    case 'approved':
    case 'subscription_approved':
    case 'renewed':
      $active = 1;
      // 12 meses a partir de hoy; ajusta si Hotmart te da fecha exacta
      $expiresAt = (new DateTime('+12 months'))->format('Y-m-d');
      break;

    case 'canceled':
    case 'refunded':
    case 'chargeback':
    case 'overdue':
    default:
      $active = 0;
      $expiresAt = (new DateTime('yesterday'))->format('Y-m-d');
      break;
  }

  // Upsert membresía
  $stm = $pdo->prepare("SELECT id FROM memberships WHERE user_id=? LIMIT 1");
  $stm->execute([$uid]);
  $mem = $stm->fetch(PDO::FETCH_ASSOC);
  if ($mem) {
    $upd = $pdo->prepare("UPDATE memberships SET active=?, expires_at=?, last_event=?, updated_at=NOW() WHERE user_id=?");
    $upd->execute([$active, $expiresAt, $evento, $uid]);
  } else {
    $insm = $pdo->prepare("INSERT INTO memberships (user_id, active, plan, expires_at, last_event, updated_at) VALUES (?,?,?,?,?,NOW())");
    $insm->execute([$uid, $active, 'anual', $expiresAt, $evento]);
  }

  $pdo->commit();
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error','msg'=>$e->getMessage()]);
}
