<?php
$__nammuCliArgs = (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) ? $argv : [];
$__nammuRunScheduledOnly = PHP_SAPI === 'cli' && in_array('--run-scheduled', $__nammuCliArgs, true);
$__nammuRunScheduledMaintenanceOnly = PHP_SAPI === 'cli' && in_array('--run-scheduled-maintenance', $__nammuCliArgs, true);
$__nammuRunScheduledHeavyOnly = PHP_SAPI === 'cli' && in_array('--run-scheduled-heavy', $__nammuCliArgs, true);
$__nammuRunClusterScheduledOnly = PHP_SAPI === 'cli' && in_array('--run-cluster-scheduled', $__nammuCliArgs, true);
$__nammuRunFediverseLinkCardRefreshOnly = PHP_SAPI === 'cli' && in_array('--run-fediverse-link-card-refresh', $__nammuCliArgs, true);
$__nammuReplayFediverseDeletesOnly = PHP_SAPI === 'cli' && in_array('--replay-fediverse-deletes', $__nammuCliArgs, true);
$__nammuCliDebugEnabled = PHP_SAPI === 'cli' && in_array('--debug', $__nammuCliArgs, true);
if (!$__nammuRunScheduledOnly && !$__nammuRunScheduledMaintenanceOnly && !$__nammuRunScheduledHeavyOnly && !$__nammuRunClusterScheduledOnly && !$__nammuRunFediverseLinkCardRefreshOnly && !$__nammuReplayFediverseDeletesOnly) {
    $secureSession = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secureSession,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_start();
}

require_once __DIR__ . '/core/bootstrap.php';
require_once NAMMU_ROOT . '/core/helpers.php';
require_once NAMMU_ROOT . '/core/postal.php';
require_once NAMMU_ROOT . '/core/admin-nisaba.php';
require_once NAMMU_ROOT . '/core/admin-telex.php';
require_once NAMMU_ROOT . '/core/admin-ideas.php';
require_once NAMMU_ROOT . '/core/webmention.php';
// Funciones del panel, troceadas por dominio en core/admin-*.php.
require_once NAMMU_ROOT . '/core/admin-cli.php';
require_once NAMMU_ROOT . '/core/admin-csrf.php';
require_once NAMMU_ROOT . '/core/admin-scheduler.php';
require_once NAMMU_ROOT . '/core/admin-content.php';
require_once NAMMU_ROOT . '/core/admin-backups.php';
require_once NAMMU_ROOT . '/core/admin-media.php';
require_once NAMMU_ROOT . '/core/admin-itineraries.php';
require_once NAMMU_ROOT . '/core/admin-artifacts.php';
require_once NAMMU_ROOT . '/core/admin-settings.php';
require_once NAMMU_ROOT . '/core/admin-search-console.php';
require_once NAMMU_ROOT . '/core/admin-urls.php';
require_once NAMMU_ROOT . '/core/admin-indexnow.php';
require_once NAMMU_ROOT . '/core/admin-social.php';
require_once NAMMU_ROOT . '/core/admin-social-senders.php';
require_once NAMMU_ROOT . '/core/admin-mailing.php';
require_once NAMMU_ROOT . '/core/admin-mailing-campaigns.php';
require_once NAMMU_ROOT . '/core/admin-view.php';
require_once NAMMU_ROOT . '/core/admin-actions.php';

if (!$__nammuRunScheduledOnly && !$__nammuRunScheduledMaintenanceOnly && !$__nammuRunScheduledHeavyOnly && !$__nammuRunClusterScheduledOnly && !$__nammuRunFediverseLinkCardRefreshOnly && !$__nammuReplayFediverseDeletesOnly) {
    admin_start_csrf_form_injection();
}

// Load dependencies (optional)
$autoload = NAMMU_ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
use Nammu\Core\Itinerary;
use Nammu\Core\ItineraryRepository;
use Nammu\Core\ItineraryTopic;
use Nammu\Core\MarkdownConverter;
use Nammu\Core\Post;
use Nammu\Core\RssGenerator;
use Nammu\Core\SitemapGenerator;
use Symfony\Component\Yaml\Yaml;

