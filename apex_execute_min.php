<?php
// apex_execute_min.php — loader mínimo para diagnóstico
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true);

$module = is_array($in) ? ($in['module'] ?? null) : null;
$data   = is_array($in) ? ($in['data'] ?? $in['payload'] ?? $in['entrada'] ?? null) : null;

if (!$module) { echo json_encode(['ok'=>false,'error'=>'Falta module']); exit; }

$norm = strtolower(str_replace(['.','/','\\'],'_', $module));
$adapter = __DIR__ . "/adapter_{$norm}.php";

$out = [
  'ok' => true,
  'received' => ['keys'=> is_array($in)?array_keys($in):null, 'has_data'=>is_array($data)],
  'module' => $module,
  'adapter' => ['path'=>$adapter, 'exists'=>file_exists($adapter)]
];

if (!file_exists($adapter)) { echo json_encode($out); exit; }

// capturar cualquier echo del adapter
ob_start();
include $adapter;
$echoed = trim(ob_get_clean());

// si el adapter define apex_run($data), la ejecutamos
if (function_exists('apex_run') && is_array($data)) {
  try {
    $res = apex_run($data);
    $out['run_apex_run'] = true;
    $out['result'] = $res;
  } catch (Throwable $e) {
    $out['run_apex_run'] = false;
    $out['error_run'] = $e->getMessage();
  }
}

$out['adapter_echo_preview'] = mb_substr($echoed, 0, 200);

echo json_encode($out, JSON_UNESCAPED_UNICODE);
