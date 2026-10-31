<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Panel';
$pageKey = $pageKey ?? '';
$userName = $_SESSION['user_name'] ?? '';
$userRole = ($_SESSION['user_role'] ?? '') === 'operador' ? 'Operador' : 'Administrador';
?>
<!-- Encabezado compartido: estilos, navegación principal y cuenta activa. -->
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(app_config()['app_name']) ?></title>
    <link rel="icon" type="image/jpeg" href="../imagen/logo.jpeg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/styles.css?v=20261031-3" rel="stylesheet">
</head>
<body class="app-body">
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="dashboard.php">
            <img class="brand-logo" src="../imagen/logo.jpeg" alt="Logotipo de E&amp;R Instalaciones Eléctricas">
            <span class="brand-copy">
                <strong>Instalaciones</strong>
                <small>Eléctricas</small>
            </span>
        </a>
        <div class="sidebar-label">MENÚ PRINCIPAL</div>
        <nav class="sidebar-nav" aria-label="Navegación principal">
            <a class="nav-item <?= $pageKey === 'dashboard' ? 'active' : '' ?>" href="dashboard.php" <?= $pageKey === 'dashboard' ? 'aria-current="page"' : '' ?>>
                <span class="nav-icon" aria-hidden="true">⌂</span> Panel principal
            </a>
            <a class="nav-item <?= $pageKey === 'quotes' ? 'active' : '' ?>" href="quotes.php" <?= $pageKey === 'quotes' ? 'aria-current="page"' : '' ?>>
                <span class="nav-icon" aria-hidden="true">▦</span> Cotizaciones
            </a>
            <a class="nav-item <?= $pageKey === 'clients' ? 'active' : '' ?>" href="clients.php" <?= $pageKey === 'clients' ? 'aria-current="page"' : '' ?>>
                <span class="nav-icon" aria-hidden="true">◈</span> Clientes
            </a>
            <a class="nav-item <?= in_array($pageKey, ['materials', 'services'], true) ? 'active' : '' ?>" href="materials.php" <?= in_array($pageKey, ['materials', 'services'], true) ? 'aria-current="page"' : '' ?>>
                <span class="nav-icon" aria-hidden="true">⚡</span> Catálogos
            </a>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-status"><span></span> Sistema listo para iniciar</div>
        </div>
    </aside>
    <main class="main-area">
        <header class="topbar">
            <div>
                <div class="eyebrow">E&amp;R INSTALACIONES ELÉCTRICAS</div>
                <div class="topbar-title"><?= e($pageTitle) ?></div>
            </div>
            <div class="user-menu">
                <div class="avatar"><?= e(strtoupper(substr($userName, 0, 1))) ?></div>
                <div class="user-details">
                    <strong><?= e($userName) ?></strong>
                    <small><?= e($userRole) ?></small>
                </div>
                <form action="logout.php" method="post" class="ms-2">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Salir</button>
                </form>
            </div>
        </header>
        <div class="content-area">
            <?php render_flash(); ?>
