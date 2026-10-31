<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/auth.php';
require_auth();

// Evita que el navegador conserve copias del PDF generado.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Carga Dompdf, necesario para convertir el documento HTML en PDF.
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(503);
    exit('Dompdf no está instalado. Ejecuta "composer install" en la carpeta del proyecto y vuelve a intentarlo.');
}
require $autoload;

$quoteId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$quoteId || $quoteId < 1) {
    http_response_code(400);
    exit('El identificador de cotización no es válido.');
}

$materialPriceOption = $_GET['mostrar_precios_materiales'] ?? '1';
if (!is_string($materialPriceOption) || !in_array($materialPriceOption, ['0', '1'], true)) {
    http_response_code(400);
    exit('La opción de precios de materiales no es válida.');
}
$showMaterialPrices = $materialPriceOption === '1';

// Recupera la cotización, sus partidas y los importes calculados en servidor.
$statement = db()->prepare(
    'SELECT q.*, c.nombre AS cliente, c.telefono, c.correo, c.direccion
     FROM cotizaciones q INNER JOIN clientes c ON c.id = q.cliente_id WHERE q.id = :id'
);
$statement->execute(['id' => $quoteId]);
$quote = $statement->fetch();
if (!$quote) {
    http_response_code(404);
    exit('La cotización solicitada no existe.');
}

$statement = db()->prepare(
    'SELECT nombre_snapshot, descripcion_snapshot, unidad_snapshot, cantidad, precio_unitario_snapshot,
            ROUND(cantidad * precio_unitario_snapshot, 2) AS importe
     FROM cotizacion_materiales WHERE cotizacion_id = :id ORDER BY id'
);
$statement->execute(['id' => $quoteId]);
$materials = $statement->fetchAll();
$statement = db()->prepare(
    'SELECT nombre_snapshot, descripcion_snapshot, unidad_snapshot, cantidad, precio_unitario_snapshot,
            ROUND(cantidad * precio_unitario_snapshot, 2) AS importe
     FROM cotizacion_servicios WHERE cotizacion_id = :id ORDER BY id'
);
$statement->execute(['id' => $quoteId]);
$services = $statement->fetchAll();
$statement = db()->prepare(
    'SELECT
       COALESCE((SELECT SUM(ROUND(cantidad * precio_unitario_snapshot, 2)) FROM cotizacion_materiales WHERE cotizacion_id = :material_quote_id), 0) AS materials_total,
       COALESCE((SELECT SUM(ROUND(cantidad * precio_unitario_snapshot, 2)) FROM cotizacion_servicios WHERE cotizacion_id = :service_quote_id), 0) AS services_total,
       ROUND(
         COALESCE((SELECT SUM(ROUND(cantidad * precio_unitario_snapshot, 2)) FROM cotizacion_materiales WHERE cotizacion_id = :total_material_quote_id), 0)
         + COALESCE((SELECT SUM(ROUND(cantidad * precio_unitario_snapshot, 2)) FROM cotizacion_servicios WHERE cotizacion_id = :total_service_quote_id), 0), 2
       ) AS grand_total'
);
$statement->execute([
    'material_quote_id' => $quoteId,
    'service_quote_id' => $quoteId,
    'total_material_quote_id' => $quoteId,
    'total_service_quote_id' => $quoteId,
]);
$totals = $statement->fetch();

// Incrusta el logotipo local para que el PDF no dependa de recursos remotos.
$logoPath = __DIR__ . '/../imagen/logo.jpeg';
if (!is_file($logoPath)) {
    http_response_code(500);
    exit('No se encontró el logotipo de la empresa.');
}
$logoContents = file_get_contents($logoPath);
if ($logoContents === false) {
    http_response_code(500);
    exit('No se pudo leer el logotipo de la empresa.');
}
$logoDataUri = 'data:image/jpeg;base64,' . base64_encode($logoContents);

// Renderiza cada partida y permite ocultar precios cuando el cliente aporta materiales.
$renderItems = static function (array $items, bool $showPrices = true): string {
    if ($items === []) {
        return '<tr><td colspan="' . ($showPrices ? '4' : '2') . '" class="empty">Sin conceptos registrados.</td></tr>';
    }
    $html = '';
    foreach ($items as $item) {
        $html .= '<tr><td><strong>' . e($item['nombre_snapshot']) . '</strong>'
            . ($item['descripcion_snapshot'] ? '<br><span class="muted">' . e($item['descripcion_snapshot']) . '</span>' : '')
            . '</td><td>' . e(format_quantity($item['cantidad'])) . ' ' . e($item['unidad_snapshot']) . '</td>';
        if ($showPrices) {
            $html .= '<td>' . e(format_money($item['precio_unitario_snapshot'])) . '</td>'
                . '<td class="number">' . e(format_money($item['importe'])) . '</td>';
        }
        $html .= '</tr>';
    }

    return $html;
};

