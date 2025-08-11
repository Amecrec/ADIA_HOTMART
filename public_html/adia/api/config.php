<?php
// ====== CONFIGURACIÓN GLOBAL (editar) ======
const DB_HOST = 'localhost';
const DB_NAME = 'TU_BD';
const DB_USER = 'TU_USUARIO';
const DB_PASS = 'TU_PASSWORD';

// JWT (firma HS256)
const JWT_SECRET = 'cambia-esta-clave-larga-y-aleatoria';
const JWT_TTL_SECONDS = 20 * 60; // 20 min

// CORS permitidos
const CORS_ALLOW_ORIGINS = ['https://adia.amecrec.org', 'http://localhost'];

// Opcional: validar postbacks de Hotmart con un secreto o firma
const HOTMART_WEBHOOK_SECRET = 'coloca-un-secreto-opcional';

// ====== UTILIDADES COMUNES ======
function cors_headers() {
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  if (in_array($origin, CORS_ALLOW_ORIGINS, true)) {
    header("Access-Control-Allow-Origin: $origin");
  }
  header("Access-Control-Allow-Headers: Authorization, Content-Type");
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Max-Age: 86400");
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { cors_headers(); exit; }
cors_headers();
header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// DB
function db() {
  static $pdo;
  if (!$pdo) {
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  }
  return $pdo;
}

// JWT simple HS256
function jwt_encode(array $payload): string {
  $header = ['typ'=>'JWT','alg'=>'HS256'];
  $segments = [
    rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '='),
    rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '='),
  ];
  $signing_input = implode('.', $segments);
  $signature = hash_hmac('sha256', $signing_input, JWT_SECRET, true);
  $segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
  return implode('.', $segments);
}
function jwt_decode(string $jwt) {
  $parts = explode('.', $jwt);
  if (count($parts) !== 3) return null;
  [$h, $p, $s] = $parts;
  $sig = base64_decode(strtr($s, '-_', '+/'));
  $valid = hash_equals($sig, hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
  if (!$valid) return null;
  $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
  if (!is_array($payload)) return null;
  if (($payload['exp'] ?? 0) < time()) return null;
  return $payload;
}

// Autorización por Bearer
function require_bearer(): array {
  $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (!preg_match('/Bearer\s+(.+)/i', $hdr, $m)) {
    http_response_code(401); echo json_encode(['ok'=>false,'error'=>'missing_bearer']); exit;
  }
  $payload = jwt_decode(trim($m[1]));
  if (!$payload) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'invalid_token']); exit; }
  return $payload; // contiene email, user_id, exp
}

// Helpers BD
function find_user_by_email(string $email) {
  $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
  $st->execute([$email]); return $st->fetch(PDO::FETCH_ASSOC);
}
function membership_of_user_id($user_id) {
  $st = db()->prepare('SELECT * FROM memberships WHERE user_id = ? LIMIT 1');
  $st->execute([$user_id]); return $st->fetch(PDO::FETCH_ASSOC);
}
function require_active_membership(int $user_id) {
  $m = membership_of_user_id($user_id);
  if (!$m || !$m['active'] || strtotime($m['expires_at']) < time()) {
    http_response_code(403); echo json_encode(['ok'=>false,'error'=>'membership_inactive']); exit;
  }
}
