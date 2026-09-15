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

// --- Routing and Logic ---

$page = $_GET['page'] ?? (is_logged_in() ? 'dashboard' : 'login');
$page = is_logged_in() && $page === 'login' ? 'dashboard' : $page;
$isItineraryAdminPage = in_array($page, ['itinerarios', 'itinerario', 'itinerario-tema'], true);
$error = null;
$user_exists = file_exists(USER_FILE);
$accountFeedback = $_SESSION['account_feedback'] ?? null;
if (!is_array($accountFeedback) || !isset($accountFeedback['message'], $accountFeedback['type'])) {
    $accountFeedback = null;
} else {
    unset($_SESSION['account_feedback']);
}
$socialFeedback = $_SESSION['social_feedback'] ?? null;
if (!is_array($socialFeedback) || !isset($socialFeedback['message'], $socialFeedback['type'])) {
    $socialFeedback = null;
} else {
    unset($_SESSION['social_feedback']);
}
$assetFeedback = $_SESSION['asset_feedback'] ?? null;
if (!is_array($assetFeedback) || !isset($assetFeedback['message'], $assetFeedback['type'])) {
    $assetFeedback = null;
} else {
    unset($_SESSION['asset_feedback']);
}
$mailingFeedback = $_SESSION['mailing_feedback'] ?? null;
if (!is_array($mailingFeedback) || !isset($mailingFeedback['message'], $mailingFeedback['type'])) {
    $mailingFeedback = null;
} else {
    unset($_SESSION['mailing_feedback']);
}
$searchConsoleFeedback = $_SESSION['search_console_feedback'] ?? null;
if (!is_array($searchConsoleFeedback) || !isset($searchConsoleFeedback['message'], $searchConsoleFeedback['type'])) {
    $searchConsoleFeedback = null;
} else {
    unset($_SESSION['search_console_feedback']);
}
$bingWebmasterFeedback = $_SESSION['bing_webmaster_feedback'] ?? null;
if (!is_array($bingWebmasterFeedback) || !isset($bingWebmasterFeedback['message'], $bingWebmasterFeedback['type'])) {
    $bingWebmasterFeedback = null;
} else {
    unset($_SESSION['bing_webmaster_feedback']);
}
$nisabaFeedback = $_SESSION['nisaba_feedback'] ?? null;
if (!is_array($nisabaFeedback) || !isset($nisabaFeedback['message'], $nisabaFeedback['type'])) {
    $nisabaFeedback = null;
} else {
    unset($_SESSION['nisaba_feedback']);
}
$telexFeedback = $_SESSION['telex_feedback'] ?? null;
if (!is_array($telexFeedback) || !isset($telexFeedback['message'], $telexFeedback['type'])) {
    $telexFeedback = null;
} else {
    unset($_SESSION['telex_feedback']);
}
$statsBackupFeedback = $_SESSION['stats_backup_feedback'] ?? null;
if (!is_array($statsBackupFeedback) || !isset($statsBackupFeedback['message'], $statsBackupFeedback['type'])) {
    $statsBackupFeedback = null;
} else {
    unset($_SESSION['stats_backup_feedback']);
}
$backupFeedback = $_SESSION['backup_feedback'] ?? null;
if (!is_array($backupFeedback) || !isset($backupFeedback['message'], $backupFeedback['type'])) {
    $backupFeedback = null;
} else {
    unset($_SESSION['backup_feedback']);
}
$fullBackupFeedback = $_SESSION['full_backup_feedback'] ?? null;
if (!is_array($fullBackupFeedback) || !isset($fullBackupFeedback['message'], $fullBackupFeedback['type'])) {
    $fullBackupFeedback = null;
} else {
    unset($_SESSION['full_backup_feedback']);
}
$contactFeedback = $_SESSION['contact_feedback'] ?? null;
if (!is_array($contactFeedback) || !isset($contactFeedback['message'], $contactFeedback['type'])) {
    $contactFeedback = null;
} else {
    unset($_SESSION['contact_feedback']);
}
$postalFeedback = $_SESSION['postal_feedback'] ?? null;
if (!is_array($postalFeedback) || !isset($postalFeedback['message'], $postalFeedback['type'])) {
    $postalFeedback = null;
} else {
    unset($_SESSION['postal_feedback']);
}
$adsFeedback = $_SESSION['ads_feedback'] ?? null;
if (!is_array($adsFeedback) || !isset($adsFeedback['message'], $adsFeedback['type'])) {
    $adsFeedback = null;
} else {
    unset($_SESSION['ads_feedback']);
}
$assetApply = $_SESSION['asset_apply'] ?? null;
if (!is_array($assetApply)) {
    $assetApply = null;
} else {
    unset($_SESSION['asset_apply']);
}
$itineraryFeedback = $_SESSION['itinerary_feedback'] ?? null;
if (!is_array($itineraryFeedback) || !isset($itineraryFeedback['message'], $itineraryFeedback['type'])) {
    $itineraryFeedback = null;
} else {
    unset($_SESSION['itinerary_feedback']);
}

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


