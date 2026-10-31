<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/auth.php';
require_auth();

$pageKey = 'quotes';
$pageTitle = 'Nueva cotización';
$errors = [];
$quoteId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$quote = [
    'cliente_id' => '',
    'fecha' => date('Y-m-d'),
    'vigencia_hasta' => (new DateTimeImmutable('today'))->modify('+30 days')->format('Y-m-d'),
    'notas' => '',
];
$materialLines = [];
$serviceLines = [];

// Al editar, carga la cotización y sus partidas para continuar el mismo documento.
if ($quoteId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $statement = db()->prepare('SELECT * FROM cotizaciones WHERE id = :id');
    $statement->execute(['id' => $quoteId]);
    $existingQuote = $statement->fetch();
    if (!$existingQuote) {
        set_flash('warning', 'La cotización solicitada no existe.');
        redirect_to('quotes.php');
    }
    $quote = $existingQuote;
    $quoteDate = DateTime::createFromFormat('!Y-m-d', (string) $quote['fecha']);
    if ($quoteDate && $quoteDate->format('Y-m-d') === $quote['fecha']) {
        $quote['vigencia_hasta'] = $quoteDate->modify('+30 days')->format('Y-m-d');
    }
    $statement = db()->prepare('SELECT id, material_id AS item_id, nombre_snapshot, unidad_snapshot, cantidad, precio_unitario_snapshot FROM cotizacion_materiales WHERE cotizacion_id = :id ORDER BY id');
    $statement->execute(['id' => $quoteId]);
    $materialLines = $statement->fetchAll();
    $statement = db()->prepare('SELECT id, servicio_id AS item_id, nombre_snapshot, unidad_snapshot, cantidad, precio_unitario_snapshot FROM cotizacion_servicios WHERE cotizacion_id = :id ORDER BY id');
    $statement->execute(['id' => $quoteId]);
    $serviceLines = $statement->fetchAll();
}

