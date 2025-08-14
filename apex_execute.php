<?php
/**
 * apex_execute.php
 * Motor central APEX — recibe solicitudes JSON y ejecuta la lógica solicitada.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Leer entrada JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['module']) || !isset($input['payload'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Formato de solicitud inválido. Debe contener 'module' y 'payload'."
    ]);
    exit;
}

// Simulación: motor APEX procesando
$response = [
    "status" => "success",
    "module" => $input['module'],
    "received_payload" => $input['payload'],
    "apex_output" => "Resultado procesado por APEX para el módulo: " . $input['module']
];

// Devolver respuesta
echo json_encode($response);
