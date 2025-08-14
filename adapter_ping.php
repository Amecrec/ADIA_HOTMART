<?php
// adapter_ping.php — adapter de prueba
declare(strict_types=1);

if (!function_exists('apex_run')) {
  function apex_run(array $data): array {
    return [
      'adapter'   => 'ping',
      'timestamp' => date('c'),
      'received'  => $data
    ];
  }
}

// También imprimimos algo por si el loader solo captura echo
echo json_encode(['echo'=>'adapter_ping echo ok', 'ts'=>time()]);
