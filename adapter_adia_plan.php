<?php
/**
 * adapter_adia_plan.php
 * Adaptador específico ADIA → APEX.
 * Toma datos de planeación y los envía al motor APEX.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['grado']) || !isset($input['campo_formativo']) || !isset($input['contenido'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Faltan datos obligatorios: grado, campo_formativo, contenido."
    ]);
    exit;
}

// Preparar payload para APEX
$apex_payload = [
    "module" => "adia_plan",
    "payload" => [
        "grado" => $input['grado'],
        "campo_formativo" => $input['campo_formativo'],
        "contenido" => $input['contenido'],
        "tema" => $input['tema'] ?? null,
        "numero_sesiones" => $input['numero_sesiones'] ?? null,
        "duracion_sesion" => $input['duracion_sesion'] ?? null
    ]
];

// Enviar a apex_execute.php internamente
$apex_url = __DIR__ . '/apex_execute.php';
ob_start();
include $apex_url;
$response = ob_get_clean();

// Responder
echo $response;
