<?php
/**
 * adapter_adia_plan.php
 * Adaptador ADIA → APEX (completo, autónomo y sin dependencias).
 * Expone apex_run(array $data) para que apex_execute.php pueda invocarlo.
 *
 * Entrada esperada (ejemplo):
 * {
 *   "grado": "Primaria",
 *   "campo": "Ciencias",
 *   "contenido": "Ecosistemas",
 *   "sesiones": 4,
 *   "duracion": 45,
 *   "tema": "Plantas y animales"
 * }
 *
 * Salida (resumen):
 * {
 *   "hipotesis": "...",
 *   "plan": "markdown ...",
 *   "tablas": [{ "id":"rubrica", "html":"..." }, { "id":"lista_cotejo", "html":"..." }],
 *   "acciones_hoy": ["...","..."],
 *   "m12_oferta": { "estado":"off|borrador|on", "texto":"..." }
 * }
 */

declare(strict_types=1);

/* ========= Helpers mínimos ========= */
function _txt(string $s): string { return trim($s); }
function _int($v, int $def): int { $n = (int)$v; return $n > 0 ? $n : $def; }
function _md_table(array $headers, array $rows): string {
  $out  = '| ' . implode(' | ', $headers) . " |\n";
  $out .= '| ' . implode(' | ', array_fill(0, count($headers), '---')) . " |\n";
  foreach ($rows as $r) { $out .= '| ' . implode(' | ', $r) . " |\n"; }
  return $out;
}

function _html_table(array $headers, array $rows): string {
  $h = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%">';
  $h .= '<thead><tr>';
  foreach ($headers as $th) $h .= '<th style="text-align:left;background:#f5f5f5">'.$th.'</th>';
  $h .= '</tr></thead><tbody>';
  foreach ($rows as $r) {
    $h .= '<tr>';
    foreach ($r as $td) $h .= '<td>'.htmlspecialchars((string)$td, ENT_QUOTES, 'UTF-8').'</td>';
    $h .= '</tr>';
  }
  $h .= '</tbody></table>';
  return $h;
}

/* ========= Núcleo: función que invoca el motor ========= */
if (!function_exists('apex_run')) {
  function apex_run(array $data): array {
    // 1) Validación básica de entrada
    $grado     = _txt($data['grado']     ?? '');
    $campo     = _txt($data['campo']     ?? ($data['campo_formativo'] ?? ''));
    $contenido = _txt($data['contenido'] ?? '');
    $tema      = _txt($data['tema']      ?? '');
    $sesiones  = _int($data['sesiones']  ?? ($data['numero_sesiones'] ?? 4), 4);
    $duracion  = _int($data['duracion']  ?? ($data['duracion_sesion'] ?? 45), 45);

    if ($grado === '' || $campo === '' || $contenido === '') {
      return ['ok'=>false,'error'=>'Faltan: grado, campo y contenido'];
    }

    // 2) Hipótesis Guiadora (resumen breve)
    $hipotesis = "Diseñar una planeación secuencial para $grado en el campo $campo, centrada en '$contenido', ".
                 "con $sesiones sesiones de $duracion minutos" . ($tema ? " y tema integrador '$tema'." : '.');

    // 3) Construcción de planeación en Markdown (plan + sesiones)
    $planMD  = "# Planeación — $grado / $campo\n\n";
    $planMD .= "**Contenido:** $contenido\n\n";
    if ($tema) $planMD .= "**Tema integrador:** $tema\n\n";
    $planMD .= "**Sesiones:** $sesiones × $duracion min\n\n";
    $planMD .= "## Objetivos\n";
    $planMD .= "- Desarrollar competencias clave en $campo.\n";
    $planMD .= "- Aplicar $contenido en situaciones cotidianas.\n\n";
    for ($i=1; $i <= $sesiones; $i++) {
      $planMD .= "### Sesión $i\n";
      $planMD .= "- **Apertura (10 min):** Activación de saberes previos.\n";
      $planMD .= "- **Desarrollo (".max(15, $duracion-20)." min):** Actividad guiada sobre *$contenido*.\n";
      $planMD .= "- **Cierre (10 min):** Reflexión y evidencias de aprendizaje.\n\n";
    }

    // 4) Tablas (rúbrica y lista de cotejo) en HTML
    $rubrica = _html_table(
      ['Criterio','Inicio','Básico','Progreso','Dominio'],
      [
        ['Comprensión del contenido', 'Identifica elementos aislados', 'Reconoce conceptos básicos', 'Relaciona y aplica', 'Explica y transfiere a nuevos contextos'],
        ['Participación y colaboración', 'Participa con apoyo', 'Participa de forma intermitente', 'Colabora activamente', 'Lidera y apoya a pares'],
        ['Producción/Evidencias', 'Producto incompleto', 'Producto básico', 'Producto completo y funcional', 'Producto destacado y creativo'],
      ]
    );
    $listaCotejo = _html_table(
      ['Indicador','Sí','No','Observaciones'],
      [
        ['Realiza actividad de apertura', '', '', ''],
        ["Aplica conceptos de $contenido", '', '', ''],
        ['Entrega evidencia final', '', '', ''],
      ]
    );

    // 5) Acciones HOY (72h)
    $acciones = [
      'Confirmar materiales (pizarrón, hojas, marcadores).',
      "Preparar guía breve de $contenido con ejemplos.",
      "Definir evidencias por sesión y criterios de evaluación."
    ];

    // 6) Política M12 (por defecto, OFF aquí; puedes poner "borrador" si quieres dispararlo)
    $m12 = [
      'estado' => 'off', // 'off' | 'borrador' | 'on'
      'texto'  => ''
    ];

    // 7) Respuesta estandarizada
    return [
      'ok'         => true,
      'hipotesis'  => $hipotesis,
      'plan'       => $planMD,
      'tablas'     => [
        ['id'=>'rubrica',       'html'=>$rubrica],
        ['id'=>'lista_cotejo',  'html'=>$listaCotejo]
      ],
      'acciones_hoy' => $acciones,
      'm12_oferta'   => $m12,
      'meta' => [
        'gates'   => ['G1'=>90,'G2'=>90,'G3'=>90,'G4'=>90,'G5'=>90],
        'version' => date('Ymd').'v1'
      ]
    ];
  }
}
