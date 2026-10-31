<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/auth.php';
require_auth();

// Comparte el formulario CRUD para materiales y servicios usando su tipo de catálogo.
function render_catalog_page(string $type): void
{
    if (!in_array($type, ['material', 'servicio'], true)) {
        throw new InvalidArgumentException('Tipo de catálogo no válido.');
    }

    $table = $type === 'material' ? 'materiales' : 'servicios';
    $singular = $type === 'material' ? 'material' : 'servicio';
    $plural = $type === 'material' ? 'materiales' : 'servicios';
    $pageKey = $type === 'material' ? 'materials' : 'services';
    $pageTitle = $type === 'material' ? 'Materiales' : 'Servicios y mano de obra';
    $errors = [];
    $form = ['nombre' => '', 'descripcion' => '', 'unidad' => '', 'precio_unitario' => ''];
    $editingId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;

    // Procesa activaciones y altas/ediciones enviadas desde los formularios.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        reject_invalid_csrf();
        $action = post_string('action');
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT) ?: 0;

        if ($action === 'toggle' && $id > 0) {
            $statement = db()->prepare("UPDATE {$table} SET activo = IF(activo = 1, 0, 1) WHERE id = :id");
            $statement->execute(['id' => $id]);
            set_flash('success', ucfirst($singular) . ' actualizado.');
            redirect_to($pageKey . '.php');
        }

        if ($action === 'save') {
            foreach ($form as $field => $default) {
                $form[$field] = post_string($field, $default);
            }
            $editingId = $id;
            if ($form['nombre'] === '' || strlen($form['nombre']) > 160) {
                $errors[] = 'El nombre es obligatorio y debe tener máximo 160 caracteres.';
            }
            if (strlen($form['unidad']) < 1 || strlen($form['unidad']) > 40) {
                $errors[] = 'Indica una unidad (máximo 40 caracteres), por ejemplo pieza, metro o servicio.';
            }
            if (!valid_decimal($form['precio_unitario'])) {
                $errors[] = 'El precio debe ser un importe positivo o cero con máximo dos decimales.';
            }

            if ($errors === []) {
                $values = [
                    'nombre' => $form['nombre'],
                    'descripcion' => $form['descripcion'] !== '' ? $form['descripcion'] : null,
                    'unidad' => $form['unidad'],
                    'precio_unitario' => $form['precio_unitario'],
                ];
                if ($id > 0) {
                    $values['id'] = $id;
                    $statement = db()->prepare(
                        "UPDATE {$table} SET nombre = :nombre, descripcion = :descripcion,
                         unidad = :unidad, precio_unitario = :precio_unitario WHERE id = :id"
                    );
                    $statement->execute($values);
                    set_flash('success', ucfirst($singular) . ' actualizado. Las cotizaciones anteriores conservan el precio histórico.');
                } else {
                    $statement = db()->prepare(
                        "INSERT INTO {$table} (nombre, descripcion, unidad, precio_unitario)
                         VALUES (:nombre, :descripcion, :unidad, :precio_unitario)"
                    );
                    $statement->execute($values);
                    set_flash('success', ucfirst($singular) . ' agregado al catálogo.');
                }
                redirect_to($pageKey . '.php');
            }
        }
    }

    // Recupera los datos actuales cuando se solicita editar un elemento.
    if ($editingId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $statement = db()->prepare("SELECT nombre, descripcion, unidad, precio_unitario FROM {$table} WHERE id = :id");
        $statement->execute(['id' => $editingId]);
        $item = $statement->fetch();
        if (!$item) {
            set_flash('warning', 'El elemento solicitado no existe.');
            redirect_to($pageKey . '.php');
        }
        $form = array_merge($form, array_map(static fn ($value): string => (string) ($value ?? ''), $item));
    }

    // Filtra el catálogo y carga la vista común con sus resultados.
    $search = trim((string) ($_GET['q'] ?? ''));
    $statement = db()->prepare(
        "SELECT id, nombre, descripcion, unidad, precio_unitario, activo
         FROM {$table}
         WHERE (:search = '' OR nombre LIKE :name_search OR descripcion LIKE :description_search)
         ORDER BY activo DESC, nombre ASC"
    );
    $term = '%' . $search . '%';
    $statement->execute(['search' => $search, 'name_search' => $term, 'description_search' => $term]);
    $items = $statement->fetchAll();

    require __DIR__ . '/views/header.php';
    ?>
    <div class="page-heading">
        <div><div class="eyebrow">CATÁLOGO</div><h1><?= e($pageTitle) ?></h1><p>Gestiona precios vigentes para nuevas cotizaciones.</p></div>
        <div class="catalog-switch">
            <a class="btn btn-sm <?= $type === 'material' ? 'btn-primary' : 'btn-outline-primary' ?>" href="materials.php">Materiales</a>
            <a class="btn btn-sm <?= $type === 'servicio' ? 'btn-primary' : 'btn-outline-primary' ?>" href="services.php">Servicios</a>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-12 col-xl-8 order-2 order-xl-1">
            <section class="data-card">
                <div class="card-heading">
                    <div><h2>Catálogo de <?= e($plural) ?></h2><p><?= count($items) ?> resultado(s)</p></div>
                    <form method="get" class="search-form"><input class="form-control form-control-sm" type="search" name="q" placeholder="Buscar..." value="<?= e($search) ?>"><button class="btn btn-outline-primary btn-sm">Buscar</button></form>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle data-table">
                        <thead><tr><th>Concepto</th><th>Unidad</th><th>Precio unitario</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><strong><?= e($item['nombre']) ?></strong><small><?= e($item['descripcion'] ?? 'Sin descripción') ?></small></td>
                                <td><?= e($item['unidad']) ?></td><td><strong><?= e(format_money($item['precio_unitario'])) ?></strong></td>
                                <td><span class="status-pill <?= $item['activo'] ? 'status-active' : 'status-inactive' ?>"><?= $item['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-light" href="<?= e($pageKey) ?>.php?edit=<?= (int) $item['id'] ?>">Editar</a>
                                    <form method="post" class="d-inline">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit"><?= $item['activo'] ? 'Desactivar' : 'Activar' ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($items === []): ?><tr><td colspan="5" class="empty-state">No hay elementos en este catálogo.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-12 col-xl-4 order-1 order-xl-2">
            <section class="form-card" id="catalog-form">
                <div class="card-heading"><div><h2><?= $editingId > 0 ? 'Editar ' . e($singular) : 'Nuevo ' . e($singular) ?></h2><p>Se usará en futuras cotizaciones.</p></div></div>
                <?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $editingId ?>">
                    <label class="form-label" for="nombre">Nombre *</label><input class="form-control mb-3" id="nombre" name="nombre" required maxlength="160" value="<?= e($form['nombre']) ?>">
                    <label class="form-label" for="descripcion">Descripción</label><textarea class="form-control mb-3" id="descripcion" name="descripcion" rows="2"><?= e($form['descripcion']) ?></textarea>
                    <label class="form-label" for="unidad">Unidad *</label><input class="form-control mb-3" id="unidad" name="unidad" required maxlength="40" placeholder="pieza, metro, servicio..." value="<?= e($form['unidad']) ?>">
                    <label class="form-label" for="precio_unitario">Precio unitario *</label><div class="input-group mb-3"><span class="input-group-text">$</span><input class="form-control" id="precio_unitario" name="precio_unitario" type="number" min="0" step="0.01" required value="<?= e($form['precio_unitario']) ?>"></div>
                    <button class="btn btn-primary w-100" type="submit"><?= $editingId > 0 ? 'Guardar cambios' : 'Agregar al catálogo' ?></button>
                    <?php if ($editingId > 0): ?><a class="btn btn-link w-100 mt-1" href="<?= e($pageKey) ?>.php">Cancelar edición</a><?php endif; ?>
                </form>
            </section>
        </div>
    </div>
    <?php
    require __DIR__ . '/views/footer.php';
}
