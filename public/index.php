<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/auth.php';

// Envía al panel si existe una sesión; de lo contrario, solicita autenticación.
header('Location: ' . (is_authenticated() ? 'dashboard.php' : 'login.php'));
exit;
