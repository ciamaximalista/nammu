<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Itinerary.php';
require_once __DIR__ . '/../core/ItineraryTopic.php';
require_once __DIR__ . '/../core/ItineraryRepository.php';

use Nammu\Core\ItineraryRepository;

function smoke_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "ERROR: {$message}\n");
        exit(1);
    }
}

function smoke_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        if (is_file($path)) {
            @unlink($path);
        }
        return;
    }
    $items = scandir($path);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        smoke_remove_tree($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
}

/**
 * Renderiza una pestaña de admin.php en un subproceso PHP (ver tests/admin-render-prepend.php) y devuelve
 * ['exit' => int, 'stdout' => string, 'stderr' => string]. Aborta el proceso si tarda más de $timeoutSeconds.
 */
function smoke_render_admin_page(string $adminRoot, string $sessionDir, string $page, bool $loggedIn, array $query = [], int $timeoutSeconds = 180): array
{
    $command = [
        PHP_BINARY,
        '-d', 'auto_prepend_file=' . $adminRoot . '/tests/admin-render-prepend.php',
        '-d', 'session.save_path=' . $sessionDir,
        '-d', 'log_errors=0',
        $adminRoot . '/admin.php',
    ];
    $env = array_merge(getenv(), [
        'NAMMU_RENDER_PAGE' => $page,
        'NAMMU_RENDER_LOGGED_OUT' => $loggedIn ? '0' : '1',
        'NAMMU_RENDER_QUERY' => http_build_query($query),
    ]);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $adminRoot, $env);
    smoke_assert(is_resource($process), "No se pudo lanzar PHP para renderizar admin.php?page={$page}.");
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $startedAt = microtime(true);
    $exitCode = -1;
    while (true) {
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = (int) $status['exitcode'];
            break;
        }
        if (microtime(true) - $startedAt > $timeoutSeconds) {
            proc_terminate($process, 9);
            $stderr .= "\nTiempo agotado tras {$timeoutSeconds}s.";
            break;
        }
        usleep(20000);
    }
    $stdout .= (string) stream_get_contents($pipes[1]);
    $stderr .= (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    return ['exit' => $exitCode, 'stdout' => $stdout, 'stderr' => trim($stderr)];
}

$root = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'nammu-smoke-' . bin2hex(random_bytes(6));

