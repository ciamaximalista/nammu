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
        'admin-cli', 'admin-csrf', 'admin-scheduler', 'admin-content', 'admin-backups', 'admin-media',
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
    foreach (['admin-request-state', 'admin-endpoints', 'admin-view-itineraries', 'admin-view-data', 'admin-layout-head',
        'admin-layout-auth', 'admin-layout-nav', 'admin-layout-modals', 'admin-layout-scripts', 'admin-actions-fediverso', 'admin-actions-oauth'] as $piece) {
        smoke_assert(is_file(__DIR__ . '/../core/' . $piece . '.php'), "Falta core/{$piece}.php.");
    }
    foreach (['admin.css', 'markdown-toolbar.js', 'media-modal.js', 'autosave.js'] as $asset) {
        smoke_assert(is_file(__DIR__ . '/../core/admin-assets/' . $asset), "Falta core/admin-assets/{$asset}.");
    }

    echo "Smoke OK\n";
} finally {
    smoke_remove_tree($root);
}
