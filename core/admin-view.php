<?php
/**
 * Nammu — panel de administración.
 * Utilidades de presentación del panel: inclusión de CSS/JS propios.
 *
 * Los recursos viven en core/admin-assets/ (directorio no servido por HTTP)
 * y se vuelcan inline en la página, como siempre hizo admin.php.
 */

function admin_inline_asset(string $relativePath): void
{
    $path = NAMMU_ROOT . '/core/admin-assets/' . ltrim($relativePath, '/');
    if (is_file($path)) {
        readfile($path);
    }
}

/**
 * Recupera y borra un mensaje de una sola lectura dejado en sesión (patrón flash).
 * Devuelve null si no existe o no tiene la forma esperada; en ese caso no se toca la sesión.
 */
function admin_take_flash(string $key, bool $requireMessage = true): ?array
{
    $value = $_SESSION[$key] ?? null;
    if (!is_array($value) || ($requireMessage && !isset($value['message'], $value['type']))) {
        return null;
    }
    unset($_SESSION[$key]);
    return $value;
}
