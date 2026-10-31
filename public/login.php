<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/auth.php';

// Evita pedir credenciales a quien ya inició sesión.
if (is_authenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$login = '';

// Valida las credenciales, inicia la sesión y renueva el token CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reject_invalid_csrf();
    $loginInput = $_POST['login'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $login = is_string($loginInput) ? trim($loginInput) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';

    if ($login !== '' && $password !== '') {
        $statement = db()->prepare(
            'SELECT id, nombre_usuario, password_hash, rol
             FROM usuarios
             WHERE activo = 1 AND (nombre_usuario = :nombre_usuario OR correo = :correo)
             LIMIT 1'
        );
        $statement->execute([
            'nombre_usuario' => $login,
            'correo' => $login,
        ]);
        $user = $statement->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['nombre_usuario'];
            $_SESSION['user_role'] = $user['rol'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: dashboard.php');
            exit;
        }
    }

    $error = 'Usuario/correo o contraseña incorrectos.';
}

$appName = app_config()['app_name'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | <?= e($appName) ?></title>
    <link rel="icon" type="image/jpeg" href="../imagen/logo.jpeg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/styles.css?v=20261024-4" rel="stylesheet">
</head>
<body class="login-body">
<main class="login-layout">
    <section class="login-brand-panel">
        <a class="brand brand-light" href="index.php">
            <img class="brand-logo" src="../imagen/logo.jpeg" alt="Logotipo de E&amp;R Instalaciones Eléctricas">
            <span class="brand-copy">
                <strong>Instalaciones</strong>
                <small>Eléctricas</small>
            </span>
        </a>
        <div class="login-message">
            <div class="login-accent"></div>
            <p class="eyebrow text-white-50">CONTROL Y ORGANIZACIÓN</p>
            <h1>Tu trabajo,<br>bien conectado.</h1>
            <p>Administra tu operación de instalaciones eléctricas desde un solo lugar.</p>
        </div>
        <div class="brand-panel-footer">E&amp;R · Instalaciones Eléctricas</div>
    </section>
    <section class="login-form-panel">
        <div class="login-card">
            <div class="eyebrow">BIENVENIDO</div>
            <h2>Iniciar sesión</h2>
            <p class="text-secondary mb-4">Ingresa tus datos para continuar al panel.</p>
            <?php if (isset($_GET['created']) && $_GET['created'] === '1'): ?>
                <div class="alert alert-success" role="status">Cuenta administradora creada. Ya puedes iniciar sesión.</div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" action="login.php">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="login" class="form-label">Usuario o correo electrónico</label>
                    <input id="login" name="login" class="form-control form-control-lg" type="text"
                           autocomplete="username" maxlength="190" required value="<?= e($login) ?>">
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <input id="password" name="password" class="form-control form-control-lg" type="password"
                           autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">Entrar al panel</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