// --- User Configuration ---
define('USER_FILE', NAMMU_ROOT . '/config/user.php');
define('CONTENT_DIR', NAMMU_ROOT . '/content');
define('ASSETS_DIR', NAMMU_ROOT . '/assets');
define('ITINERARIES_DIR', NAMMU_ROOT . '/itinerarios');
define('MEDIA_TAGS_FILE', NAMMU_ROOT . '/config/media-tags.json');
define('MAILING_SUBSCRIBERS_FILE', NAMMU_ROOT . '/config/mailing-subscribers.json');
define('MAILING_SUPPRESSED_FILE', NAMMU_ROOT . '/config/mailing-suppressed.json');
define('MAILING_BOUNCES_STATE_FILE', NAMMU_ROOT . '/config/mailing-bounces-state.json');
define('MAILING_SECRET_FILE', NAMMU_ROOT . '/config/mailing-secret.key');
nammu_ensure_directory(ITINERARIES_DIR);
$runScheduledOnly = $__nammuRunScheduledOnly;
$runScheduledMaintenanceOnly = $__nammuRunScheduledMaintenanceOnly;
$runScheduledHeavyOnly = $__nammuRunScheduledHeavyOnly;
$runClusterScheduledOnly = $__nammuRunClusterScheduledOnly;
$runFediverseLinkCardRefreshOnly = $__nammuRunFediverseLinkCardRefreshOnly;
$runReplayFediverseDeletesOnly = $__nammuReplayFediverseDeletesOnly;


if ($runScheduledOnly) {
    $result = admin_run_with_scheduled_lock('admin_run_scheduled_tasks');
    $result['mode'] = 'light';
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
}

if ($runScheduledMaintenanceOnly) {
    $result = admin_run_with_scheduled_lock('admin_run_scheduled_maintenance_tasks');
    $result['mode'] = 'maintenance';
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
}

if ($runScheduledHeavyOnly) {
    $result = admin_run_with_scheduled_lock('admin_run_scheduled_heavy_tasks');
    $result['mode'] = 'heavy';
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
}

if ($runClusterScheduledOnly) {
    $result = admin_run_cluster_scheduled_tasks();
    $result['mode'] = 'cluster';
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
}

if ($runFediverseLinkCardRefreshOnly) {
    $result = admin_run_with_scheduled_lock('admin_run_fediverse_link_card_refresh_tasks');
    $result['mode'] = 'fediverse_link_cards';
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
}

if ($runReplayFediverseDeletesOnly) {
    $result = admin_run_with_scheduled_lock(static function (): array {
        $config = nammu_load_config();
        if (!function_exists('nammu_fediverse_replay_all_deletes') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
            require_once NAMMU_ROOT . '/core/fediverso.php';
        }
        return function_exists('nammu_fediverse_replay_all_deletes')
            ? nammu_fediverse_replay_all_deletes($config)
            : ['ok' => false, 'message' => 'No se pudo cargar el replay de deletes del Fediverso.'];
    });
    $result['mode'] = 'delete_replay';
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
}

// --- Enrutado y estado de la petición ---

$page = $_GET['page'] ?? (is_logged_in() ? 'dashboard' : 'login');
$page = is_logged_in() && $page === 'login' ? 'dashboard' : $page;
$isItineraryAdminPage = in_array($page, ['itinerarios', 'itinerario', 'itinerario-tema'], true);
$error = null;
$user_exists = file_exists(USER_FILE);
// Mensajes de una sola lectura que las acciones dejan en sesión antes de redirigir.
$accountFeedback = admin_take_flash('account_feedback');
$socialFeedback = admin_take_flash('social_feedback');
$assetFeedback = admin_take_flash('asset_feedback');
$mailingFeedback = admin_take_flash('mailing_feedback');
$searchConsoleFeedback = admin_take_flash('search_console_feedback');
$bingWebmasterFeedback = admin_take_flash('bing_webmaster_feedback');
$nisabaFeedback = admin_take_flash('nisaba_feedback');
$telexFeedback = admin_take_flash('telex_feedback');
$statsBackupFeedback = admin_take_flash('stats_backup_feedback');
$backupFeedback = admin_take_flash('backup_feedback');
$fullBackupFeedback = admin_take_flash('full_backup_feedback');
$contactFeedback = admin_take_flash('contact_feedback');
$postalFeedback = admin_take_flash('postal_feedback');
$adsFeedback = admin_take_flash('ads_feedback');
$assetApply = admin_take_flash('asset_apply', false);
$itineraryFeedback = admin_take_flash('itinerary_feedback');

