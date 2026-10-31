<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/auth.php';
require_auth();

$pageKey = 'quotes';
$pageTitle = 'Cotizaciones';

// Atiende la eliminación solicitada desde el historial con protección CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reject_invalid_csrf();
    if (post_string('action') === 'delete') {
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            http_response_code(400);
            exit('La cotización indicada no es válida.');
        }
        $statement = db()->prepare('DELETE FROM cotizaciones WHERE id = :id');
        $statement->execute(['id' => $id]);
        set_flash('success', 'La cotización y sus partidas se eliminaron.');
        redirect_to('quotes.php');
    }
}

// Filtra el historial por folio, cliente y estado, y calcula el total guardado.
$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['estado'] ?? ''));
$validStatuses = ['borrador', 'enviada', 'aceptada', 'rechazada', 'vencida'];
if (!in_array($status, $validStatuses, true)) {
    $status = '';
}
$statement = db()->prepare(
    "SELECT q.id, q.folio, q.fecha, q.vigencia_hasta, q.estado, c.nombre AS cliente,
            ROUND(
                COALESCE((SELECT SUM(ROUND(cm.cantidad * cm.precio_unitario_snapshot, 2))
                          FROM cotizacion_materiales cm WHERE cm.cotizacion_id = q.id), 0)
              + COALESCE((SELECT SUM(ROUND(cs.cantidad * cs.precio_unitario_snapshot, 2))
                          FROM cotizacion_servicios cs WHERE cs.cotizacion_id = q.id), 0), 2
            ) AS total
     FROM cotizaciones q
     INNER JOIN clientes c ON c.id = q.cliente_id
     WHERE (:search = '' OR q.folio LIKE :folio_search OR c.nombre LIKE :client_search)
       AND (:status = '' OR q.estado = :selected_status)
     ORDER BY q.fecha DESC, q.id DESC"
);
$term = '%' . $search . '%';
$statement->execute([
    'search' => $search,
    'folio_search' => $term,
    'client_search' => $term,
    'status' => $status,
    'selected_status' => $status,
]);
$quotes = $statement->fetchAll();
$statusLabels = ['borrador' => 'Borrador', 'enviada' => 'Enviada', 'aceptada' => 'Aceptada', 'rechazada' => 'Rechazada', 'vencida' => 'Vencida'];

require __DIR__ . '/../app/views/header.php';
?>
<div class="page-heading">
    <div><div class="eyebrow">SEGUIMIENTO COMERCIAL</div><h1>Cotizaciones</h1><p>Crea propuestas, consulta su historial y descarga documentos PDF.</p></div>
    <a class="btn btn-primary" href="quote_form.php">+ Nueva cotización</a>
</div>
<section class="data-card">
    <div class="card-heading">
        <div><h2>Historial de cotizaciones</h2><p><?= count($quotes) ?> resultado(s)</p></div>
        <form method="get" class="search-form quote-search">
            <input class="form-control form-control-sm" type="search" name="q" placeholder="Folio o cliente..." value="<?= e($search) ?>">
            <select class="form-select form-select-sm" name="estado">
                <option value="">Todos los estados</option>
                <?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-outline-primary btn-sm" type="submit">Buscar</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead><tr><th>Folio</th><th>Cliente</th><th>Fecha</th><th>Estado</th><th>Total</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($quotes as $quote): ?>
                <tr>
                    <td><a class="quote-number" href="quote_view.php?id=<?= (int) $quote['id'] ?>"><?= e($quote['folio']) ?></a></td>
                    <td><strong><?= e($quote['cliente']) ?></strong><small><?= $quote['vigencia_hasta'] ? 'Vigencia al ' . e($quote['vigencia_hasta']) : 'Sin fecha de vigencia' ?></small></td>
                    <td><?= e($quote['fecha']) ?></td>
                    <td><span class="status-pill status-<?= e($quote['estado']) ?>"><?= e($statusLabels[$quote['estado']] ?? $quote['estado']) ?></span></td>
                    <td><strong><?= e(format_money($quote['total'])) ?></strong></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-light" href="quote_view.php?id=<?= (int) $quote['id'] ?>">Ver</a>
                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta cotización?');">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $quote['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($quotes === []): ?><tr><td colspan="6" class="empty-state">Aún no hay cotizaciones para mostrar.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
