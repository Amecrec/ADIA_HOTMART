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
$beforeClasses= get_declared_classes();
$beforeVars   = array_keys(get_defined_vars());

ob_start();
require_once $adapterFile; // tu adapter puede declarar funciones/clases/variables o incluso hacer echo
$adapterEcho = trim(ob_get_clean());

$afterFuncs   = get_defined_functions()['user'];
$afterClasses = get_declared_classes();
$newFuncs     = array_values(array_diff($afterFuncs, $beforeFuncs));
$newClasses   = array_values(array_diff($afterClasses, $beforeClasses));

/* ====== Estrategia de ejecución (por compatibilidad) ======
   1) Función apex_run($data)
   2) Función run_<module_normalizado>($data)  e.g. run_adia_plan
   3) Clase ApexAdapter con método run($data)
   4) Clase Adapter<Camello> con método run($data), ej. AdapterAdiaPlan
   5) Variable $APEX_RESPONSE definida por el adapter (array)
   6) Si el adapter produjo output JSON directo, lo devolvemos tal cual
*/
$response = null;
$errors   = [];

try {
  if (function_exists('apex_run')) {
    $response = apex_run($data);
  } elseif (function_exists("run_{$norm}")) {
    $fn = "run_{$norm}";
    $response = $fn($data);
  } elseif (class_exists('ApexAdapter') && method_exists('ApexAdapter','run')) {
    $obj = new ApexAdapter();
    $response = $obj->run($data);
  } else {
    // Buscar clase Adapter<Camello>
    $candClass = "Adapter" . camelize($norm); // p.ej. AdapterAdiaPlan
    if (class_exists($candClass) && method_exists($candClass,'run')) {
      $obj = new $candClass();
      $response = $obj->run($data);
    }
  }

  // ¿Variable global $APEX_RESPONSE?
  if ($response === null && array_key_exists('APEX_RESPONSE', $GLOBALS)) {
    $response = $GLOBALS['APEX_RESPONSE'];
  }

  // ¿El adapter hizo echo de JSON?
  if ($response === null && $adapterEcho !== '') {
    $maybe = json_decode($adapterEcho, true);
    if (is_array($maybe)) {
      $response = $maybe;
    } else {
      // si no es JSON, lo regresamos como texto informativo
      $response = ['adapter_output'=>$adapterEcho];
    }
  }

} catch (Throwable $e) {
  $errors[] = $e->getMessage();
}

/* ====== Validación y salida ====== */
if ($response === null) {
  http_json(500, [
    'ok'=>false,
    'error'=>'No se encontró forma de ejecutar el adapter',
    'module'=>$module,
    'buscado'=>[
      'function apex_run',
      "function run_{$norm}",
      'class ApexAdapter::run',
      'class Adapter'.camelize($norm).'::run',
      'var $APEX_RESPONSE',
      'echo JSON'
    ],
    'adapter_output_preview'=> mb_substr($adapterEcho, 0, 400),
    'errors'=>$errors
  ]);
}

http_json(200, [
  'ok'=>true,
  'module'=>$module,
  'result'=>$response
]);