// Retornos OAuth (GET) de Bing Webmaster Tools y Gmail.
include NAMMU_ROOT . '/core/admin-actions-oauth.php';

// Acciones POST: cada grupo de formularios vive en core/admin-actions-<grupo>.php (ver core/admin-actions.php).
$adminCsrfValid = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfRequired = !isset($_POST['register']) && !isset($_POST['login']);
    $adminCsrfValid = !$csrfRequired || admin_csrf_is_valid($_POST['_nammu_csrf'] ?? '');
    if (!$adminCsrfValid) {
        $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
    } else {
        $adminActionFile = admin_action_file_for_request($_POST);
        if ($adminActionFile !== '') {
            include NAMMU_ROOT . '/core/' . $adminActionFile;
        }
    }
}

// Página a mostrar según la sesión.
$isLoggedIn = is_logged_in();
if ($isLoggedIn) {
    $page = $_GET['page'] ?? 'dashboard';
    if ($page === 'redes') {
        header('Location: admin.php?page=publish');
        exit;
    }
} else {
    $page = $user_exists ? 'login' : 'register';
}
include NAMMU_ROOT . '/core/admin-request-state.php';
if ($isLoggedIn && $page === 'fediverso') {
    require_once NAMMU_ROOT . '/core/fediverso.php';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminCsrfValid) {
        include NAMMU_ROOT . '/core/admin-actions-fediverso.php';
    }
}

// Respuestas GET que terminan aquí: fragmentos/estado del Fediverso (AJAX) y descarga de backups.
include NAMMU_ROOT . '/core/admin-endpoints.php';
$isItineraryAdminPage = in_array($page, ['itinerarios', 'itinerario', 'itinerario-tema'], true);

// Datos para las vistas.
include NAMMU_ROOT . '/core/admin-view-itineraries.php';
include NAMMU_ROOT . '/core/admin-view-data.php';

// --- Salida HTML ---
include NAMMU_ROOT . '/core/admin-layout-head.php';
?>
        <body>
        <div class="container">
<?php if (!$isLoggedIn): ?>
<?php include NAMMU_ROOT . '/core/admin-layout-auth.php'; ?>
<?php else: ?>
                <div class="admin-container">
<?php include NAMMU_ROOT . '/core/admin-layout-nav.php'; ?>
                    <div class="tab-content">
<?php
// Plantilla de cada pestaña (core/admin-page-*.php); cada una se incluye en este mismo ámbito.
$adminPageTemplates = [
    'dashboard' => 'admin-page-dashboard.php',
    'publish' => 'admin-page-publish.php',
    'edit' => 'admin-page-edit.php',
    'edit-post' => 'admin-page-edit.php',
    'edit-note' => 'admin-page-edit.php',
    'edit-news' => 'admin-page-edit.php',
    'resources' => 'admin-page-resources.php',
    'template' => 'admin-page-template.php',
    'itinerarios' => 'admin-page-itinerarios.php',
    'itinerario' => 'admin-page-itinerario.php',
    'itinerario-tema' => 'admin-page-itinerario-tema.php',
    'lista-correo' => 'admin-page-lista-correo.php',
    'correo-postal' => 'admin-page-correo-postal.php',
    'anuncios' => 'admin-page-anuncios.php',
    'fediverso' => 'admin-page-fediverso.php',
    'configuracion' => 'admin-page-configuracion.php',
];
if (isset($adminPageTemplates[$page])) {
    include NAMMU_ROOT . '/core/' . $adminPageTemplates[$page];
}
?>
                    </div>
                </div>
<?php endif; ?>
        </div>
<?php include NAMMU_ROOT . '/core/admin-layout-modals.php'; ?>
<?php include NAMMU_ROOT . '/core/admin-layout-scripts.php'; ?>
        </body>
        </html>
