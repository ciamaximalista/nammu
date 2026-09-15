<?php
/**
 * Nammu — panel de administración.
 * Directorio de backups y listado/restauración de copias de estadísticas y completas.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

function admin_stats_backup_dir(): string {
    $config = function_exists('nammu_load_config') ? nammu_load_config() : load_config_file();
    return nammu_backup_dir($config, NAMMU_ROOT);
}

function admin_copy_backup_directory(string $source, string $destination, ?string &$error = null): bool
{
    if (!is_dir($source)) {
        return true;
    }
    if (!nammu_ensure_directory($destination)) {
        $error = 'No se pudo crear el nuevo directorio de backups.';
        return false;
    }
    $items = @scandir($source);
    if (!is_array($items)) {
        $error = 'No se pudo leer el directorio actual de backups.';
        return false;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $from = rtrim($source, '/') . '/' . $item;
        $to = rtrim($destination, '/') . '/' . $item;
        if (is_dir($from) && !is_link($from)) {
            if (!admin_copy_backup_directory($from, $to, $error)) {
                return false;
            }
            continue;
        }
        if (!@copy($from, $to)) {
            $error = 'No se pudo copiar el backup ' . $item . ' al nuevo directorio.';
            return false;
        }
        nammu_apply_shared_permissions($to, 0664, dirname($to));
    }
    return true;
}

function admin_backup_directory_is_safe(string $directory): bool
{
    $directory = rtrim($directory, '/');
    if ($directory === '' || $directory === '/') {
        return false;
    }
    $blocked = [
        NAMMU_ROOT,
        dirname(NAMMU_ROOT),
        dirname(dirname(NAMMU_ROOT)),
        '/var',
        '/var/www',
        '/var/www/html',
        '/tmp',
    ];
    return !in_array($directory, array_map(static fn(string $path): string => rtrim($path, '/'), $blocked), true);
}

function admin_update_backup_directory(string $newDirectory, ?string &$error = null): bool
{
    $config = load_config_file();
    $currentDirectory = admin_stats_backup_dir();
    $configuredDirectory = nammu_normalize_backup_dir($newDirectory, NAMMU_ROOT);
    $targetDirectory = nammu_backup_dir(['backups' => ['directory' => $configuredDirectory]], NAMMU_ROOT);
    if (!admin_backup_directory_is_safe($configuredDirectory) || !admin_backup_directory_is_safe($targetDirectory)) {
        $error = 'La ruta del directorio de backups no es válida.';
        return false;
    }
    if (!nammu_ensure_directory($targetDirectory)) {
        $error = 'No se pudo crear el directorio de backups indicado.';
        return false;
    }
    if (!is_writable($targetDirectory)) {
        $error = 'El directorio de backups indicado no es escribible.';
        return false;
    }
    $directoryChanged = $currentDirectory !== $targetDirectory;
    if ($directoryChanged) {
        if (str_starts_with(rtrim($targetDirectory, '/') . '/', rtrim($currentDirectory, '/') . '/')) {
            $error = 'El nuevo directorio de backups no puede estar dentro del directorio antiguo.';
            return false;
        }
        if (!admin_copy_backup_directory($currentDirectory, $targetDirectory, $error)) {
            return false;
        }
    }
    $config['backups'] = is_array($config['backups'] ?? null) ? $config['backups'] : [];
    $config['backups']['directory'] = $configuredDirectory;
    save_config_file($config);
    if ($directoryChanged && is_dir($currentDirectory) && admin_backup_directory_is_safe($currentDirectory) && !admin_recursive_delete_path($currentDirectory)) {
        $error = 'Los backups se copiaron y la configuración se guardó, pero no se pudo borrar el directorio antiguo.';
        return false;
    }
    return true;
}

/**
 * @return array<int, array{file:string,mtime:int,size:int,label:string,download_url:string}>
 */
