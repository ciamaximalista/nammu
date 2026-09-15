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