// Prepara fechas y encabezados reutilizables para las páginas del documento.
$formatPdfDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return 'No especificada';
    }

    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    return $parsedDate && $parsedDate->format('Y-m-d') === $date
        ? $parsedDate->format('d/m/Y')
        : $date;
};
$renderHeader = static function (string $section) use ($logoDataUri, $quote, $formatPdfDate): string {
    return '<div class="top"><table class="brand-layout"><tr>'
        . '<td class="brand-info"><img class="brand-logo" src="' . $logoDataUri . '"><div class="brand-block">'
        . '<div class="brand">E&amp;R Instalaciones Eléctricas</div><div class="subtitle">Soluciones eléctricas residenciales</div></div></td>'
        . '<td class="quote-info"><div class="eyebrow">' . e($section) . '</div><div class="folio">' . e($quote['folio']) . '</div>'
        . '<div class="quote-meta">Fecha: ' . e($formatPdfDate($quote['fecha'])) . ' &nbsp;·&nbsp; Vigencia: '
        . e($formatPdfDate($quote['vigencia_hasta'] ?: null)) . '</div></td></tr></table></div>';
};
$materialTotalRow = $showMaterialPrices
    ? '<tr><td>Subtotal de materiales</td><td class="number">' . e(format_money($totals['materials_total'])) . '</td></tr>'
    : '';
$proposalTotal = $showMaterialPrices ? $totals['grand_total'] : $totals['services_total'];
$totalLabel = $showMaterialPrices ? 'Total de la propuesta' : 'Total de mano de obra y servicios';
$summaryNotice = $showMaterialPrices
    ? 'No se agregan impuestos automáticamente. Los precios corresponden a los registrados al elaborar esta cotización.'
    : 'Los materiales serán suministrados por el cliente y no están incluidos en el total. No se agregan impuestos automáticamente.';