// Valida los datos enviados y prepara las partidas para crear o actualizar.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reject_invalid_csrf();
    $quoteId = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT) ?: 0;
    $quote['cliente_id'] = post_string('cliente_id');
    $quote['fecha'] = post_string('fecha');
    $quote['vigencia_hasta'] = '';
    $quote['notas'] = post_string('notas');

    $rawMaterialIds = is_array($_POST['material_id'] ?? null) ? $_POST['material_id'] : [];
    $rawMaterialQtys = is_array($_POST['material_qty'] ?? null) ? $_POST['material_qty'] : [];
    $rawMaterialExisting = is_array($_POST['material_line_id'] ?? null) ? $_POST['material_line_id'] : [];
    $rawServiceIds = is_array($_POST['service_id'] ?? null) ? $_POST['service_id'] : [];
    $rawServiceQtys = is_array($_POST['service_qty'] ?? null) ? $_POST['service_qty'] : [];
    $rawServiceExisting = is_array($_POST['service_line_id'] ?? null) ? $_POST['service_line_id'] : [];

    $clientId = filter_var($quote['cliente_id'], FILTER_VALIDATE_INT);
    if (!$clientId || $clientId < 1) {
        $errors[] = 'Selecciona un cliente.';
    } else {
        $statement = db()->prepare('SELECT id FROM clientes WHERE id = :id');
        $statement->execute(['id' => $clientId]);
        if (!$statement->fetchColumn()) {
            $errors[] = 'El cliente seleccionado no existe.';
        }
    }
    $date = DateTime::createFromFormat('!Y-m-d', $quote['fecha']);
    if (!$date || $date->format('Y-m-d') !== $quote['fecha']) {
        $errors[] = 'La fecha de cotización no es válida.';
    } else {
        $quote['vigencia_hasta'] = $date->modify('+30 days')->format('Y-m-d');
    }
    $materialRows = [];
    foreach ($rawMaterialIds as $index => $rawId) {
        $itemId = is_string($rawId) || is_int($rawId) ? filter_var((string) $rawId, FILTER_VALIDATE_INT) : false;
        $quantity = isset($rawMaterialQtys[$index]) && is_string($rawMaterialQtys[$index]) ? trim($rawMaterialQtys[$index]) : '';
        $existingId = isset($rawMaterialExisting[$index]) && is_string($rawMaterialExisting[$index])
            ? (filter_var($rawMaterialExisting[$index], FILTER_VALIDATE_INT) ?: 0) : 0;
        if (!$itemId && $quantity === '') {
            continue;
        }
        if (!$itemId || $itemId < 1 || $quantity === '') {
            $errors[] = 'Completa el concepto y la cantidad de cada renglón de materiales.';
            continue;
        }
        if (!valid_decimal($quantity, 3, false)) {
            $errors[] = 'Las cantidades de materiales deben ser mayores a cero y tener máximo tres decimales.';
            continue;
        }
        $materialRows[] = ['item_id' => $itemId, 'quantity' => $quantity, 'existing_id' => $existingId];
    }

    $serviceRows = [];
    foreach ($rawServiceIds as $index => $rawId) {
        $itemId = is_string($rawId) || is_int($rawId) ? filter_var((string) $rawId, FILTER_VALIDATE_INT) : false;
        $quantity = isset($rawServiceQtys[$index]) && is_string($rawServiceQtys[$index]) ? trim($rawServiceQtys[$index]) : '';
        $existingId = isset($rawServiceExisting[$index]) && is_string($rawServiceExisting[$index])
            ? (filter_var($rawServiceExisting[$index], FILTER_VALIDATE_INT) ?: 0) : 0;
        if (!$itemId && $quantity === '') {
            continue;
        }
        if (!$itemId || $itemId < 1 || $quantity === '') {
            $errors[] = 'Completa el concepto y la cantidad de cada renglón de servicios.';
            continue;
        }
        if (!valid_decimal($quantity, 3, false)) {
            $errors[] = 'Las cantidades de servicios deben ser mayores a cero y tener máximo tres decimales.';
            continue;
        }
        $serviceRows[] = ['item_id' => $itemId, 'quantity' => $quantity, 'existing_id' => $existingId];
    }

    if ($materialRows === [] && $serviceRows === []) {
        $errors[] = 'Agrega al menos un material o servicio a la cotización.';
    }

    // Guarda encabezado y partidas en una transacción para evitar cotizaciones parciales.
    if ($errors === []) {
        $pdo = db();
        $folioLockName = null;
        $savedQuoteId = 0;
        try {
            if ($quoteId === 0) {
                $folioDate = new DateTimeImmutable($quote['fecha']);
                $folioLockName = acquire_quote_folio_lock($pdo, $folioDate);
                $folio = next_quote_folio($pdo, $folioDate);
            }
            $pdo->beginTransaction();
            $oldMaterialSnapshots = [];
            $oldServiceSnapshots = [];
            if ($quoteId > 0) {
                $statement = $pdo->prepare(
                    'UPDATE cotizaciones SET cliente_id = :cliente_id, fecha = :fecha,
                     vigencia_hasta = :vigencia_hasta, notas = :notas
                     WHERE id = :id'
                );
                $statement->execute([
                    'cliente_id' => $clientId,
                    'fecha' => $quote['fecha'],
                    'vigencia_hasta' => $quote['vigencia_hasta'] !== '' ? $quote['vigencia_hasta'] : null,
                    'notas' => $quote['notas'] !== '' ? $quote['notas'] : null,
                    'id' => $quoteId,
                ]);
                if ($statement->rowCount() === 0) {
                    $check = $pdo->prepare('SELECT id FROM cotizaciones WHERE id = :id');
                    $check->execute(['id' => $quoteId]);
                    if (!$check->fetchColumn()) {
                        throw new QuoteValidationException('La cotización que intentas editar ya no existe.');
                    }
                }
                $folioStatement = $pdo->prepare('SELECT folio FROM cotizaciones WHERE id = :id');
                $folioStatement->execute(['id' => $quoteId]);
                $folio = (string) $folioStatement->fetchColumn();
                $snapshotStatement = $pdo->prepare(
                    'SELECT id, material_id, nombre_snapshot, descripcion_snapshot, unidad_snapshot, precio_unitario_snapshot
                     FROM cotizacion_materiales WHERE cotizacion_id = :id'
                );
                $snapshotStatement->execute(['id' => $quoteId]);
                $oldMaterialSnapshots = [];
                foreach ($snapshotStatement->fetchAll() as $snapshot) {
                    $oldMaterialSnapshots[(int) $snapshot['id']] = $snapshot;
                }
                $snapshotStatement = $pdo->prepare(
                    'SELECT id, servicio_id, nombre_snapshot, descripcion_snapshot, unidad_snapshot, precio_unitario_snapshot
                     FROM cotizacion_servicios WHERE cotizacion_id = :id'
                );
                $snapshotStatement->execute(['id' => $quoteId]);
                $oldServiceSnapshots = [];
                foreach ($snapshotStatement->fetchAll() as $snapshot) {
                    $oldServiceSnapshots[(int) $snapshot['id']] = $snapshot;
                }
                $pdo->prepare('DELETE FROM cotizacion_materiales WHERE cotizacion_id = :id')->execute(['id' => $quoteId]);
                $pdo->prepare('DELETE FROM cotizacion_servicios WHERE cotizacion_id = :id')->execute(['id' => $quoteId]);
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO cotizaciones (folio, cliente_id, usuario_id, fecha, vigencia_hasta, notas)
                     VALUES (:folio, :cliente_id, :usuario_id, :fecha, :vigencia_hasta, :notas)'
                );
                $statement->execute([
                    'folio' => $folio,
                    'cliente_id' => $clientId,
                    'usuario_id' => $_SESSION['user_id'],
                    'fecha' => $quote['fecha'],
                    'vigencia_hasta' => $quote['vigencia_hasta'] !== '' ? $quote['vigencia_hasta'] : null,
                    'notas' => $quote['notas'] !== '' ? $quote['notas'] : null,
                ]);
                $quoteId = (int) $pdo->lastInsertId();
            }

            $insertMaterial = $pdo->prepare(
                'INSERT INTO cotizacion_materiales
                 (cotizacion_id, material_id, nombre_snapshot, descripcion_snapshot, unidad_snapshot, cantidad, precio_unitario_snapshot)
                 VALUES (:cotizacion_id, :material_id, :nombre, :descripcion, :unidad, :cantidad, :precio)'
            );
            $materialLookup = $pdo->prepare('SELECT id, nombre, descripcion, unidad, precio_unitario, activo FROM materiales WHERE id = :id');
            foreach ($materialRows as $line) {
                $materialLookup->execute(['id' => $line['item_id']]);
                $item = $materialLookup->fetch();
                $old = $quoteId > 0 ? ($oldMaterialSnapshots[$line['existing_id']] ?? false) : false;
                $isPreserved = $old && (int) $old['material_id'] === (int) $line['item_id'];
                if (!$item || (!(int) $item['activo'] && !$isPreserved)) {
                    throw new QuoteValidationException('Uno de los materiales seleccionados ya no está disponible.');
                }
                if ($isPreserved) {
                    $item = [
                        'id' => $item['id'],
                        'nombre' => $old['nombre_snapshot'],
                        'descripcion' => $old['descripcion_snapshot'],
                        'unidad' => $old['unidad_snapshot'],
                        'precio_unitario' => $old['precio_unitario_snapshot'],
                    ];
                }
                $insertMaterial->execute([
                    'cotizacion_id' => $quoteId,
                    'material_id' => $item['id'],
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'],
                    'unidad' => $item['unidad'],
                    'cantidad' => $line['quantity'],
                    'precio' => $item['precio_unitario'],
                ]);
            }

            $insertService = $pdo->prepare(
                'INSERT INTO cotizacion_servicios
                 (cotizacion_id, servicio_id, nombre_snapshot, descripcion_snapshot, unidad_snapshot, cantidad, precio_unitario_snapshot)
                 VALUES (:cotizacion_id, :servicio_id, :nombre, :descripcion, :unidad, :cantidad, :precio)'
            );
            $serviceLookup = $pdo->prepare('SELECT id, nombre, descripcion, unidad, precio_unitario, activo FROM servicios WHERE id = :id');
            foreach ($serviceRows as $line) {
                $serviceLookup->execute(['id' => $line['item_id']]);
                $item = $serviceLookup->fetch();
                $old = $quoteId > 0 ? ($oldServiceSnapshots[$line['existing_id']] ?? false) : false;
                $isPreserved = $old && (int) $old['servicio_id'] === (int) $line['item_id'];
                if (!$item || (!(int) $item['activo'] && !$isPreserved)) {
                    throw new QuoteValidationException('Uno de los servicios seleccionados ya no está disponible.');
                }
                if ($isPreserved) {
                    $item = [
                        'id' => $item['id'],
                        'nombre' => $old['nombre_snapshot'],
                        'descripcion' => $old['descripcion_snapshot'],
                        'unidad' => $old['unidad_snapshot'],
                        'precio_unitario' => $old['precio_unitario_snapshot'],
                    ];
                }
                $insertService->execute([
                    'cotizacion_id' => $quoteId,
                    'servicio_id' => $item['id'],
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'],
                    'unidad' => $item['unidad'],
                    'cantidad' => $line['quantity'],
                    'precio' => $item['precio_unitario'],
                ]);
            }

            $pdo->commit();
            set_flash('success', 'La cotización ' . $folio . ' se guardó correctamente.');
            $savedQuoteId = $quoteId;
        } catch (QuoteValidationException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getMessage();
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

        if ($savedQuoteId > 0) {
            redirect_to('quote_view.php?id=' . $savedQuoteId);
        }
    }

    $materialLines = [];
    foreach ($materialRows as $line) {
        $materialLines[] = ['item_id' => $line['item_id'], 'cantidad' => $line['quantity'], 'id' => $line['existing_id']];
    }
    $serviceLines = [];
    foreach ($serviceRows as $line) {
        $serviceLines[] = ['item_id' => $line['item_id'], 'cantidad' => $line['quantity'], 'id' => $line['existing_id']];
    }
}

