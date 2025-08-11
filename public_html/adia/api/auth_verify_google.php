<?php
require_once __DIR__.'/config.php';

// Espera: POST JSON { id_token: "..." }
$body = json_decode(file_get_contents('php://input'), true);
$id_token = $body['id_token'] ?? '';
if (!$id_token) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'missing_id_token']); exit; }

// Verificar ID Token con Google (endpoint público)
$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token='.urlencode($id_token));
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10]);
$resp = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
if ($http !== 200) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'invalid_google_token']); exit; }
$g = json_decode($resp, true);
$email = $g['email'] ?? null;
if (!$email) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'email_not_found']); exit; }

// Buscar usuario en BD (creado/actualizado por Hotmart webhook)
$user = find_user_by_email($email);
if (!$user) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'user_not_registered']); exit; }

// Emitir JWT efímero para el resto de endpoints
$payload = [
  'user_id' => (int)$user['id'],
  'email'   => $user['email'],
  'exp'     => time() + JWT_TTL_SECONDS,
];
$access = jwt_encode($payload);

// Estado de membresía
$mem = membership_of_user_id((int)$user['id']);
$active = $mem && $mem['active'] && strtotime($mem['expires_at']) >= time();

echo json_encode([
  'ok'=>true,
  'access_token'=>$access,
  'user'=>[
    'email'=>$user['email'],
    'nombre_docente'=>$user['nombre_docente'],
    'escuela'=>$user['escuela'],
    'nivel'=>$user['nivel'],
    'grado'=>$user['grado'],
    'grupo'=>$user['grupo'],
    'logo_url'=>$user['logo_url'],
  ],
  'membership'=>[
    'active'=>(bool)$active,
    'expires_at'=>$mem['expires_at'] ?? null
  ]
]);
