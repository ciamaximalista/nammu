<?php
/**
 * Nammu — smoke test. Se carga con `php -d auto_prepend_file=tests/admin-render-prepend.php admin.php` para renderizar una
 * pestaña del panel desde la CLI como si fuera una petición GET de un usuario con sesión iniciada (o sin ella, con
 * NAMMU_RENDER_LOGGED_OUT=1). La pestaña llega en NAMMU_RENDER_PAGE y los parámetros extra en NAMMU_RENDER_QUERY.
 * Cualquier aviso de PHP se vuelca a stderr para que tests/smoke.php lo detecte.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

$renderPage = (string) (getenv('NAMMU_RENDER_PAGE') ?: 'dashboard');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REQUEST_URI'] = '/admin.php?page=' . $renderPage;
$_SERVER['SCRIPT_NAME'] = '/admin.php';
$_SERVER['PHP_SELF'] = '/admin.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'nammu-smoke';
$_GET = ['page' => $renderPage];
parse_str((string) getenv('NAMMU_RENDER_QUERY'), $renderExtraQuery);
if (is_array($renderExtraQuery)) {
    $_GET = array_merge($_GET, $renderExtraQuery);
}
$_REQUEST = $_GET;

if (getenv('NAMMU_RENDER_LOGGED_OUT') !== '1') {
    // admin.php hace su propio session_start(); dejamos preparada una sesión con la marca de login que lee is_logged_in().
    session_id('nammusmoke' . substr(sha1($renderPage . '|' . getmypid()), 0, 16));
    session_start();
    $_SESSION['loggedin'] = true;
    session_write_close();
}
unset($renderPage, $renderExtraQuery);