// Carga las opciones de cliente y los catálogos usados por el formulario.
$clients = db()->query('SELECT id, nombre FROM clientes ORDER BY nombre')->fetchAll();
$materials = db()->query('SELECT id, nombre, unidad, precio_unitario, activo FROM materiales ORDER BY nombre')->fetchAll();
$services = db()->query('SELECT id, nombre, unidad, precio_unitario, activo FROM servicios ORDER BY nombre')->fetchAll();

require __DIR__ . '/../app/views/header.php';
?>
<!-- Presenta el formulario de datos, selector de conceptos y resumen calculado. -->
<div class="page-heading">
    <div><div class="eyebrow">PROPUESTA COMERCIAL</div><h1><?= $quoteId > 0 ? 'Editar cotización' : 'Nueva cotización' ?></h1><p><?= $quoteId > 0 ? 'Folio: ' . e((string) $quote['folio']) . ' · Agrega artículos o modifica las partidas. Al guardar, se actualizará esta misma cotización.' : 'Agrega materiales y mano de obra en un mismo documento.' ?></p></div>
    <a class="btn btn-outline-secondary" href="quotes.php">Volver al historial</a>
</div>
<?php if ($clients === []): ?>
    <div class="alert alert-warning">Primero agrega un cliente para poder crear cotizaciones. <a href="clients.php">Ir a clientes</a></div>
