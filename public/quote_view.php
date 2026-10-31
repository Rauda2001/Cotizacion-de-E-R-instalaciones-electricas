<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/auth.php';
require_auth();

$quoteId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$quoteId || $quoteId < 1) {
    http_response_code(400);
    exit('El identificador de cotización no es válido.');
}

$statement = db()->prepare(
    'SELECT q.*, c.nombre AS cliente, c.telefono, c.correo, c.direccion, u.nombre_usuario
     FROM cotizaciones q
     INNER JOIN clientes c ON c.id = q.cliente_id
     INNER JOIN usuarios u ON u.id = q.usuario_id
     WHERE q.id = :id'
);
$statement->execute(['id' => $quoteId]);
$quote = $statement->fetch();
if (!$quote) {
    http_response_code(404);
    exit('La cotización solicitada no existe.');
}

// Duplica la cotización en una transacción con folio anual nuevo y estado inicial.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reject_invalid_csrf();
    $action = post_string('action');
    if ($action === 'duplicate') {
        $pdo = db();
        $folioLockName = null;
        $duplicateId = 0;
        $duplicationError = null;
        try {
            $today = new DateTimeImmutable('today');
            $folioLockName = acquire_quote_folio_lock($pdo, $today);
            $folio = next_quote_folio($pdo, $today);
            $pdo->beginTransaction();
            $statement = $pdo->prepare(
                "INSERT INTO cotizaciones (folio, cliente_id, usuario_id, fecha, vigencia_hasta, estado, notas)
                 SELECT :folio, cliente_id, :usuario_id, :fecha, :vigencia_hasta, 'borrador', notas
                 FROM cotizaciones WHERE id = :id"
            );
            $statement->execute([
                'folio' => $folio,
                'usuario_id' => $_SESSION['user_id'],
                'fecha' => $today->format('Y-m-d'),
                'vigencia_hasta' => $today->modify('+30 days')->format('Y-m-d'),
                'id' => $quoteId,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new QuoteValidationException('No se pudo encontrar la cotización que deseas duplicar.');
            }
            $duplicateId = (int) $pdo->lastInsertId();

            $statement = $pdo->prepare(
                'INSERT INTO cotizacion_materiales
                 (cotizacion_id, material_id, nombre_snapshot, descripcion_snapshot, unidad_snapshot, cantidad, precio_unitario_snapshot)
                 SELECT :duplicate_id, material_id, nombre_snapshot, descripcion_snapshot, unidad_snapshot, cantidad, precio_unitario_snapshot
                 FROM cotizacion_materiales WHERE cotizacion_id = :source_id'
            );
            $statement->execute(['duplicate_id' => $duplicateId, 'source_id' => $quoteId]);

            $statement = $pdo->prepare(
                'INSERT INTO cotizacion_servicios
                 (cotizacion_id, servicio_id, nombre_snapshot, descripcion_snapshot, unidad_snapshot, cantidad, precio_unitario_snapshot)
                 SELECT :duplicate_id, servicio_id, nombre_snapshot, descripcion_snapshot, unidad_snapshot, cantidad, precio_unitario_snapshot
                 FROM cotizacion_servicios WHERE cotizacion_id = :source_id'
            );
            $statement->execute(['duplicate_id' => $duplicateId, 'source_id' => $quoteId]);

            $pdo->commit();
        } catch (QuoteValidationException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $duplicationError = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        } finally {
            if ($folioLockName !== null) {
                release_quote_folio_lock($pdo, $folioLockName);
            }
        }

        if ($duplicationError !== null) {
            set_flash('danger', $duplicationError);
            redirect_to('quote_view.php?id=' . $quoteId);
        }

        set_flash('success', 'Se creó la copia ' . $folio . ' como borrador. Puedes modificarla sin afectar la cotización original.');
        redirect_to('quote_form.php?id=' . $duplicateId);
    }

    http_response_code(400);
    exit('La acción solicitada no es válida.');
}