// If logged in, show admin panel
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
$socialBroadcastFeedback = null;
$socialBroadcastText = '';
$socialBroadcastImage = '';
$socialBroadcastActuality = false;
$socialBroadcastNetworks = [];
$fediverseFeedback = null;
$fediverseActorInput = '';
$fediverseMessageRecipient = '';
$fediverseMessageText = '';
$fediverseInspectUrl = '';
$fediverseInspectResult = null;
$fediverseRedirect = false;
$fediverseRedirectState = [];
$notesFeedback = $_SESSION['notes_feedback'] ?? null;
if ($notesFeedback !== null) {
    unset($_SESSION['notes_feedback']);
}
$newsFeedback = $_SESSION['news_feedback'] ?? null;
if ($newsFeedback !== null) {
    unset($_SESSION['news_feedback']);
}
if (!empty($_SESSION['fediverse_feedback'])) {
    $fediverseFeedback = is_array($_SESSION['fediverse_feedback']) ? $_SESSION['fediverse_feedback'] : null;
    unset($_SESSION['fediverse_feedback']);
}
if (!empty($_SESSION['fediverse_state']) && is_array($_SESSION['fediverse_state'])) {
    $fediverseRedirectState = $_SESSION['fediverse_state'];
    unset($_SESSION['fediverse_state']);
    $fediverseActorInput = (string) ($fediverseRedirectState['actor_input'] ?? $fediverseActorInput);
    $fediverseMessageRecipient = (string) ($fediverseRedirectState['message_recipient'] ?? $fediverseMessageRecipient);
    $fediverseMessageText = (string) ($fediverseRedirectState['message_text'] ?? $fediverseMessageText);
}
if ($isLoggedIn && $page === 'publish') {
    require_once NAMMU_ROOT . '/core/admin-redes.php';
    if (!empty($_SESSION['social_broadcast_feedback'])) {
        $socialBroadcastFeedback = is_array($_SESSION['social_broadcast_feedback']) ? $_SESSION['social_broadcast_feedback'] : null;
        unset($_SESSION['social_broadcast_feedback']);
    }
    if (!empty($_SESSION['social_broadcast_state']) && is_array($_SESSION['social_broadcast_state'])) {
        $socialBroadcastState = $_SESSION['social_broadcast_state'];
        unset($_SESSION['social_broadcast_state']);
        $socialBroadcastText = (string) ($socialBroadcastState['message_text'] ?? '');
        $socialBroadcastImage = (string) ($socialBroadcastState['image'] ?? '');
        $socialBroadcastActuality = !empty($socialBroadcastState['actuality']);
        $socialBroadcastNetworks = is_array($socialBroadcastState['networks'] ?? null) ? $socialBroadcastState['networks'] : [];
    }
}
if ($isLoggedIn && $page === 'fediverso') {
    require_once NAMMU_ROOT . '/core/fediverso.php';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminCsrfValid) {
        include NAMMU_ROOT . '/core/admin-actions-fediverso.php';
    }
}

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
$isItineraryAdminPage = in_array($page, ['itinerarios', 'itinerario', 'itinerario-tema'], true);

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

$itinerariesList = [];
$selectedItinerary = null;
$selectedTopic = null;
$itineraryFormData = [
    'title' => '',
    'description' => '',
    'image' => '',
    'slug' => '',
    'content' => '',
    'class_choice' => '',
    'class_custom' => '',
    'usage_logic' => 'free',
    'status' => 'draft',
    'quiz' => '',
    'quiz_summary' => '',
    'mode' => 'new',
];
$topicFormData = [
    'title' => '',
    'description' => '',
    'image' => '',
    'slug' => '',
    'content' => '',
    'number' => 1,
    'quiz' => '',
    'quiz_summary' => '',
    'mode' => 'new',
];
$topicNumberOptions = [1];

if (is_logged_in() && $isItineraryAdminPage) {
    $itinerariesList = admin_list_itineraries();
    $requestedSlug = isset($_GET['itinerary']) ? ItineraryRepository::normalizeSlug((string) $_GET['itinerary']) : '';
    if ($requestedSlug !== '') {
        $selectedItinerary = admin_load_itinerary($requestedSlug);
    }
    $isNewItinerary = isset($_GET['new']) || $selectedItinerary === null;
    if ($selectedItinerary !== null) {
        $classState = admin_itinerary_class_form_state($selectedItinerary->getClassLabel());
        $itineraryQuiz = method_exists($selectedItinerary, 'getQuiz') ? $selectedItinerary->getQuiz() : [];
        $itineraryFormData = [
            'title' => $selectedItinerary->getTitle(),
            'description' => $selectedItinerary->getDescription(),
            'image' => $selectedItinerary->getImage() ?? '',
            'slug' => $selectedItinerary->getSlug(),
            'content' => $selectedItinerary->getContent(),
            'class_choice' => $classState['choice'] ?? '',
            'class_custom' => $classState['custom'] ?? '',
            'usage_logic' => method_exists($selectedItinerary, 'getUsageLogic') ? $selectedItinerary->getUsageLogic() : 'free',
            'status' => method_exists($selectedItinerary, 'getStatus') ? $selectedItinerary->getStatus() : 'published',
            'quiz' => admin_quiz_json($itineraryQuiz),
            'quiz_summary' => admin_quiz_summary($itineraryQuiz),
            'order' => method_exists($selectedItinerary, 'getOrder') ? (int) $selectedItinerary->getOrder() : 0,
            'mode' => 'existing',
        ];
    } else {
        $itineraryFormData['mode'] = 'new';
        $itineraryFormData['order'] = admin_next_itinerary_order();
    }
    $topicParam = $_GET['topic'] ?? '';
    if ($selectedItinerary !== null && $topicParam !== '' && $topicParam !== 'new') {
        $normalizedTopic = ItineraryRepository::normalizeSlug($topicParam);
        if ($normalizedTopic !== '') {
            $selectedTopic = admin_load_itinerary_topic($selectedItinerary->getSlug(), $normalizedTopic);
        }
    }
    if ($selectedTopic !== null) {
        $quizData = $selectedTopic->getQuiz();
        $topicFormData = [
            'title' => $selectedTopic->getTitle(),
            'description' => $selectedTopic->getDescription(),
            'image' => $selectedTopic->getImage() ?? '',
            'slug' => $selectedTopic->getSlug(),
            'content' => $selectedTopic->getContent(),
            'number' => max(1, $selectedTopic->getNumber()),
            'quiz' => admin_quiz_json($quizData),
            'quiz_summary' => admin_quiz_summary($quizData),
            'mode' => 'existing',
        ];
    } else {
        $topicFormData['mode'] = 'new';
        if ($selectedItinerary !== null) {
            $topicFormData['number'] = max(1, $selectedItinerary->getTopicCount() + 1);
        }
    }
    if ($selectedItinerary !== null) {
        $topicCount = $selectedItinerary->getTopicCount();
        $maxOptions = $topicCount + ($selectedTopic === null ? 1 : 0);
        $maxOptions = max(1, $maxOptions);
        $topicNumberOptions = range(1, $maxOptions);
        if ($topicFormData['number'] > $maxOptions) {
            $topicFormData['number'] = $maxOptions;
        }
    } else {
        $topicNumberOptions = [1];
        $topicFormData['number'] = 1;
    }
    if ($isNewItinerary) {
        $itineraryFormData['slug'] = '';
        $itineraryFormData['content'] = '';
        $itineraryFormData['usage_logic'] = 'free';
        $itineraryFormData['status'] = 'draft';
    }
}