<?php elseif ($materials === [] && $services === []): ?>
    <div class="alert alert-warning">Agrega materiales o servicios a los catálogos antes de crear una cotización. <a href="materials.php">Ir a materiales</a> · <a href="services.php">Ir a servicios</a></div>
<?php else: ?>
    <?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" id="quote-form">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $quoteId ?>">
        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <section class="form-card mb-4">
                    <div class="card-heading"><div><h2>Datos de la cotización</h2><p>Define cliente, fechas y seguimiento.</p></div></div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6"><label class="form-label" for="cliente_id">Cliente *</label>
                            <select class="form-select" id="cliente_id" name="cliente_id" required><option value="">Selecciona un cliente</option><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>" <?= (string) $quote['cliente_id'] === (string) $client['id'] ? 'selected' : '' ?>><?= e($client['nombre']) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="col-6 col-md-3"><label class="form-label" for="fecha">Fecha *</label><input class="form-control" id="fecha" name="fecha" type="date" required value="<?= e((string) $quote['fecha']) ?>"></div>
                        <div class="col-6 col-md-3"><label class="form-label" for="vigencia_hasta">Vigencia hasta</label><input class="form-control" id="vigencia_hasta" name="vigencia_hasta" type="date" value="<?= e((string) ($quote['vigencia_hasta'] ?? '')) ?>" readonly aria-describedby="vigencia-ayuda"><div id="vigencia-ayuda" class="form-text">Se calcula a 30 días de la fecha.</div></div>
                        <div class="col-12"><label class="form-label" for="notas">Notas y condiciones</label><textarea class="form-control" id="notas" name="notas" rows="2"><?= e((string) $quote['notas']) ?></textarea></div>
                    </div>
                </section>

                <?php foreach ([
                    ['kind' => 'material', 'title' => 'Materiales', 'lines' => $materialLines, 'items' => $materials, 'id_name' => 'material_id[]', 'qty_name' => 'material_qty[]', 'line_name' => 'material_line_id[]'],
                    ['kind' => 'service', 'title' => 'Mano de obra y servicios', 'lines' => $serviceLines, 'items' => $services, 'id_name' => 'service_id[]', 'qty_name' => 'service_qty[]', 'line_name' => 'service_line_id[]'],
                ] as $section): ?>
                    <section class="form-card mb-4 quote-lines" data-line-kind="<?= e($section['kind']) ?>">
                        <div class="card-heading">
                            <div><h2><?= e($section['title']) ?></h2><p>Los importes se toman del catálogo al guardar.</p></div>
                        </div>
                        <div class="line-item-select item-picker">
                            <label class="form-label" for="item-search-<?= e($section['kind']) ?>">Buscar <?= e($section['title']) ?></label>
                            <input id="item-search-<?= e($section['kind']) ?>" class="form-control item-search" type="search" placeholder="Escribe el nombre del producto..." aria-label="Buscar <?= e($section['title']) ?> por nombre" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false">
                            <div class="item-suggestions" role="listbox" hidden></div>
                        </div>
                        <div class="line-list">
                            <?php foreach ($section['lines'] as $line): ?>
                                <div class="quote-line">
                                    <input type="hidden" name="<?= e($section['line_name']) ?>" value="<?= (int) ($line['id'] ?? 0) ?>">
                                    <div class="line-item-select">
                                        <label class="form-label">Concepto</label>
                                        <div class="form-control line-item-name"><?= e((string) ($line['nombre_snapshot'] ?? '')) ?></div>
                                        <select class="form-select line-item visually-hidden" name="<?= e($section['id_name']) ?>" tabindex="-1" aria-hidden="true">
                                            <option value="">Selecciona...</option>
                                            <?php foreach ($section['items'] as $item): ?>
                                                <option value="<?= (int) $item['id'] ?>" data-price="<?= e((string) ((string) ($line['item_id'] ?? '') === (string) $item['id'] ? ($line['precio_unitario_snapshot'] ?? $item['precio_unitario']) : $item['precio_unitario'])) ?>" data-unit="<?= e($item['unidad']) ?>" <?= (string) ($line['item_id'] ?? '') === (string) $item['id'] ? 'selected' : '' ?>>
                                                    <?= e($item['nombre']) ?><?= !(int) $item['activo'] ? ' (inactivo)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="line-quantity"><label class="form-label">Cantidad</label><input class="form-control line-qty" name="<?= e($section['qty_name']) ?>" type="number" min="0.001" step="0.001" value="<?= e((string) ($line['cantidad'] ?? '1')) ?>"></div>
                                    <div class="line-price"><label class="form-label">P. unitario</label><div class="line-price-value">Se calcula al guardar</div></div>
                                    <div class="line-remove"><label class="form-label d-block">&nbsp;</label><button type="button" class="btn btn-outline-danger remove-line" aria-label="Quitar renglón">×</button></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <template class="line-template">
                            <div class="quote-line">
                                <input type="hidden" name="<?= e($section['line_name']) ?>" value="0">
                                <div class="line-item-select"><label class="form-label">Concepto</label><div class="form-control line-item-name"></div><select class="form-select line-item visually-hidden" name="<?= e($section['id_name']) ?>" tabindex="-1" aria-hidden="true"><option value="">Selecciona...</option><?php foreach ($section['items'] as $item): if (!(int) $item['activo']) { continue; } ?><option value="<?= (int) $item['id'] ?>" data-price="<?= e((string) $item['precio_unitario']) ?>" data-unit="<?= e($item['unidad']) ?>"><?= e($item['nombre']) ?></option><?php endforeach; ?></select></div>
                                <div class="line-quantity"><label class="form-label">Cantidad</label><input class="form-control line-qty" name="<?= e($section['qty_name']) ?>" type="number" min="0.001" step="0.001" value="1"></div>
                                <div class="line-price"><label class="form-label">P. unitario</label><div class="line-price-value">Se calcula al guardar</div></div>
                                <div class="line-remove"><label class="form-label d-block">&nbsp;</label><button type="button" class="btn btn-outline-danger remove-line" aria-label="Quitar renglón">×</button></div>
                            </div>
                        </template>
                    </section>
                <?php endforeach; ?>
            </div>
            <div class="col-12 col-xl-4">
                <section class="form-card quote-total-card">
                    <div class="eyebrow">RESUMEN</div><h2>Totales</h2>
                    <div class="total-row"><span>Materiales</span><strong id="materials-total">$0.00</strong></div>
                    <div class="total-row"><span>Servicios y mano de obra</span><strong id="services-total">$0.00</strong></div>
                    <div class="total-row grand-total"><span>Total</span><strong id="grand-total">$0.00</strong></div>
                    <p class="total-note">El total no incluye impuestos. Los importes se recalculan al guardar.</p>
                    <button class="btn btn-primary btn-lg w-100 mt-3" type="submit"><?= $quoteId > 0 ? 'Guardar cambios' : 'Guardar cotización' ?></button>
                    <a class="btn btn-link w-100 mt-1" href="quotes.php">Cancelar</a>
                </section>
            </div>
        </div>
    </form>
    <dialog class="quantity-dialog" id="quantity-dialog" aria-labelledby="quantity-dialog-title" aria-describedby="quantity-details">
        <div class="quantity-dialog-content">
            <div class="eyebrow">AGREGAR A LA COTIZACIÓN</div>
            <h2 id="quantity-dialog-title">Selecciona la cantidad</h2>
            <p class="quantity-product" id="quantity-product"></p>
            <p class="quantity-details" id="quantity-details"></p>
            <label class="form-label" for="product-quantity">Cantidad</label>
            <input class="form-control" id="product-quantity" type="number" min="0.001" step="0.001" value="1" required>
            <div class="quantity-dialog-actions">
                <button class="btn btn-outline-secondary" id="cancel-product-quantity" type="button">Cancelar</button>
                <button class="btn btn-primary" id="confirm-product-quantity" type="button">Agregar artículo</button>
            </div>
        </div>
    </dialog>
    <script src="assets/js/quote-form.js?v=20261031-4"></script>
<?php endif; ?>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
