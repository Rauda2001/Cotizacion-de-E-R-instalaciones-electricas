<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

// Solo admite cierre de sesión por POST y verifica el token del formulario.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método no permitido.');
}

reject_invalid_csrf();
// Borra los datos de sesión y expira la cookie antes de volver al acceso.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => 'Lax',
    ]);
}

session_destroy();
header('Location: login.php');
exit;