// Define las páginas y el formato visual que Dompdf convertirá al final.
$html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>
@page{margin:34px 42px 48px}
body{font-family:DejaVu Sans,sans-serif;color:#243b53;font-size:9px;line-height:1.45}
table{width:100%;border-collapse:collapse}
.top{border-bottom:3px solid #0878b9;padding-bottom:12px;margin-bottom:17px}
.brand-layout td{vertical-align:middle}
.brand-info{width:62%}
.brand-logo{width:54px;height:54px;vertical-align:middle}
.brand-block{display:inline-block;vertical-align:middle;margin-left:9px}
.brand{font-size:15px;font-weight:bold;color:#0b315b}
.subtitle{color:#738397;font-size:8px;margin-top:3px}
.quote-info{text-align:right}
.eyebrow{color:#738397;font-size:8px;font-weight:bold;letter-spacing:1px}
.folio{color:#0878b9;font-size:13px;font-weight:bold;margin:3px 0 5px}
.quote-meta{font-size:8px;color:#687b8f;line-height:1.6}
h1{font-size:19px;color:#0b315b;margin:0 0 12px}
.client{background:#f1f7fb;border:1px solid #dce8f0;border-left:3px solid #f58220;padding:9px 11px;margin:0 0 13px}
.client-heading{color:#738397;font-size:8px;font-weight:bold;letter-spacing:.5px;padding-bottom:5px}
.client-name{font-size:11px;font-weight:bold;color:#0b315b}
.client-details{color:#526579;font-size:8px;line-height:1.6}
.section-title{color:#0b315b;font-size:14px;font-weight:bold;margin:16px 0 8px;page-break-after:avoid}
.section-count{color:#78899a;font-size:8px;font-weight:normal}
thead{display:table-header-group}
th{background:#0b315b;color:#fff;text-align:left;padding:8px 9px;font-size:8px}
td{padding:8px 9px;border-bottom:1px solid #e6edf2;vertical-align:top}
tbody tr{page-break-inside:avoid}
td.number,th.number{text-align:right;white-space:nowrap}
.muted{color:#78899a;font-size:8px}
.empty{text-align:center;color:#78899a;padding:14px}
.summary{width:58%;margin:18px 0 0 auto;page-break-inside:avoid}
.summary td{padding:7px 9px}
.summary .grand td{background:#e8f4fb;border-top:2px solid #0878b9;color:#0b315b;font-size:12px;font-weight:bold;padding-top:10px;padding-bottom:10px}
.labor-total{width:58%;margin:16px 0 0 auto;page-break-inside:avoid}
.labor-total td{background:#e8f4fb;border-top:2px solid #0878b9;color:#0b315b;font-size:11px;font-weight:bold;padding:10px}
.labor-total .number{text-align:right;white-space:nowrap}
.note{color:#78899a;font-size:8px;line-height:1.5;margin:10px 0 0}
.notes{background:#fff7ed;border:1px solid #f6dfc7;border-left:3px solid #f58220;padding:8px 10px;margin:0 0 13px;color:#526579;line-height:1.5;page-break-inside:avoid}
.notes strong{display:block;color:#0b315b;margin-bottom:3px}
.additional-notes{margin-top:5px}
.page-break{page-break-after:always}
.footer{position:fixed;bottom:-29px;left:0;right:0;border-top:1px solid #e6edf2;padding-top:6px;color:#91a0ae;font-size:8px}
</style></head><body>
' . $renderHeader('COTIZACIÓN') . '
<h1>Propuesta de trabajo</h1>
<div class="client"><table><tr><td><div class="client-heading">PREPARADA PARA</div><div class="client-name">' . e($quote['cliente']) . '</div>
<div class="client-details">' . e($quote['direccion'] ?: 'Dirección no registrada')
    . ($quote['telefono'] ? ' · ' . e($quote['telefono']) : '')
    . ($quote['correo'] ? '<br>' . e($quote['correo']) : '') . '</div></td></tr></table></div>'
    . '<div class="notes"><strong>Notas y condiciones</strong><div>Cotización válida por 30 días o hasta agotar existencias.</div>'
    . ($quote['notas'] ? '<div class="additional-notes">' . nl2br(e($quote['notas'])) . '</div>' : '')
    . '</div>'
    . '<div class="section-title">Materiales <span class="section-count">(' . count($materials) . ' conceptos)</span></div>
<table><thead><tr><th style="width:' . ($showMaterialPrices ? '52%' : '76%') . '">Concepto</th><th style="width:' . ($showMaterialPrices ? '18%' : '24%') . '">Cantidad</th>'
    . ($showMaterialPrices ? '<th style="width:15%">Precio unitario</th><th class="number" style="width:15%">Importe</th>' : '')
    . '</tr></thead><tbody>' . $renderItems($materials, $showMaterialPrices) . '</tbody></table>'
    . ($showMaterialPrices ? '' : '<p class="note">Materiales suministrados por el cliente; sus precios no se muestran ni se incluyen en el total.</p>')
    . '<div class="page-break"></div>' . $renderHeader('MANO DE OBRA Y SERVICIOS') . '
<div class="section-title">Mano de obra y servicios <span class="section-count">(' . count($services) . ' conceptos)</span></div>
<table><thead><tr><th style="width:52%">Concepto</th><th style="width:18%">Cantidad</th><th style="width:15%">Precio unitario</th><th class="number" style="width:15%">Importe</th></tr></thead><tbody>' . $renderItems($services) . '</tbody></table>
<table class="labor-total"><tbody><tr><td>Total a cobrar por mano de obra y servicios</td><td class="number">' . e(format_money($totals['services_total'])) . '</td></tr></tbody></table>
<div class="page-break"></div>' . $renderHeader('RESUMEN DE LA COTIZACIÓN') . '
<h1>Resumen de importes</h1><table class="summary"><tbody>' . $materialTotalRow
    . '<tr><td>Subtotal de mano de obra y servicios</td><td class="number">' . e(format_money($totals['services_total'])) . '</td></tr>
<tr class="grand"><td>' . e($totalLabel) . '</td><td class="number">' . e(format_money($proposalTotal)) . '</td></tr></tbody></table>
<p class="note">' . e($summaryNotice) . '</p>'
    . '<div class="footer">E&amp;R Instalaciones Eléctricas · ' . e($quote['folio']) . '</div></body></html>';

// Renderiza el documento y lo envía en línea o como archivo descargable.
$options = new Dompdf\Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf\Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
$dompdf->getCanvas()->page_text(475, 755, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 8, [145, 160, 174]);
$inline = ($_GET['inline'] ?? '') === '1';
$dompdf->stream('cotizacion-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $quote['folio']) . '.pdf', ['Attachment' => !$inline]);
exit;
