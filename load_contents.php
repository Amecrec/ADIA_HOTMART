<?php
require_once __DIR__ . '/config.php';

// Cargar el archivo JSON
$json_file = __DIR__ . '/CON_PLAN.json';
if (!file_exists($json_file)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'json_file_not_found']);
    exit;
}
$data = json_decode(file_get_contents($json_file), true);
if (!$data) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'invalid_json_format']);
    exit;
}

// Inserciones en la base de datos
$pdo = db();
$pdo->beginTransaction();

try {
    // Vaciar la tabla 'contents' para evitar duplicados en cada ejecución
    $pdo->exec("TRUNCATE TABLE contents");

    $insert_stmt = $pdo->prepare("INSERT INTO contents (nivel, grado, campo, clave, titulo, descripcion, estado) VALUES (?, ?, ?, ?, ?, ?, 'activo')");

    foreach ($data as $grado => $campos_formativos) {
        foreach ($campos_formativos as $campo => $contenido_data) {
            foreach ($contenido_data['byContenido'] as $titulo => $pdas) {
                // Generar una clave simple para el contenido
                $clave = "{$grado}-" . strtoupper(substr($campo, 0, 3)) . "-" . (isset($clave_counter) ? ++$clave_counter : $clave_counter = 1);
                $descripcion = implode('; ', $pdas); // Concatenar todos los PDAs en la descripción

                $insert_stmt->execute([
                    'Primaria', // Asumimos 'Primaria' para estos datos
                    $grado,
                    $campo,
                    $clave,
                    $titulo,
                    $descripcion
                ]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'message' => 'Contenidos cargados exitosamente.']);

} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'msg' => $e->getMessage()]);
}
?>