$settings = get_settings();
$socialDefaults = [
    'default_description' => '',
    'home_image' => '',
    'podcast_image' => '',
    'podcast_category' => 'Technology',
    'twitter' => '',
    'linkedin' => '',
    'facebook_app_id' => '',
];
$socialSettings = array_merge($socialDefaults, $settings['social'] ?? []);
$socialDefaultDescription = $socialSettings['default_description'] ?? '';
$socialHomeImage = $socialSettings['home_image'] ?? '';
$socialPodcastImage = $socialSettings['podcast_image'] ?? '';
$socialPodcastCategory = $socialSettings['podcast_category'] ?? 'Technology';
$socialPodcastCategoryOptions = ['Arts', 'Business', 'Comedy', 'Education', 'Fiction', 'Government', 'History', 'Health & Fitness', 'Kids & Family', 'Leisure', 'Music', 'News', 'Religion & Spirituality', 'Science', 'Society & Culture', 'Sports', 'Technology', 'True Crime', 'TV & Film'];
if (!in_array($socialPodcastCategory, $socialPodcastCategoryOptions, true)) {
    $socialPodcastCategory = 'Technology';
}
$socialTwitter = $socialSettings['twitter'] ?? '';
$socialLinkedin = $socialSettings['linkedin'] ?? '';
$socialFacebookAppId = $socialSettings['facebook_app_id'] ?? '';
$nisabaConfig = $settings['nisaba'] ?? [];
$nisabaUrls = is_array($nisabaConfig['urls'] ?? null) ? $nisabaConfig['urls'] : [];
$nisabaUrl = trim((string) ($nisabaConfig['url'] ?? ''));
if ($nisabaUrl !== '' && !in_array($nisabaUrl, $nisabaUrls, true)) {
    array_unshift($nisabaUrls, $nisabaUrl);
}
$nisabaUrlsValue = implode("\n", array_values(array_filter(array_map('strval', $nisabaUrls))));
$nisabaPrimaryUrl = $nisabaUrls[0] ?? '';
$nisabaEnabled = $nisabaPrimaryUrl !== '' && function_exists('admin_nisaba_fetch_notes');
$nisabaFeedUrl = $nisabaEnabled ? admin_nisaba_feed_url($nisabaPrimaryUrl) : '';
$nisabaPages = ['publish', 'edit', 'edit-post', 'itinerario', 'itinerario-tema'];
$nisabaModalEnabled = $nisabaEnabled && in_array($page, $nisabaPages, true);
$nisabaNotes = $nisabaModalEnabled ? admin_nisaba_fetch_notes($nisabaPrimaryUrl, 14) : [];
$telexConfig = $settings['telex'] ?? [];
$telexUrls = is_array($telexConfig['urls'] ?? null) ? $telexConfig['urls'] : [];
$telexEnabled = !empty($telexUrls) && function_exists('admin_telex_fetch_notes');
$telexPages = ['publish', 'edit', 'edit-post', 'itinerario', 'itinerario-tema'];
$telexModalEnabled = $telexEnabled && in_array($page, $telexPages, true);
$telexNotes = $telexModalEnabled ? admin_telex_fetch_notes($telexUrls, 14) : [];
$ideasPages = ['publish', 'edit', 'edit-post', 'itinerario', 'itinerario-tema'];
$ideasModalEnabled = in_array($page, $ideasPages, true) && function_exists('admin_ideas_build');
$ideasEnabled = $ideasModalEnabled;
$ideasSuggestions = $ideasModalEnabled ? admin_ideas_build(CONTENT_DIR, 30) : [];
$templateImages = is_array($settings['template']['images'] ?? null) ? $settings['template']['images'] : [];
$adminLogoPath = trim((string) ($templateImages['logo'] ?? ''));
$adminLogoUrl = '';
if ($adminLogoPath !== '') {
    if (preg_match('#^https?://#i', $adminLogoPath)) {
        $adminLogoUrl = $adminLogoPath;
    } else {
        $normalizedLogo = ltrim($adminLogoPath, '/');
        $normalizedLogo = str_replace(['../', '..\\', './', '.\\'], '', $normalizedLogo);
        if (!str_starts_with($normalizedLogo, 'assets/')) {
            $normalizedLogo = 'assets/' . $normalizedLogo;
        }
        $adminLogoUrl = $normalizedLogo;
    }
}
$adminLogoLink = trim((string) ($settings['site_url'] ?? ''));
$adminLogoLink = $adminLogoLink !== '' ? $adminLogoLink : 'index.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nammu</title>
    <link rel="icon" href="nammu.png" type="image/png">
    <script>
        (function() {
            try {
                if (localStorage.getItem('nammuAdminTheme') === 'dark') {
                    document.documentElement.setAttribute('data-admin-theme', 'dark');
                }
            } catch (error) {
                // Si el navegador bloquea localStorage, el admin permanece en modo claro.
            }
        })();
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Gabarito:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
<?php admin_inline_asset('admin.css'); ?>
            </style>

        </head>

        <body>

        

        <div class="container">

        

            <?php if (!is_logged_in()): ?>

                <div class="auth-container">

                    <img src="nammu.png" alt="Nammu Logo" class="logo">

                    <?php if ($page === 'register'): ?>

                        <h2 class="text-center">Registrarse</h2>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>

                        <form method="post">

                            <div class="form-group">

                                <label for="username">Usuario</label>

                                <input type="text" name="username" id="username" class="form-control" required>

                            </div>

                            <div class="form-group">

                                <label for="password">Contraseña</label>

                                <input type="password" name="password" id="password" class="form-control" required>

                            </div>

                            <button type="submit" name="register" class="btn btn-primary btn-block">Registrarse</button>

                        </form>

                    <?php else: ?>

                        <h2 class="text-center">Iniciar sesión</h2>

                        <?php if ($error): ?>

                            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>

                        <?php endif; ?>

                        <form method="post">

                            <div class="form-group">

                                <label for="username">Usuario</label>

                                <input type="text" name="username" id="username" class="form-control" required>

                            </div>

                            <div class="form-group">

                                <label for="password">Contraseña</label>

                                <input type="password" name="password" id="password" class="form-control" required>

                            </div>

                            <button type="submit" name="login" class="btn btn-primary btn-block">Iniciar sesión</button>

                        </form>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="admin-container">

                    <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">

                        <a class="navbar-brand" href="?page=dashboard"><img src="nammu.png" alt="Nammu Logo" style="max-width: 100px;"></a>
                        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Mostrar navegación">
                            <span class="navbar-toggler-icon"></span>
                        </button>

                        <div class="collapse navbar-collapse" id="adminNavbar">

                            <ul class="navbar-nav mr-auto">

                                <li class="nav-item <?= $page === 'dashboard' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=dashboard" title="Escritorio Nammu" aria-label="Escritorio Nammu">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 4h7v7H4V4zm9 0h7v4h-7V4zm0 6h7v10h-7V10zm-9 3h7v7H4v-7z" fill="currentColor"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'publish' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=publish" title="Publicar" aria-label="Publicar">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M14 4l6 6-9.5 9.5H4v-6.5L13.5 4H14z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M12.5 5.5l6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M8.5 15.5l5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M4 20h6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= in_array($page, ['edit', 'edit-post', 'edit-note', 'edit-news'], true) ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=edit" title="Editar" aria-label="Editar">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 20h4l10-10-4-4L4 16v4z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M13 6l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'resources' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=resources" title="Recursos" aria-label="Recursos">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                            <circle cx="9" cy="10" r="2" fill="currentColor"/>
                                            <path d="M5 17l4-4 3 3 3-3 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'template' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=template" title="Plantilla" aria-label="Plantilla">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 4h16v16H4V4z" stroke="currentColor" stroke-width="2"/>
                                            <path d="M4 9h16M9 4v16" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= ($page === 'itinerarios' || $page === 'itinerario' || $page === 'itinerario-tema') ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=itinerarios" title="Itinerarios" aria-label="Itinerarios">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 5H10C11.1046 5 12 5.89543 12 7V19H4C2.89543 19 2 18.1046 2 17V7C2 5.89543 2.89543 5 4 5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M20 5H14C12.8954 5 12 5.89543 12 7V19H20C21.1046 19 22 18.1046 22 17V7C22 5.89543 21.1046 5 20 5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <line x1="12" y1="7" x2="12" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'lista-correo' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=lista-correo" title="Lista" aria-label="Lista">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 6h16v12H4V6z" stroke="currentColor" stroke-width="2"/>
                                            <path d="M4 7l8 6 8-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'correo-postal' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=correo-postal" title="Correo Postal" aria-label="Correo Postal">
                                        <svg width="44" height="44" viewBox="-55 -55 407 407" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path fill="currentColor" d="M149.999,162.915v120.952c0,7.253,5.74,13.133,12.993,13.133c7.253,0,12.993-5.88,12.993-13.133V162.915h100.813c7.253,0,13.128-6.401,13.128-13.654V74.254c0-19.599-7.78-38.348-21.912-52.364C253.934,7.926,235.386,0,215.783,0H80.675C40.091,0,7.074,33.626,7.074,74.026v75.236c0,7.253,5.88,13.654,13.133,13.654H149.999z M33.06,135.929V74.026c0-25.918,21.376-47.003,47.476-47.003c26.1,0,47.474,21.188,47.474,47.231v61.675H33.06z M263.94,135.929H154.997V74.254c0-18.05-7.285-35.274-18.135-48.267h78.922c25.955,0,48.156,22.51,48.156,48.267V135.929z"/>
                                            <path fill="currentColor" d="M80.036,58.311c-7.253,0-12.993,5.88-12.993,13.133v1.052c0,7.253,5.74,13.133,12.993,13.133c7.253,0,12.993-5.88,12.993-13.133v-1.052C93.029,64.19,87.289,58.311,80.036,58.311z"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'anuncios' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=anuncios" title="Difusión" aria-label="Difusión">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4 10v4l8 2V6l-8 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M12 6l8-2v16l-8-2" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M6 14l2 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'fediverso' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=fediverso" title="Fediverso" aria-label="Fediverso">
                                        <?= function_exists('nammu_fediverse_glyph_svg') ? nammu_fediverse_glyph_svg(48) : nammu_fediverse_symbol_svg(44) ?>
                                    </a>
                                </li>

                                <li class="nav-item <?= $page === 'configuracion' ? 'active' : '' ?>">
                                    <a class="nav-link" href="?page=configuracion" title="Configuración" aria-label="Configuración">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 8a4 4 0 100 8 4 4 0 000-8z" stroke="currentColor" stroke-width="2"/>
                                            <path d="M3 12h3M18 12h3M12 3v3M12 18v3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </a>
                                </li>

                            </ul>

                            <form method="post" class="form-inline my-2 my-lg-0 ml-lg-3">
                                <div class="d-flex flex-column align-items-stretch">
                                    <button type="button" class="btn btn-outline-secondary btn-sm mb-2" id="adminThemeToggle" aria-pressed="false">Modo oscuro</button>
                                    <button type="submit" name="logout" class="btn btn-outline-danger my-2 my-sm-0">Cerrar</button>
                                    <a href="index.php" class="btn btn-outline-secondary btn-sm mt-2">Portada</a>
                                </div>
                            </form>

                        </div>

                    </nav>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger mb-3"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if (!empty($mailingFeedback)): ?>
                        <div class="alert alert-<?= htmlspecialchars($mailingFeedback['type'], ENT_QUOTES, 'UTF-8') ?> mb-3"><?= htmlspecialchars($mailingFeedback['message'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <div class="tab-content">

                        <?php if ($page === 'dashboard'): ?>

                            <?php include NAMMU_ROOT . '/core/admin-page-dashboard.php'; ?>

<?php elseif ($page === 'publish'): ?>

                            <?php include NAMMU_ROOT . '/core/admin-page-publish.php'; ?>

<?php elseif ($page === 'edit' || $page === 'edit-post' || $page === 'edit-note' || $page === 'edit-news'): ?>

    <?php include NAMMU_ROOT . '/core/admin-page-edit.php'; ?>

<?php elseif ($page === 'resources'): ?>

    <?php include NAMMU_ROOT . '/core/admin-page-resources.php'; ?>
    
<?php elseif ($page === 'template'): ?>

    <?php include NAMMU_ROOT . '/core/admin-page-template.php'; ?>

<?php elseif ($page === 'itinerarios'): ?>

    <?php include NAMMU_ROOT . '/core/admin-page-itinerarios.php'; ?>


<?php elseif ($page === 'itinerario'): ?>

    <?php include NAMMU_ROOT . '/core/admin-page-itinerario.php'; ?>

<?php elseif ($page === 'itinerario-tema'): ?>

    <?php include NAMMU_ROOT . '/core/admin-page-itinerario-tema.php'; ?>

<?php elseif ($page === 'lista-correo'): ?>

    <?php include NAMMU_ROOT . '/core/admin-page-lista-correo.php'; ?>

<?php elseif ($page === 'correo-postal'): ?>

    <?php include NAMMU_ROOT . '/core/admin-page-correo-postal.php'; ?>

<?php elseif ($page === 'anuncios'): ?>

    <?php include NAMMU_ROOT . '/core/admin-page-anuncios.php'; ?>

<?php elseif ($page === 'fediverso'): ?>

    <?php include NAMMU_ROOT . '/core/admin-page-fediverso.php'; ?>

<?php elseif ($page === 'configuracion'): ?>

    <?php include NAMMU_ROOT . '/core/admin-page-configuracion.php'; ?>

<?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>

        
        
        </div>

        <?php if ($adminLogoUrl !== ''): ?>
            <a class="admin-floating-logo" href="<?= htmlspecialchars($adminLogoLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" aria-label="Ir al blog">
                <img src="<?= htmlspecialchars($adminLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo del blog">
            </a>
        <?php endif; ?>
        <?php if (!empty($nisabaModalEnabled)): ?>
            <div class="modal fade" id="nisabaModal" tabindex="-1" role="dialog" aria-labelledby="nisabaModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="nisabaModalLabel">Notas recientes de Nisaba</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <?php if (!empty($nisabaNotes)): ?>
                                <p class="text-muted mb-3">Selecciona las notas de los últimos 14 días que quieres insertar.</p>
                                <?php foreach ($nisabaNotes as $index => $note): ?>
                                    <?php
                                    $noteId = 'nisaba-note-' . $index;
                                    $noteTitle = $note['title'] ?? '';
                                    $noteLink = $note['link'] ?? '';
                                    $noteContent = $note['insert_content'] ?? '';
                                    if (trim($noteContent) === '') {
                                        $noteContent = $note['content'] ?? '';
                                    }
                                    if (trim($noteContent) === '') {
                                        $noteContent = $note['display_content'] ?? '';
                                    }
                                    $noteDisplay = $note['display_content'] ?? '';
                                    $noteDateLabel = isset($note['timestamp']) ? date('d/m/y', (int) $note['timestamp']) : '';
                                    $noteDomain = '';
                                    if ($noteLink !== '') {
                                        $host = parse_url($noteLink, PHP_URL_HOST);
                                        if (is_string($host)) {
                                            $noteDomain = preg_replace('/^www\\./i', '', $host);
                                        }
                                    }
                                    ?>
                                    <div class="border rounded p-3 mb-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox"
                                                   class="custom-control-input nisaba-note-toggle"
                                                   id="<?= htmlspecialchars($noteId, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-nisaba-item="1"
                                                   data-note-title="<?= htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-link="<?= htmlspecialchars($noteLink, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-domain="<?= htmlspecialchars($noteDomain, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-content="<?= htmlspecialchars(base64_encode($noteContent), ENT_QUOTES, 'UTF-8') ?>">
                                            <label class="custom-control-label" for="<?= htmlspecialchars($noteId, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8') ?>
                                            </label>
                                        </div>
                                        <?php if ($noteDisplay !== ''): ?>
                                            <div class="mt-2 text-muted nisaba-note-preview"><?= $noteDisplay ?></div>
                                        <?php endif; ?>
                                        <?php if ($noteDateLabel !== ''): ?>
                                            <small class="text-muted d-block mt-2"><?= htmlspecialchars($noteDateLabel, ENT_QUOTES, 'UTF-8') ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">No hay notas recientes en <strong><?= htmlspecialchars($nisabaFeedUrl, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="nisabaInsert" <?= empty($nisabaNotes) ? 'disabled' : '' ?>>Insertar notas</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($telexModalEnabled)): ?>
            <div class="modal fade" id="telexModal" tabindex="-1" role="dialog" aria-labelledby="telexModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="telexModalLabel">Notas recientes de Telex</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <?php if (!empty($telexNotes)): ?>
                                <p class="text-muted mb-3">Selecciona las notas de los últimos 14 días que quieres insertar.</p>
                                <?php foreach ($telexNotes as $index => $note): ?>
                                    <?php
                                    $noteId = 'telex-note-' . $index;
                                    $noteTitle = $note['title'] ?? '';
                                    $noteLink = $note['link'] ?? '';
                                    $noteContent = $note['insert_content'] ?? ($note['content'] ?? '');
                                    $noteDisplay = $note['display_content'] ?? '';
                                    $noteDateLabel = isset($note['timestamp']) ? date('d/m/y', (int) $note['timestamp']) : '';
                                    $noteDomain = '';
                                    if ($noteLink !== '') {
                                        $host = parse_url($noteLink, PHP_URL_HOST);
                                        if (is_string($host)) {
                                            $noteDomain = preg_replace('/^www\\./i', '', $host);
                                        }
                                    }
                                    ?>
                                    <div class="border rounded p-3 mb-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox"
                                                   class="custom-control-input telex-note-toggle"
                                                   id="<?= htmlspecialchars($noteId, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-telex-item="1"
                                                   data-note-title="<?= htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-link="<?= htmlspecialchars($noteLink, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-domain="<?= htmlspecialchars($noteDomain, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-content="<?= htmlspecialchars(base64_encode($noteContent), ENT_QUOTES, 'UTF-8') ?>">
                                            <label class="custom-control-label" for="<?= htmlspecialchars($noteId, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8') ?>
                                            </label>
                                        </div>
                                        <?php if ($noteDisplay !== ''): ?>
                                            <div class="mt-2 text-muted telex-note-preview"><?= $noteDisplay ?></div>
                                        <?php endif; ?>
                                        <?php if ($noteDateLabel !== ''): ?>
                                            <small class="text-muted d-block mt-2"><?= htmlspecialchars($noteDateLabel, ENT_QUOTES, 'UTF-8') ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">No hay notas recientes en las feeds configuradas.</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="telexInsert" <?= empty($telexNotes) ? 'disabled' : '' ?>>Insertar notas</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!empty($ideasModalEnabled)): ?>
            <div class="modal fade" id="ideasModal" tabindex="-1" role="dialog" aria-labelledby="ideasModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="ideasModalLabel">Ideas para nuevas publicaciones</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <?php if (!empty($ideasSuggestions)): ?>
                                <ul class="mb-0">
                                    <?php foreach ($ideasSuggestions as $idea): ?>
                                        <li><?= htmlspecialchars($idea, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">Todavía no hay suficientes datos para generar sugerencias.</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        

        <div class="modal fade" id="imageEditorModal" tabindex="-1" role="dialog" aria-labelledby="imageEditorModalLabel" aria-hidden="true">

            <div class="modal-dialog modal-xl" role="document">

                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title" id="imageEditorModalLabel">Editar Imagen</h5>

                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">

                            <span aria-hidden="true">&times;</span>

                        </button>

                    </div>

                    <div class="modal-body">

                        <div class="row">

                            <div class="col-md-9">

                                <canvas id="imageCanvas" style="max-width: 100%;"></canvas>

                            </div>

                            <div class="col-md-3">

                                <h5>Controles</h5>

                                <button id="cropBtn" class="btn btn-secondary btn-block mb-2">Recortar a la selección</button>

                                <button id="pixelateBtn" class="btn btn-secondary btn-block mb-2">Pixelar selección</button>

                                <hr>

                                <div class="form-group">

                                    <label for="brightness">Brillo</label>

                                    <input type="range" class="form-control-range" id="brightness" min="0" max="200" value="100">

                                </div>

                                <div class="form-group">

                                    <label for="contrast">Contraste</label>

                                    <input type="range" class="form-control-range" id="contrast" min="0" max="200" value="100">

                                </div>

                                <div class="form-group">

                                    <label for="saturation">Intensidad</label>

                                    <input type="range" class="form-control-range" id="saturation" min="0" max="200" value="100">

                                </div>

                                <button id="resetFiltersBtn" class="btn btn-info btn-block">Reiniciar filtros</button>

                                <hr>

                                <div class="form-group">

                                    <label for="image_tags">Etiquetas</label>

                                    <input type="text" class="form-control" id="image_tags" placeholder="Ej. portada, equipo">

                                    <small class="form-text text-muted">Escribe etiquetas separadas por comas.</small>

                                </div>

                                <button type="button" class="btn btn-outline-primary btn-block mb-2" id="save-tags-only">Guardar etiquetas</button>

                                <input type="hidden" id="image-tags-target" value="">

                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <div class="form-inline">

                            <label for="new-image-name" class="mr-2">Guardar como:</label>

                            <input type="text" id="new-image-name" class="form-control mr-2" placeholder="nuevo-nombre.png">

                            <button type="button" id="save-image-btn" class="btn btn-primary">Guardar</button>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        

        <div class="modal fade" id="imageModal" tabindex="-1" role="dialog" aria-labelledby="imageModalLabel" aria-hidden="true">

            <div class="modal-dialog modal-lg" role="document">

                <div class="modal-content">

                    <div class="modal-header">

                        <h5 class="modal-title" id="imageModalLabel">Seleccionar recurso</h5>

                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">

                            <span aria-hidden="true">&times;</span>

                        </button>

                    </div>

                    <div class="modal-body">

                        <form action="admin.php" method="post" enctype="multipart/form-data" class="mb-3">
                            <input type="hidden" name="upload_asset" value="1">
                            <input type="hidden" name="redirect_page" value="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="redirect_p" value="<?= isset($_GET['p']) ? (int) $_GET['p'] : 1 ?>">
                            <input type="hidden" name="redirect_search" value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="redirect_file" value="<?= ($page === 'edit-post' && isset($safeEditFilename)) ? htmlspecialchars($safeEditFilename, ENT_QUOTES, 'UTF-8') : '' ?>">
                            <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="redirect_anchor" id="imageUploadRedirectAnchor" value="">
                            <input type="hidden" name="autosave_payload" id="imageUploadAutosavePayload" value="">
                            <input type="hidden" name="target_type" id="imageUploadTargetType" value="">
                            <input type="hidden" name="target_input" id="imageUploadTargetInput" value="">
                            <input type="hidden" name="target_editor" id="imageUploadTargetEditor" value="">
                            <input type="hidden" name="target_prefix" id="imageUploadTargetPrefix" value="">
                            <input type="hidden" name="target_selection_start" id="imageUploadSelectionStart" value="">
                            <input type="hidden" name="target_selection_end" id="imageUploadSelectionEnd" value="">
                            <input type="hidden" name="target_selection_scroll" id="imageUploadSelectionScroll" value="">
                            <div class="form-group mb-2">
                                <label class="d-block">Subir nuevo archivo</label>
                                <input type="file" name="asset_files[]" class="form-control-file" multiple>
                                <small class="form-text text-muted">Formatos permitidos: imágenes, audio, vídeo, documentos y Markdown.</small>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Subir</button>
                        </form>

                        <div class="form-group">
                            <label for="modal-image-search">Buscar recursos</label>
                            <input type="search" class="form-control" id="modal-image-search" placeholder="Filtra por nombre o etiqueta">
                            <small class="form-text text-muted">La galería mostrará solo los elementos que coincidan con tu búsqueda.</small>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted" id="image-modal-count"></small>
                            <small class="text-muted d-none" id="image-modal-selected-help">Recurso seleccionado. Elige abajo cómo insertarlo.</small>
                        </div>
                        <div class="alert alert-light border d-none" id="image-modal-empty" role="status">No hay recursos que coincidan con la búsqueda.</div>

                        <div class="row image-gallery">

                            <?php

                            $media_data = get_media_items(1, 0); // Load all media for modal filtering and pagination
                            $modal_media_tags = load_media_tags();

                            foreach ($media_data['items'] as $media):

                                $media_name = $media['name'];
                                $media_relative = $media['relative'];
                                $media_type = $media['type'];
                                $media_extension = $media['extension'] ?? '';
                                $media_mime = $media['mime'];
                                $media_src = 'assets/' . $media_relative;
                                $media_tags_list = $modal_media_tags[$media_relative] ?? [];
                                $media_tags_text = implode(', ', $media_tags_list);
                                $media_search = trim($media_name . ' ' . $media_relative . ' ' . $media_tags_text);

                            ?>

                                <div class="col-md-3 mb-3 gallery-item" data-media-search="<?= htmlspecialchars($media_search, ENT_QUOTES, 'UTF-8') ?>">

                                    <?php if ($media_type === 'image'): ?>
                                        <img src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover; cursor: pointer;" data-media-name="<?= htmlspecialchars($media_name, ENT_QUOTES, 'UTF-8') ?>" data-media-type="image" data-media-src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" data-media-mime="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>" data-media-tags="<?= htmlspecialchars($media_tags_text, ENT_QUOTES, 'UTF-8') ?>" data-media-gallery-target="1">
                                    <?php elseif ($media_type === 'video'): ?>
                                        <div class="video-thumb-wrapper" data-media-name="<?= htmlspecialchars($media_name, ENT_QUOTES, 'UTF-8') ?>" data-media-type="video" data-media-src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" data-media-mime="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>" data-media-tags="<?= htmlspecialchars($media_tags_text, ENT_QUOTES, 'UTF-8') ?>" style="cursor: pointer; position: relative;">
                                            <video class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover; pointer-events: none;" muted preload="metadata">
                                                <source src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" type="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>">
                                            </video>
                                            <span class="badge badge-dark video-badge" style="position: absolute; bottom: 8px; right: 12px;">Video</span>
                                        </div>
                                    <?php elseif ($media_type === 'audio'): ?>
                                        <div class="doc-thumb-wrapper" data-media-name="<?= htmlspecialchars($media_name, ENT_QUOTES, 'UTF-8') ?>" data-media-type="audio" data-media-src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" data-media-mime="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>" data-media-tags="<?= htmlspecialchars($media_tags_text, ENT_QUOTES, 'UTF-8') ?>" style="cursor: pointer; border: 1px dashed rgba(0,0,0,0.2); border-radius: var(--nammu-radius-md, 12px); padding: 2.5rem 1rem; text-align: center;">
                                            <i class="fas fa-music" style="font-size: 3rem; color: #1e88e5;"></i>
                                            <div class="small mt-2 text-muted">Audio</div>
                                        </div>
                                    <?php else: ?>
                                        <?php $isPdf = strtolower($media_extension) === 'pdf'; ?>
                                        <div class="doc-thumb-wrapper" data-media-name="<?= htmlspecialchars($media_name, ENT_QUOTES, 'UTF-8') ?>" data-media-type="<?= $isPdf ? 'pdf' : 'document' ?>" data-media-src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" data-media-mime="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>" data-media-tags="<?= htmlspecialchars($media_tags_text, ENT_QUOTES, 'UTF-8') ?>" style="cursor: pointer; border: 1px dashed rgba(0,0,0,0.2); border-radius: var(--nammu-radius-md, 12px); padding: 2.5rem 1rem; text-align: center;">
                                            <i class="fas fa-file-alt" style="font-size: 3rem; color: #5f6368;"></i>
                                            <div class="small mt-2 text-muted"><?= $isPdf ? 'PDF' : 'Documento' ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($media_tags_text !== ''): ?>
                                        <div class="mt-1">
                                            <?php foreach ($media_tags_list as $tag): ?>
                                                <a href="#" class="badge badge-primary badge-pill mr-1 mb-1" data-tag-filter="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>" data-tag-scope="modal" style="font-size: 0.7rem;">&num;<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <small class="d-block text-muted text-truncate mt-1">Sin etiquetas</small>
                                    <?php endif; ?>
                                    <div class="mt-2">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-info edit-tags-btn"
                                                data-tag-list="<?= htmlspecialchars($media_tags_text, ENT_QUOTES, 'UTF-8') ?>"
                                                data-tag-target="<?= htmlspecialchars($media_relative, ENT_QUOTES, 'UTF-8') ?>">
                                            Etiquetas
                                        </button>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                    <div class="modal-footer">
                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between w-100">
                            <div id="image-insert-actions" class="d-none mb-3 mb-md-0">
                                <div class="d-flex flex-column align-items-start">
                                    <span class="mb-1">Insertar como:</span>
                                    <div class="btn-group mb-2" role="group" data-insert-group="image">
                                        <button type="button" class="btn btn-sm btn-primary" data-insert-mode="full">Imagen completa</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-insert-mode="vignette">Viñeta</button>
                                    </div>
                                    <div class="btn-group mb-2 d-none" role="group" data-insert-group="pdf">
                                        <button type="button" class="btn btn-sm btn-primary" data-insert-mode="embed">PDF incrustado</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-insert-mode="link">Enlace</button>
                                    </div>
                                    <div class="btn-group mb-2 d-none" role="group" data-insert-group="video">
                                        <button type="button" class="btn btn-sm btn-primary" data-insert-mode="embed">Vídeo incrustado</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-insert-mode="link">Enlace</button>
                                    </div>
                                </div>
                            </div>
                            <div id="image-gallery-actions" class="d-none mb-3 mb-md-0">
                                <div class="d-flex flex-column align-items-start">
                                    <span class="mb-1">Galería seleccionada: <strong id="image-gallery-count">0</strong></span>
                                    <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Orden de galería">
                                        <input type="radio" class="btn-check d-none" name="image_gallery_order" id="image-gallery-order-manual" value="manual" checked>
                                        <label class="btn btn-outline-secondary active" for="image-gallery-order-manual" data-gallery-order="manual">Orden manual</label>
                                        <input type="radio" class="btn-check d-none" name="image_gallery_order" id="image-gallery-order-asc" value="asc">
                                        <label class="btn btn-outline-secondary" for="image-gallery-order-asc" data-gallery-order="asc">A-Z</label>
                                        <input type="radio" class="btn-check d-none" name="image_gallery_order" id="image-gallery-order-desc" value="desc">
                                        <label class="btn btn-outline-secondary" for="image-gallery-order-desc" data-gallery-order="desc">Z-A</label>
                                    </div>
                                    <div class="btn-group" role="group" aria-label="Acciones de galería">
                                        <button type="button" class="btn btn-sm btn-primary" id="image-gallery-insert">Insertar galería</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="image-gallery-clear">Vaciar</button>
                                    </div>
                                </div>
                            </div>

                            <nav aria-label="Page navigation" class="ml-md-auto">
                                <ul class="pagination pagination-break" id="image-pagination"></ul>
                            </nav>
                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="modal fade" id="tagsModal" tabindex="-1" role="dialog" aria-labelledby="tagsModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tagsModalLabel">Editar etiquetas</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="tagsModalForm" method="post">
                            <input type="hidden" name="update_image_tags" value="1">
                            <input type="hidden" name="original_image" id="tagsModalTarget" value="">
                            <input type="hidden" name="redirect_p" id="tagsModalRedirect" value="<?= isset($current_page) ? (int) $current_page : 1 ?>">
                            <input type="hidden" name="redirect_search" id="tagsModalRedirectSearch" value="<?= htmlspecialchars($resourceSearchTerm ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="redirect_page" id="tagsModalRedirectPage" value="">
                            <input type="hidden" name="redirect_url" id="tagsModalRedirectUrl" value="">
                            <input type="hidden" name="redirect_file" id="tagsModalRedirectFile" value="">
                            <input type="hidden" name="redirect_anchor" id="tagsModalRedirectAnchor" value="">
                            <input type="hidden" name="return_to_modal" id="tagsModalReturnToModal" value="">
                            <input type="hidden" name="target_type" id="tagsModalTargetType" value="">
                            <input type="hidden" name="target_input" id="tagsModalTargetInput" value="">
                            <input type="hidden" name="target_editor" id="tagsModalTargetEditor" value="">
                            <input type="hidden" name="target_prefix" id="tagsModalTargetPrefix" value="">
                            <input type="hidden" name="target_selection_start" id="tagsModalSelectionStart" value="">
                            <input type="hidden" name="target_selection_end" id="tagsModalSelectionEnd" value="">
                            <input type="hidden" name="target_selection_scroll" id="tagsModalSelectionScroll" value="">
                            <div class="form-group">
                                <label for="tagsModalInput">Etiquetas</label>
                                <input type="text" class="form-control" name="image_tags" id="tagsModalInput" placeholder="Ej. portada, dossier, pdf">
                                <small class="form-text text-muted">Separa las etiquetas con comas.</small>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="tagsModalSave">Guardar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="calloutModal" tabindex="-1" role="dialog" aria-labelledby="calloutModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="calloutModalLabel">Caja destacada</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="calloutTitle">Título</label>
                            <input type="text" id="calloutTitle" class="form-control" value="Aviso">
                        </div>
                        <div class="form-group">
                            <label for="calloutBody">Contenido del aviso (texto o enlaces)</label>
                            <textarea id="calloutBody" class="form-control" rows="4" placeholder="Añade aquí bibliografía, enlaces o notas. Usa Enter para nueva línea."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="calloutInsert">Insertar</button>
                    </div>
                </div>
            </div>
        </div>

        

        <div class="modal fade" id="deletePostModal" tabindex="-1" role="dialog" aria-labelledby="deletePostModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form method="post">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deletePostModalLabel">Borrar contenido</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>Vas a borrar <strong data-delete-post-title></strong>.</p>
                            <p class="text-muted small mb-3">Archivo: <span data-delete-post-file></span></p>
                            <p class="mb-0">Esta acción no se puede deshacer.</p>
                            <input type="hidden" name="delete_filename" id="delete-post-filename">
                            <input type="hidden" name="delete_template" id="delete-post-template" value="single">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                            <button type="submit" name="delete_post" class="btn btn-danger">Borrar definitivamente</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

<?php if ($page === 'template'): ?>
        <style>
        body { margin-top: 8px; margin-bottom: 8px; }
        </style>

        <script>
<?php admin_inline_asset('template-settings.js'); ?>
        </script>
<?php endif; ?>

        <script>
<?php admin_inline_asset('markdown-toolbar.js'); ?>
        </script>

        <div class="topic-quiz-modal-backdrop d-none" data-topic-quiz-backdrop></div>
        <div class="topic-quiz-modal d-none" data-topic-quiz-modal aria-hidden="true">
            <div class="topic-quiz-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="topicQuizModalTitle">
                <div class="topic-quiz-modal__header">
                    <h4 id="topicQuizModalTitle" class="mb-0">Autoevaluación del tema</h4>
                    <button type="button" class="close" aria-label="Cerrar" data-topic-quiz-close>&times;</button>
                </div>
                <div class="topic-quiz-modal__body">
                    <div class="form-group">
                        <label for="topic_quiz_minimum">Preguntas mínimas correctas para aprobar</label>
                        <input type="number" min="1" value="1" class="form-control" id="topic_quiz_minimum" data-topic-quiz-min>
                        <small class="form-text text-muted">Debe ser un número entre 1 y el total de preguntas configuradas.</small>
                    </div>
                    <div class="topic-quiz-modal__questions" data-topic-quiz-questions></div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-topic-quiz-add-question>Añadir pregunta</button>
                </div>
                <div class="topic-quiz-modal__footer">
                    <button type="button" class="btn btn-link text-danger mr-auto" data-topic-quiz-clear>Eliminar autoevaluación</button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" data-topic-quiz-close>Cancelar</button>
                        <button type="button" class="btn btn-primary" data-topic-quiz-save>Guardar autoevaluación</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="itineraryStatsModal" tabindex="-1" role="dialog" aria-labelledby="itineraryStatsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="itineraryStatsModalLabel">Estadísticas del itinerario</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p class="font-weight-bold" data-stats-title></p>
                        <p data-stats-started class="mb-3 text-muted"></p>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Tema</th>
                                        <th>Usuarios</th>
                                        <th>% sobre quienes iniciaron</th>
                                    </tr>
                                </thead>
                                <tbody data-stats-table-body>
                                    <tr>
                                        <td colspan="3" class="text-muted">Cargando...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center">
                        <small class="text-muted mb-2 mb-sm-0" data-stats-note></small>
                        <form method="post" class="mb-0" data-reset-stats-form>
                            <input type="hidden" name="reset_stats_slug" value="" data-reset-stats-slug>
                            <button type="submit" name="reset_itinerary_stats" class="btn btn-sm btn-outline-danger" data-reset-stats-button>
                                Poner estadísticas a cero
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>

        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>

        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

        <script>
        window.nammuAssetApply = <?= json_encode($assetApply, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
<?php admin_inline_asset('media-modal.js'); ?>
        </script>

        <script>
<?php admin_inline_asset('media-gallery.js'); ?>
        </script>

        <script>
<?php admin_inline_asset('image-editor.js'); ?>
        </script>

        <script>
<?php admin_inline_asset('itineraries.js'); ?>
        </script>

        <script>
<?php admin_inline_asset('editor-forms.js'); ?>
        </script>



        <script>
<?php admin_inline_asset('autosave.js'); ?>
        </script>
        <script>
<?php admin_inline_asset('admin-theme.js'); ?>
        </script>
        <script>
<?php admin_inline_asset('delete-post-modal.js'); ?>
        </script>

        </body>

        </html>
