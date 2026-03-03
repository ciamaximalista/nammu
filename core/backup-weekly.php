#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$root = dirname(__DIR__);
$defaultBackupDir = $root . '/backups';
$defaultRetentionWeeks = 8;
$lockFile = $root . '/config/backup-full.lock';

$options = getopt('', ['dest::', 'retention-weeks::', 'cleanup-only']);
$backupDir = isset($options['dest']) && is_string($options['dest']) && trim($options['dest']) !== ''
    ? trim($options['dest'])
    : $defaultBackupDir;
$retentionWeeks = isset($options['retention-weeks']) ? (int) $options['retention-weeks'] : $defaultRetentionWeeks;
$cleanupOnly = array_key_exists('cleanup-only', $options);
if ($retentionWeeks < 1) {
    $retentionWeeks = $defaultRetentionWeeks;
}
$retentionDays = $retentionWeeks * 7;

if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "No se pudo crear el directorio de backups: {$backupDir}\n");
    exit(1);
}

$lockHandle = @fopen($lockFile, 'c+');
if (!$lockHandle) {
    fwrite(STDERR, "No se pudo abrir el lock de backup completo: {$lockFile}\n");
    exit(1);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "Otro backup completo está en ejecución.\n");
    fclose($lockHandle);
    exit(0);
}

$timestamp = time();
$stamp = date('Y-m-d_His', $timestamp);
$archiveName = "nammu-full-backup-{$stamp}.tar.gz";
$archivePath = rtrim($backupDir, '/') . '/' . $archiveName;
$tempPath = $archivePath . '.tmp';

/**
 * @return int archivos borrados
 */
$cleanupOldBackups = static function (string $dir, int $currentTs, int $retention) : int {
    $deleted = 0;
    $cutoff = $currentTs - ($retention * 86400);
    $dirItems = @scandir($dir);
    if (!is_array($dirItems)) {
        return 0;
    }
    foreach ($dirItems as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!preg_match('/^nammu-full-backup-\d{4}-\d{2}-\d{2}_\d{6}\.tar\.gz(?:\.sha256)?$/', $item)) {
            continue;
        }
        $fullPath = rtrim($dir, '/') . '/' . $item;
        $mtime = @filemtime($fullPath);
        if ($mtime !== false && $mtime < $cutoff && @unlink($fullPath)) {
            $deleted++;
        }
    }
    return $deleted;
};

if ($cleanupOnly) {
    $deleted = $cleanupOldBackups($backupDir, $timestamp, $retentionDays);
    fwrite(STDOUT, "Limpieza completada. Backups completos borrados: {$deleted}\n");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(0);
}

$paths = ['content', 'assets', 'config', 'itinerarios'];
$includePaths = [];
foreach ($paths as $path) {
    if (file_exists($root . '/' . $path)) {
        $includePaths[] = $path;
    }
}
if (empty($includePaths)) {
    fwrite(STDERR, "No se encontraron rutas para backup completo.\n");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

$fileListPath = rtrim($backupDir, '/') . '/.backup-full-filelist.txt';
$fileListPayload = implode(PHP_EOL, $includePaths) . PHP_EOL;
if (@file_put_contents($fileListPath, $fileListPayload) === false) {
    fwrite(STDERR, "No se pudo crear la lista de rutas para backup completo.\n");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

$command = implode(' ', [
    'tar',
    '--ignore-failed-read',
    '-czf',
    escapeshellarg($tempPath),
    '-C',
    escapeshellarg($root),
    '-T',
    escapeshellarg($fileListPath),
]);

exec($command . ' 2>&1', $outputLines, $exitCode);
@unlink($fileListPath);
if ($exitCode !== 0 || !is_file($tempPath)) {
    @unlink($tempPath);
    fwrite(STDERR, "Fallo al crear backup completo (tar exit {$exitCode}).\n" . implode("\n", $outputLines) . "\n");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

if (!@rename($tempPath, $archivePath)) {
    @unlink($tempPath);
    fwrite(STDERR, "No se pudo mover el backup completo temporal a destino final.\n");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

$hash = @hash_file('sha256', $archivePath) ?: '';
if ($hash !== '') {
    @file_put_contents($archivePath . '.sha256', $hash . '  ' . basename($archivePath) . PHP_EOL);
}

$deleted = $cleanupOldBackups($backupDir, $timestamp, $retentionDays);

$summary = [
    'timestamp' => $timestamp,
    'archive' => $archiveName,
    'size_bytes' => @filesize($archivePath) ?: 0,
    'sha256' => $hash,
    'retention_weeks' => $retentionWeeks,
    'paths' => $includePaths,
];
@file_put_contents(rtrim($backupDir, '/') . '/latest-full-backup.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

fwrite(STDOUT, "Backup completo creado: {$archivePath}\n");
if ($hash !== '') {
    fwrite(STDOUT, "SHA256: {$hash}\n");
}
if ($deleted > 0) {
    fwrite(STDOUT, "Backups completos antiguos borrados: {$deleted}\n");
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
exit(0);
