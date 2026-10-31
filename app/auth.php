<?php
declare(strict_types=1);

// Comprueba que la sesión tenga los datos de una cuenta autenticada.
function is_authenticated(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['user_name']);
}

// Impide el acceso a páginas privadas y envía al usuario al inicio de sesión.
function require_auth(): void
{
    if (!is_authenticated()) {
        header('Location: login.php');
        exit;
    }
}