try {
    smoke_assert(nammu_ensure_directory($root), 'No se pudo crear el directorio temporal.');

    $jsonFile = $root . '/atomic.json';
    smoke_assert(nammu_atomic_write_file($jsonFile, "{\"ok\":true}\n"), 'La escritura atomica fallo.');
    smoke_assert(is_file($jsonFile), 'La escritura atomica no creo el archivo.');
    smoke_assert((string) file_get_contents($jsonFile) === "{\"ok\":true}\n", 'La escritura atomica guardo contenido incorrecto.');

    $repo = new ItineraryRepository($root . '/itinerarios');
    $itinerary = $repo->saveItinerary('ruta-prueba', [
        'Title' => 'Ruta de prueba',
        'Description' => 'Smoke test',
        'Status' => 'published',
        'Order' => 1,
    ], 'Contenido de prueba.', [
        'questions' => [[
            'question' => 'Pregunta',
            'answers' => [[
                'text' => 'Respuesta',
                'correct' => true,
            ]],
        ]],
    ]);

    smoke_assert($itinerary->getSlug() === 'ruta-prueba', 'El repositorio no devolvio el slug esperado.');
    smoke_assert(is_file($root . '/itinerarios/ruta-prueba/index.md'), 'No se creo index.md del itinerario.');
    smoke_assert(is_file($root . '/itinerarios/ruta-prueba/index.quiz.json'), 'No se creo la autoevaluacion del itinerario.');

    $rssLinks = nammu_site_rss_links(
        ['site_name' => 'Sitio de prueba'],
        ['home' => ['content' => 'fediverse'], 'blog' => 'Sitio de prueba'],
        'https://example.test',
        true,
        true
    );
    smoke_assert(($rssLinks[0]['label'] ?? '') === 'RSS del sitio', 'La RSS del sitio no aparece primero.');
    smoke_assert(($rssLinks[0]['href'] ?? '') === 'https://example.test/rss.xml', 'La RSS del sitio no usa /rss.xml.');
    smoke_assert(($rssLinks[1]['href'] ?? '') === 'https://example.test/blog.xml', 'La RSS especifica del blog no usa /blog.xml.');
    smoke_assert(nammu_home_content_mode(['home' => ['content' => 'podcast']], true, false) === 'blog', 'El modo podcast sin episodios no cae a blog.');

    // Piezas del panel troceadas en core/admin-*.php: deben cargar sin errores fatales y el
    // despachador de acciones POST debe resolver claves a ficheros existentes.
    require_once __DIR__ . '/../core/bootstrap.php';
    foreach ([
        'admin-cli', 'admin-csrf', 'admin-scheduler', 'admin-content', 'admin-pending-submission', 'admin-backups', 'admin-media',
        'admin-itineraries', 'admin-artifacts', 'admin-settings', 'admin-search-console', 'admin-urls',
        'admin-indexnow', 'admin-social', 'admin-social-senders', 'admin-mailing', 'admin-mailing-campaigns',
        'admin-view', 'admin-actions',
    ] as $piece) {
        require_once __DIR__ . '/../core/' . $piece . '.php';
    }
    smoke_assert(function_exists('admin_run_scheduled_tasks') && function_exists('get_settings'), 'Las funciones del panel no se cargaron.');
    foreach (admin_action_files() as $key => $file) {
        smoke_assert(is_file(__DIR__ . '/../core/' . $file), "El despachador apunta a un fichero inexistente para {$key}: {$file}.");
    }
    smoke_assert(admin_action_file_for_request(['delete_post' => '1']) === 'admin-actions-content.php', 'El despachador no resuelve delete_post.');
    smoke_assert(admin_action_file_for_request(['fediverse_like_item' => '1']) === '', 'El despachador no debe atender acciones del Fediverso.');

    // Envío del editor con la sesión caducada: se conserva en la sesión (sin el token CSRF) hasta el siguiente login.
    $_SESSION = [];
    smoke_assert(!admin_pending_submission_stash(['login' => '1', 'username' => 'x']), 'Un login no debe conservarse como envío pendiente.');
    smoke_assert(!admin_pending_submission_stash(['save_draft' => '1', 'title' => '', 'content' => '  ']), 'Un editor vacío no debe conservarse.');
    smoke_assert(!admin_pending_submission_pending(), 'No debería haber envío pendiente todavía.');
    smoke_assert(admin_pending_submission_stash(['publish' => '1', 'title' => 'Prueba', 'content' => 'Texto', '_nammu_csrf' => 'x']), 'El envío de Publicar no se conservó.');
    smoke_assert(admin_pending_submission_pending(), 'El envío pendiente no quedó en la sesión.');
    $pendingSmoke = admin_pending_submission_take();
    smoke_assert(($pendingSmoke['context'] ?? '') === 'publish' && ($pendingSmoke['fields']['content'] ?? '') === 'Texto' && !isset($pendingSmoke['fields']['_nammu_csrf']), 'El envío pendiente no conserva los campos esperados.');
    smoke_assert(!admin_pending_submission_pending() && admin_pending_submission_take() === null, 'El envío pendiente debe consumirse al recogerlo.');
    smoke_assert(admin_pending_submission_stash(['update' => '1', 'filename' => 'entrada.md', 'title' => 'Prueba', 'content' => 'Texto']) && (admin_pending_submission_take()['context'] ?? '') === 'edit', 'El envío de Actualizar debe conservarse con contexto edit.');
    $_SESSION = [];
    foreach (['admin-request-state', 'admin-endpoints', 'admin-view-itineraries', 'admin-view-data', 'admin-view-dashboard',
        'admin-view-dashboard-queues', 'admin-view-dashboard-search', 'admin-view-dashboard-analytics', 'admin-view-dashboard-top',
        'admin-view-dashboard-counts', 'admin-layout-head', 'admin-layout-auth', 'admin-layout-nav', 'admin-layout-modals',
        'admin-layout-scripts', 'admin-actions-fediverso', 'admin-actions-oauth'] as $piece) {
        smoke_assert(is_file(__DIR__ . '/../core/' . $piece . '.php'), "Falta core/{$piece}.php.");
    }
    foreach (['admin.css', 'markdown-toolbar.js', 'media-modal.js', 'autosave.js', 'dashboard.css', 'dashboard.js', 'fediverso.css',
        'fediverso.js', 'edit.css', 'edit.js', 'publish.css', 'publish.js'] as $asset) {
        smoke_assert(is_file(__DIR__ . '/../core/admin-assets/' . $asset), "Falta core/admin-assets/{$asset}.");
    }

    // Render real de cada pestaña como usuario con sesión (y de la pantalla de acceso sin ella): debe terminar sin
    // avisos de PHP y con el panel completo. Es lo que detecta variables perdidas al mover código entre piezas.
    $adminRoot = dirname(__DIR__);
    $sessionDir = $root . '/sessions';
    smoke_assert(nammu_ensure_directory($sessionDir), 'No se pudo crear el directorio de sesiones de prueba.');
    $adminPages = [];
    foreach ([
        'dashboard', 'publish', 'edit', 'edit-post', 'edit-note', 'edit-news', 'resources', 'template', 'itinerarios',
        'itinerario', 'itinerario-tema', 'lista-correo', 'correo-postal', 'anuncios', 'configuracion',
    ] as $adminPage) {
        $adminPages[$adminPage] = [$adminPage, []];
    }
    foreach (['home', 'notifications', 'messages', 'mentions', 'network', 'settings'] as $fediverseTab) {
        $adminPages["fediverso&tab={$fediverseTab}"] = ['fediverso', ['tab' => $fediverseTab]];
    }
    foreach ($adminPages as $adminLabel => [$adminPage, $adminQuery]) {
        $render = smoke_render_admin_page($adminRoot, $sessionDir, $adminPage, true, $adminQuery);
        smoke_assert($render['exit'] === 0, "admin.php?page={$adminLabel} terminó con código {$render['exit']}.\n{$render['stderr']}");
        smoke_assert($render['stderr'] === '', "admin.php?page={$adminLabel} emitió avisos de PHP:\n{$render['stderr']}");
        smoke_assert(str_contains($render['stdout'], '</html>'), "admin.php?page={$adminLabel} no completó el HTML.");
        smoke_assert(
            str_contains($render['stdout'], 'class="admin-container"') && str_contains($render['stdout'], 'tab-pane'),
            "admin.php?page={$adminLabel} no pintó la pestaña con la sesión iniciada."
        );
    }
    $render = smoke_render_admin_page($adminRoot, $sessionDir, 'dashboard', false);
    smoke_assert($render['exit'] === 0 && $render['stderr'] === '', "La pantalla de acceso terminó con código {$render['exit']}.\n{$render['stderr']}");
    smoke_assert(
        str_contains($render['stdout'], '</html>') && !str_contains($render['stdout'], 'class="admin-container"')
            && (str_contains($render['stdout'], 'name="login"') || str_contains($render['stdout'], 'name="register"')),
        'Sin sesión, admin.php no mostró la pantalla de acceso o registro.'
    );

    echo "Smoke OK\n";
} finally {
    smoke_remove_tree($root);
}
