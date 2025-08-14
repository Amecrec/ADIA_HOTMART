<?php
// adapter_adia_plan.php
header('Content-Type: application/json');

// Respuesta de prueba
echo json_encode([
    "status" => "ok",
    "mensaje" => "Adapter ADIA Plan ejecutado correctamente",
    "datos_recibidos" => $_POST
]);
