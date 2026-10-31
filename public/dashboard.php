<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/auth.php';
require_auth();

// Reúne los contadores que se muestran en el panel principal.
$pageTitle = 'Panel principal';
$pageKey = 'dashboard';
$stats = [
    'clients' => (int) db()->query('SELECT COUNT(*) FROM clientes')->fetchColumn(),
    'materials' => (int) db()->query('SELECT COUNT(*) FROM materiales WHERE activo = 1')->fetchColumn(),
    'quotes' => (int) db()->query('SELECT COUNT(*) FROM cotizaciones')->fetchColumn(),
];
require __DIR__ . '/../app/views/header.php';
?>
<section class="welcome-banner">
    <div class="welcome-copy">
        <span class="welcome-pill"><span></span> PANEL DE CONTROL</span>
        <h1>Bienvenido, <?= e($_SESSION['user_name']) ?></h1>
        <p>Administra tus clientes, prepara cotizaciones y organiza los materiales y servicios de cada proyecto.</p>
    </div>
    <div class="welcome-art" aria-hidden="true">
        <div class="art-ring"></div>
        <div class="art-bolt">⚡</div>
        <div class="art-dot dot-one"></div>
        <div class="art-dot dot-two"></div>
    </div>
</section>

<div class="section-heading">
    <div>
        <div class="eyebrow">RESUMEN</div>
        <h2>Tu espacio de trabajo</h2>
    </div>
    <span class="stage-badge">GESTIÓN OPERATIVA</span>
</div>

<section class="row g-3 dashboard-cards" aria-label="Resumen de módulos">
    <div class="col-12 col-md-4">
        <a class="module-card module-card-link" href="quotes.php">
            <div class="module-icon icon-blue">▦</div>
            <div class="module-label">COTIZACIONES</div>
            <div class="module-stat"><?= $stats['quotes'] ?></div>
            <h3>Control de propuestas</h3>
            <p>Consulta el historial y prepara nuevas cotizaciones.</p>
            <span class="module-status">Abrir cotizaciones →</span>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a class="module-card module-card-link" href="materials.php">
            <div class="module-icon icon-orange">⚡</div>
            <div class="module-label">CATÁLOGOS</div>
            <div class="module-stat"><?= $stats['materials'] ?></div>
            <h3>Materiales y servicios</h3>
            <p>Organiza insumos, mano de obra y precios vigentes.</p>
            <span class="module-status">Administrar catálogos →</span>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a class="module-card module-card-link" href="clients.php">
            <div class="module-icon icon-navy">◈</div>
            <div class="module-label">CLIENTES</div>
            <div class="module-stat"><?= $stats['clients'] ?></div>
            <h3>Directorio de clientes</h3>
            <p>Mantén sus datos y consulta las cotizaciones relacionadas.</p>
            <span class="module-status">Abrir directorio →</span>
        </a>
    </div>
</section>

<section class="next-step-card">
    <div class="next-step-icon">✓</div>
    <div>
        <h3>Tu espacio de trabajo está listo</h3>
        <p>Las cotizaciones guardan los precios usados en cada propuesta, aunque después cambies el catálogo.</p>
    </div>
    <span class="next-step-tag">SIN IMPUESTOS AUTOMÁTICOS</span>
</section>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
