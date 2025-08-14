<?php
/**
 * apex_execute.php
 * Cargador/ejecutor del motor APEX con compatibilidad amplia de adapters.
 * NO requiere modificar tu adapter_adia_plan.php existente.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/* ====== CORS básico (permite tu subdominio y localhost) ====== */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && (preg_match('~^https?://(localhost(:\d+)?|.*\.amecrec\.org)$~', $origin))) {
  header("Access-Control-Allow-Origin: $origin");
  header("Vary: Origin");
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

/* ====== Util ====== */
function http_json(int $code, array $body): void {
  http_response_code($code);
  echo json_encode($body, JSON_UNESCAPED_UNICODE);
  exit;
}
function camelize(string $s): string {
  $s = preg_replace('/[^a-z0-9]+/i', ' ', $s);
  $s = ucwords(strtolower(trim($s)));
  return str_replace(' ', '', $s);
}

/* ====== Entrada ====== */
$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) {
  http_json(400, ['ok'=>false,'error'=>'JSON inválido']);
}
$module = $input['module'] ?? $input['task'] ?? 'adia_plan';
$data   = $input['data']   ?? $input['payload'] ?? $input['entrada'] ?? null;

if (!$module || !is_array($data)) {
  http_json(422, ['ok'=>false,'error'=>"Faltan 'module' y/o 'data'"]);
}

/* ====== Resolver archivo del adapter ====== */
/* Acepta: "adia_plan", "adia.plan", "adia/plan" → adapter_adia_plan.php */
$norm = strtolower(trim($module));
$norm = str_replace(['.', '/','\\'], '_', $norm);
$adapterFile = __DIR__ . "/adapter_{$norm}.php";
if (!is_file($adapterFile)) {
  http_json(404, ['ok'=>false,'error'=>"Módulo '{$module}' no encontrado (busqué adapter_{$norm}.php)"]);
}

/* ====== Cargar adapter sin imponer firma ====== */
$beforeFuncs  = get_defined_functions()['user'];
$b
