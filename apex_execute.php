<?php
/**
 * apex_execute_min.php
 * Loader mínimo y AUTÓNOMO para validar que PHP recibe JSON y responde.
 * No depende de adapters. Implementa dos módulos de prueba:
 *  - "ping"      → responde pong
 *  - "adia_plan" → devuelve eco de la planeación recibida
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/* CORS básico para pruebas (localhost y *.amecrec.org) */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && preg_match('~^https?://(localhost(:\d+)?|.*\.amecrec\.org)$~', $origin)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Vary: Origin");
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

/* Util */
function out(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

/* Entrada */
$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);
if (!is_array($in)) {
  out(400, ['ok'=>false, 'error'=>'JSON inválido', 'raw_len'=>strlen($raw)]);
}

$module = $in['module'] ?? $in['task'] ?? null;
$data   = $in['data']   ?? $in['payload'] ?? $in['entrada'] ?? [];

/* Ruteo mínimo */
switch ($module) {
  case 'ping':
    out(200, [
      'ok'      => true,
      'module'  => 'ping',
      'message' => 'pong',
      'echo'    => $data,
      'ts'      => date('c'),
    ]);

  case 'adia_plan':
    // respuesta de prueba (eco controlado)
    $grado     = trim((string)($data['grado'] ?? ''));
    $campo     = trim((string)($data['campo'] ?? ($data['campo_formativo'] ?? '')));
    $contenido = trim((string)($data['contenido'] ?? ''));
    $sesiones  = (int)($data['sesiones'] ?? ($data['numero_sesiones'] ?? 4));
    $duracion  = (int)($data['duracion'] ?? ($data['duracion_sesion'] ?? 45));
    $tema      = trim((string)($data['tema'] ?? ''));

    out(200, [
      'ok'     => true,
      'module' => 'adia_plan',
      'preview'=> [
        'resumen'  => "Plan para $grado · $campo · '$contenido' ($sesiones x $duracion min)".($tema? " · Tema: $tema":""),
        'entrada'  => $data
      ],
      'ts' => date('c')
    ]);

  default:
    out(404, [
      'ok'=>false,
      'error'=>"Módulo no reconocido",
      'module_recibido'=>$module,
      'keys'=>array_keys($in)
    ]);
}
