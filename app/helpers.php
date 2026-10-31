<?php
declare(strict_types=1);

final class QuoteValidationException extends RuntimeException
{
}

// Funciones compartidas para navegación, mensajes y lectura segura de formularios.
function redirect_to(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function post_string(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function format_money(string|int|float $amount): string
{
    return '$' . number_format((float) $amount, 2, '.', ',');
}

function format_quantity(string|int|float $quantity): string
{
    return rtrim(rtrim(number_format((float) $quantity, 3, '.', ''), '0'), '.');
}

function item_total(string|int|float $quantity, string|int|float $price): string
{
    return number_format((float) $quantity * (float) $price, 2, '.', '');
}

// Serializa la generación de folios del mismo año para evitar duplicados concurrentes.
function acquire_quote_folio_lock(PDO $connection, DateTimeInterface $date): string
{
    $lockName = 'er_instalaciones_quote_folio_' . $date->format('Y');
    $statement = $connection->prepare('SELECT GET_LOCK(:lock_name, 10)');
    $statement->execute(['lock_name' => $lockName]);

    if ((int) $statement->fetchColumn() !== 1) {
        throw new RuntimeException('No se pudo reservar el siguiente folio. Inténtalo de nuevo.');
    }

    return $lockName;
}

// Avanza el contador anual usando los folios históricos como punto de partida.
function next_quote_folio(PDO $connection, DateTimeInterface $date): string
{
    $year = (int) $date->format('Y');
    $prefix = 'E&R-' . $year . '-';
    $statement = $connection->prepare(
        'SELECT folio FROM cotizaciones WHERE folio LIKE :prefix_pattern'
    );
    $statement->execute([
        'prefix_pattern' => $prefix . '%',
    ]);

    $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
    $highestNumber = 0;
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $folio) {
        if (is_string($folio) && preg_match($pattern, $folio, $matches)) {
            $highestNumber = max($highestNumber, (int) $matches[1]);
        }
    }

    $statement = $connection->prepare(
        'SELECT ultimo_correlativo FROM cotizacion_folio_secuencias WHERE anio = :anio'
    );
    $statement->execute(['anio' => $year]);
    $lastNumber = $statement->fetchColumn();

    if ($lastNumber === false) {
        $nextNumber = $highestNumber + 1;
        $statement = $connection->prepare(
            'INSERT INTO cotizacion_folio_secuencias (anio, ultimo_correlativo)
             VALUES (:anio, :ultimo_correlativo)'
        );
    } else {
        $nextNumber = max((int) $lastNumber, $highestNumber) + 1;
        $statement = $connection->prepare(
            'UPDATE cotizacion_folio_secuencias SET ultimo_correlativo = :ultimo_correlativo
             WHERE anio = :anio'
        );
    }

    $statement->execute([
        'anio' => $year,
        'ultimo_correlativo' => $nextNumber,
    ]);

    return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
}

// Libera el bloqueo anual una vez que terminó la operación de guardado.
function release_quote_folio_lock(PDO $connection, string $lockName): void
{
    $statement = $connection->prepare('SELECT RELEASE_LOCK(:lock_name)');
    $statement->execute(['lock_name' => $lockName]);

    if ((int) $statement->fetchColumn() !== 1) {
        throw new RuntimeException('No se pudo liberar el bloqueo para generar folios.');
    }
}

// Valida importes y cantidades decimales antes de guardarlos en la base.
function valid_decimal(string $value, int $scale = 2, bool $allowZero = true): bool
{
    $pattern = $scale === 3
        ? '/^(?:0|[1-9]\d{0,8})(?:\.\d{1,3})?$/'
        : '/^(?:0|[1-9]\d{0,9})(?:\.\d{1,2})?$/';

    if (!preg_match($pattern, $value)) {
        return false;
    }

    return $allowZero || (float) $value > 0;
}

// Muestra una sola vez el mensaje guardado durante una redirección.
function render_flash(): void
{
    $flash = take_flash();
    if ($flash === null) {
        return;
    }

    $allowedTypes = ['success', 'danger', 'warning', 'info'];
    $type = in_array($flash['type'] ?? '', $allowedTypes, true) ? $flash['type'] : 'info';
    $message = is_string($flash['message'] ?? null) ? $flash['message'] : '';
    ?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php
}
