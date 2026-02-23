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
$defaultRetention = 30;
$lockFile = $root . '/config/backup.lock';

$options = getopt('', ['dest::', 'retention::']);
$backupDir = isset($options['dest']) && is_string($options['dest']) && trim($options['dest']) !== ''
    ? trim($options['dest'])
    : $defaultBackupDir;
$retentionDays = isset($options['retention']) ? (int) $options['retention'] : $defaultRetention;
if ($retentionDays < 1) {
    $retentionDays = $defaultRetention;
}

if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "No se pudo crear el directorio de backups: {$backupDir}\n");
    exit(1);
}

$lockHandle = @fopen($lockFile, 'c+');
if (!$lockHandle) {
    fwrite(STDERR, "No se pudo abrir el lock de backup: {$lockFile}\n");
    exit(1);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "Otro backup está en ejecución.\n");
    fclose($lockHandle);
    exit(0);
}

$timestamp = time();
$stamp = date('Y-m-d_His', $timestamp);
$archiveName = "nammu-stats-backup-{$stamp}.tar.gz";
$archivePath = rtrim($backupDir, '/') . '/' . $archiveName;
$tempPath = $archivePath . '.tmp';

$statsFiles = [
    'config/analytics.json',
    'config/gsc-cache.json',
    'config/bing-cache.json',
    'config/indexnow-log.json',
];

foreach (glob($root . '/itinerarios/*/stats.json') ?: [] as $statsPath) {
    $relative = ltrim(str_replace($root, '', $statsPath), '/');
    if ($relative !== '') {
        $statsFiles[] = $relative;
    }
}

$statsFiles = array_values(array_unique(array_filter($statsFiles, static function (string $relative) use ($root): bool {
    return is_file($root . '/' . ltrim($relative, '/'));
})));

if (empty($statsFiles)) {
    fwrite(STDOUT, "No hay archivos de estadísticas para respaldar.\n");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(0);
}

$fileListPath = rtrim($backupDir, '/') . '/.backup-stats-filelist.txt';
$fileListPayload = implode(PHP_EOL, $statsFiles) . PHP_EOL;
if (@file_put_contents($fileListPath, $fileListPayload) === false) {
    fwrite(STDERR, "No se pudo crear la lista de archivos de estadísticas.\n");
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
    fwrite(STDERR, "Fallo al crear backup (tar exit {$exitCode}).\n" . implode("\n", $outputLines) . "\n");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

if (!@rename($tempPath, $archivePath)) {
    @unlink($tempPath);
    fwrite(STDERR, "No se pudo mover el backup temporal a destino final.\n");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

$hash = @hash_file('sha256', $archivePath) ?: '';
if ($hash !== '') {
    @file_put_contents($archivePath . '.sha256', $hash . '  ' . basename($archivePath) . PHP_EOL);
}

$cutoff = $timestamp - ($retentionDays * 86400);
$dirItems = @scandir($backupDir);
if (is_array($dirItems)) {
    foreach ($dirItems as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!preg_match('/^nammu-(?:stats-)?backup-\d{4}-\d{2}-\d{2}_\d{6}\.tar\.gz(?:\.sha256)?$/', $item)) {
            continue;
        }
        $fullPath = rtrim($backupDir, '/') . '/' . $item;
        $mtime = @filemtime($fullPath);
        if ($mtime !== false && $mtime < $cutoff) {
            @unlink($fullPath);
        }
    }
}

$summary = [
    'timestamp' => $timestamp,
    'archive' => $archiveName,
    'size_bytes' => @filesize($archivePath) ?: 0,
    'sha256' => $hash,
    'retention_days' => $retentionDays,
    'files' => $statsFiles,
];
@file_put_contents(rtrim($backupDir, '/') . '/latest-backup.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

fwrite(STDOUT, "Backup creado: {$archivePath}\n");
if ($hash !== '') {
    fwrite(STDOUT, "SHA256: {$hash}\n");
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
exit(0);
