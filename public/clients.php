<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/auth.php';
require_auth();

$pageKey = 'clients';
$pageTitle = 'Clientes';
$errors = [];
$form = ['nombre' => '', 'telefono' => '', 'correo' => '', 'direccion' => '', 'notas' => ''];
$editingId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;

// Valida y guarda cambios de clientes; conserva la relación con cotizaciones existentes.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reject_invalid_csrf();
    $action = post_string('action');
    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT) ?: 0;

    if ($action === 'save') {
        foreach ($form as $field => $default) {
            $form[$field] = post_string($field, $default);
        }
        if ($id > 0) {
            $editingId = $id;
        }
        if ($form['nombre'] === '' || strlen($form['nombre']) > 160) {
            $errors[] = 'El nombre es obligatorio y debe tener máximo 160 caracteres.';
        }
        if (strlen($form['telefono']) > 30) {
            $errors[] = 'El teléfono debe tener máximo 30 caracteres.';
        }
        if ($form['correo'] !== '' && (!filter_var($form['correo'], FILTER_VALIDATE_EMAIL) || strlen($form['correo']) > 190)) {
            $errors[] = 'Escribe un correo válido de máximo 190 caracteres.';
        }
        if (strlen($form['direccion']) > 500) {
            $errors[] = 'La dirección debe tener máximo 500 caracteres.';
        }

        if ($errors === []) {
            $values = [
                'nombre' => $form['nombre'],
                'telefono' => $form['telefono'] !== '' ? $form['telefono'] : null,
                'correo' => $form['correo'] !== '' ? $form['correo'] : null,
                'direccion' => $form['direccion'] !== '' ? $form['direccion'] : null,
                'notas' => $form['notas'] !== '' ? $form['notas'] : null,
            ];
            if ($id > 0) {
                $values['id'] = $id;
                $statement = db()->prepare(
                    'UPDATE clientes SET nombre = :nombre, telefono = :telefono, correo = :correo,
                     direccion = :direccion, notas = :notas WHERE id = :id'
                );
                $statement->execute($values);
                set_flash('success', 'Los datos del cliente se actualizaron.');
            } else {
                $statement = db()->prepare(
                    'INSERT INTO clientes (nombre, telefono, correo, direccion, notas)
                     VALUES (:nombre, :telefono, :correo, :direccion, :notas)'
                );
                $statement->execute($values);
                set_flash('success', 'El cliente se agregó al directorio.');
            }
            redirect_to('clients.php');
        }
    } elseif ($action === 'delete' && $id > 0) {
        try {
            $statement = db()->prepare('DELETE FROM clientes WHERE id = :id');
            $statement->execute(['id' => $id]);
            set_flash('success', 'El cliente se eliminó.');
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            set_flash('warning', 'No se puede eliminar un cliente que ya tiene cotizaciones. Conserva el historial y edita sus datos si es necesario.');
        }
        redirect_to('clients.php');
    }
}

// Carga los datos existentes para rellenar el formulario de edición.
if ($editingId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $statement = db()->prepare('SELECT nombre, telefono, correo, direccion, notas FROM clientes WHERE id = :id');
    $statement->execute(['id' => $editingId]);
    $client = $statement->fetch();
    if (!$client) {
        set_flash('warning', 'El cliente solicitado no existe.');
        redirect_to('clients.php');
    }
    $form = array_merge($form, array_map(static fn ($value): string => (string) ($value ?? ''), $client));
}

// Busca clientes y cuenta las cotizaciones relacionadas para el directorio.
$search = trim((string) ($_GET['q'] ?? ''));
$statement = db()->prepare(
    "SELECT c.id, c.nombre, c.telefono, c.correo, c.direccion, c.creado_en,
            (SELECT COUNT(*) FROM cotizaciones q WHERE q.cliente_id = c.id) AS total_cotizaciones
     FROM clientes c
     WHERE (:search = '' OR c.nombre LIKE :name_search OR c.correo LIKE :email_search OR c.telefono LIKE :phone_search)
     ORDER BY c.nombre ASC"
);
$term = '%' . $search . '%';
$statement->execute([
    'search' => $search,
    'name_search' => $term,
    'email_search' => $term,
    'phone_search' => $term,
]);
$clients = $statement->fetchAll();

require __DIR__ . '/../app/views/header.php';
?>
<div class="page-heading">
    <div>
        <div class="eyebrow">DIRECTORIO</div>
        <h1>Clientes</h1>
        <p>Administra los datos de contacto y consulta sus cotizaciones.</p>
    </div>
    <a class="btn btn-primary" href="#client-form">+ Agregar cliente</a>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8 order-2 order-xl-1">
        <section class="data-card">
            <div class="card-heading">
                <div><h2>Directorio de clientes</h2><p><?= count($clients) ?> resultado(s)</p></div>
                <form method="get" class="search-form">
                    <input class="form-control form-control-sm" type="search" name="q" placeholder="Buscar cliente..." value="<?= e($search) ?>">
                    <button class="btn btn-outline-primary btn-sm" type="submit">Buscar</button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table align-middle data-table">
                    <thead><tr><th>Cliente</th><th>Contacto</th><th>Cotizaciones</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><strong><?= e($client['nombre']) ?></strong><small><?= e($client['direccion'] ?? 'Sin dirección') ?></small></td>
                            <td><?= e($client['telefono'] ?? '—') ?><small><?= e($client['correo'] ?? 'Sin correo') ?></small></td>
                            <td><span class="count-pill"><?= (int) $client['total_cotizaciones'] ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-light" href="clients.php?edit=<?= (int) $client['id'] ?>">Editar</a>
                                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este cliente?');">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($clients === []): ?><tr><td colspan="4" class="empty-state">No hay clientes que coincidan con la búsqueda.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-4 order-1 order-xl-2">
        <section class="form-card" id="client-form">
            <div class="card-heading"><div><h2><?= $editingId > 0 ? 'Editar cliente' : 'Nuevo cliente' ?></h2><p>Los campos con * son obligatorios.</p></div></div>
            <?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $editingId ?>">
                <label class="form-label" for="nombre">Nombre completo o empresa *</label>
                <input class="form-control mb-3" id="nombre" name="nombre" required maxlength="160" value="<?= e($form['nombre']) ?>">
                <label class="form-label" for="telefono">Teléfono</label>
                <input class="form-control mb-3" id="telefono" name="telefono" maxlength="30" value="<?= e($form['telefono']) ?>">
                <label class="form-label" for="correo">Correo</label>
                <input class="form-control mb-3" id="correo" name="correo" type="email" maxlength="190" value="<?= e($form['correo']) ?>">
                <label class="form-label" for="direccion">Dirección del proyecto</label>
                <textarea class="form-control mb-3" id="direccion" name="direccion" rows="2" maxlength="500"><?= e($form['direccion']) ?></textarea>
                <label class="form-label" for="notas">Notas</label>
                <textarea class="form-control mb-3" id="notas" name="notas" rows="2"><?= e($form['notas']) ?></textarea>
                <button class="btn btn-primary w-100" type="submit"><?= $editingId > 0 ? 'Guardar cambios' : 'Agregar cliente' ?></button>
                <?php if ($editingId > 0): ?><a class="btn btn-link w-100 mt-1" href="clients.php">Cancelar edición</a><?php endif; ?>
            </form>
        </section>
    </div>
</div>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