// Recupera las partidas guardadas y calcula subtotales para la vista de detalle.
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
$pageTitle = 'Cotización ' . $quote['folio'];
$pageKey = 'quotes';

require __DIR__ . '/../app/views/header.php';
?>
<!-- Acciones para consultar, editar, duplicar y exportar esta cotización. -->
<div class="page-heading">
    <div><div class="eyebrow">DETALLE DE PROPUESTA</div><h1><?= e($quote['folio']) ?></h1><p><?= e($quote['cliente']) ?> · Fecha <?= e($quote['fecha']) ?></p></div>
    <div class="page-actions">
        <a class="btn btn-outline-primary" id="view-quote-pdf" href="quote_pdf.php?id=<?= (int) $quoteId ?>&amp;inline=1&amp;mostrar_precios_materiales=1" target="_blank" rel="noopener">Ver PDF</a>
        <button class="btn btn-outline-primary" id="share-quote" type="button"
                data-pdf-url="quote_pdf.php?id=<?= (int) $quoteId ?>&amp;mostrar_precios_materiales=1"
                data-file-name="cotizacion-<?= e(preg_replace('/[^A-Za-z0-9_-]/', '-', $quote['folio'])) ?>.pdf"
                data-title="Cotización <?= e($quote['folio']) ?>">Compartir PDF</button>
        <a class="btn btn-outline-secondary" id="download-quote-pdf" href="quote_pdf.php?id=<?= (int) $quoteId ?>&amp;mostrar_precios_materiales=1">Descargar PDF</a>
        <a class="btn btn-primary" href="quote_form.php?id=<?= (int) $quoteId ?>">Editar / agregar artículos</a>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="duplicate">
            <button class="btn btn-outline-primary" type="submit">Duplicar cotización</button>
        </form>
        <a class="btn btn-outline-secondary" href="quotes.php">Volver</a>
    </div>
</div>
<section class="form-card mb-3">
    <div class="form-check form-switch">
        <input class="form-check-input" id="show-material-prices" type="checkbox" role="switch" checked>
        <label class="form-check-label" for="show-material-prices">Mostrar precios de materiales en el PDF</label>
    </div>
    <div class="form-text">Si lo desactivas, el PDF indicará que el cliente aporta los materiales y el total incluirá solo mano de obra y servicios.</div>
</section>
<div id="share-feedback" class="alert d-none" role="status"></div>
<div class="row g-4">
    <div class="col-12 col-xl-8">
        <section class="data-card mb-4">
            <div class="card-heading"><div><h2>Cliente</h2><p>Datos registrados al consultar la cotización.</p></div></div>
            <div class="client-summary">
                <div><small>Nombre</small><strong><?= e($quote['cliente']) ?></strong></div>
                <div><small>Teléfono</small><strong><?= e($quote['telefono'] ?: '—') ?></strong></div>
                <div><small>Correo</small><strong><?= e($quote['correo'] ?: '—') ?></strong></div>
                <div><small>Vigencia</small><strong><?= e($quote['vigencia_hasta'] ?: 'Sin fecha') ?></strong></div>
            </div>
        </section>
        <?php foreach ([['title' => 'Materiales', 'items' => $materials], ['title' => 'Mano de obra y servicios', 'items' => $services]] as $section): ?>
            <section class="data-card mb-4">
                <div class="card-heading"><div><h2><?= e($section['title']) ?></h2><p><?= count($section['items']) ?> concepto(s)</p></div></div>
                <div class="table-responsive">
                    <table class="table align-middle data-table">
                        <thead><tr><th>Concepto</th><th>Cantidad</th><th>Precio unitario</th><th class="text-end">Importe</th></tr></thead>
                        <tbody>
                        <?php foreach ($section['items'] as $item): ?>
                            <tr><td><strong><?= e($item['nombre_snapshot']) ?></strong><small><?= e($item['descripcion_snapshot'] ?? '') ?></small></td>
                                <td><?= e(format_quantity($item['cantidad'])) ?> <?= e($item['unidad_snapshot']) ?></td>
                                <td><?= e(format_money($item['precio_unitario_snapshot'])) ?></td>
                                <td class="text-end"><strong><?= e(format_money($item['importe'])) ?></strong></td></tr>
                        <?php endforeach; ?>
                        <?php if ($section['items'] === []): ?><tr><td colspan="4" class="empty-state">Sin conceptos.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
    <div class="col-12 col-xl-4">
        <section class="form-card quote-total-card">
            <div class="eyebrow">RESUMEN</div><h2>Importes</h2>
            <div class="total-row"><span>Subtotal materiales</span><strong><?= e(format_money($totals['materials_total'])) ?></strong></div>
            <div class="total-row"><span>Subtotal mano de obra</span><strong><?= e(format_money($totals['services_total'])) ?></strong></div>
            <div class="total-row grand-total"><span>Total</span><strong><?= e(format_money($totals['grand_total'])) ?></strong></div>
            <p class="total-note">No se agregan impuestos automáticamente.</p>
        </section>
        <?php if ($quote['notas']): ?><section class="form-card mt-4"><div class="eyebrow">NOTAS</div><p class="quote-notes"><?= nl2br(e($quote['notas'])) ?></p></section><?php endif; ?>
    </div>
