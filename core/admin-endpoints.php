<?php
/**
 * Nammu — panel de administración. Respuestas GET que terminan la petición sin pintar el panel: fragmentos y estado de las
 * pestañas del Fediverso (AJAX) y descarga de backups completos. Se incluye desde admin.php (ámbito global).
 */
if ($isLoggedIn && $page === 'fediverso' && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fediverse_fragment'])) {
    $normalizeFediversePanelFragment = static function (string $html): string {
        $startMarker = '<!-- FEDIVERSE_TAB_PANEL_START -->';
        $endMarker = '<!-- FEDIVERSE_TAB_PANEL_END -->';
        $startPos = strpos($html, $startMarker);
        $endPos = strpos($html, $endMarker);
        if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
            return trim(substr($html, $startPos + strlen($startMarker), $endPos - ($startPos + strlen($startMarker))));
        }
        return trim($html);
    };
    $fediverseFragmentTab = strtolower(trim((string) ($_GET['tab'] ?? 'home')));
    if (!in_array($fediverseFragmentTab, ['home', 'notifications', 'messages', 'mentions', 'network', 'settings'], true)) {
        $fediverseFragmentTab = 'home';
    }
    $fediverseFragmentContext = [];
    if ($fediverseFragmentTab === 'home') {
        $fediverseFragmentContext['timeline_page'] = max(1, (int) ($_GET['timeline_page'] ?? 1));
    }
    $fediverseFragmentVersion = nammu_fediverse_tab_version($fediverseFragmentTab);
    $fediverseCachedFragment = nammu_fediverse_get_cached_fragment($fediverseFragmentTab, $fediverseFragmentVersion, $fediverseFragmentContext, 20);
    if ($fediverseCachedFragment !== '') {
        $fediverseCachedFragment = $normalizeFediversePanelFragment($fediverseCachedFragment);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Fediverse-Version: ' . $fediverseFragmentVersion);
        echo $fediverseCachedFragment;
        exit;
    }
    ob_start();
    include NAMMU_ROOT . '/core/admin-page-fediverso.php';
    $fediverseHtml = (string) ob_get_clean();
    $fediverseHtml = $normalizeFediversePanelFragment($fediverseHtml);
    nammu_fediverse_store_cached_fragment($fediverseFragmentTab, $fediverseFragmentVersion, $fediverseFragmentContext, $fediverseHtml);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Fediverse-Version: ' . $fediverseFragmentVersion);
    echo $fediverseHtml;
    exit;
}

if ($isLoggedIn && $page === 'fediverso' && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fediverse_state'])) {
    require_once NAMMU_ROOT . '/core/fediverso.php';
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    $tabs = ['home', 'notifications', 'messages', 'mentions', 'network', 'settings'];
    echo json_encode(['versions' => nammu_fediverse_stream_state($tabs)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($isLoggedIn && isset($_GET['download_full_backup'])) {
    $backupFile = trim((string) ($_GET['download_full_backup'] ?? ''));
    if (!preg_match('/^nammu-full-backup-\d{4}-\d{2}-\d{2}_\d{6}\.tar\.gz$/', $backupFile)) {
        $_SESSION['full_backup_feedback'] = [
            'type' => 'danger',
            'message' => 'Backup inválido.',
        ];
        header('Location: admin.php?page=configuracion');
        exit;
    }
    $backupPath = admin_stats_backup_dir() . '/' . $backupFile;
    if (!is_file($backupPath) || !is_readable($backupPath)) {
        $_SESSION['full_backup_feedback'] = [
            'type' => 'danger',
            'message' => 'No se pudo encontrar el backup solicitado.',
        ];
        header('Location: admin.php?page=configuracion');
        exit;
    }
    if (ob_get_length()) {
        @ob_end_clean();
    }
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . basename($backupPath) . '"');
    header('Content-Length: ' . (string) (@filesize($backupPath) ?: 0));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    readfile($backupPath);
    exit;
}
