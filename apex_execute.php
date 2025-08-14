<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

// Leer entrada JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['module']) || !isset($input['data'])) {
    echo json_encode(['error' => 'Solicitud inválida.']);
    exit;
}

$module = preg_replace('/[^a-zA-Z0-9_]/', '', $input['module']);
$module_file = __DIR__ . "/adapter_{$module}.php";

if (!file_exists($module_file)) {
    echo json_encode(['error' => "El módulo '{$module}' no existe."]);
    exit;
}

// Ejecutar módulo
require_once $module_file;
$response = apex_run($input['data']);

// Respuesta
echo json_encode([
    'status' => 'ok',
    'module' => $module,
    'result' => $response
], JSON_UNESCAPED_UNICODE);