</div>
<script>
    (() => {
        const button = document.getElementById('share-quote');
        const feedback = document.getElementById('share-feedback');
        const priceOption = document.getElementById('show-material-prices');
        const pdfLinks = [
            document.getElementById('view-quote-pdf'),
            document.getElementById('download-quote-pdf'),
        ];
        const baseFileName = button.dataset.fileName;

        // Aplica la opción de precios a los enlaces de ver, descargar y compartir.
        function updatePdfOptions() {
            const showPrices = priceOption.checked;
            const priceValue = showPrices ? '1' : '0';
            const shareUrl = new URL(button.dataset.pdfUrl, window.location.href);
            shareUrl.searchParams.set('mostrar_precios_materiales', priceValue);
            button.dataset.pdfUrl = shareUrl.toString();
            button.dataset.fileName = showPrices
                ? baseFileName
                : baseFileName.replace(/\.pdf$/, '-sin-precios-de-materiales.pdf');

            for (const link of pdfLinks) {
                const url = new URL(link.href, window.location.href);
                url.searchParams.set('mostrar_precios_materiales', priceValue);
                link.href = url.toString();
            }
        }

        priceOption.addEventListener('change', updatePdfOptions);
        updatePdfOptions();

        // Muestra el resultado de compartir o descargar al usuario.
        function showFeedback(message, type = 'info') {
            feedback.className = `alert alert-${type}`;
            feedback.textContent = message;
        }

        // Genera el PDF con la opción actual y lo comparte si el navegador lo permite.
        button.addEventListener('click', async () => {
            if (!navigator.share || !navigator.canShare) {
                showFeedback('Este navegador no permite compartir archivos directamente. Usa “Ver PDF” para abrir la cotización y compartirla desde el visor.', 'warning');
                return;
            }

            button.disabled = true;
            try {
                const response = await fetch(button.dataset.pdfUrl);
                if (!response.ok) {
                    throw new Error(`No se pudo generar el PDF (HTTP ${response.status}).`);
                }
                const file = new File([await response.blob()], button.dataset.fileName, { type: 'application/pdf' });
                if (!navigator.canShare({ files: [file] })) {
                    showFeedback('Este dispositivo no permite compartir archivos PDF directamente. Usa “Ver PDF” para abrir la cotización y compartirla desde el visor.', 'warning');
                    return;
                }

                await navigator.share({ files: [file], title: button.dataset.title });
                showFeedback('Cotización compartida correctamente.', 'success');
            } catch (error) {
                if (error.name !== 'AbortError') {
                    showFeedback(error.message || 'Ocurrió un error al compartir la cotización.', 'danger');
                }
            } finally {
                button.disabled = false;
            }
        });
    })();
</script>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