function admin_list_full_backups(int $limit = 8): array {
    $dir = admin_stats_backup_dir();
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/nammu-full-backup-*.tar.gz') ?: [];
    $items = [];
    foreach ($files as $fullPath) {
        $base = basename($fullPath);
        if (!preg_match('/^nammu-full-backup-\d{4}-\d{2}-\d{2}_\d{6}\.tar\.gz$/', $base)) {
            continue;
        }
        $mtime = (int) (@filemtime($fullPath) ?: 0);
        $size = (int) (@filesize($fullPath) ?: 0);
        $items[] = [
            'file' => $base,
            'mtime' => $mtime,
            'size' => $size,
            'label' => date('d/m/Y H:i:s', $mtime) . ' · ' . number_format($size / 1024 / 1024, 2, ',', '.') . ' MiB',
            'download_url' => 'admin.php?page=configuracion&download_full_backup=' . rawurlencode($base),
        ];
    }
    usort($items, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
    if ($limit > 0 && count($items) > $limit) {
        $items = array_slice($items, 0, $limit);
    }
    return $items;
}

/**
 * @return array<int, array{file:string,mtime:int,size:int,label:string}>
 */
function admin_list_stats_backups(int $maxDays = 7): array {
    $dir = admin_stats_backup_dir();
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/nammu-stats-backup-*.tar.gz') ?: [];
    $items = [];
    $cutoff = time() - max(1, $maxDays) * 86400;
    foreach ($files as $fullPath) {
        $base = basename($fullPath);
        if (!preg_match('/^nammu-stats-backup-\d{4}-\d{2}-\d{2}_\d{6}\.tar\.gz$/', $base)) {
            continue;
        }
        $mtime = (int) (@filemtime($fullPath) ?: 0);
        if ($mtime > 0 && $mtime < $cutoff) {
            continue;
        }
        $size = (int) (@filesize($fullPath) ?: 0);
        $items[] = [
            'file' => $base,
            'mtime' => $mtime,
            'size' => $size,
            'label' => date('d/m/Y H:i:s', $mtime) . ' · ' . $base . ' · ' . number_format($size / 1024, 1, ',', '.') . ' KiB',
        ];
    }
    usort($items, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
    return $items;
}

function admin_restore_stats_backup(string $archiveFile, ?string &$error = null): bool {
    $archiveFile = trim($archiveFile);
    if (!preg_match('/^nammu-stats-backup-\d{4}-\d{2}-\d{2}_\d{6}\.tar\.gz$/', $archiveFile)) {
        $error = 'Backup inválido.';
        return false;
    }
    $archivePath = admin_stats_backup_dir() . '/' . $archiveFile;
    if (!is_file($archivePath)) {
        $error = 'El archivo de backup no existe.';
        return false;
    }
    $tmpBase = NAMMU_ROOT . '/config/.stats-restore-' . bin2hex(random_bytes(6));
    if (!@mkdir($tmpBase, 0775, true) && !is_dir($tmpBase)) {
        $error = 'No se pudo crear el directorio temporal de restauración.';
        return false;
    }
    $cmd = 'tar -xzf ' . escapeshellarg($archivePath) . ' -C ' . escapeshellarg($tmpBase);
    exec($cmd . ' 2>&1', $out, $code);
    if ($code !== 0) {
        admin_recursive_delete_path($tmpBase);
        $error = 'No se pudo descomprimir el backup.';
        return false;
    }

    $root = NAMMU_ROOT;
    $restored = 0;
    $safeFiles = [
        'config/analytics.json',
        'config/analytics.last-good.json',
        'config/gsc-cache.json',
        'config/bing-cache.json',
    ];
    foreach ($safeFiles as $relative) {
        $src = $tmpBase . '/' . $relative;
        $dst = $root . '/' . $relative;
        if (!is_file($src)) {
            continue;
        }
        $dstDir = dirname($dst);
        if (!is_dir($dstDir)) {
            @mkdir($dstDir, 0775, true);
        }
        if (@copy($src, $dst)) {
            if (function_exists('nammu_apply_shared_permissions')) {
                nammu_apply_shared_permissions($dst, 0664, $dstDir);
            } else {
                @chmod($dst, 0664);
            }
            $restored++;
        }
    }
    foreach (glob($tmpBase . '/itinerarios/*/stats.json') ?: [] as $srcStats) {
        $relative = ltrim(str_replace($tmpBase, '', $srcStats), '/');
        if (!preg_match('#^itinerarios/[a-z0-9._-]+/stats\.json$#i', $relative)) {
            continue;
        }
        $dst = $root . '/' . $relative;
        $dstDir = dirname($dst);
        if (!is_dir($dstDir)) {
            @mkdir($dstDir, 0775, true);
        }
        if (@copy($srcStats, $dst)) {
            if (function_exists('nammu_apply_shared_permissions')) {
                nammu_apply_shared_permissions($dst, 0664, $dstDir);
            } else {
                @chmod($dst, 0664);
            }
            $restored++;
        }
    }
    admin_recursive_delete_path($tmpBase);
    if ($restored === 0) {
        $error = 'El backup no contenía archivos de estadísticas restaurables.';
        return false;
    }
    return true;
}

function admin_recursive_delete_path(string $target): bool {
    if (!file_exists($target)) {
        return true;
    }
    if (is_file($target) || is_link($target)) {
        if (@unlink($target)) {
            return true;
        }
        @chmod($target, 0664);
        return @unlink($target);
    }
    $items = scandir($target);
    if ($items === false) {
        return false;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $target . '/' . $item;
        if (!admin_recursive_delete_path($child)) {
            @chmod($child, 0775);
            if (!admin_recursive_delete_path($child)) {
                return false;
            }
            return false;
        }
    }
    if (@rmdir($target)) {
        return true;
    }
    @chmod($target, 0775);
    return @rmdir($target);
}
