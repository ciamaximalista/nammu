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

if (isset($_GET['bing_oauth'])) {
    $action = (string) $_GET['bing_oauth'];
    if ($action === 'start') {
        $config = load_config_file();
        $bing = $config['bing_webmaster'] ?? [];
        $clientId = trim((string) ($bing['client_id'] ?? ''));
        $clientSecret = trim((string) ($bing['client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            $_SESSION['bing_webmaster_feedback'] = [
                'type' => 'danger',
                'message' => 'Faltan el Client ID o el Client Secret de Bing Webmaster Tools.',
            ];
            header('Location: admin.php?page=configuracion');
            exit;
        }
        $state = bin2hex(random_bytes(16));
        $_SESSION['bing_oauth_state'] = $state;
        $redirectUri = admin_bing_oauth_redirect_uri();
        $authUrl = 'https://www.bing.com/webmasters/oauth/authorize?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'webmaster.manage offline_access',
            'state' => $state,
            'prompt' => 'consent',
        ]);
        header('Location: ' . $authUrl);
        exit;
    }
    if ($action === 'callback') {
        $state = $_GET['state'] ?? '';
        $code = $_GET['code'] ?? '';
        $error = $_GET['error'] ?? '';
        $errorDesc = $_GET['error_description'] ?? '';
        $expectedState = $_SESSION['bing_oauth_state'] ?? '';
        unset($_SESSION['bing_oauth_state']);
        if ($error !== '') {
            $_SESSION['bing_webmaster_feedback'] = [
                'type' => 'danger',
                'message' => 'Bing OAuth rechazado: ' . $error . ' ' . $errorDesc,
            ];
            header('Location: admin.php?page=configuracion');
            exit;
        }
        if ($code === '') {
            $_SESSION['bing_webmaster_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo validar la autenticación con Bing.',
            ];
            header('Location: admin.php?page=configuracion');
            exit;
        }
        if ($expectedState !== '' && !hash_equals($expectedState, (string) $state)) {
            // Continuamos para no bloquear el OAuth si la sesión se pierde en el retorno.
            $expectedState = '';
        }
        try {
            $config = load_config_file();
            $bing = $config['bing_webmaster'] ?? [];
            $clientId = trim((string) ($bing['client_id'] ?? ''));
            $clientSecret = trim((string) ($bing['client_secret'] ?? ''));
            if ($clientId === '' || $clientSecret === '') {
                throw new RuntimeException('Faltan credenciales OAuth de Bing.');
            }
            $token = admin_bing_fetch_token([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => admin_bing_oauth_redirect_uri(),
            ]);
            $accessToken = (string) ($token['access_token'] ?? '');
            $refreshToken = (string) ($token['refresh_token'] ?? '');
            if ($accessToken === '') {
                throw new RuntimeException('Bing no devolvió un access token.');
            }
            $bing['access_token'] = $accessToken;
            if ($refreshToken !== '') {
                $bing['refresh_token'] = $refreshToken;
            }
            $expiresIn = (int) ($token['expires_in'] ?? 0);
            if ($expiresIn > 0) {
                $bing['access_expires_at'] = time() + $expiresIn;
            }
            $config['bing_webmaster'] = $bing;
            save_config_file($config);
            $_SESSION['bing_webmaster_feedback'] = [
                'type' => 'success',
                'message' => 'Conexión OAuth correcta con Bing Webmaster Tools.',
            ];
        } catch (Throwable $e) {
            $_SESSION['bing_webmaster_feedback'] = [
                'type' => 'danger',
                'message' => 'Error al conectar con Bing Webmaster Tools: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=anuncios#facebook');
        exit;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfRequired = !isset($_POST['register']) && !isset($_POST['login']);
    if ($csrfRequired && !admin_csrf_is_valid($_POST['_nammu_csrf'] ?? '')) {
        $error = 'La sesión del formulario ha caducado. Vuelve a intentarlo.';
    } elseif (isset($_POST['register'])) {
        if (!$user_exists) {
            try {
                register_user($_POST['username'], $_POST['password']);
                header('Location: admin.php');
                exit;
            } catch (Throwable $e) {
                $error = 'No se pudo crear el usuario inicial. Comprueba los permisos de la carpeta config/ y vuelve a intentarlo. Detalle: ' . $e->getMessage();
            }
        } else {
            $error = 'Ya existe un usuario registrado.';
        }
    } elseif (isset($_POST['login'])) {
        if (verify_user($_POST['username'], $_POST['password'])) {
            $_SESSION['loggedin'] = true;
            header('Location: admin.php?page=dashboard');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } elseif (isset($_POST['logout'])) {
        session_destroy();
        header('Location: admin.php');
        exit;
    } elseif (isset($_POST['send_newsletter'])) {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $dateInput = $_POST['date'] ?? '';
        $timestamp = $dateInput !== '' ? strtotime($dateInput) : time();
        if ($timestamp === false) {
            $timestamp = time();
        }
        $date = date('Y-m-d', $timestamp);
        $image = trim($_POST['image'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $lang = trim($_POST['lang'] ?? '');
        $filenameInput = trim($_POST['filename'] ?? '');
        $slugPattern = '/^[a-z0-9-]+$/i';
        if ($filenameInput !== '' && !preg_match($slugPattern, $filenameInput)) {
            $error = 'El slug solo puede contener letras, números y guiones medios.';
        }
        if ($filenameInput === '' && $title !== '') {
            $filenameInput = nammu_slugify($title);
        }
        if ($error === null) {
            $filename = nammu_unique_filename($filenameInput !== '' ? $filenameInput : 'newsletter');
        } else {
            $filename = '';
        }
        $content = $_POST['content'] ?? '';
        $settings = get_settings();
        if (!admin_is_mailing_ready($settings)) {
            $error = 'Configura el correo de la lista antes de enviar la newsletter.';
        }
        $mailing = $settings['mailing'] ?? [];
        if ($error === null && ($mailing['auto_newsletter'] ?? 'off') !== 'on') {
            $error = 'Activa la newsletter en Lista > Preferencias de envio.';
        }
        $subscribers = admin_mailing_recipients_for_type('newsletter', $settings);
        if ($error === null && empty($subscribers)) {
            $error = 'No hay suscriptores en la lista de correo.';
        }
        $newsletterSendResult = null;
        if ($error === null && $filename !== '') {
            $markdown = new MarkdownConverter();
            $contentHtml = $markdown->toHtml($content);
            $contentText = trim(strip_tags($contentHtml));
            $newsletterSendResult = admin_schedule_mailing_broadcast('newsletter', $subscribers, [
                'filename' => $filename . '.md',
                'title' => $title,
                'image' => $image,
                'content_html' => $contentHtml,
                'content_text' => $contentText,
            ]);
            if (($newsletterSendResult['queued_recipients'] ?? 0) === 0) {
                $error = 'No se pudo encolar la newsletter. Revisa la configuración de correo.';
            }
        }
        if ($error === null && $filename !== '') {
            $all_posts = get_all_posts_metadata();
            $max_ordo = 0;
            foreach ($all_posts as $post) {
                if (isset($post['metadata']['Ordo']) && (int)$post['metadata']['Ordo'] > $max_ordo) {
                    $max_ordo = (int)$post['metadata']['Ordo'];
                }
            }
            $ordo = $max_ordo + 1;
            $targetFilename = nammu_normalize_filename($filename . '.md');
            if ($targetFilename === '') {
                $error = 'El nombre de archivo no es válido.';
            } else {
                $filepath = CONTENT_DIR . '/' . $targetFilename;
                $file_content = "---
";
                $file_content .= "Title: " . $title . "
";
                $file_content .= "Template: newsletter
";
                $file_content .= "Category: " . $category . "
";
                $file_content .= "Date: " . $date . "
";
                $file_content .= "Image: " . $image . "
";
                $file_content .= "Description: " . $description . "
";
                if ($lang !== '') {
                    $file_content .= "Lang: " . $lang . "
";
                }
                $file_content .= "Status: newsletter
";
                $file_content .= "Ordo: " . $ordo . "
";
                $file_content .= "---

";
                $file_content .= $content;
                if (file_put_contents($filepath, $file_content, LOCK_EX) === false) {
                    $error = 'No se pudo guardar la newsletter. Revisa los permisos de la carpeta content/.';
                } else {
                    admin_chmod_content_file($filepath);
                    if (is_array($newsletterSendResult)) {
                        $queuedCount = (int) ($newsletterSendResult['queued_recipients'] ?? 0);
                        $batchCount = (int) ($newsletterSendResult['queued_batches'] ?? 0);
                        $_SESSION['mailing_feedback'] = [
                            'type' => 'success',
                            'message' => 'Newsletter encolada en ' . $batchCount . ' tanda' . ($batchCount === 1 ? '' : 's') . ' para ' . $queuedCount . ' destinatario' . ($queuedCount === 1 ? '' : 's') . '.',
                        ];
                    } else {
                        $_SESSION['mailing_feedback'] = [
                            'type' => 'success',
                            'message' => 'Newsletter encolada correctamente.',
                        ];
                    }
                    header('Location: admin.php?page=edit&template=newsletter&created=' . urlencode($targetFilename));
                    exit;
                }
            }
        }
    } elseif (isset($_POST['publish']) || isset($_POST['save_draft']) || isset($_POST['publish_and_view'])) {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $dateInput = $_POST['date'] ?? '';
        $timestamp = $dateInput !== '' ? strtotime($dateInput) : time();
        if ($timestamp === false) {
            $timestamp = time();
        }
        $date = date('Y-m-d', $timestamp);
        $publishAtDate = trim($_POST['publish_at_date'] ?? '');
        $publishAtTime = trim($_POST['publish_at_time'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $lang = trim($_POST['lang'] ?? '');
        $audio = trim($_POST['audio'] ?? '');
        $video = trim($_POST['video'] ?? '');
        $audioLengthInput = trim($_POST['audio_length'] ?? '');
        $audioDuration = trim($_POST['audio_duration'] ?? '');
        $pageVisibilityInput = strtolower(trim((string) ($_POST['page_visibility'] ?? 'public')));
        $relatedSlugsFieldPresent = array_key_exists('related_slugs', $_POST);
        $relatedSlugsInput = trim((string) ($_POST['related_slugs'] ?? ''));
        $type = $_POST['type'] ?? 'Entrada';
        if ($type === 'Página') {
            $type = 'Página';
        } elseif ($type === 'Podcast') {
            $type = 'Podcast';
        } elseif ($type === 'Newsletter') {
            $type = 'Newsletter';
        } elseif ($type === 'Nota') {
            $type = 'Nota';
        } else {
            $type = 'Entrada';
        }
        if ($type === 'Nota') {
            require_once NAMMU_ROOT . '/core/admin-redes.php';
            $messageText = trim((string) ($_POST['content'] ?? ''));
            $messageImages = trim((string) ($_POST['social_broadcast_image'] ?? ''));
            $socialBroadcastState = admin_handle_social_broadcast_submission(get_settings(), $messageText, $messageImages);
            $_SESSION['social_broadcast_feedback'] = $socialBroadcastState['feedback'] ?? null;
            $_SESSION['social_broadcast_state'] = [
                'message_text' => (($socialBroadcastState['feedback']['type'] ?? '') === 'success') ? '' : (string) ($socialBroadcastState['message_text'] ?? $messageText),
                'image' => (($socialBroadcastState['feedback']['type'] ?? '') === 'success') ? '' : (string) ($socialBroadcastState['image'] ?? $messageImages),
                'actuality' => !empty($socialBroadcastState['actuality']),
                'networks' => is_array($socialBroadcastState['networks'] ?? null) ? $socialBroadcastState['networks'] : [],
            ];
            header('Location: admin.php?page=publish');
            exit;
        }
        $pageVisibility = ($type === 'Página' && $pageVisibilityInput === 'private') ? 'private' : 'public';
        $filenameInput = trim($_POST['filename'] ?? '');
        $viewAfterSave = isset($_POST['publish_and_view']);
        $isDraft = isset($_POST['save_draft']);
        if ($viewAfterSave) {
            // "Ver en la web" siempre previsualiza: guardar como borrador.
            $isDraft = true;
        }
        $statusValue = $isDraft ? 'draft' : 'published';
        $publishAtValue = '';
        $slugPattern = '/^[a-z0-9-]+$/i';

        if ($filenameInput !== '' && !preg_match($slugPattern, $filenameInput)) {
            $error = 'El slug solo puede contener letras, números y guiones medios.';
        }

        if ($filenameInput === '' && $title !== '') {
            $filenameInput = nammu_slugify($title);
        }

        $relatedSlugs = [];
        if ($type === 'Entrada' || $type === 'Podcast') {
            $existingRelatedRaw = '';
            if (is_array($existing_post_data) && isset($existing_post_data['metadata']) && is_array($existing_post_data['metadata'])) {
                $existingRelatedRaw = trim((string) ($existing_post_data['metadata']['Related'] ?? $existing_post_data['metadata']['related'] ?? ''));
            }
            if ($relatedSlugsInput === '' && $existingRelatedRaw !== '' && $relatedSlugsFieldPresent) {
                // Evita perder relacionados al actualizar campos no relacionados (por ejemplo, imagen).
                $relatedSlugs = admin_parse_related_slugs_input($existingRelatedRaw);
            } else {
                $relatedSlugs = admin_parse_related_slugs_input($relatedSlugsInput);
            }
            if ($relatedSlugsInput !== '' && count($relatedSlugs) < 2) {
                $error = 'Entradas o itinerarios relacionados: indica al menos 2 slugs válidos.';
            } elseif (count($relatedSlugs) > 8) {
                $error = 'Entradas o itinerarios relacionados: el máximo son 8 slugs.';
            }
        }

        if ($error === null) {
            $filename = nammu_unique_filename($filenameInput);
        } else {
            $filename = '';
        }

        $all_posts = get_all_posts_metadata();
        $max_ordo = 0;
        foreach ($all_posts as $post) {
            if (isset($post['metadata']['Ordo']) && (int)$post['metadata']['Ordo'] > $max_ordo) {
                $max_ordo = (int)$post['metadata']['Ordo'];
            }
        }
        $ordo = $max_ordo + 1;

        $content = $_POST['content'] ?? '';
        $audioLength = '';
        $audioUrl = '';
        if ($type === 'Podcast') {
            if ($audio === '') {
                $error = 'El archivo mp3 del podcast es obligatorio.';
            }
            if ($audio !== '' && !preg_match('/\.mp3$/i', $audio)) {
                $error = 'El audio del podcast debe ser un archivo mp3.';
            }
            if ($video !== '' && !preg_match('/\.mp4$/i', $video)) {
                $error = 'El vídeo del podcast debe ser un archivo mp4.';
            }
            if ($audioDuration === '') {
                $error = 'Indica la duración del episodio en formato hh:mm:ss.';
            }
            $category = '';
            if ($audio !== '') {
                $audioPath = ltrim($audio, '/');
                $audioPath = str_starts_with($audioPath, 'assets/') ? substr($audioPath, strlen('assets/')) : $audioPath;
                $candidate = ASSETS_DIR . '/' . $audioPath;
                if (is_file($candidate)) {
                    $audioLength = (string) filesize($candidate);
                }
            }
            if ($audioLength === '' && $audioLengthInput !== '' && ctype_digit($audioLengthInput)) {
                $audioLength = $audioLengthInput;
            }
        } elseif ($type === 'Newsletter') {
            $category = '';
        }

        if ($filename !== '') {
            $targetFilename = nammu_normalize_filename($filename . '.md');
            if ($targetFilename === '') {
                $error = 'El nombre de archivo no es válido.';
            } else {
                $filepath = CONTENT_DIR . '/' . $targetFilename;

                $file_content = "---
";
                $file_content .= "Title: " . $title . "
";
                $templateValue = $type === 'Página' ? 'page' : ($type === 'Podcast' ? 'podcast' : ($type === 'Newsletter' ? 'newsletter' : 'post'));
                $file_content .= "Template: " . $templateValue . "
";
                $file_content .= "Category: " . $category . "
";
                $file_content .= "Date: " . $date . "
";
                $file_content .= "Image: " . $image . "
";
                $file_content .= "Description: " . $description . "
";
                if ($type === 'Podcast') {
                    $file_content .= "Audio: " . $audio . "
";
                    if ($video !== '') {
                        $file_content .= "Video: " . $video . "
";
                    }
                    if ($audioLength !== '') {
                        $file_content .= "AudioLength: " . $audioLength . "
";
                    }
                    if ($audioDuration !== '') {
                        $file_content .= "AudioDuration: " . $audioDuration . "
";
                    }
                }
                if ($lang !== '') {
                    $file_content .= "Lang: " . $lang . "
";
                }
                if ($templateValue === 'page') {
                    $file_content .= "Visibility: " . $pageVisibility . "
";
                }
                if (!empty($relatedSlugs) && in_array($templateValue, ['post', 'podcast'], true)) {
                    $file_content .= "Related: " . implode(', ', $relatedSlugs) . "
";
                }
                $file_content .= "Status: " . $statusValue . "
";
                if ($isDraft && $publishAtDate !== '') {
                    $publishAtTime = $publishAtTime !== '' ? $publishAtTime : '00:00';
                    $publishTimestamp = strtotime($publishAtDate . ' ' . $publishAtTime);
                    if ($publishTimestamp !== false) {
                        $publishAtValue = date('Y-m-d H:i', $publishTimestamp);
                    }
                }
                if ($publishAtValue !== '') {
                    $file_content .= "PublishAt: " . $publishAtValue . "
";
                }
                $file_content .= "Ordo: " . $ordo . "
";
                $file_content .= "---

";
                $file_content .= $content;

                if (file_put_contents($filepath, $file_content, LOCK_EX) === false) {
                    $error = 'No se pudo guardar el contenido. Revisa los permisos de la carpeta content/.';
                } else {
                    admin_chmod_content_file($filepath);
                    if (!$isDraft && $type === 'Entrada') {
                        $imageUrl = admin_public_asset_url($image);
                        admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description, $image, '', $imageUrl);
                        $settings = get_settings();
                        $mailing = $settings['mailing'] ?? [];
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $link = admin_public_post_url($slug);
                        if (($mailing['auto_posts'] ?? 'off') === 'on' && admin_is_mailing_ready($settings)) {
                            $subscribers = admin_mailing_recipients_for_type('posts', $settings);
                            if (!empty($subscribers)) {
                                $payload = admin_prepare_mailing_payload('single', $settings, $title, $description, $link, $image);
                                admin_schedule_mailing_broadcast('post', $subscribers, [
                                    'filename' => $targetFilename,
                                    'slug' => $slug,
                                    'title' => $title,
                                    'description' => $description,
                                    'image' => $image,
                                    'template' => 'single',
                                ]);
                            }
                        }
                        admin_maybe_enqueue_push_notification('post', $title, $description, $link, $image);
                    }
                    if (!$isDraft && $type === 'Podcast') {
                        $audioUrl = admin_public_asset_url($audio);
                        $imageUrl = admin_public_asset_url($image);
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $podcastUrl = $slug !== '' ? admin_public_podcast_url($slug) : '';
                        if ($podcastUrl !== '') {
                            admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description, $image, $podcastUrl, $imageUrl);
                        }
                        $settings = get_settings();
                        $mailing = $settings['mailing'] ?? [];
                        if (($mailing['auto_podcast'] ?? 'off') === 'on' && admin_is_mailing_ready($settings) && $audioUrl !== '') {
                            $subscribers = admin_mailing_recipients_for_type('podcast', $settings);
                            if (!empty($subscribers)) {
                                $payload = admin_prepare_mailing_payload('podcast', $settings, $title, $description, $audioUrl, $image);
                                admin_schedule_mailing_broadcast('podcast', $subscribers, [
                                    'filename' => $targetFilename,
                                    'slug' => $slug,
                                    'title' => $title,
                                    'description' => $description,
                                    'image' => $image,
                                    'audio' => $audio,
                                    'template' => 'podcast',
                                ]);
                            }
                        }
                    }
                    if (!$isDraft) {
                        admin_regenerate_public_artifacts();
                    }
                    if (!$isDraft) {
                        $indexnowUrls = [];
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        if (in_array($type, ['Entrada', 'Página'], true) && $slug !== '' && !($type === 'Página' && $pageVisibility === 'private')) {
                            $indexnowUrls[] = admin_public_post_url($slug);
                        } elseif ($type === 'Podcast' && $slug !== '') {
                            $indexnowUrls[] = admin_public_podcast_url($slug);
                        }
                        if (!empty($indexnowUrls)) {
                            admin_maybe_send_indexnow($indexnowUrls);
                        }
                    }
                    if ($viewAfterSave) {
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $viewUrl = '';
                        if ($slug !== '') {
                            if ($type === 'Podcast') {
                                $viewUrl = admin_public_podcast_url($slug);
                            } elseif ($type === 'Newsletter') {
                                $viewUrl = admin_public_newsletter_url($slug);
                            } elseif (in_array($type, ['Entrada', 'Página'], true)) {
                                $viewUrl = admin_public_post_url($slug);
                            }
                        }
                        if ($viewUrl !== '' && $isDraft) {
                            $viewUrl .= (str_contains($viewUrl, '?') ? '&' : '?') . 'preview=1';
                        }
                        if ($viewUrl !== '') {
                            header('Location: ' . $viewUrl);
                            exit;
                        }
                    }
                    $redirectTemplate = $isDraft ? 'draft' : ($type === 'Página' ? 'page' : ($type === 'Podcast' ? 'podcast' : ($type === 'Newsletter' ? 'newsletter' : 'single')));
                    header('Location: admin.php?page=edit&template=' . $redirectTemplate . '&created=' . urlencode($targetFilename));
                    exit;
                }
            }
        }
    } elseif (isset($_POST['resend_newsletter_edit'])) {
        $title = trim($_POST['title'] ?? '');
        $editFilename = trim($_POST['filename'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $content = $_POST['content'] ?? '';
        $settings = get_settings();
        if (!admin_is_mailing_ready($settings)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Configura el correo de la lista antes de reenviar la newsletter.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $mailing = $settings['mailing'] ?? [];
        if (($mailing['auto_newsletter'] ?? 'off') !== 'on') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'Activa la newsletter en Lista > Preferencias de envio.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $subscribers = admin_mailing_recipients_for_type('newsletter', $settings);
        if (empty($subscribers)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'No hay suscriptores en la lista de correo.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $markdown = new MarkdownConverter();
        $contentHtml = $markdown->toHtml($content);
        $contentText = trim(strip_tags($contentHtml));
        $queueResult = admin_schedule_mailing_broadcast('newsletter', $subscribers, [
            'filename' => $editFilename,
            'title' => $title,
            'image' => $image,
            'content_html' => $contentHtml,
            'content_text' => $contentText,
        ]);
        if (($queueResult['queued_recipients'] ?? 0) === 0) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo encolar la newsletter. Revisa la configuración de correo.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $_SESSION['mailing_feedback'] = [
            'type' => 'success',
            'message' => 'Newsletter reencolada en ' . ((int) ($queueResult['queued_batches'] ?? 0)) . ' tanda' . (((int) ($queueResult['queued_batches'] ?? 0)) === 1 ? '' : 's') . ' para ' . ((int) ($queueResult['queued_recipients'] ?? 0)) . ' destinatario' . (((int) ($queueResult['queued_recipients'] ?? 0)) === 1 ? '' : 's') . '.',
        ];
        header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
        exit;
    } elseif (isset($_POST['send_newsletter_custom_edit'])) {
        $title = trim($_POST['title'] ?? '');
        $editFilename = trim($_POST['filename'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $content = $_POST['content'] ?? '';
        $rawRecipients = trim((string) ($_POST['custom_recipients'] ?? ''));
        $_SESSION['newsletter_custom_recipients'] = $rawRecipients;
        $settings = get_settings();
        if (!admin_is_mailing_ready($settings)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Configura el correo de la lista antes de enviar la newsletter.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        if ($rawRecipients === '') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'Indica al menos una dirección de email en "Enviar a...".',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $normalized = str_replace([',', ';', "\t"], ' ', $rawRecipients);
        $tokens = preg_split('/\s+/u', $normalized) ?: [];
        $recipientsMap = [];
        $invalidCount = 0;
        foreach ($tokens as $token) {
            $email = admin_normalize_email($token);
            if ($email === '') {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidCount++;
                continue;
            }
            $recipientsMap[$email] = true;
        }
        $recipients = array_keys($recipientsMap);
        if (empty($recipients)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'No se detectaron direcciones válidas en "Enviar a...".',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $markdown = new MarkdownConverter();
        $contentHtml = $markdown->toHtml($content);
        $contentText = trim(strip_tags($contentHtml));
        $queueResult = admin_schedule_mailing_broadcast('newsletter', $recipients, [
            'filename' => $editFilename,
            'title' => $title,
            'image' => $image,
            'content_html' => $contentHtml,
            'content_text' => $contentText,
        ]);
        if (($queueResult['queued_recipients'] ?? 0) === 0) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo encolar la newsletter para los destinatarios indicados.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $queuedCount = (int) ($queueResult['queued_recipients'] ?? 0);
        $batchCount = (int) ($queueResult['queued_batches'] ?? 0);
        $message = 'Newsletter encolada en ' . $batchCount . ' tanda' . ($batchCount === 1 ? '' : 's') . ' para ' . $queuedCount . ' destinatario' . ($queuedCount === 1 ? '' : 's');
        if ($invalidCount > 0) {
            $message .= '. ' . $invalidCount . ' dirección' . ($invalidCount === 1 ? '' : 'es') . ' inválida' . ($invalidCount === 1 ? '' : 's') . ' ignorada' . ($invalidCount === 1 ? '' : 's') . '.';
        } else {
            $message .= '.';
        }
        $_SESSION['mailing_feedback'] = [
            'type' => 'success',
            'message' => $message,
        ];
        unset($_SESSION['newsletter_custom_recipients']);
        header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
        exit;
    } elseif (isset($_POST['send_newsletter_edit'])) {
        $title = trim($_POST['title'] ?? '');
        $editFilename = trim($_POST['filename'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $dateInput = $_POST['date'] ?? '';
        $timestamp = $dateInput !== '' ? strtotime($dateInput) : time();
        if ($timestamp === false) {
            $timestamp = time();
        }
        $date = date('Y-m-d', $timestamp);
        $image = trim($_POST['image'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $lang = trim($_POST['lang'] ?? '');
        $content = $_POST['content'] ?? '';
        $settings = get_settings();
        if (!admin_is_mailing_ready($settings)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Configura el correo de la lista antes de enviar la newsletter.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $mailing = $settings['mailing'] ?? [];
        if (($mailing['auto_newsletter'] ?? 'off') !== 'on') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'Activa la newsletter en Lista > Preferencias de envio.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $subscribers = admin_mailing_recipients_for_type('newsletter', $settings);
        if (empty($subscribers)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'No hay suscriptores en la lista de correo.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $markdown = new MarkdownConverter();
        $contentHtml = $markdown->toHtml($content);
        $contentText = trim(strip_tags($contentHtml));
        $queueResult = admin_schedule_mailing_broadcast('newsletter', $subscribers, [
            'filename' => $editFilename,
            'title' => $title,
            'image' => $image,
            'content_html' => $contentHtml,
            'content_text' => $contentText,
        ]);
        if (($queueResult['queued_recipients'] ?? 0) === 0) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo encolar la newsletter. Revisa la configuración de correo.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $targetFilename = nammu_normalize_filename($editFilename);
        if ($targetFilename !== '') {
            $existing = get_post_content($targetFilename);
            $existingOrdo = '';
            if (is_array($existing) && isset($existing['metadata']) && is_array($existing['metadata'])) {
                $existingOrdo = trim((string) ($existing['metadata']['Ordo'] ?? ''));
            }
            if ($existingOrdo === '') {
                $allPosts = get_all_posts_metadata();
                $maxOrdo = 0;
                foreach ($allPosts as $post) {
                    if (isset($post['metadata']['Ordo']) && (int) $post['metadata']['Ordo'] > $maxOrdo) {
                        $maxOrdo = (int) $post['metadata']['Ordo'];
                    }
                }
                $existingOrdo = (string) ($maxOrdo + 1);
            }
            $filepath = CONTENT_DIR . '/' . $targetFilename;
            $fileContent = "---
";
            $fileContent .= "Title: " . $title . "
";
            $fileContent .= "Template: newsletter
";
            $fileContent .= "Category: " . $category . "
";
            $fileContent .= "Date: " . $date . "
";
            $fileContent .= "Image: " . $image . "
";
            $fileContent .= "Description: " . $description . "
";
            if ($lang !== '') {
                $fileContent .= "Lang: " . $lang . "
";
            }
            $fileContent .= "Status: newsletter
";
            $fileContent .= "Ordo: " . $existingOrdo . "
";
            $fileContent .= "---

";
            $fileContent .= $content;
            if (@file_put_contents($filepath, $fileContent, LOCK_EX) !== false) {
                admin_chmod_content_file($filepath);
            }
        }
        $_SESSION['mailing_feedback'] = [
            'type' => 'success',
            'message' => 'Newsletter encolada en ' . ((int) ($queueResult['queued_batches'] ?? 0)) . ' tanda' . (((int) ($queueResult['queued_batches'] ?? 0)) === 1 ? '' : 's') . ' para ' . ((int) ($queueResult['queued_recipients'] ?? 0)) . ' destinatario' . (((int) ($queueResult['queued_recipients'] ?? 0)) === 1 ? '' : 's') . '.',
        ];
        header('Location: admin.php?page=edit-post&file=' . urlencode($targetFilename !== '' ? $targetFilename : $editFilename));
        exit;
    } elseif (isset($_POST['update']) || isset($_POST['update_and_view']) || isset($_POST['publish_draft_entry']) || isset($_POST['publish_draft_page']) || isset($_POST['publish_draft_podcast']) || isset($_POST['convert_to_draft'])) {
        $existing_post_data = null;
        $filename = $_POST['filename'] ?? '';
        $title = $_POST['title'] ?? '';
        $category = $_POST['category'] ?? '';
        $date = $_POST['date'] ? date('Y-m-d', strtotime($_POST['date'])) : date('Y-m-d');
        $publishAtDate = trim($_POST['publish_at_date'] ?? '');
        $publishAtTime = trim($_POST['publish_at_time'] ?? '');
        $image = $_POST['image'] ?? '';
        $description = $_POST['description'] ?? '';
        $lang = trim($_POST['lang'] ?? '');
        $audio = trim($_POST['audio'] ?? '');
        $video = trim($_POST['video'] ?? '');
        $audioLengthInput = trim($_POST['audio_length'] ?? '');
        $audioDuration = trim($_POST['audio_duration'] ?? '');
        $pageVisibilityInputRaw = strtolower(trim((string) ($_POST['page_visibility'] ?? '')));
        $relatedSlugsInput = trim((string) ($_POST['related_slugs'] ?? ''));
        $type = $_POST['type'] ?? null;
        $statusPosted = strtolower(trim($_POST['status'] ?? ''));
        $viewAfterSave = isset($_POST['update_and_view']);
        $publishDraftAsEntry = isset($_POST['publish_draft_entry']);
        $publishDraftAsPage = isset($_POST['publish_draft_page']);
        $publishDraftAsPodcast = isset($_POST['publish_draft_podcast']);
        $convertToDraft = isset($_POST['convert_to_draft']);

        // Preserve existing Ordo value on update
        $normalizedFilename = nammu_normalize_filename($filename);
        $previousStatus = 'published';
        $existingFediverseId = '';
        $previousFediverseTemplate = '';
        if ($normalizedFilename !== '') {
            $existing_post_data = get_post_content($normalizedFilename);
            $ordo = $existing_post_data['metadata']['Ordo'] ?? '';
            $previousStatus = strtolower($existing_post_data['metadata']['Status'] ?? 'published');
            $previousFediverseTemplate = strtolower(trim((string) ($existing_post_data['metadata']['Template'] ?? 'post')));
            if ($previousFediverseTemplate === 'single' || $previousFediverseTemplate === 'draft') {
                $previousFediverseTemplate = 'post';
            }
            $existingFediverseId = trim((string) (($existing_post_data['metadata']['FediverseId'] ?? '') ?: ($existing_post_data['metadata']['fediverse_id'] ?? '')));
            if ($lang === '') {
                $lang = trim((string) ($existing_post_data['metadata']['Lang'] ?? ''));
            }
            if ($type === null) {
                $currentTemplate = strtolower($existing_post_data['metadata']['Template'] ?? 'post');
                if ($currentTemplate === 'page') {
                    $type = 'Página';
                } elseif ($currentTemplate === 'podcast') {
                    $type = 'Podcast';
                } elseif ($currentTemplate === 'newsletter') {
                    $type = 'Newsletter';
                } else {
                    $type = 'Entrada';
                }
            }
        } else {
            $ordo = '';
        }

        if ($type === 'Página') {
            $type = 'Página';
        } elseif ($type === 'Podcast') {
            $type = 'Podcast';
        } elseif ($type === 'Newsletter') {
            $type = 'Newsletter';
        } else {
            $type = 'Entrada';
        }
        if ($publishDraftAsEntry) {
            $type = 'Entrada';
        } elseif ($publishDraftAsPage) {
            $type = 'Página';
        } elseif ($publishDraftAsPodcast) {
            $type = 'Podcast';
        }
        $existingVisibility = '';
        if (is_array($existing_post_data) && isset($existing_post_data['metadata']) && is_array($existing_post_data['metadata'])) {
            $existingVisibility = strtolower(trim((string) ($existing_post_data['metadata']['Visibility'] ?? '')));
        }
        $pageVisibility = 'public';
        if ($type === 'Página') {
            if ($pageVisibilityInputRaw === 'private' || ($pageVisibilityInputRaw === '' && $existingVisibility === 'private')) {
                $pageVisibility = 'private';
            }
        }
        $template = $type === 'Página' ? 'page' : ($type === 'Podcast' ? 'podcast' : ($type === 'Newsletter' ? 'newsletter' : 'post'));
        if ($publishDraftAsEntry || $publishDraftAsPage || $publishDraftAsPodcast) {
            $status = 'published';
        } elseif ($convertToDraft) {
            $status = 'draft';
        } else {
            if ($statusPosted === 'draft') {
                $status = 'draft';
            } elseif ($statusPosted === 'newsletter' && $type === 'Newsletter') {
                $status = 'newsletter';
            } else {
                $status = 'published';
            }
        }

        $newFilenameInput = trim($_POST['new_filename'] ?? '');
        $originalSlugBase = $normalizedFilename !== '' ? pathinfo($normalizedFilename, PATHINFO_FILENAME) : '';
        $originalSlugNormalized = $originalSlugBase !== '' ? nammu_slugify($originalSlugBase) : '';
        $content = $_POST['content'] ?? '';
        $audioLength = '';
        if ($type === 'Podcast') {
            if ($audio === '') {
                $error = 'El archivo mp3 del podcast es obligatorio.';
            }
            if ($audio !== '' && !preg_match('/\.mp3$/i', $audio)) {
                $error = 'El audio del podcast debe ser un archivo mp3.';
            }
            if ($video !== '' && !preg_match('/\.mp4$/i', $video)) {
                $error = 'El vídeo del podcast debe ser un archivo mp4.';
            }
            if ($audioDuration === '') {
                $error = 'Indica la duración del episodio en formato hh:mm:ss.';
            }
            $category = '';
            if ($audio !== '') {
                $audioPath = ltrim($audio, '/');
                $audioPath = str_starts_with($audioPath, 'assets/') ? substr($audioPath, strlen('assets/')) : $audioPath;
                $candidate = ASSETS_DIR . '/' . $audioPath;
                if (is_file($candidate)) {
                    $audioLength = (string) filesize($candidate);
                }
            }
            if ($audioLength === '' && $audioLengthInput !== '' && ctype_digit($audioLengthInput)) {
                $audioLength = $audioLengthInput;
            }
        } elseif ($type === 'Newsletter') {
            $category = '';
        }

        $relatedSlugs = [];
        if ($type === 'Entrada' || $type === 'Podcast') {
            $relatedSlugs = admin_parse_related_slugs_input($relatedSlugsInput);
            if ($relatedSlugsInput !== '' && count($relatedSlugs) < 2) {
                $error = 'Entradas o itinerarios relacionados: indica al menos 2 slugs válidos.';
            } elseif (count($relatedSlugs) > 8) {
                $error = 'Entradas o itinerarios relacionados: el máximo son 8 slugs.';
            }
        }

        $targetFilename = $normalizedFilename;
        $renameRequested = false;
        $slugPattern = '/^[a-z0-9-]+$/i';
        if ($newFilenameInput !== '' && !preg_match($slugPattern, $newFilenameInput)) {
            $error = 'El nuevo slug solo puede contener letras, números y guiones medios.';
        }
        if ($error === null && $newFilenameInput !== '') {
            $desiredSlug = nammu_slugify($newFilenameInput);
            if ($desiredSlug === '' && $title !== '') {
                $desiredSlug = nammu_slugify($title);
            }
            if ($desiredSlug === '' && $originalSlugNormalized !== '') {
                $desiredSlug = $originalSlugNormalized;
            }
            if ($desiredSlug === '') {
                $desiredSlug = 'entrada';
            }
            if ($desiredSlug === $originalSlugNormalized) {
                $newFilenameInput = '';
            } else {
                $candidateFilename = nammu_normalize_filename($desiredSlug . '.md');
                if ($candidateFilename === '') {
                    $error = 'El nombre de archivo proporcionado no es válido.';
                } elseif ($candidateFilename !== $normalizedFilename && file_exists(CONTENT_DIR . '/' . $candidateFilename)) {
                    $error = 'Ya existe otro contenido con ese nombre de archivo.';
                } else {
                    $targetFilename = $candidateFilename;
                    $renameRequested = $targetFilename !== $normalizedFilename;
                }
            }
        } elseif ($error === null && $newFilenameInput === '' && $normalizedFilename === '' && $title !== '') {
            $autoSlug = nammu_slugify($title);
            if ($autoSlug === '') {
                $autoSlug = 'entrada';
            }
            $targetFilename = nammu_unique_filename($autoSlug);
        }

        if ($targetFilename === '') {
            $error = 'No se pudo identificar el archivo a actualizar.';
        }

        if ($error === null) {
            $finalPath = CONTENT_DIR . '/' . $targetFilename;
            $publishAtValue = '';
            if ($status === 'draft' && $publishAtDate !== '') {
                $publishAtTime = $publishAtTime !== '' ? $publishAtTime : '00:00';
                $publishTimestamp = strtotime($publishAtDate . ' ' . $publishAtTime);
                if ($publishTimestamp !== false) {
                    $publishAtValue = date('Y-m-d H:i', $publishTimestamp);
                }
            }

            $file_content = "---
";
            $file_content .= "Title: " . $title . "
";
            $file_content .= "Template: " . $template . "
";
            $file_content .= "Category: " . $category . "
";
            $file_content .= "Date: " . $date . "
";
            $file_content .= "Image: " . $image . "
";
            $file_content .= "Description: " . $description . "
";
            if ($type === 'Podcast') {
                $file_content .= "Audio: " . $audio . "
";
                if ($video !== '') {
                    $file_content .= "Video: " . $video . "
";
                }
                if ($audioLength !== '') {
                    $file_content .= "AudioLength: " . $audioLength . "
";
                }
                if ($audioDuration !== '') {
                    $file_content .= "AudioDuration: " . $audioDuration . "
";
                }
            }
            if ($lang !== '') {
                $file_content .= "Lang: " . $lang . "
";
            }
            if ($template === 'page') {
                $file_content .= "Visibility: " . $pageVisibility . "
";
            }
            if (!empty($relatedSlugs) && in_array($template, ['post', 'podcast'], true)) {
                $file_content .= "Related: " . implode(', ', $relatedSlugs) . "
";
            }
            $fediverseIdToPreserve = $existingFediverseId;
            if ($fediverseIdToPreserve === ''
                && $renameRequested
                && $normalizedFilename !== ''
                && $previousStatus === 'published'
                && in_array($previousFediverseTemplate, ['post', 'page', 'podcast'], true)
            ) {
                $baseForFediverseId = function_exists('nammu_fediverse_base_url')
                    ? nammu_fediverse_base_url(load_config_file())
                    : rtrim(admin_base_url(), '/');
                $previousSlug = pathinfo($normalizedFilename, PATHINFO_FILENAME);
                if ($baseForFediverseId !== '' && $previousSlug !== '') {
                    $fediverseIdToPreserve = rtrim($baseForFediverseId, '/') . '/ap/objects/' . rawurlencode($previousFediverseTemplate) . '-' . rawurlencode($previousSlug);
                }
            }
            if ($fediverseIdToPreserve !== '') {
                $file_content .= "FediverseId: " . $fediverseIdToPreserve . "
";
            }
            $file_content .= "Status: " . $status . "
";
            if ($publishAtValue !== '') {
                $file_content .= "PublishAt: " . $publishAtValue . "
";
            }
            $file_content .= "Ordo: " . $ordo . "
";
            $file_content .= "---

";
            $file_content .= $content;

            $tempPath = tempnam(CONTENT_DIR, 'upd_');
            if ($tempPath === false) {
                $error = 'No se pudo crear un archivo temporal para actualizar el contenido.';
            } elseif (file_put_contents($tempPath, $file_content, LOCK_EX) === false) {
                @unlink($tempPath);
                $error = 'No se pudo guardar el contenido actualizado. Revisa los permisos de la carpeta content/.';
            } else {
                admin_chmod_content_file($tempPath);
                $writeSucceeded = false;
                if (@rename($tempPath, $finalPath)) {
                    $writeSucceeded = true;
                } else {
                    @unlink($tempPath);
                    if (file_put_contents($finalPath, $file_content, LOCK_EX) !== false) {
                        $writeSucceeded = true;
                    }
                }

                if ($writeSucceeded) {
                    admin_chmod_content_file($finalPath);
                    $shouldAutoShare = false;
                    $shouldAutoSharePodcast = false;
                    if ($template === 'post' && $status === 'published') {
                        if ($publishDraftAsEntry || $previousStatus === 'draft') {
                            $shouldAutoShare = true;
                        }
                    }
                    if ($template === 'podcast' && $status === 'published') {
                        if ($publishDraftAsPodcast || $previousStatus === 'draft') {
                            $shouldAutoSharePodcast = true;
                        }
                    }
                    if (
                        $status === 'published'
                        && $previousStatus === 'published'
                        && !$shouldAutoShare
                        && !$shouldAutoSharePodcast
                        && in_array($template, ['post', 'podcast', 'page'], true)
                    ) {
                        if (!function_exists('nammu_fediverse_mark_item_delivered_to_followers') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
                            require_once NAMMU_ROOT . '/core/fediverso.php';
                        }
                        if (function_exists('nammu_fediverse_mark_item_delivered_to_followers')) {
                            $fediverseConfig = load_config_file();
                            $fediverseObjectId = trim((string) ($fediverseIdToPreserve ?? ''));
                            if ($fediverseObjectId === '') {
                                $fediverseBaseUrl = function_exists('nammu_fediverse_base_url')
                                    ? nammu_fediverse_base_url($fediverseConfig)
                                    : rtrim(admin_base_url(), '/');
                                $fediverseSlug = pathinfo($targetFilename, PATHINFO_FILENAME);
                                if ($fediverseBaseUrl !== '' && $fediverseSlug !== '') {
                                    $fediverseObjectId = rtrim($fediverseBaseUrl, '/') . '/ap/objects/' . rawurlencode($template) . '-' . rawurlencode($fediverseSlug);
                                }
                            }
                            if ($fediverseObjectId !== '') {
                                nammu_fediverse_mark_item_delivered_to_followers($fediverseObjectId, $fediverseConfig);
                            }
                        }
                    }
                    if ($shouldAutoShare) {
                        $imageUrl = admin_public_asset_url($image);
                        admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description, $image, '', $imageUrl);
                    }
                    if ($shouldAutoSharePodcast) {
                        $audioUrl = admin_public_asset_url($audio);
                        $imageUrl = admin_public_asset_url($image);
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $podcastUrl = $slug !== '' ? admin_public_podcast_url($slug) : '';
                        if ($podcastUrl !== '') {
                            admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description, $image, $podcastUrl, $imageUrl);
                        }
                        $settings = get_settings();
                        $mailing = $settings['mailing'] ?? [];
                        if (($mailing['auto_podcast'] ?? 'off') === 'on' && admin_is_mailing_ready($settings) && $audioUrl !== '') {
                            $subscribers = admin_mailing_recipients_for_type('podcast', $settings);
                            if (!empty($subscribers)) {
                                $payload = admin_prepare_mailing_payload('podcast', $settings, $title, $description, $audioUrl, $image);
                                admin_schedule_mailing_broadcast('podcast', $subscribers, [
                                    'filename' => $targetFilename,
                                    'slug' => $slug,
                                    'title' => $title,
                                    'description' => $description,
                                    'image' => $image,
                                    'audio' => $audio,
                                    'template' => 'podcast',
                                ]);
                            }
                        }
                    }
                    if ($shouldAutoShare) {
                        $settings = get_settings();
                        $mailing = $settings['mailing'] ?? [];
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $link = admin_public_post_url($slug);
                        if (($mailing['auto_posts'] ?? 'off') === 'on' && admin_is_mailing_ready($settings)) {
                            $subscribers = admin_mailing_recipients_for_type('posts', $settings);
                            if (!empty($subscribers)) {
                                $payload = admin_prepare_mailing_payload('single', $settings, $title, $description, $link, $image);
                                admin_schedule_mailing_broadcast('post', $subscribers, [
                                    'filename' => $targetFilename,
                                    'slug' => $slug,
                                    'title' => $title,
                                    'description' => $description,
                                    'image' => $image,
                                    'template' => 'single',
                                ]);
                            }
                        }
                        admin_maybe_enqueue_push_notification('post', $title, $description, $link, $image);
                    }
                    if ($renameRequested && $normalizedFilename !== '' && $normalizedFilename !== $targetFilename) {
                        $previousPath = CONTENT_DIR . '/' . $normalizedFilename;
                        if ($previousPath !== $finalPath && is_file($previousPath)) {
                            @unlink($previousPath);
                        }
                    }
                    admin_regenerate_public_artifacts();
                    if ($status === 'published') {
                        $shouldIndexnow = $previousStatus === 'published' || $previousStatus === 'draft' || $publishDraftAsEntry || $publishDraftAsPage || $publishDraftAsPodcast || $renameRequested;
                        if ($shouldIndexnow) {
                            $indexnowUrls = [];
                            $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                            if (in_array($template, ['post', 'page'], true) && $slug !== '' && !($template === 'page' && $pageVisibility === 'private')) {
                                $indexnowUrls[] = admin_public_post_url($slug);
                            } elseif ($template === 'podcast') {
                                if ($slug !== '') {
                                    $indexnowUrls[] = admin_public_podcast_url($slug);
                                }
                            }
                            if (!empty($indexnowUrls)) {
                                admin_maybe_send_indexnow($indexnowUrls);
                            }
                        }
                    }
                    $redirectTemplate = $status === 'draft' ? 'draft' : ($template === 'page' ? 'page' : ($template === 'podcast' ? 'podcast' : ($template === 'newsletter' ? 'newsletter' : 'single')));
                    $feedbackMessage = 'Contenido actualizado correctamente.';
                    if ($publishDraftAsEntry) {
                        $feedbackMessage = 'Borrador publicado como entrada.';
                    } elseif ($publishDraftAsPage) {
                        $feedbackMessage = 'Borrador publicado como página.';
                    } elseif ($publishDraftAsPodcast) {
                        $feedbackMessage = 'Borrador publicado como podcast.';
                    } elseif ($convertToDraft) {
                        $feedbackMessage = 'Contenido pasado a borrador.';
                    }
                    if ($viewAfterSave) {
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $viewUrl = '';
                        if ($slug !== '') {
                            if ($template === 'podcast') {
                                $viewUrl = admin_public_podcast_url($slug);
                            } elseif ($template === 'newsletter') {
                                $viewUrl = admin_public_newsletter_url($slug);
                            } elseif (in_array($template, ['post', 'page'], true)) {
                                $viewUrl = admin_public_post_url($slug);
                            }
                        }
                        if ($viewUrl !== '' && $status === 'draft') {
                            $viewUrl .= (str_contains($viewUrl, '?') ? '&' : '?') . 'preview=1';
                        }
                        if ($viewUrl !== '') {
                            header('Location: ' . $viewUrl);
                            exit;
                        }
                    }
                    $_SESSION['edit_feedback'] = [
                        'type' => 'success',
                        'message' => $feedbackMessage,
                    ];
                    header('Location: admin.php?page=edit-post&file=' . urlencode($targetFilename));
                    exit;
                } else {
                    $error = 'No se pudo guardar el contenido actualizado. Revisa los permisos de la carpeta content/.';
                }
            }
        }
    } elseif (isset($_POST['send_social_post'])) {
        $networkKey = $_POST['social_network'] ?? '';
        $filename = $_POST['social_filename'] ?? '';
        $templateTarget = $_POST['social_template'] ?? 'single';
        $templateTarget = in_array($templateTarget, ['single', 'page', 'draft', 'newsletter', 'podcast'], true) ? $templateTarget : 'single';
        $redirectTemplate = urlencode($templateTarget);
        $networkLabels = admin_social_network_labels(true);
        if (!isset($networkLabels[$networkKey])) {
            $_SESSION['social_feedback'] = ['type' => 'danger', 'message' => 'Red social no válida.'];
            header('Location: admin.php?page=edit&template=' . $redirectTemplate);
            exit;
        }
        $filename = nammu_normalize_filename($filename);
        $feedback = [
            'type' => 'danger',
            'message' => 'No se pudo encontrar la entrada solicitada.',
        ];
        if ($filename !== '' && is_file(CONTENT_DIR . '/' . $filename)) {
            $postData = get_post_content($filename);
            if ($postData) {
                $metadata = $postData['metadata'] ?? [];
                $template = strtolower(trim((string) ($metadata['Template'] ?? 'post')));
                if (in_array($template, ['single', 'post', 'page', 'podcast'], true)) {
                    $slug = pathinfo($filename, PATHINFO_FILENAME);
                    $slug = $slug !== '' ? $slug : $filename;
                    $title = (string) ($metadata['Title'] ?? $slug);
                    $description = (string) ($metadata['Description'] ?? '');
                    $image = $metadata['Image'] ?? '';
                    $imageUrl = admin_public_asset_url((string) $image);
                    $customUrl = '';
                    if ($networkKey === 'fediverse') {
                        if (!function_exists('nammu_fediverse_deliver_named_local_item') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
                            require_once NAMMU_ROOT . '/core/fediverso.php';
                        }
                        if (!function_exists('nammu_fediverse_deliver_named_local_item')) {
                            $feedback['message'] = 'La integración con Fediverso no está disponible en esta instalación.';
                            $_SESSION['social_feedback'] = $feedback;
                            header('Location: admin.php?page=edit&template=' . $redirectTemplate);
                            exit;
                        }
                        $fediverseResult = nammu_fediverse_deliver_named_local_item($slug, $template, load_config_file());
                        $feedback = [
                            'type' => !empty($fediverseResult['ok']) ? 'success' : 'danger',
                            'message' => (string) ($fediverseResult['message'] ?? 'No se pudo reenviar el contenido al Fediverso.'),
                        ];
                        $_SESSION['social_feedback'] = $feedback;
                        header('Location: admin.php?page=edit&template=' . $redirectTemplate);
                        exit;
                    }
                    if ($template === 'podcast') {
                        $customUrl = admin_public_podcast_url($slug);
                        $imagePath = (string) ($metadata['Image'] ?? '');
                        $imageUrl = admin_public_asset_url($imagePath);
                        if ($customUrl === '') {
                            $feedback['message'] = 'No se encontró la URL pública del episodio para compartir.';
                            $_SESSION['social_feedback'] = $feedback;
                            header('Location: admin.php?page=edit&template=' . $redirectTemplate);
                            exit;
                        }
                    }
                    $allSocialSettings = admin_cached_social_settings();
                    $networkSettings = $allSocialSettings[$networkKey] ?? [];
                    $sendResult = admin_send_content_to_social_network($networkKey, $slug, $title, $description, (string) $image, $networkSettings, $customUrl, $imageUrl, 'publicación');
                    $feedback = [
                        'type' => !empty($sendResult['ok']) ? 'success' : 'danger',
                        'message' => (string) ($sendResult['message'] ?? ''),
                    ];
                } else {
                    $feedback['message'] = 'Sólo las entradas, páginas y podcasts pueden enviarse a redes sociales.';
                }
            }
        }
        $_SESSION['social_feedback'] = $feedback;
        header('Location: admin.php?page=edit&template=' . $redirectTemplate);
        exit;
    } elseif (isset($_POST['send_social_actuality'])) {
        $networkKey = trim((string) ($_POST['social_network'] ?? ''));
        $actualityType = trim((string) ($_POST['actuality_type'] ?? ''));
        $actualityId = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['actuality_id'] ?? '')) ?? '';
        $templateTarget = $actualityType === 'news' ? 'news' : 'notes';
        $networkLabels = admin_social_network_labels(true);
        $feedback = [
            'type' => 'danger',
            'message' => 'No se pudo encontrar el contenido solicitado.',
        ];
        if (!isset($networkLabels[$networkKey])) {
            $feedback['message'] = 'Red social no válida.';
        } elseif ($actualityId !== '') {
            if (!function_exists('nammu_actuality_get_manual_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
                require_once NAMMU_ROOT . '/core/actualidad.php';
            }
            $item = null;
            if ($actualityType === 'news' && function_exists('nammu_actuality_get_news_item')) {
                $item = nammu_actuality_get_news_item($actualityId);
            } elseif ($actualityType === 'notes' && function_exists('nammu_actuality_get_manual_item')) {
                $item = nammu_actuality_get_manual_item($actualityId);
            }
            if (is_array($item)) {
                if ($networkKey === 'fediverse') {
                    if (!function_exists('nammu_fediverse_deliver_actuality_item') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
                        require_once NAMMU_ROOT . '/core/fediverso.php';
                    }
                    if (!function_exists('nammu_fediverse_deliver_actuality_item')) {
                        $feedback['message'] = 'La integración con Fediverso no está disponible en esta instalación.';
                    } else {
                        $fediverseResult = nammu_fediverse_deliver_actuality_item($actualityId, load_config_file());
                        $feedback = [
                            'type' => !empty($fediverseResult['ok']) ? 'success' : 'danger',
                            'message' => (string) ($fediverseResult['message'] ?? 'No se pudo reenviar el contenido al Fediverso.'),
                        ];
                    }
                } else {
                    if (!function_exists('admin_social_broadcast_fediverse_url_for_actuality_item') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
                        require_once NAMMU_ROOT . '/core/admin-redes.php';
                    }
                    $title = trim((string) ($item['title'] ?? ''));
                    $description = trim((string) (($item['raw_text'] ?? '') ?: ($item['description'] ?? '')));
                    if ($title === '' && $actualityType === 'notes') {
                        $title = 'Nota';
                    }
                    $fediverseUrl = function_exists('admin_social_broadcast_fediverse_url_for_actuality_item')
                        ? admin_social_broadcast_fediverse_url_for_actuality_item($item)
                        : '';
                    $images = array_values(array_filter(array_map('strval', is_array($item['images'] ?? null) ? $item['images'] : [])));
                    $image = trim((string) (($item['image'] ?? '') ?: ($images[0] ?? '')));
                    $allSocialSettings = admin_cached_social_settings();
                    $networkSettings = $allSocialSettings[$networkKey] ?? [];
                    $sendResult = admin_send_content_to_social_network($networkKey, $actualityId, $title, $description, $image, $networkSettings, $fediverseUrl, $image, $actualityType === 'news' ? 'noticia' : 'nota');
                    if (!empty($sendResult['ok']) && $actualityType === 'news' && function_exists('admin_social_rss_mark_broadcast_result')) {
                        admin_social_rss_mark_broadcast_result([
                            'source' => 'social-rss-news',
                            'source_id' => $actualityId,
                        ], 'sent', [
                            'sent_networks' => [$networkKey],
                        ]);
                    }
                    $feedback = [
                        'type' => !empty($sendResult['ok']) ? 'success' : 'danger',
                        'message' => (string) ($sendResult['message'] ?? ''),
                    ];
                }
            }
        }
        if ($templateTarget === 'news') {
            $_SESSION['news_feedback'] = $feedback;
        } else {
            $_SESSION['notes_feedback'] = $feedback;
        }
        header('Location: admin.php?page=edit&template=' . urlencode($templateTarget));
        exit;
    } elseif (isset($_POST['send_social_itinerary'])) {
        $networkKey = $_POST['social_network'] ?? '';
        $itinerarySlug = ItineraryRepository::normalizeSlug($_POST['itinerary_slug'] ?? '');
        $networkLabels = admin_social_network_labels();
        $feedback = [
            'type' => 'danger',
            'message' => 'No se pudo encontrar el itinerario solicitado.',
        ];
        if (!isset($networkLabels[$networkKey])) {
            $feedback = ['type' => 'danger', 'message' => 'Red social no válida.'];
        } elseif ($itinerarySlug !== '') {
            $itinerary = admin_load_itinerary($itinerarySlug);
            if ($itinerary) {
                $title = (string) $itinerary->getTitle();
                $description = (string) $itinerary->getDescription();
                $image = $itinerary->getImage() ?? '';
                $customUrl = admin_public_itinerary_url($itinerary->getSlug());
                $imageUrl = admin_public_asset_url($image);
                $allSocialSettings = admin_cached_social_settings();
                $networkSettings = $allSocialSettings[$networkKey] ?? [];
                $sendResult = admin_send_content_to_social_network($networkKey, $itinerarySlug, $title, $description, (string) $image, $networkSettings, $customUrl, $imageUrl, 'itinerario');
                $feedback = [
                    'type' => !empty($sendResult['ok']) ? 'success' : 'danger',
                    'message' => (string) ($sendResult['message'] ?? ''),
                ];
            }
        }
        $_SESSION['itinerary_feedback'] = $feedback;
        header('Location: admin.php?page=itinerarios');
        exit;
    } elseif (isset($_POST['save_itinerary']) || isset($_POST['save_itinerary_view']) || isset($_POST['publish_itinerary'])) {
        $viewItineraryAfterSave = isset($_POST['save_itinerary_view']);
        $publishItineraryNow = isset($_POST['publish_itinerary']);
        $title = trim($_POST['itinerary_title'] ?? '');
        $description = trim($_POST['itinerary_description'] ?? '');
        $image = trim($_POST['itinerary_image'] ?? '');
        $content = $_POST['itinerary_content'] ?? '';
        $classChoice = $_POST['itinerary_class'] ?? '';
        $classCustom = $_POST['itinerary_class_custom'] ?? '';
        $itineraryQuizPayload = $_POST['itinerary_quiz_payload'] ?? '';
        $usageLogicInput = $_POST['itinerary_usage_logic'] ?? '';
        $statusInput = $_POST['itinerary_status'] ?? '';
        $slugInput = trim($_POST['itinerary_slug'] ?? '');
        $originalSlugInput = trim($_POST['itinerary_original_slug'] ?? '');
        $mode = $_POST['itinerary_mode'] ?? '';
        $orderInput = (int) ($_POST['itinerary_order'] ?? 0);
        $previousStatus = 'draft';
        if ($originalSlugInput !== '') {
            $existingItinerary = admin_load_itinerary($originalSlugInput);
            if ($existingItinerary) {
                $previousStatus = $existingItinerary->getStatus();
            }
        }
        if ($slugInput === '' && $title !== '') {
            $slugInput = $title;
        }
        $slug = ItineraryRepository::normalizeSlug($slugInput);
        $originalSlug = ItineraryRepository::normalizeSlug($originalSlugInput);
        $redirectBase = 'admin.php?page=itinerario';
        if ($originalSlug !== '') {
            $redirectBase .= '&itinerary=' . urlencode($originalSlug);
        } elseif ($mode === 'new') {
            $redirectBase .= '&new=1';
        }
        if ($title === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'El título del itinerario es obligatorio.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        $itineraryQuizResult = admin_parse_quiz_payload($itineraryQuizPayload);
        if ($itineraryQuizResult['error'] !== null) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => $itineraryQuizResult['error']];
            header('Location: ' . $redirectBase);
            exit;
        }
        if ($slug === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'El slug del itinerario no es válido.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        $targetDir = ITINERARIES_DIR . '/' . $slug;
        if ($originalSlug === '' && is_dir($targetDir)) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'Ya existe un itinerario con ese slug.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        if ($originalSlug !== '' && $originalSlug !== $slug) {
            $originalDir = ITINERARIES_DIR . '/' . $originalSlug;
            if (is_dir($targetDir)) {
                $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'Ya existe un itinerario con el slug solicitado.'];
                header('Location: ' . $redirectBase);
                exit;
            }
            if (is_dir($originalDir)) {
                if (!@rename($originalDir, $targetDir)) {
                    $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo renombrar la carpeta del itinerario.'];
                    header('Location: ' . $redirectBase);
                    exit;
                }
            } else {
                if (!is_dir($targetDir)) {
                    nammu_ensure_directory($targetDir);
                }
            }
        }
        if ($orderInput <= 0) {
            $orderInput = admin_next_itinerary_order();
        }
        try {
            $classLabel = admin_normalize_itinerary_class_label($classChoice, $classCustom);
            $usageLogic = admin_normalize_itinerary_usage_logic($usageLogicInput);
            $statusValue = $publishItineraryNow
                ? 'published'
                : (strtolower(trim((string) $statusInput)) === 'draft' ? 'draft' : 'published');
            $saved = admin_itinerary_repository()->saveItinerary($slug, [
                'Title' => $title,
                'Description' => $description,
                'Image' => $image,
                'ItineraryClass' => $classLabel,
                'UsageLogic' => $usageLogic,
                'Status' => $statusValue,
                'Order' => $orderInput,
            ], $content, !empty($itineraryQuizResult['data']['questions']) ? $itineraryQuizResult['data'] : null);
            admin_regenerate_public_artifacts();
            $shouldDispatchPublication = $publishItineraryNow || ($statusValue === 'published' && ($mode === 'new' || $previousStatus === 'draft'));
            $shouldAutoMail = $statusValue === 'published' && $shouldDispatchPublication;
            if ($shouldAutoMail) {
                $settings = get_settings();
                $mailing = $settings['mailing'] ?? [];
                if (($mailing['auto_itineraries'] ?? 'off') === 'on' && admin_is_mailing_ready($settings)) {
                    $subscribers = admin_mailing_recipients_for_type('itineraries', $settings);
                    if (!empty($subscribers)) {
                        $link = admin_public_itinerary_url($saved->getSlug());
                        $payload = admin_prepare_mailing_payload('itinerario', $settings, $title, $description, $link, $image);
                        admin_schedule_mailing_broadcast('itinerary', $subscribers, [
                            'slug' => $saved->getSlug(),
                            'title' => $title,
                            'description' => $description,
                            'image' => $image,
                            'template' => 'itinerario',
                        ]);
                    }
                }
                $link = admin_public_itinerary_url($saved->getSlug());
                admin_maybe_enqueue_push_notification('itinerary', $title, $description, $link, $image);
                $imageUrl = admin_public_asset_url($image);
                admin_maybe_auto_post_to_social_networks($saved->getSlug(), $title, $description, $image, $link, $imageUrl);
                admin_maybe_send_indexnow([$link]);
            }
            if ($viewItineraryAfterSave) {
                $itineraryUrl = admin_public_itinerary_url($saved->getSlug());
                if ($statusValue === 'draft') {
                    $itineraryUrl .= (str_contains($itineraryUrl, '?') ? '&' : '?') . 'preview=1';
                }
                header('Location: ' . $itineraryUrl);
                exit;
            }
            $_SESSION['itinerary_feedback'] = [
                'type' => 'success',
                'message' => $publishItineraryNow
                    ? 'Itinerario publicado y enviado a los canales automáticos configurados.'
                    : 'Itinerario guardado correctamente.',
            ];
            header('Location: admin.php?page=itinerario&itinerary=' . urlencode($saved->getSlug()));
            exit;
        } catch (Throwable $e) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo guardar el itinerario: ' . $e->getMessage()];
            header('Location: ' . $redirectBase);
            exit;
        }
    } elseif (isset($_POST['save_itinerary_topic']) || isset($_POST['save_itinerary_topic_add']) || isset($_POST['save_itinerary_topic_view'])) {
        $redirectToNewForm = isset($_POST['save_itinerary_topic_add']);
        $viewTopicAfterSave = isset($_POST['save_itinerary_topic_view']);
        $itinerarySlug = ItineraryRepository::normalizeSlug($_POST['topic_itinerary_slug'] ?? '');
        $title = trim($_POST['topic_title'] ?? '');
        $description = trim($_POST['topic_description'] ?? '');
        $image = trim($_POST['topic_image'] ?? '');
        $content = $_POST['topic_content'] ?? '';
        $numberRequested = (int) ($_POST['topic_number'] ?? 1);
        $slugInput = trim($_POST['topic_slug'] ?? '');
        $originalSlugInput = trim($_POST['topic_original_slug'] ?? '');
        $mode = $_POST['topic_mode'] ?? '';
        $quizPayload = $_POST['topic_quiz_payload'] ?? '';
        $quizResult = admin_parse_quiz_payload($quizPayload);
        if ($quizResult['error'] !== null) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => $quizResult['error']];
            header('Location: ' . $redirectBase);
            exit;
        }
        $quizData = $quizResult['data'];
        if ($slugInput === '' && $title !== '') {
            $slugInput = $title;
        }
        $topicSlug = ItineraryRepository::normalizeSlug($slugInput);
        $originalSlug = ItineraryRepository::normalizeSlug($originalSlugInput);
        $redirectBase = 'admin.php?page=itinerario-tema';
        if ($itinerarySlug !== '') {
            $redirectBase .= '&itinerary=' . urlencode($itinerarySlug);
        }
        if ($redirectToNewForm) {
            $redirectBase .= '&topic=new';
        } elseif ($mode === 'new') {
            $redirectBase .= '&topic=new';
        } elseif ($originalSlug !== '') {
            $redirectBase .= '&topic=' . urlencode($originalSlug);
        }
        if ($itinerarySlug === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'Selecciona un itinerario antes de añadir un tema.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        if ($title === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'El título del tema es obligatorio.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        if ($topicSlug === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'El slug del tema no es válido.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        try {
            $repository = admin_itinerary_repository();
            $itinerary = $repository->find($itinerarySlug);
            if ($itinerary === null) {
                $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'El itinerario seleccionado no existe.'];
                header('Location: ' . $redirectBase);
                exit;
            }
            $topics = $itinerary->getTopics();
            $filteredTopics = [];
            foreach ($topics as $topic) {
                if ($topic->getSlug() === $topicSlug && $topicSlug !== $originalSlug) {
                    $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'Ya existe un tema con ese slug en el itinerario.'];
                    header('Location: ' . $redirectBase);
                    exit;
                }
                if ($topic->getSlug() !== $originalSlug) {
                    $filteredTopics[] = $topic;
                }
            }
            $position = max(1, $numberRequested);
            $maxPosition = count($filteredTopics) + 1;
            if ($position > $maxPosition) {
                $position = $maxPosition;
            }
            $sequence = [];
            $inserted = false;
            foreach ($filteredTopics as $existingTopic) {
                if (!$inserted && count($sequence) === $position - 1) {
                    $sequence[] = ['type' => 'new'];
                    $inserted = true;
                }
                $sequence[] = ['type' => 'existing', 'topic' => $existingTopic];
            }
            if (!$inserted) {
                $sequence[] = ['type' => 'new'];
            }
            if ($originalSlug !== '' && $originalSlug !== $topicSlug) {
                $oldFile = ITINERARIES_DIR . '/' . $itinerarySlug . '/' . $originalSlug . '.md';
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
                $oldQuiz = ITINERARIES_DIR . '/' . $itinerarySlug . '/' . $originalSlug . '.quiz.json';
                if (is_file($oldQuiz)) {
                    @unlink($oldQuiz);
                }
            }
            $newSaved = false;
            foreach ($sequence as $index => $entry) {
                $number = $index + 1;
                if ($entry['type'] === 'new') {
                    if ($newSaved) {
                        continue;
                    }
                    $metadata = [
                        'Title' => $title,
                        'Description' => $description,
                        'Number' => $number,
                        'Image' => $image,
                    ];
                    $repository->saveTopic($itinerarySlug, $topicSlug, $metadata, $content, !empty($quizData['questions']) ? $quizData : null);
                    $newSaved = true;
                } else {
                    /** @var ItineraryTopic $existingTopic */
                    $existingTopic = $entry['topic'];
                    $metadata = $existingTopic->getMetadata();
                    $metadata['Number'] = $number;
                    $repository->saveTopic(
                        $itinerarySlug,
                        $existingTopic->getSlug(),
                        $metadata,
                        $existingTopic->getContent(),
                        $existingTopic->getQuiz()
                    );
                }
            }
            admin_regenerate_public_artifacts();
            if ($itinerary->isPublished()) {
                $topicUrl = admin_public_itinerary_url($itinerarySlug) . '/' . rawurlencode($topicSlug);
                admin_maybe_send_indexnow([$topicUrl]);
            }
            if ($viewTopicAfterSave) {
                $topicUrl = admin_public_itinerary_url($itinerarySlug) . '/' . rawurlencode($topicSlug);
                if ($itinerary->isDraft()) {
                    $topicUrl .= (str_contains($topicUrl, '?') ? '&' : '?') . 'preview=1';
                }
                header('Location: ' . $topicUrl);
                exit;
            }
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Tema guardado correctamente.'];
            if ($redirectToNewForm) {
                header('Location: admin.php?page=itinerario-tema&itinerary=' . urlencode($itinerarySlug) . '&topic=new');
            } else {
                header('Location: admin.php?page=itinerario-tema&itinerary=' . urlencode($itinerarySlug) . '&topic=' . urlencode($topicSlug));
            }
            exit;
        } catch (Throwable $e) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo guardar el tema: ' . $e->getMessage()];
            header('Location: ' . $redirectBase);
            exit;
        }
    } elseif (isset($_POST['delete_itinerary'])) {
        $slug = ItineraryRepository::normalizeSlug($_POST['delete_itinerary_slug'] ?? '');
        $redirectBase = 'admin.php?page=itinerarios';
        if ($slug === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo borrar el itinerario seleccionado.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        $targetDir = ITINERARIES_DIR . '/' . $slug;
        if (!is_dir($targetDir)) {
            $_SESSION['itinerary_feedback'] = ['type' => 'warning', 'message' => 'El itinerario ya no existe.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        if (admin_recursive_delete_path($targetDir)) {
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Itinerario borrado correctamente.'];
            admin_regenerate_public_artifacts();
        } else {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo borrar la carpeta del itinerario. Revisa los permisos.'];
        }
        header('Location: ' . $redirectBase);
        exit;
    } elseif (isset($_POST['delete_itinerary_topic'])) {
        $itinerarySlug = ItineraryRepository::normalizeSlug($_POST['delete_topic_itinerary_slug'] ?? '');
        $topicSlug = ItineraryRepository::normalizeSlug($_POST['delete_topic_slug'] ?? '');
        $redirectBase = 'admin.php?page=itinerario';
        if ($itinerarySlug !== '') {
            $redirectBase .= '&itinerary=' . urlencode($itinerarySlug);
        }
        if ($itinerarySlug === '' || $topicSlug === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo borrar el tema seleccionado.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        $filePath = ITINERARIES_DIR . '/' . $itinerarySlug . '/' . $topicSlug . '.md';
        if (!is_file($filePath)) {
            $_SESSION['itinerary_feedback'] = ['type' => 'warning', 'message' => 'El tema ya no existe en el itinerario.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        if (@unlink($filePath)) {
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Tema borrado correctamente.'];
            admin_regenerate_public_artifacts();
        } else {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo borrar el archivo del tema. Revisa los permisos.'];
        }
        header('Location: ' . $redirectBase);
        exit;
    } elseif (isset($_POST['reset_itinerary_stats'])) {
        $slug = ItineraryRepository::normalizeSlug($_POST['reset_stats_slug'] ?? '');
        $redirectBase = 'admin.php?page=itinerarios';
        if ($slug === '') {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo identificar el itinerario para reiniciar estadísticas.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        $itinerary = admin_load_itinerary($slug);
        if ($itinerary === null) {
            $_SESSION['itinerary_feedback'] = ['type' => 'warning', 'message' => 'El itinerario solicitado ya no existe.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        try {
            admin_itinerary_repository()->resetItineraryStats($slug);
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Las estadísticas del itinerario se pusieron a cero.'];
        } catch (Throwable $e) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudieron reiniciar las estadísticas: ' . $e->getMessage()];
        }
        header('Location: ' . $redirectBase);
        exit;
    } elseif (isset($_POST['update_actuality_note'])) {
        if (!function_exists('nammu_actuality_update_manual_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
            require_once NAMMU_ROOT . '/core/actualidad.php';
        }
        $noteId = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['note_id'] ?? '')) ?? '';
        $noteText = trim((string) ($_POST['note_text'] ?? ''));
        $noteImagesRaw = trim((string) ($_POST['note_images'] ?? ''));
        $noteImages = array_values(array_filter(array_map('trim', preg_split('/\R+/', $noteImagesRaw) ?: [])));
        if ($noteId === '') {
            $_SESSION['notes_feedback'] = ['type' => 'danger', 'message' => 'No se pudo identificar la nota.'];
            header('Location: admin.php?page=edit&template=notes');
            exit;
        }
        if ($noteText === '') {
            $_SESSION['notes_feedback'] = ['type' => 'danger', 'message' => 'La nota no puede estar vacía.'];
            header('Location: admin.php?page=edit-note&id=' . urlencode($noteId));
            exit;
        }
        $config = load_config_file();
        $siteTitle = trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog'));
        $siteDescription = trim((string) (($config['site_description'] ?? '') ?: ''));
        $siteLang = trim((string) (($config['site_lang'] ?? '') ?: 'es'));
        $baseUrl = trim((string) ($config['site_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = nammu_base_url();
        }
        if (!function_exists('nammu_actuality_update_manual_item') || !nammu_actuality_update_manual_item($noteId, $noteText, $baseUrl, $siteTitle, $noteImages[0] ?? '', $noteImages)) {
            $_SESSION['notes_feedback'] = ['type' => 'danger', 'message' => 'No se pudo actualizar la nota.'];
            header('Location: admin.php?page=edit-note&id=' . urlencode($noteId));
            exit;
        }
        if (function_exists('nammu_actuality_replace_manual_item_in_snapshots')) {
            nammu_actuality_replace_manual_item_in_snapshots($noteId);
        } elseif (function_exists('nammu_actuality_rebuild_snapshot')) {
            nammu_actuality_rebuild_snapshot($baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
        }
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
        $fediverseUpdateDelivered = null;
        if (!function_exists('nammu_fediverse_notify_followers_of_object_update') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
            require_once NAMMU_ROOT . '/core/fediverso.php';
        }
        if (function_exists('nammu_fediverse_notify_followers_of_object_update') && function_exists('nammu_fediverse_local_content_items')) {
            $fediverseItemId = rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . rawurlencode($noteId);
            foreach (nammu_fediverse_local_content_items($config) as $localItem) {
                if (trim((string) ($localItem['id'] ?? '')) !== $fediverseItemId) {
                    continue;
                }
                $fediverseUpdateDelivered = nammu_fediverse_notify_followers_of_object_update($localItem, $config);
                break;
            }
        }
        $message = 'Nota actualizada.';
        if ($fediverseUpdateDelivered !== null) {
            $message .= ' Update federado: ' . (int) $fediverseUpdateDelivered . ' entrega' . ((int) $fediverseUpdateDelivered === 1 ? '' : 's') . '.';
        }
        $_SESSION['notes_feedback'] = ['type' => 'success', 'message' => $message];
        header('Location: admin.php?page=edit&template=notes');
        exit;
    } elseif (isset($_POST['update_actuality_news'])) {
        if (!function_exists('nammu_actuality_update_news_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
            require_once NAMMU_ROOT . '/core/actualidad.php';
        }
        $newsId = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['news_id'] ?? '')) ?? '';
        $newsTitle = trim((string) ($_POST['news_title'] ?? ''));
        $newsText = trim((string) ($_POST['news_text'] ?? ''));
        $newsLink = trim((string) ($_POST['news_link'] ?? ''));
        $newsImagesRaw = trim((string) ($_POST['news_images'] ?? ''));
        $newsImages = array_values(array_filter(array_map('trim', preg_split('/\R+/', $newsImagesRaw) ?: [])));
        if ($newsId === '') {
            $_SESSION['news_feedback'] = ['type' => 'danger', 'message' => 'No se pudo identificar la noticia.'];
            header('Location: admin.php?page=edit&template=news');
            exit;
        }
        if ($newsTitle === '' || $newsText === '' || $newsLink === '') {
            $_SESSION['news_feedback'] = ['type' => 'danger', 'message' => 'La noticia debe tener título, texto y enlace.'];
            header('Location: admin.php?page=edit-news&id=' . urlencode($newsId));
            exit;
        }
        $config = load_config_file();
        $siteTitle = trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog'));
        $siteDescription = trim((string) (($config['site_description'] ?? '') ?: ''));
        $siteLang = trim((string) (($config['site_lang'] ?? '') ?: 'es'));
        $baseUrl = trim((string) ($config['site_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = nammu_base_url();
        }
        if (!function_exists('nammu_actuality_update_news_item') || !nammu_actuality_update_news_item($newsId, $newsTitle, $newsText, $newsLink, $baseUrl, $newsImages[0] ?? '', $newsImages)) {
            $_SESSION['news_feedback'] = ['type' => 'danger', 'message' => 'No se pudo actualizar la noticia.'];
            header('Location: admin.php?page=edit-news&id=' . urlencode($newsId));
            exit;
        }
        if (function_exists('nammu_actuality_replace_news_item_in_snapshots')) {
            nammu_actuality_replace_news_item_in_snapshots($newsId, $baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
        } elseif (function_exists('nammu_actuality_rebuild_snapshot')) {
            nammu_actuality_rebuild_snapshot($baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
        }
        $fediverseUpdateDelivered = null;
        if (!function_exists('nammu_fediverse_notify_followers_of_object_update') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
            require_once NAMMU_ROOT . '/core/fediverso.php';
        }
        if (function_exists('nammu_fediverse_notify_followers_of_object_update') && function_exists('nammu_fediverse_local_content_items')) {
            $fediverseItemId = rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . rawurlencode($newsId);
            foreach (nammu_fediverse_local_content_items($config) as $localItem) {
                if (trim((string) ($localItem['id'] ?? '')) !== $fediverseItemId) {
                    continue;
                }
                $fediverseUpdateDelivered = nammu_fediverse_notify_followers_of_object_update($localItem, $config);
                break;
            }
        }
        $message = 'Noticia actualizada.';
        if ($fediverseUpdateDelivered !== null) {
            $message .= ' Update federado: ' . (int) $fediverseUpdateDelivered . ' entrega' . ((int) $fediverseUpdateDelivered === 1 ? '' : 's') . '.';
        }
        $_SESSION['news_feedback'] = ['type' => 'success', 'message' => $message];
        header('Location: admin.php?page=edit&template=news');
        exit;
    } elseif (isset($_POST['delete_actuality_note'])) {
        if (!function_exists('nammu_actuality_delete_manual_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
            require_once NAMMU_ROOT . '/core/actualidad.php';
        }
        if (!function_exists('nammu_fediverse_enqueue_delete_local_item') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
            require_once NAMMU_ROOT . '/core/fediverso.php';
        }
        $noteId = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['delete_note_id'] ?? '')) ?? '';
        if ($noteId === '') {
            $_SESSION['notes_feedback'] = ['type' => 'danger', 'message' => 'No se pudo identificar la nota para borrarla.'];
            header('Location: admin.php?page=edit&template=notes');
            exit;
        }
        $config = load_config_file();
        $baseUrl = trim((string) ($config['site_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = nammu_base_url();
        }
        $deleteMessages = [];
        if (function_exists('nammu_fediverse_enqueue_delete_local_item')) {
            $fediverseItemId = rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . rawurlencode($noteId);
            $fediverseDelete = nammu_fediverse_enqueue_delete_local_item($fediverseItemId, $config);
            if (!empty($fediverseDelete['message'])) {
                $deleteMessages[] = trim((string) $fediverseDelete['message']);
            }
        }
        if (!function_exists('nammu_actuality_delete_manual_item') || !nammu_actuality_delete_manual_item($noteId)) {
            $_SESSION['notes_feedback'] = ['type' => 'warning', 'message' => 'La nota ya no existe o no se pudo borrar.'];
            header('Location: admin.php?page=edit&template=notes');
            exit;
        }
        if (function_exists('nammu_actuality_remove_manual_item_from_snapshots')) {
            nammu_actuality_remove_manual_item_from_snapshots($noteId);
        }
        if (function_exists('nammu_fediverse_remove_local_item_from_home_snapshot')) {
            $fediverseItemId = rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . rawurlencode($noteId);
            nammu_fediverse_remove_local_item_from_home_snapshot($fediverseItemId);
        }
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
        $_SESSION['notes_feedback'] = ['type' => 'success', 'message' => trim('Nota borrada. ' . implode(' ', array_filter($deleteMessages)))];
        header('Location: admin.php?page=edit&template=notes');
        exit;
    } elseif (isset($_POST['delete_actuality_news'])) {
        if (!function_exists('nammu_actuality_delete_news_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
            require_once NAMMU_ROOT . '/core/actualidad.php';
        }
        if (!function_exists('nammu_fediverse_enqueue_delete_local_item') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
            require_once NAMMU_ROOT . '/core/fediverso.php';
        }
        $newsId = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['delete_news_id'] ?? '')) ?? '';
        if ($newsId === '') {
            $_SESSION['news_feedback'] = ['type' => 'danger', 'message' => 'No se pudo identificar la noticia para borrarla.'];
            header('Location: admin.php?page=edit&template=news');
            exit;
        }
        $config = load_config_file();
        $siteTitle = trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog'));
        $siteDescription = trim((string) (($config['site_description'] ?? '') ?: ''));
        $siteLang = trim((string) (($config['site_lang'] ?? '') ?: 'es'));
        $baseUrl = trim((string) ($config['site_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = nammu_base_url();
        }
        $deleteMessages = [];
        if (function_exists('nammu_fediverse_enqueue_delete_local_item')) {
            $fediverseItemId = rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . rawurlencode($newsId);
            $fediverseDelete = nammu_fediverse_enqueue_delete_local_item($fediverseItemId, $config);
            if (!empty($fediverseDelete['message'])) {
                $deleteMessages[] = trim((string) $fediverseDelete['message']);
            }
        }
        if (!function_exists('nammu_actuality_delete_news_item') || !nammu_actuality_delete_news_item($newsId)) {
            $_SESSION['news_feedback'] = ['type' => 'warning', 'message' => 'La noticia ya no existe o no se pudo borrar.'];
            header('Location: admin.php?page=edit&template=news');
            exit;
        }
        if (function_exists('nammu_actuality_remove_news_item_from_snapshots')) {
            nammu_actuality_remove_news_item_from_snapshots($newsId);
        }
        if (function_exists('nammu_fediverse_remove_local_item_from_home_snapshot')) {
            $fediverseItemId = rtrim($baseUrl, '/') . '/ap/objects/actualidad-' . rawurlencode($newsId);
            nammu_fediverse_remove_local_item_from_home_snapshot($fediverseItemId);
        }
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
        $_SESSION['news_feedback'] = ['type' => 'success', 'message' => trim('Noticia borrada. ' . implode(' ', array_filter($deleteMessages)))];
        header('Location: admin.php?page=edit&template=news');
        exit;
    } elseif (isset($_POST['delete_post'])) {
        $filename = $_POST['delete_filename'] ?? '';
        $filename = trim($filename);
        $templateTarget = $_POST['delete_template'] ?? 'single';
        $templateTarget = in_array($templateTarget, ['single', 'page', 'draft', 'newsletter', 'podcast', 'notes', 'news'], true) ? $templateTarget : 'single';
        $templateParam = urlencode($templateTarget);
        if ($filename !== '') {
            // Ensure only filenames from content directory are used
            $basename = basename($filename);
            $filepath = CONTENT_DIR . '/' . $basename;
            if (is_file($filepath)) {
                @unlink($filepath);
                admin_regenerate_public_artifacts();
                header('Location: admin.php?page=edit&template=' . $templateParam . '&deleted=' . urlencode($basename));
                exit;
            }
        }
        header('Location: admin.php?page=edit&template=' . $templateParam . '&deleted=0');
        exit;
    } elseif (isset($_POST['reorder_itinerary'])) {
        $slug = ItineraryRepository::normalizeSlug($_POST['itinerary_slug'] ?? '');
        $direction = $_POST['direction'] ?? '';
        $redirectBase = 'admin.php?page=itinerarios';
        if ($slug === '' || ($direction !== 'up' && $direction !== 'down')) {
            $_SESSION['itinerary_feedback'] = ['type' => 'warning', 'message' => 'No se pudo reordenar el itinerario.'];
            header('Location: ' . $redirectBase);
            exit;
        }
        try {
            $repo = admin_itinerary_repository();
            $list = $repo->all();
            $count = count($list);
            $index = null;
            foreach ($list as $i => $item) {
                if ($item->getSlug() === $slug) {
                    $index = $i;
                    break;
                }
            }
            if ($index === null) {
                $_SESSION['itinerary_feedback'] = ['type' => 'warning', 'message' => 'Itinerario no encontrado para reordenar.'];
                header('Location: ' . $redirectBase);
                exit;
            }
            $swapWith = null;
            if ($direction === 'up' && $index > 0) {
                $swapWith = $index - 1;
            } elseif ($direction === 'down' && $index < $count - 1) {
                $swapWith = $index + 1;
            }
            if ($swapWith === null) {
                header('Location: ' . $redirectBase);
                exit;
            }
            $current = $list[$index];
            $other = $list[$swapWith];
            $orderCurrent = (int) ($current->getMetadata()['Order'] ?? ($index + 1));
            $orderOther = (int) ($other->getMetadata()['Order'] ?? ($swapWith + 1));
            $metaCurrent = $current->getMetadata();
            $metaOther = $other->getMetadata();
            $metaCurrent['Order'] = $orderOther;
            $metaOther['Order'] = $orderCurrent;
            $repo->saveItinerary($current->getSlug(), $metaCurrent, $current->getContent(), $current->getQuiz());
            $repo->saveItinerary($other->getSlug(), $metaOther, $other->getContent(), $other->getQuiz());
            admin_regenerate_public_artifacts();
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Orden actualizado.'];
        } catch (Throwable $e) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo reordenar: ' . $e->getMessage()];
        }
        header('Location: ' . $redirectBase);
        exit;
    } elseif (isset($_POST['upload_asset'])) {
        $filesField = $_FILES['asset_files'] ?? ($_FILES['asset_file'] ?? null);
        $redirectTarget = 'admin.php?page=resources';
        $redirectUrlRaw = trim((string) ($_POST['redirect_url'] ?? ''));
        $redirectPageRaw = trim((string) ($_POST['redirect_page'] ?? ''));
        $redirectAnchorRaw = trim((string) ($_POST['redirect_anchor'] ?? ''));
        $redirectAnchor = '';
        if ($redirectAnchorRaw !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $redirectAnchorRaw)) {
            $redirectAnchor = '#' . $redirectAnchorRaw;
        }
        $pattern = '/^page=[a-z0-9._%\-\/&=]+$/i';
        if ($redirectUrlRaw !== '' && preg_match($pattern, $redirectUrlRaw)) {
            $redirectTarget = 'admin.php?' . $redirectUrlRaw;
        } else {
            $allowedPages = ['resources','publish','edit','edit-post','template','itinerarios','itinerario','configuracion','correo-postal','anuncios'];
            if (in_array($redirectPageRaw, $allowedPages, true)) {
                $redirectTarget = 'admin.php?page=' . $redirectPageRaw;
                if ($redirectPageRaw === 'edit-post') {
                    $redirectFileRaw = trim((string) ($_POST['redirect_file'] ?? ''));
                    $safeRedirectFile = nammu_normalize_filename($redirectFileRaw);
                    if ($safeRedirectFile !== '') {
                        $redirectTarget .= '&file=' . urlencode($safeRedirectFile);
                    }
                }
            }
        }
        if ($redirectAnchor !== '') {
            $redirectTarget .= $redirectAnchor;
        }
        $autosavePayloadRaw = $_POST['autosave_payload'] ?? '';
        $autosaveResult = ['saved' => false, 'filename' => '', 'message' => ''];
        if (is_string($autosavePayloadRaw) && trim($autosavePayloadRaw) !== '') {
            $autosaveResult = admin_autosave_from_payload($autosavePayloadRaw);
            if ($autosaveResult['saved'] && $autosaveResult['filename'] !== '') {
                $redirectTarget = 'admin.php?page=edit-post&file=' . urlencode($autosaveResult['filename']) . $redirectAnchor;
            }
            if ($autosaveResult['message'] !== '') {
                $_SESSION['edit_feedback'] = [
                    'type' => $autosaveResult['saved'] ? 'success' : 'warning',
                    'message' => $autosaveResult['message'],
                ];
            }
        }
        $targetTypeRaw = $_POST['target_type'] ?? '';
        $targetInputRaw = $_POST['target_input'] ?? '';
        $targetEditorRaw = $_POST['target_editor'] ?? '';
        $targetPrefixRaw = $_POST['target_prefix'] ?? '';
        $targetType = in_array($targetTypeRaw, ['field', 'editor'], true) ? $targetTypeRaw : '';
        $targetInput = preg_match('/^[A-Za-z0-9_-]+$/', $targetInputRaw) ? $targetInputRaw : '';
        $targetEditor = '';
        if (is_string($targetEditorRaw) && trim($targetEditorRaw) !== '') {
            $targetEditor = substr($targetEditorRaw, 0, 200);
        }
        $targetPrefix = '';
        if (is_string($targetPrefixRaw)) {
            $targetPrefix = substr($targetPrefixRaw, 0, 100);
        }
        $selectionStartRaw = $_POST['target_selection_start'] ?? '';
        $selectionEndRaw = $_POST['target_selection_end'] ?? '';
        $selectionScrollRaw = $_POST['target_selection_scroll'] ?? '';
        $selection = null;
        if ($selectionStartRaw !== '' && $selectionEndRaw !== '') {
            $selection = [
                'start' => max(0, (int) $selectionStartRaw),
                'end' => max(0, (int) $selectionEndRaw),
                'scrollTop' => max(0, (int) $selectionScrollRaw),
            ];
        }
        $normalizedFiles = [];
        if ($filesField !== null) {
            if (is_array($filesField['name'])) {
                $count = count($filesField['name']);
                for ($i = 0; $i < $count; $i++) {
                    $normalizedFiles[] = [
                        'name' => $filesField['name'][$i] ?? '',
                        'type' => $filesField['type'][$i] ?? '',
                        'tmp_name' => $filesField['tmp_name'][$i] ?? '',
                        'error' => $filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        'size' => $filesField['size'][$i] ?? 0,
                    ];
                }
            } else {
                $normalizedFiles[] = [
                    'name' => $filesField['name'] ?? '',
                    'type' => $filesField['type'] ?? '',
                    'tmp_name' => $filesField['tmp_name'] ?? '',
                    'error' => $filesField['error'] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $filesField['size'] ?? 0,
                ];
            }
        }
        $uploads = [];
        foreach ($normalizedFiles as $file) {
            $name = trim((string) ($file['name'] ?? ''));
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($name === '' && $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $uploads[] = $file;
        }
        $savedAssets = [];
        if (empty($uploads)) {
            $feedback = ['type' => 'warning', 'message' => 'No se seleccionó ningún archivo.'];
        } else {
            $allowedExtensions = nammu_allowed_media_extensions();
            $successCount = 0;
            $errorMessages = [];
            foreach ($uploads as $file) {
                $originalName = $file['name'] ?? 'archivo';
                $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($errorCode === UPLOAD_ERR_INI_SIZE || $errorCode === UPLOAD_ERR_FORM_SIZE) {
                    $errorMessages[] = $originalName . ': supera el tamaño máximo permitido por el servidor (' . nammu_upload_limits_label() . ').';
                    continue;
                }
                if ($errorCode === UPLOAD_ERR_NO_FILE) {
                    $errorMessages[] = $originalName . ': no se seleccionó correctamente en el formulario.';
                    continue;
                }
                if ($errorCode !== UPLOAD_ERR_OK) {
                    $errorMessages[] = $originalName . ': error al subir (código ' . $errorCode . ').';
                    continue;
                }
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions, true)) {
                    $errorMessages[] = $originalName . ': formato no permitido. Usa imágenes o vídeos compatibles (jpg, png, mp4, webm...).';
                    continue;
                }
                $base = nammu_slugify(pathinfo($originalName, PATHINFO_FILENAME));
                if ($base === '') {
                    $base = 'archivo';
                }
                $targetName = $base . '.' . $ext;
                $targetName = nammu_unique_asset_filename($targetName);
                $targetPath = ASSETS_DIR . '/' . $targetName;
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    if (function_exists('nammu_apply_shared_permissions')) {
                        nammu_apply_shared_permissions($targetPath, 0664, dirname($targetPath));
                    } else {
                        @chmod($targetPath, 0664);
                    }
                    $successCount++;
                    nammu_generate_webp_variant_for_asset($targetPath);
                    $savedAssets[] = nammu_admin_media_item_payload($targetName);
                } else {
                    $errorMessages[] = $originalName . ': no se pudo mover el archivo. Revisa los permisos de la carpeta assets/.';
                }
            }
            if ($successCount > 0 && empty($errorMessages)) {
                $feedback = [
                    'type' => 'success',
                    'message' => $successCount === 1 ? 'Archivo subido correctamente.' : $successCount . ' archivos subidos correctamente.',
                ];
            } elseif ($successCount > 0) {
                $feedback = [
                    'type' => 'warning',
                    'message' => ($successCount === 1 ? '1 archivo subido correctamente. ' : $successCount . ' archivos subidos correctamente. ') . 'Errores: ' . implode(' ', $errorMessages),
                ];
            } else {
                $feedback = [
                    'type' => 'danger',
                    'message' => 'No se pudieron subir los archivos. Detalles: ' . implode(' ', $errorMessages),
                ];
            }
        }
        if (!empty($savedAssets) || (is_string($autosavePayloadRaw) && trim($autosavePayloadRaw) !== '')) {
            $_SESSION['asset_apply'] = [
                'mode' => $targetType,
                'input' => $targetInput,
                'editor' => $targetEditor,
                'prefix' => $targetPrefix,
                'anchor' => $redirectAnchor,
                'selection' => $selection,
                'return_to_modal' => in_array($targetType, ['field', 'editor'], true),
                'files' => $savedAssets,
                'restore_payload' => is_string($autosavePayloadRaw) ? $autosavePayloadRaw : '',
            ];
        }
        $_SESSION['asset_feedback'] = $feedback;
        if (nammu_admin_wants_json_response()) {
            nammu_admin_send_json_response([
                'ok' => !empty($savedAssets) && ($feedback['type'] ?? '') !== 'danger',
                'feedback' => $feedback,
                'assets' => $savedAssets,
            ]);
        }
        if (!empty($redirectTarget)) {
            header('Location: ' . $redirectTarget);
        } else {
            $redirectPage = isset($_POST['redirect_p']) ? max(1, (int) $_POST['redirect_p']) : 1;
            $redirectSearch = isset($_POST['redirect_search']) ? trim((string) $_POST['redirect_search']) : '';
            $redirectParams = 'page=resources';
            if ($redirectPage > 1) {
                $redirectParams .= '&p=' . $redirectPage;
            }
            if ($redirectSearch !== '') {
                $redirectParams .= '&search=' . urlencode($redirectSearch);
            }
            header('Location: admin.php?' . $redirectParams);
        }
        exit;
    } elseif (isset($_POST['save_edited_image'])) {
        $image_data = $_POST['image_data'] ?? '';
        $image_name = $_POST['image_name'] ?? '';
        $tagsInput = $_POST['image_tags'] ?? '';
        $redirectPage = isset($_POST['redirect_p']) ? max(1, (int) $_POST['redirect_p']) : 1;
        $redirectSearch = isset($_POST['redirect_search']) ? trim((string) $_POST['redirect_search']) : '';

        if ($image_data && $image_name) {
            $image_name = preg_replace('/[^A-Za-z0-9\._-]/ ', '', basename($image_name));

            list($type, $image_data) = explode(';', $image_data);
            list(, $image_data)      = explode(',', $image_data);
            $image_data = base64_decode($image_data);

            $target_path = ASSETS_DIR . '/' . $image_name;

            if (function_exists('nammu_atomic_write_file')) {
                nammu_atomic_write_file($target_path, $image_data);
                nammu_apply_shared_permissions($target_path, 0664, dirname($target_path));
            } else {
                file_put_contents($target_path, $image_data, LOCK_EX);
                @chmod($target_path, 0664);
            }
            nammu_generate_webp_variant_for_asset($target_path);
            update_media_tags_entry($image_name, parse_media_tags_input($tagsInput));
            $_SESSION['asset_feedback'] = [
                'type' => 'success',
                'message' => 'Imagen guardada y etiquetas actualizadas.',
            ];
        }

        $redirectParams = 'page=resources';
        if ($redirectPage > 1) {
            $redirectParams .= '&p=' . $redirectPage;
        }
        if ($redirectSearch !== '') {
            $redirectParams .= '&search=' . urlencode($redirectSearch);
        }

        header('Location: admin.php?' . $redirectParams);
        exit;
    } elseif (isset($_POST['update_image_tags'])) {
        $targetRelative = $_POST['original_image'] ?? '';
        $normalizedTarget = normalize_media_tag_key($targetRelative);
        $redirectPage = isset($_POST['redirect_p']) ? max(1, (int) $_POST['redirect_p']) : 1;
        $redirectSearch = isset($_POST['redirect_search']) ? trim((string) $_POST['redirect_search']) : '';
        $redirectTarget = 'admin.php?page=resources';
        $redirectUrlRaw = trim((string) ($_POST['redirect_url'] ?? ''));
        $redirectPageRaw = trim((string) ($_POST['redirect_page'] ?? ''));
        $redirectAnchorRaw = trim((string) ($_POST['redirect_anchor'] ?? ''));
        $redirectAnchor = '';
        if ($redirectAnchorRaw !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $redirectAnchorRaw)) {
            $redirectAnchor = '#' . $redirectAnchorRaw;
        }
        $pattern = '/^page=[a-z0-9._%\-\/&=]+$/i';
        if ($redirectUrlRaw !== '' && preg_match($pattern, $redirectUrlRaw)) {
            $redirectTarget = 'admin.php?' . $redirectUrlRaw;
        } else {
            $allowedPages = ['resources','publish','edit','edit-post','template','itinerarios','itinerario','configuracion','correo-postal','anuncios'];
            if (in_array($redirectPageRaw, $allowedPages, true)) {
                $redirectTarget = 'admin.php?page=' . $redirectPageRaw;
                if ($redirectPageRaw === 'edit-post') {
                    $redirectFileRaw = trim((string) ($_POST['redirect_file'] ?? ''));
                    $safeRedirectFile = nammu_normalize_filename($redirectFileRaw);
                    if ($safeRedirectFile !== '') {
                        $redirectTarget .= '&file=' . urlencode($safeRedirectFile);
                    }
                }
            }
        }
        if ($redirectAnchor !== '') {
            $redirectTarget .= $redirectAnchor;
        }
        if ($normalizedTarget !== '') {
            update_media_tags_entry($normalizedTarget, parse_media_tags_input($_POST['image_tags'] ?? ''));
            $_SESSION['asset_feedback'] = [
                'type' => 'success',
                'message' => 'Etiquetas guardadas correctamente.',
            ];
        } else {
            $_SESSION['asset_feedback'] = [
                'type' => 'warning',
                'message' => 'No se pudo actualizar las etiquetas del recurso seleccionado.',
            ];
        }
        if (nammu_admin_wants_json_response()) {
            nammu_admin_send_json_response([
                'ok' => $normalizedTarget !== '',
                'feedback' => $_SESSION['asset_feedback'],
                'asset' => $normalizedTarget !== '' ? nammu_admin_media_item_payload($normalizedTarget) : null,
            ]);
        }
        $returnToModal = isset($_POST['return_to_modal']) && $_POST['return_to_modal'] === '1';
        if ($returnToModal) {
            $selection = [
                'start' => isset($_POST['target_selection_start']) ? (int) $_POST['target_selection_start'] : null,
                'end' => isset($_POST['target_selection_end']) ? (int) $_POST['target_selection_end'] : null,
                'scrollTop' => isset($_POST['target_selection_scroll']) ? (int) $_POST['target_selection_scroll'] : null,
            ];
            $_SESSION['asset_apply'] = [
                'mode' => trim((string) ($_POST['target_type'] ?? '')),
                'input' => trim((string) ($_POST['target_input'] ?? '')),
                'editor' => trim((string) ($_POST['target_editor'] ?? '')),
                'prefix' => trim((string) ($_POST['target_prefix'] ?? '')),
                'anchor' => $redirectAnchor,
                'selection' => $selection,
                'return_to_modal' => true,
                'files' => [],
                'restore_payload' => '',
            ];
            header('Location: ' . $redirectTarget);
            exit;
        }
        $redirectParams = 'page=resources';
        if ($redirectPage > 1) {
            $redirectParams .= '&p=' . $redirectPage;
        }
        if ($redirectSearch !== '') {
            $redirectParams .= '&search=' . urlencode($redirectSearch);
        }
        header('Location: admin.php?' . $redirectParams);
        exit;
    } elseif (isset($_POST['delete_tag_global'])) {
        $tagToDelete = nammu_normalize_tag($_POST['delete_tag_choice'] ?? '');
        $redirectPage = isset($_POST['redirect_p']) ? max(1, (int) $_POST['redirect_p']) : 1;
        $redirectSearch = isset($_POST['redirect_search']) ? trim((string) $_POST['redirect_search']) : '';
        if ($tagToDelete === '') {
            $_SESSION['asset_feedback'] = [
                'type' => 'warning',
                'message' => 'Selecciona una etiqueta para borrar.',
            ];
        } else {
            $map = load_media_tags();
            $changed = false;
            foreach ($map as $key => $tags) {
                if (!is_array($tags)) {
                    continue;
                }
                $filtered = [];
                foreach ($tags as $tag) {
                    $normalized = nammu_normalize_tag((string) $tag);
                    if ($normalized === '' || $normalized === $tagToDelete) {
                        $changed = $changed || $normalized === $tagToDelete;
                        continue;
                    }
                    $filtered[] = $normalized;
                }
                if (empty($filtered)) {
                    unset($map[$key]);
                    $changed = true;
                } elseif (count($filtered) !== count($tags)) {
                    $map[$key] = array_values(array_unique($filtered));
                    $changed = true;
                }
            }
            if ($changed) {
                save_media_tags($map);
                $_SESSION['asset_feedback'] = [
                    'type' => 'success',
                    'message' => 'Etiqueta borrada de todos los recursos.',
                ];
            } else {
                $_SESSION['asset_feedback'] = [
                    'type' => 'warning',
                    'message' => 'La etiqueta no estaba asignada a ningún recurso.',
                ];
            }
        }
        $redirectParams = 'page=resources';
        if ($redirectPage > 1) {
            $redirectParams .= '&p=' . $redirectPage;
        }
        if ($redirectSearch !== '') {
            $redirectParams .= '&search=' . urlencode($redirectSearch);
        }
        header('Location: admin.php?' . $redirectParams);
        exit;
    } elseif (isset($_POST['delete_asset'])) {
        $file_to_delete = $_POST['delete_asset'] ?? ($_POST['file_to_delete'] ?? '');
        $redirectPage = isset($_POST['redirect_p']) ? max(1, (int) $_POST['redirect_p']) : 1;
        $redirectSearch = isset($_POST['redirect_search']) ? trim((string) $_POST['redirect_search']) : '';
        $file_to_delete = ltrim((string) $file_to_delete, '/');
        $feedback = null;
        if ($file_to_delete !== '' && strpos($file_to_delete, '..') === false) {
            $filepath = ASSETS_DIR . '/' . $file_to_delete;
            if (file_exists($filepath)) {
                if (@unlink($filepath)) {
                    delete_media_tags_entry($file_to_delete);
                    $feedback = ['type' => 'success', 'message' => 'Recurso borrado correctamente.'];
                } else {
                    $feedback = ['type' => 'warning', 'message' => 'No se pudo borrar el archivo. Revisa permisos.'];
                }
            } else {
                $feedback = ['type' => 'warning', 'message' => 'El recurso ya no existe.'];
            }
        } else {
            $feedback = ['type' => 'warning', 'message' => 'Recurso no válido para borrar.'];
        }
        $_SESSION['asset_feedback'] = $feedback;
        $redirectParams = 'page=resources';
        if ($redirectPage > 1) {
            $redirectParams .= '&p=' . $redirectPage;
        }
        if ($redirectSearch !== '') {
            $redirectParams .= '&search=' . urlencode($redirectSearch);
        }
        header('Location: admin.php?' . $redirectParams);
        exit;
    } elseif (isset($_POST['recalculate_ordo'])) {
        $all_posts = get_all_posts_metadata();
        
        $single_posts = [];
        $postTemplates = ['single', 'post'];
        foreach ($all_posts as $post) {
            $templateValue = strtolower($post['metadata']['Template'] ?? '');
            if (!in_array($templateValue, $postTemplates, true)) {
                continue;
            }

            $date = $post['metadata']['Date'] ?? '01/01/1970';
            $dt = DateTime::createFromFormat('d/m/Y', $date);
            if ($dt) {
                $timestamp = $dt->getTimestamp();
            } else {
                $timestamp = strtotime($date);
            }
            if ($timestamp === false) {
                $timestamp = 0;
            }
            
            $single_posts[] = [
                'filename' => $post['filename'],
                'timestamp' => $timestamp,
            ];
        }

        usort($single_posts, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        $ordo = 1;
        foreach ($single_posts as $sorted_post) {
            $post_data = get_post_content($sorted_post['filename']);
            if ($post_data) {
                $post_data['metadata']['Ordo'] = $ordo;

                $file_content = "---
";
                foreach ($post_data['metadata'] as $key => $value) {
                    $file_content .= $key . ": " . $value . "
";
                }
                $file_content .= "---

";
                $file_content .= $post_data['content'];

                $sortedPath = CONTENT_DIR . '/' . $sorted_post['filename'];
                if (file_put_contents($sortedPath, $file_content, LOCK_EX) !== false) {
                    admin_chmod_content_file($sortedPath);
                }
                $ordo++;
            }
        }

        header('Location: admin.php?page=anuncios');
        exit;
    } elseif (isset($_POST['test_gsc'])) {
        $gsc_property = trim($_POST['gsc_property'] ?? '');
        $gsc_client_id = trim($_POST['gsc_client_id'] ?? '');
        $gsc_client_secret = trim($_POST['gsc_client_secret'] ?? '');
        $gsc_refresh_token = trim($_POST['gsc_refresh_token'] ?? '');
        try {
            $config = load_config_file();
            if ($gsc_property !== '' || $gsc_client_id !== '' || $gsc_client_secret !== '' || $gsc_refresh_token !== '') {
                $config['search_console'] = [
                    'property' => $gsc_property,
                    'client_id' => $gsc_client_id,
                    'client_secret' => $gsc_client_secret,
                    'refresh_token' => $gsc_refresh_token,
                ];
            } else {
                unset($config['search_console']);
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $_SESSION['search_console_feedback'] = [
                'type' => 'danger',
                'message' => 'Error guardando Search Console: ' . $e->getMessage(),
            ];
            header('Location: admin.php?page=configuracion');
            exit;
        }
        $feedback = [
            'type' => 'danger',
            'message' => 'Faltan datos para conectar con Search Console.',
        ];
        if ($gsc_property !== '' && $gsc_client_id !== '' && $gsc_client_secret !== '' && $gsc_refresh_token !== '') {
            try {
                $tokenData = admin_google_refresh_access_token($gsc_client_id, $gsc_client_secret, $gsc_refresh_token);
                $accessToken = $tokenData['access_token'] ?? '';
                if ($accessToken === '') {
                    throw new RuntimeException('No se pudo obtener un access token válido.');
                }
                $opts = [
                    'http' => [
                        'method' => 'GET',
                        'header' => "Authorization: Bearer {$accessToken}\r\n",
                        'timeout' => 12,
                        'ignore_errors' => true,
                    ],
                ];
                $resp = @file_get_contents('https://www.googleapis.com/webmasters/v3/sites', false, stream_context_create($opts));
                $statusLine = $http_response_header[0] ?? '';
                $statusOk = is_string($statusLine) && preg_match('/\s200\s/', $statusLine);
                $decoded = json_decode((string) $resp, true);
                $propertyFound = false;
                if (is_array($decoded) && !empty($decoded['siteEntry'])) {
                    $normalizedProperty = rtrim($gsc_property, '/') . '/';
                    foreach ($decoded['siteEntry'] as $entry) {
                        $siteUrl = $entry['siteUrl'] ?? '';
                        if ($siteUrl === $gsc_property || $siteUrl === $normalizedProperty) {
                            $propertyFound = true;
                            break;
                        }
                    }
                }
                if ($statusOk && $propertyFound) {
                    $feedback = [
                        'type' => 'success',
                        'message' => 'Conexión correcta con Search Console.',
                    ];
                } elseif ($statusOk) {
                    $feedback = [
                        'type' => 'danger',
                        'message' => 'La cuenta no tiene acceso a la propiedad indicada en Search Console.',
                    ];
                } else {
                    $errorMsg = is_array($decoded) ? ($decoded['error']['message'] ?? '') : '';
                    $feedback = [
                        'type' => 'danger',
                        'message' => $errorMsg !== '' ? $errorMsg : 'No se pudo conectar con Search Console.',
                    ];
                }
            } catch (Throwable $e) {
                $feedback = [
                    'type' => 'danger',
                    'message' => 'Error al conectar con Search Console: ' . $e->getMessage(),
                ];
            }
        }
        $_SESSION['search_console_feedback'] = $feedback;
        header('Location: admin.php?page=configuracion');
        exit;
    } elseif (isset($_POST['test_bing'])) {
        $bing_site_url = trim($_POST['bing_site_url'] ?? '');
        $bing_client_id = trim($_POST['bing_client_id'] ?? '');
        $bing_client_secret = trim($_POST['bing_client_secret'] ?? '');
        $bing_api_key = trim($_POST['bing_api_key'] ?? '');
        try {
            $config = load_config_file();
            if ($bing_site_url !== '' || $bing_client_id !== '' || $bing_client_secret !== '') {
                $currentBing = $config['bing_webmaster'] ?? [];
                $clearTokens = false;
                if (($currentBing['client_id'] ?? '') !== $bing_client_id || ($currentBing['client_secret'] ?? '') !== $bing_client_secret) {
                    $clearTokens = true;
                }
                $config['bing_webmaster'] = array_merge($currentBing, [
                    'site_url' => $bing_site_url,
                    'client_id' => $bing_client_id,
                    'client_secret' => $bing_client_secret,
                    'api_key' => $bing_api_key,
                ]);
                if ($clearTokens) {
                    $config['bing_webmaster']['refresh_token'] = '';
                    $config['bing_webmaster']['access_token'] = '';
                    $config['bing_webmaster']['access_expires_at'] = 0;
                }
            } else {
                unset($config['bing_webmaster']);
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $_SESSION['bing_webmaster_feedback'] = [
                'type' => 'danger',
                'message' => 'Error guardando Bing Webmaster Tools: ' . $e->getMessage(),
            ];
            header('Location: admin.php?page=configuracion');
            exit;
        }
        $feedback = [
            'type' => 'danger',
            'message' => 'Faltan datos para conectar con Bing Webmaster Tools.',
        ];
        if ($bing_site_url !== '' && ($bing_client_id !== '' && $bing_client_secret !== '')) {
            try {
                $token = admin_bing_get_access_token(true);
                if ($token === null) {
                    $feedback = [
                        'type' => 'success',
                        'message' => 'Credenciales guardadas. Pulsa "Conectar con Bing" para autorizar la cuenta.',
                    ];
                } else {
                    $feedback = [
                        'type' => 'success',
                        'message' => 'Conexión OAuth correcta con Bing Webmaster Tools.',
                    ];
                }
            } catch (Throwable $e) {
                $feedback = [
                    'type' => 'danger',
                    'message' => 'Error al conectar con Bing Webmaster Tools: ' . $e->getMessage(),
                ];
            }
        }
        $_SESSION['bing_webmaster_feedback'] = $feedback;
        header('Location: admin.php?page=configuracion');
        exit;
    } elseif (isset($_POST['save_backup_settings'])) {
        $backupDirectory = trim((string) ($_POST['backup_directory'] ?? ''));
        $backupError = null;
        if (admin_update_backup_directory($backupDirectory, $backupError)) {
            $_SESSION['backup_feedback'] = [
                'type' => 'success',
                'message' => 'Directorio de backups actualizado correctamente.',
            ];
        } else {
            $_SESSION['backup_feedback'] = [
                'type' => 'danger',
                'message' => $backupError ?: 'No se pudo actualizar el directorio de backups.',
            ];
        }
        header('Location: admin.php?page=configuracion');
        exit;
    } elseif (isset($_POST['restore_stats_backup'])) {
        $selectedBackup = trim((string) ($_POST['stats_backup_file'] ?? ''));
        $restoreError = null;
        if ($selectedBackup === '') {
            $_SESSION['stats_backup_feedback'] = [
                'type' => 'danger',
                'message' => 'Selecciona un backup para restaurar.',
            ];
        } elseif (admin_restore_stats_backup($selectedBackup, $restoreError)) {
            $_SESSION['stats_backup_feedback'] = [
                'type' => 'success',
                'message' => 'Estadísticas restauradas correctamente desde ' . $selectedBackup . '.',
            ];
        } else {
            $_SESSION['stats_backup_feedback'] = [
                'type' => 'danger',
                'message' => $restoreError ?: 'No se pudieron restaurar las estadísticas.',
            ];
        }
        header('Location: admin.php?page=configuracion');
        exit;
    } elseif (isset($_POST['save_rejected_origins'])) {
        $rawDomains = trim((string) ($_POST['rejected_origin_domains'] ?? ''));
        $domains = [];
        foreach (preg_split('/[\r\n,]+/', $rawDomains) ?: [] as $entry) {
            $entry = strtolower(trim((string) $entry));
            if ($entry === '') {
                continue;
            }
            if (preg_match('#^https?://#i', $entry) !== 1) {
                $entry = 'https://' . $entry;
            }
            $host = strtolower(trim((string) (parse_url($entry, PHP_URL_HOST) ?? '')));
            $host = preg_replace('/^www\./i', '', $host) ?? $host;
            $host = trim($host, ". \t\n\r\0\x0B");
            if ($host === '' || preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host) !== 1) {
                continue;
            }
            $domains[$host] = true;
        }
        try {
            $config = load_config_file();
            if (!empty($domains)) {
                $config['rejected_origins'] = [
                    'domains' => array_keys($domains),
                ];
                sort($config['rejected_origins']['domains'], SORT_NATURAL | SORT_FLAG_CASE);
                $_SESSION['rejected_origins_feedback'] = [
                    'type' => 'success',
                    'message' => 'Orígenes rechazados guardados correctamente.',
                ];
            } else {
                unset($config['rejected_origins']);
                $_SESSION['rejected_origins_feedback'] = [
                    'type' => 'success',
                    'message' => 'Lista de orígenes rechazados vaciada.',
                ];
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $_SESSION['rejected_origins_feedback'] = [
                'type' => 'danger',
                'message' => 'Error guardando orígenes rechazados: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=configuracion#rejected-origins');
        exit;
    } elseif (isset($_POST['save_settings'])) {
        $sort_order = $_POST['sort_order'] ?? 'date';
        $sort_order = $sort_order === 'alpha' ? 'alpha' : 'date';
        $site_author = trim($_POST['site_author'] ?? '');
        $site_name = trim($_POST['site_name'] ?? '');
        $site_url = trim($_POST['site_url'] ?? '');
        $site_lang = trim($_POST['site_lang'] ?? 'es');
        $eupl_notice = isset($_POST['eupl_notice']) ? 'on' : 'off';
        $multi_instance_enabled = isset($_POST['multi_instance_enabled']) ? 'on' : 'off';
        $multi_instance_cluster = trim((string) ($_POST['multi_instance_cluster'] ?? ''));
        $multi_instance_shared_cache_dir = trim((string) ($_POST['multi_instance_shared_cache_dir'] ?? ''));
        $multi_instance_shared_queue_dir = trim((string) ($_POST['multi_instance_shared_queue_dir'] ?? ''));
        $multi_instance_instances_root_dir = trim((string) ($_POST['multi_instance_instances_root_dir'] ?? ''));
        $multi_instance_scheduler_mode = trim((string) ($_POST['multi_instance_scheduler_mode'] ?? 'standalone'));
        $multi_instance_scheduler_strategy = trim((string) ($_POST['multi_instance_scheduler_strategy'] ?? 'fixed'));
        if (!in_array($multi_instance_scheduler_mode, ['standalone', 'central'], true)) {
            $multi_instance_scheduler_mode = 'standalone';
        }
        if (!in_array($multi_instance_scheduler_strategy, ['fixed', 'activity'], true)) {
            $multi_instance_scheduler_strategy = 'fixed';
        }
        $social_default_description = trim($_POST['social_default_description'] ?? '');

        try {
            $config = load_config_file();

            $config['pages_order_by'] = $sort_order;
            $config['pages_order'] = $sort_order === 'date' ? 'desc' : 'asc';

            if ($site_author !== '') {
                $config['site_author'] = $site_author;
            } else {
                unset($config['site_author']);
            }

            if ($site_name !== '') {
                $config['site_name'] = $site_name;
            } else {
                unset($config['site_name']);
            }
            if ($site_url !== '') {
                $config['site_url'] = $site_url;
            } else {
                unset($config['site_url']);
            }
            if ($site_lang !== '') {
                $config['site_lang'] = $site_lang;
            } else {
                unset($config['site_lang']);
            }
            $config['eupl_notice'] = $eupl_notice;
            if (
                $multi_instance_enabled === 'on'
                || $multi_instance_cluster !== ''
                || $multi_instance_shared_cache_dir !== ''
                || $multi_instance_shared_queue_dir !== ''
                || $multi_instance_instances_root_dir !== ''
                || $multi_instance_scheduler_mode !== 'standalone'
                || $multi_instance_scheduler_strategy !== 'fixed'
            ) {
                $config['multi_instance'] = [
                    'enabled' => $multi_instance_enabled,
                    'cluster' => $multi_instance_cluster,
                    'shared_cache_dir' => $multi_instance_shared_cache_dir,
                    'shared_queue_dir' => $multi_instance_shared_queue_dir,
                    'instances_root_dir' => $multi_instance_instances_root_dir,
                    'scheduler_mode' => $multi_instance_scheduler_mode,
                    'scheduler_strategy' => $multi_instance_scheduler_strategy,
                ];
            } else {
                unset($config['multi_instance']);
            }

            $social = $config['social'] ?? [];
            if ($social_default_description !== '') {
                $social['default_description'] = $social_default_description;
            } else {
                unset($social['default_description']);
            }
            if (!empty($social)) {
                $config['social'] = $social;
            } else {
                unset($config['social']);
            }

            save_config_file($config);

        } catch (Throwable $e) {
            $error = "Error guardando la configuración: " . $e->getMessage();
        }

        header('Location: admin.php?page=configuracion');
        exit;
    } elseif (isset($_POST['save_machine_files'])) {
        $llmsContent = trim((string) ($_POST['llms_content'] ?? ''));
        $identityContent = trim((string) ($_POST['identity_content'] ?? ''));

        try {
            $config = load_config_file();

            if ($llmsContent !== '') {
                $config['llms'] = ['content' => $llmsContent];
            } else {
                unset($config['llms']);
            }

            if ($identityContent !== '') {
                $config['identity'] = ['content' => $identityContent];
            } else {
                unset($config['identity']);
            }

            save_config_file($config);
        } catch (Throwable $e) {
            $error = "Error guardando llms.txt e identity.txt: " . $e->getMessage();
        }

        header('Location: admin.php?page=configuracion');
        exit;
    } elseif (isset($_POST['save_contact'])) {
        $contact_telegram = trim($_POST['contact_telegram'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $contact_footer = isset($_POST['contact_footer']) ? 'on' : 'off';
        $contact_signature = isset($_POST['contact_signature']) ? 'on' : 'off';
        $contact_signature_fields = $_POST['contact_signature_fields'] ?? [];
        if (!is_array($contact_signature_fields)) {
            $contact_signature_fields = [];
        }
        $contact_signature_fields = array_values(array_intersect(['telegram', 'email', 'phone'], $contact_signature_fields));
        try {
            $config = load_config_file();
            if ($contact_telegram !== '' || $contact_email !== '' || $contact_phone !== '' || $contact_footer === 'on' || $contact_signature === 'on') {
                $config['contact'] = [
                    'telegram' => $contact_telegram,
                    'email' => $contact_email,
                    'phone' => $contact_phone,
                    'footer' => $contact_footer,
                    'signature' => $contact_signature,
                    'signature_fields' => $contact_signature_fields,
                ];
            } else {
                unset($config['contact']);
            }
            save_config_file($config);
            $_SESSION['contact_feedback'] = [
                'type' => 'success',
                'message' => 'Formas de contacto guardadas correctamente.',
            ];
        } catch (Throwable $e) {
            $_SESSION['contact_feedback'] = [
                'type' => 'danger',
                'message' => "Error guardando las formas de contacto: " . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=configuracion');
        exit;
    } elseif (isset($_POST['save_google_fonts'])) {
        $google_fonts_api = trim($_POST['google_fonts_api'] ?? '');
        try {
            $config = load_config_file();
            if ($google_fonts_api !== '') {
                $config['google_fonts_api'] = $google_fonts_api;
            } else {
                unset($config['google_fonts_api']);
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $error = "Error guardando Google Fonts: " . $e->getMessage();
        }
        header('Location: admin.php?page=configuracion');
        exit;
    } elseif (isset($_POST['save_nisaba'])) {
        $nisaba_raw = trim((string) ($_POST['nisaba_urls'] ?? ''));
        $nisaba_urls = array_values(array_filter(array_map('trim', preg_split('/\\r?\\n/', $nisaba_raw))));
        $nisaba_urls = array_values(array_filter($nisaba_urls, static function (string $url): bool {
            return $url !== '' && preg_match('#^https?://#i', $url);
        }));
        try {
            $config = load_config_file();
            if (!empty($nisaba_urls)) {
                $config['nisaba'] = [
                    'urls' => $nisaba_urls,
                ];
                $_SESSION['nisaba_feedback'] = [
                    'type' => 'success',
                    'message' => 'Configuración de Nisaba guardada correctamente.',
                ];
            } else {
                unset($config['nisaba']);
                $_SESSION['nisaba_feedback'] = [
                    'type' => 'success',
                    'message' => 'Integración con Nisaba desactivada.',
                ];
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $_SESSION['nisaba_feedback'] = [
                'type' => 'danger',
                'message' => 'Error guardando Nisaba: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=configuracion');
        exit;
    } elseif (isset($_POST['save_telex'])) {
        $telex_raw = trim($_POST['telex_urls'] ?? '');
        $telex_urls = array_values(array_filter(array_map('trim', preg_split('/\\r?\\n/', $telex_raw))));
        $telex_urls = array_values(array_filter($telex_urls, static function (string $url): bool {
            return $url !== '' && preg_match('~\\.xml(\\?|#|$)~i', $url);
        }));
        try {
            $config = load_config_file();
            if (!empty($telex_urls)) {
                $config['telex'] = [
                    'urls' => $telex_urls,
                ];
                $_SESSION['telex_feedback'] = [
                    'type' => 'success',
                    'message' => 'Configuración de Telex guardada correctamente.',
                ];
            } else {
                unset($config['telex']);
                $_SESSION['telex_feedback'] = [
                    'type' => 'success',
                    'message' => 'Integración con Telex desactivada.',
                ];
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $_SESSION['telex_feedback'] = [
                'type' => 'danger',
                'message' => 'Error guardando Telex: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=configuracion');
        exit;
    } elseif (isset($_POST['save_social'])) {
        $podcastCategoryOptions = ['Arts', 'Business', 'Comedy', 'Education', 'Fiction', 'Government', 'History', 'Health & Fitness', 'Kids & Family', 'Leisure', 'Music', 'News', 'Religion & Spirituality', 'Science', 'Society & Culture', 'Sports', 'Technology', 'True Crime', 'TV & Film'];
        $social_default_description = trim($_POST['social_default_description'] ?? '');
        $social_home_image = trim($_POST['social_home_image'] ?? '');
        $social_podcast_image = trim($_POST['social_podcast_image'] ?? '');
        $social_podcast_category = trim($_POST['social_podcast_category'] ?? 'Technology');
        if (!in_array($social_podcast_category, $podcastCategoryOptions, true)) {
            $social_podcast_category = 'Technology';
        }
        $social_twitter = trim($_POST['social_twitter'] ?? '');
        if ($social_twitter !== '' && $social_twitter[0] === '@') {
            $social_twitter = substr($social_twitter, 1);
        }
        $social_linkedin = trim($_POST['social_linkedin'] ?? '');
        $social_facebook_app_id = trim($_POST['social_facebook_app_id'] ?? '');
        $telegram_token = trim($_POST['telegram_token'] ?? '');
        $telegram_channel = trim($_POST['telegram_channel'] ?? '');
        $telegram_auto = isset($_POST['telegram_auto']) ? 'on' : 'off';
        $facebook_token = trim($_POST['facebook_token'] ?? '');
        $facebook_app_secret = trim($_POST['facebook_app_secret'] ?? '');
        $facebook_channel = trim($_POST['facebook_channel'] ?? '');
        $facebook_auto = isset($_POST['facebook_auto']) ? 'on' : 'off';
        $twitter_api_key = trim($_POST['twitter_api_key'] ?? '');
        $twitter_api_secret = trim($_POST['twitter_api_secret'] ?? '');
        $twitter_access_token = trim($_POST['twitter_access_token'] ?? '');
        $twitter_access_secret = trim($_POST['twitter_access_secret'] ?? '');
        $twitter_auto = isset($_POST['twitter_auto']) ? 'on' : 'off';
        $linkedin_token = trim($_POST['linkedin_token'] ?? '');
        $linkedin_author = trim($_POST['linkedin_author'] ?? '');
        $linkedin_auto = isset($_POST['linkedin_auto']) ? 'on' : 'off';
        $bluesky_service = trim($_POST['bluesky_service'] ?? '');
        $bluesky_identifier = trim($_POST['bluesky_identifier'] ?? '');
        $bluesky_app_password = trim($_POST['bluesky_app_password'] ?? '');
        $bluesky_auto = isset($_POST['bluesky_auto']) ? 'on' : 'off';
        $instagram_token = trim($_POST['instagram_token'] ?? '');
        $instagram_channel = trim($_POST['instagram_channel'] ?? '');
        $instagram_profile = trim($_POST['instagram_profile'] ?? '');
        $instagram_auto = isset($_POST['instagram_auto']) ? 'on' : 'off';
        $podcast_spotify = trim($_POST['podcast_spotify'] ?? '');
        $podcast_ivoox = trim($_POST['podcast_ivoox'] ?? '');
        $podcast_apple = trim($_POST['podcast_apple'] ?? '');
        $podcast_youtube_music = trim($_POST['podcast_youtube_music'] ?? '');

        try {
            $config = load_config_file();
            $social = [
                'default_description' => $social_default_description,
                'home_image' => $social_home_image,
                'podcast_image' => $social_podcast_image,
                'podcast_category' => $social_podcast_category,
                'twitter' => $social_twitter,
                'linkedin' => $social_linkedin,
                'facebook_app_id' => $social_facebook_app_id,
            ];
            $hasSocial = array_filter($social, function ($value) {
                return $value !== '';
            });
            if (!empty($hasSocial)) {
                $config['social'] = $social;
            } else {
                unset($config['social']);
            }
            if ($telegram_token !== '' || $telegram_channel !== '' || $telegram_auto === 'on') {
                $config['telegram'] = [
                    'token' => $telegram_token,
                    'channel' => $telegram_channel,
                    'auto_post' => $telegram_auto,
                ];
            } else {
                unset($config['telegram']);
            }
            unset($config['whatsapp']);
            if ($facebook_token !== '' || $facebook_channel !== '' || $facebook_app_secret !== '' || $facebook_auto === 'on') {
                $config['facebook'] = [
                    'token' => $facebook_token,
                    'channel' => $facebook_channel,
                    'auto_post' => $facebook_auto,
                    'app_secret' => $facebook_app_secret,
                ];
            } else {
                unset($config['facebook']);
            }
            if ($twitter_api_key !== '' || $twitter_api_secret !== '' || $twitter_access_token !== '' || $twitter_access_secret !== '' || $twitter_auto === 'on') {
                $config['twitter'] = [
                    'api_key' => $twitter_api_key,
                    'api_secret' => $twitter_api_secret,
                    'access_token' => $twitter_access_token,
                    'access_secret' => $twitter_access_secret,
                    'auto_post' => $twitter_auto,
                ];
            } else {
                unset($config['twitter']);
            }
            if ($linkedin_token !== '' || $linkedin_author !== '' || $linkedin_auto === 'on') {
                $config['linkedin'] = [
                    'token' => $linkedin_token,
                    'author' => $linkedin_author,
                    'auto_post' => $linkedin_auto,
                ];
            } else {
                unset($config['linkedin']);
            }
            if ($bluesky_service !== '' || $bluesky_identifier !== '' || $bluesky_app_password !== '' || $bluesky_auto === 'on') {
                $service = $bluesky_service !== '' ? $bluesky_service : 'https://bsky.social';
                $config['bluesky'] = [
                    'service' => $service,
                    'identifier' => $bluesky_identifier,
                    'app_password' => $bluesky_app_password,
                    'auto_post' => $bluesky_auto,
                ];
            } else {
                unset($config['bluesky']);
            }
            if ($instagram_token !== '' || $instagram_channel !== '' || $instagram_profile !== '' || $instagram_auto === 'on') {
                $config['instagram'] = [
                    'token' => $instagram_token,
                    'channel' => $instagram_channel,
                    'profile' => $instagram_profile,
                    'auto_post' => $instagram_auto,
                ];
            } else {
                unset($config['instagram']);
            }
            $podcastServices = [
                'spotify' => $podcast_spotify,
                'ivoox' => $podcast_ivoox,
                'apple' => $podcast_apple,
                'youtube_music' => $podcast_youtube_music,
            ];
            $hasPodcastServices = array_filter($podcastServices, static function ($value) {
                return $value !== '';
            });
            if (!empty($hasPodcastServices)) {
                $config['podcast_services'] = $podcastServices;
            } else {
                unset($config['podcast_services']);
            }

            save_config_file($config);
            $_SESSION['social_feedback'] = [
                'type' => 'success',
                'message' => 'Redes sociales guardadas correctamente.',
            ];
        } catch (Throwable $e) {
            $_SESSION['social_feedback'] = [
                'type' => 'danger',
                'message' => 'Error guardando la configuración: ' . $e->getMessage(),
            ];
        }

        header('Location: admin.php?page=anuncios#facebook');
        exit;
    } elseif (isset($_POST['save_mailing'])) {
        $mailingGmail = trim($_POST['mailing_gmail'] ?? '');
        $mailingClientId = trim($_POST['mailing_client_id'] ?? '');
        $mailingClientSecret = trim($_POST['mailing_client_secret'] ?? '');
        try {
            $config = load_config_file();
            if ($mailingGmail !== '') {
                $config['mailing'] = [
                    'provider' => 'gmail',
                    'gmail_address' => $mailingGmail,
                    'client_id' => $mailingClientId,
                    'client_secret' => $mailingClientSecret,
                    'smtp_host' => 'smtp.gmail.com',
                    'smtp_port' => 465,
                    'auth_method' => 'oauth2',
                    'security' => 'ssl',
                    'status' => 'pending',
                ];
            } else {
                unset($config['mailing']);
                admin_delete_mailing_tokens();
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $error = "Error guardando la configuración de correo: " . $e->getMessage();
        }
        header('Location: admin.php?page=lista-correo#mailing');
        exit;
    } elseif (isset($_POST['add_subscriber'])) {
        $rawEmails = trim((string) ($_POST['subscriber_email'] ?? ''));
        $redirect = 'admin.php?page=lista-correo#suscriptores';
        if ($rawEmails === '') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Introduce al menos un correo válido para suscribir.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        $tokens = preg_split('/[\r\n,]+/', $rawEmails) ?: [];
        $emails = [];
        $invalidCount = 0;
        foreach ($tokens as $token) {
            $email = admin_normalize_email($token);
            if ($email === '') {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidCount++;
                continue;
            }
            $emails[$email] = true;
        }
        if (empty($emails)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se detectaron direcciones de correo válidas.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        try {
            $subscribers = admin_load_mailing_subscriber_entries();
            $suppressed = admin_mailing_suppressed_map();
            $existing = [];
            foreach ($subscribers as $subscriber) {
                $existingEmail = admin_normalize_email((string) ($subscriber['email'] ?? ''));
                if ($existingEmail !== '') {
                    $existing[$existingEmail] = true;
                }
            }
            $addedCount = 0;
            $existsCount = 0;
            $suppressedCount = 0;
            foreach (array_keys($emails) as $email) {
                if (isset($suppressed[$email])) {
                    $suppressedCount++;
                    continue;
                }
                if (isset($existing[$email])) {
                    $existsCount++;
                    continue;
                }
                $subscribers[] = [
                    'email' => $email,
                    'prefs' => admin_mailing_default_prefs(),
                ];
                $existing[$email] = true;
                $addedCount++;
            }
            if ($addedCount > 0) {
                admin_save_mailing_subscriber_entries($subscribers);
            }
            if ($addedCount > 0) {
                $parts = [];
                $parts[] = $addedCount === 1 ? '1 suscriptor añadido.' : $addedCount . ' suscriptores añadidos.';
                if ($existsCount > 0) {
                    $parts[] = $existsCount === 1 ? '1 ya existía.' : $existsCount . ' ya existían.';
                }
                if ($suppressedCount > 0) {
                    $parts[] = $suppressedCount === 1 ? '1 dirección estaba suprimida por rebote duro y no se añadió.' : $suppressedCount . ' direcciones estaban suprimidas por rebote duro y no se añadieron.';
                }
                if ($invalidCount > 0) {
                    $parts[] = $invalidCount === 1 ? '1 correo inválido ignorado.' : $invalidCount . ' correos inválidos ignorados.';
                }
                $_SESSION['mailing_feedback'] = [
                    'type' => 'success',
                    'message' => implode(' ', $parts),
                ];
            } else {
                $parts = ['Todas las direcciones ya estaban en la lista.'];
                if ($suppressedCount > 0) {
                    $parts[] = $suppressedCount === 1 ? '1 dirección estaba suprimida por rebote duro.' : $suppressedCount . ' direcciones estaban suprimidas por rebote duro.';
                }
                if ($invalidCount > 0) {
                    $parts[] = $invalidCount === 1 ? '1 correo inválido ignorado.' : $invalidCount . ' correos inválidos ignorados.';
                }
                $_SESSION['mailing_feedback'] = [
                    'type' => 'info',
                    'message' => implode(' ', $parts),
                ];
            }
        } catch (Throwable $e) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo guardar la lista: ' . $e->getMessage(),
            ];
        }
        header('Location: ' . $redirect);
        exit;
    } elseif (isset($_POST['remove_subscriber'])) {
        $email = admin_normalize_email($_POST['subscriber_email'] ?? '');
        $redirect = 'admin.php?page=lista-correo#suscriptores';
        if ($email === '') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'No se recibió el correo a eliminar.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        try {
            $subscribers = admin_load_mailing_subscriber_entries();
            $filtered = array_values(array_filter($subscribers, static function ($item) use ($email) {
                return admin_normalize_email((string) ($item['email'] ?? '')) !== $email;
            }));
            admin_save_mailing_subscriber_entries($filtered);
            $_SESSION['mailing_feedback'] = [
                'type' => 'success',
                'message' => 'Suscriptor eliminado.',
            ];
        } catch (Throwable $e) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo actualizar la lista: ' . $e->getMessage(),
            ];
        }
        header('Location: ' . $redirect);
        exit;
    } elseif (isset($_POST['save_mailing_flags'])) {
        $autoPosts = isset($_POST['mailing_auto_posts']) ? 'on' : 'off';
        $autoItineraries = isset($_POST['mailing_auto_itineraries']) ? 'on' : 'off';
        $autoPodcast = isset($_POST['mailing_auto_podcast']) ? 'on' : 'off';
        $autoNewsletter = isset($_POST['mailing_auto_newsletter']) ? 'on' : 'off';
        $format = $_POST['mailing_format'] ?? 'html';
        $format = $format === 'text' ? 'text' : 'html';
        try {
            $config = load_config_file();
            if (!isset($config['mailing'])) {
                $config['mailing'] = [];
            }
            $config['mailing']['auto_posts'] = $autoPosts;
            $config['mailing']['auto_itineraries'] = $autoItineraries;
            $config['mailing']['auto_podcast'] = $autoPodcast;
            $config['mailing']['auto_newsletter'] = $autoNewsletter;
            $config['mailing']['format'] = $format;
            save_config_file($config);
            $_SESSION['mailing_feedback'] = [
                'type' => 'success',
                'message' => 'Preferencias de lista guardadas.',
            ];
        } catch (Throwable $e) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudieron guardar las preferencias: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=lista-correo');
        exit;
    } elseif (isset($_POST['save_ads_settings'])) {
        $enabled = isset($_POST['ads_enabled']) ? 'on' : 'off';
        $scope = $_POST['ads_scope'] ?? 'home';
        if (!in_array($scope, ['home', 'all'], true)) {
            $scope = 'home';
        }
        $text = trim((string) ($_POST['ads_text'] ?? ''));
        $image = trim((string) ($_POST['ads_image'] ?? ''));
        $link = trim((string) ($_POST['ads_link'] ?? ''));
        $linkLabel = trim((string) ($_POST['ads_link_label'] ?? ''));
        try {
            $config = load_config_file();
            if (!isset($config['ads'])) {
                $config['ads'] = [];
            }
            $config['ads']['enabled'] = $enabled;
            $config['ads']['scope'] = $scope;
            $config['ads']['text'] = $text;
            $config['ads']['image'] = $image;
            $config['ads']['link'] = $link;
            $config['ads']['link_label'] = $linkLabel;
            save_config_file($config);
            $_SESSION['ads_feedback'] = [
                'type' => 'success',
                'message' => 'Preferencias de anuncios guardadas.',
            ];
        } catch (Throwable $e) {
            $_SESSION['ads_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudieron guardar las preferencias: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=anuncios');
        exit;
    } elseif (isset($_POST['save_push_settings'])) {
        $pushEnabled = isset($_POST['push_enabled']) ? 'on' : 'off';
        $pushPosts = isset($_POST['push_posts']) ? 'on' : 'off';
        $pushItineraries = isset($_POST['push_itineraries']) ? 'on' : 'off';
        try {
            $config = load_config_file();
            if (!isset($config['ads'])) {
                $config['ads'] = [];
            }
            $config['ads']['push_enabled'] = $pushEnabled;
            $config['ads']['push_posts'] = $pushPosts;
            $config['ads']['push_itineraries'] = $pushItineraries;
            save_config_file($config);
            $_SESSION['ads_feedback'] = [
                'type' => 'success',
                'message' => 'Preferencias de notificaciones push guardadas.',
            ];
        } catch (Throwable $e) {
            $_SESSION['ads_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudieron guardar las preferencias: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=anuncios');
        exit;
    } elseif (isset($_POST['save_indexnow_settings'])) {
        $indexnowEnabled = isset($_POST['indexnow_enabled']) ? 'on' : 'off';
        try {
            $config = load_config_file();
            if (!isset($config['indexnow'])) {
                $config['indexnow'] = [];
            }
            $config['indexnow']['enabled'] = $indexnowEnabled;
            if ($indexnowEnabled === 'on') {
                admin_indexnow_prepare_config($config);
            }
            save_config_file($config);
            $_SESSION['ads_feedback'] = [
                'type' => 'success',
                'message' => 'Preferencias de IndexNow guardadas.',
            ];
        } catch (Throwable $e) {
            $_SESSION['ads_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudieron guardar las preferencias de IndexNow: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=anuncios');
        exit;
    } elseif (isset($_POST['save_postal_settings'])) {
        $enabled = isset($_POST['postal_enabled']) ? 'on' : 'off';
        try {
            $config = load_config_file();
            if (!isset($config['postal'])) {
                $config['postal'] = [];
            }
            $config['postal']['enabled'] = $enabled;
            save_config_file($config);
            $_SESSION['postal_feedback'] = [
                'type' => 'success',
                'message' => 'Preferencias de correo postal guardadas.',
            ];
        } catch (Throwable $e) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudieron guardar las preferencias: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=correo-postal');
        exit;
    } elseif (isset($_POST['postal_update'])) {
        $entries = postal_load_entries();
        $email = postal_normalize_email((string) ($_POST['postal_email'] ?? ''));
        $entryId = trim((string) ($_POST['postal_id'] ?? ''));
        $passwordRaw = trim((string) ($_POST['postal_password'] ?? ''));
        $passwordHash = $passwordRaw !== '' ? password_hash($passwordRaw, PASSWORD_DEFAULT) : null;
        try {
            $entries = postal_upsert_entry([
                'email' => $email,
                'id' => $entryId,
                'name' => $_POST['postal_name'] ?? '',
                'address' => $_POST['postal_address'] ?? '',
                'city' => $_POST['postal_city'] ?? '',
                'postal_code' => $_POST['postal_postal_code'] ?? '',
                'region' => $_POST['postal_region'] ?? '',
                'country' => $_POST['postal_country'] ?? '',
            ], $passwordHash, $entries);
            postal_save_entries($entries);
            if ($email !== '') {
                admin_maybe_add_to_mailing_list($email);
            }
            $_SESSION['postal_feedback'] = [
                'type' => 'success',
                'message' => 'Dirección postal actualizada.',
            ];
        } catch (Throwable $e) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo guardar: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=correo-postal');
        exit;
    } elseif (isset($_POST['postal_delete'])) {
        $entries = postal_load_entries();
        $email = postal_normalize_email((string) ($_POST['postal_email'] ?? ''));
        $entryId = trim((string) ($_POST['postal_id'] ?? ''));
        $deleteKey = $email !== '' ? $email : $entryId;
        if ($deleteKey === '') {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'Falta el identificador para borrar.',
            ];
            header('Location: admin.php?page=correo-postal');
            exit;
        }
        $entries = postal_delete_entry($deleteKey, $entries);
        try {
            postal_save_entries($entries);
            $_SESSION['postal_feedback'] = [
                'type' => 'success',
                'message' => 'Dirección eliminada.',
            ];
        } catch (Throwable $e) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo eliminar: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=correo-postal');
        exit;
    } elseif (isset($_POST['download_postal_csv'])) {
        $entries = postal_load_entries();
        $csv = postal_csv_export($entries);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="correos-postales.csv"');
        echo $csv;
        exit;
    } elseif (isset($_POST['download_postal_pdf'])) {
        $entries = postal_load_entries();
        $theme = nammu_template_settings();
        $fontName = $theme['fonts']['body'] ?? 'Helvetica';
        $pdf = postal_build_labels_pdf($entries, $fontName);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="correos-postales.pdf"');
        echo $pdf;
        exit;
    } elseif (isset($_POST['import_postal_csv'])) {
        $file = $_FILES['postal_csv'] ?? null;
        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo leer el archivo CSV.',
            ];
            header('Location: admin.php?page=correo-postal');
            exit;
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'Archivo CSV invalido.',
            ];
            header('Location: admin.php?page=correo-postal');
            exit;
        }
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo abrir el archivo CSV.',
            ];
            header('Location: admin.php?page=correo-postal');
            exit;
        }
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = ',';
        if (is_string($firstLine) && substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }
        $headers = fgetcsv($handle, 0, $delimiter);
        if ($headers === false) {
            fclose($handle);
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'El CSV esta vacio.',
            ];
            header('Location: admin.php?page=correo-postal');
            exit;
        }
        $map = admin_postal_csv_column_map($headers);
        $defaultOrder = ['email', 'name', 'address', 'city', 'postal_code', 'region', 'country'];
        $hasHeader = count($map) >= 2 && isset($map['email']);
        if (!$hasHeader) {
            $map = array_flip($defaultOrder);
            rewind($handle);
        }
        $entries = postal_load_entries();
        $imported = 0;
        $skipped = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $emailIndex = $map['email'] ?? null;
            $data = [
                'email' => '',
                'id' => '',
                'name' => isset($map['name'], $row[$map['name']]) ? $row[$map['name']] : '',
                'address' => isset($map['address'], $row[$map['address']]) ? $row[$map['address']] : '',
                'city' => isset($map['city'], $row[$map['city']]) ? $row[$map['city']] : '',
                'postal_code' => isset($map['postal_code'], $row[$map['postal_code']]) ? $row[$map['postal_code']] : '',
                'region' => isset($map['region'], $row[$map['region']]) ? $row[$map['region']] : '',
                'country' => isset($map['country'], $row[$map['country']]) ? $row[$map['country']] : '',
            ];
            $email = ($emailIndex !== null && isset($row[$emailIndex])) ? postal_normalize_email((string) $row[$emailIndex]) : '';
            $data['email'] = $email;
            if ($email === '') {
                $data['id'] = 'id-' . bin2hex(random_bytes(6));
            }
            try {
                $entries = postal_upsert_entry($data, null, $entries);
                if ($email !== '') {
                    admin_maybe_add_to_mailing_list($email);
                }
                $imported++;
            } catch (Throwable $e) {
                $skipped++;
            }
        }
        fclose($handle);
        try {
            postal_save_entries($entries);
            $_SESSION['postal_feedback'] = [
                'type' => 'success',
                'message' => 'Importacion completada. Nuevos: ' . $imported . '. Omitidos: ' . $skipped . '.',
            ];
        } catch (Throwable $e) {
            $_SESSION['postal_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo guardar la libreta: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=correo-postal');
        exit;
    } elseif (isset($_POST['send_mailing_post'])) {
        $filename = nammu_normalize_filename($_POST['mailing_filename'] ?? '');
        $template = $_POST['mailing_template'] ?? 'single';
        $redirect = 'admin.php?page=edit&template=' . urlencode($template);
        $settings = get_settings();
        if (!admin_is_mailing_ready($settings)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Configura Gmail y conecta con Google antes de enviar a la lista.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        $recipientType = admin_mailing_type_for_template($template);
        $subscribers = admin_mailing_recipients_for_type($recipientType, $settings);
        if (empty($subscribers)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'warning',
                'message' => 'No hay suscriptores en la lista.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        if ($filename === '') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se encontró la publicación a enviar.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        $postData = get_post_content($filename);
        if ($postData === null) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo cargar la publicación seleccionada.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        $metadata = $postData['metadata'] ?? [];
        $title = $metadata['Title'] ?? pathinfo($filename, PATHINFO_FILENAME);
        $description = $metadata['Description'] ?? '';
        $slug = pathinfo($filename, PATHINFO_FILENAME);
        $imagePath = $metadata['Image'] ?? ($metadata['image'] ?? '');
        if ($template === 'podcast') {
            $audioPath = (string) ($metadata['Audio'] ?? '');
            $link = admin_public_asset_url($audioPath);
            if ($link === '') {
                $_SESSION['mailing_feedback'] = [
                    'type' => 'danger',
                    'message' => 'No se encontró el mp3 del podcast para enviar.',
                ];
                header('Location: ' . $redirect);
                exit;
            }
        } else {
            $link = admin_public_post_url($slug);
        }
        $context = $template === 'podcast' ? 'podcast' : 'post';
        $queueResult = admin_schedule_mailing_broadcast($context, $subscribers, [
            'filename' => $filename,
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'image' => $imagePath,
            'audio' => $template === 'podcast' ? $audioPath : '',
            'template' => $template,
        ]);
        if (($queueResult['queued_recipients'] ?? 0) === 0) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo encolar el aviso para la lista.',
            ];
        } else {
            $_SESSION['mailing_feedback'] = [
                'type' => 'success',
                'message' => 'Aviso encolado en ' . ((int) ($queueResult['queued_batches'] ?? 0)) . ' tanda' . (((int) ($queueResult['queued_batches'] ?? 0)) === 1 ? '' : 's') . ' para ' . ((int) ($queueResult['queued_recipients'] ?? 0)) . ' destinatario' . (((int) ($queueResult['queued_recipients'] ?? 0)) === 1 ? '' : 's') . '.',
            ];
        }
        header('Location: ' . $redirect);
        exit;
    } elseif (isset($_POST['update_account'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newUsername = trim($_POST['new_username'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $userData = get_user_data();
        $feedback = null;
        if (!$userData) {
            $feedback = ['type' => 'danger', 'message' => 'No existe un usuario configurado.'];
        } elseif ($currentPassword === '' || !password_verify($currentPassword, $userData['password'])) {
            $feedback = ['type' => 'danger', 'message' => 'La contraseña actual no es correcta.'];
        } elseif ($newUsername === '') {
            $feedback = ['type' => 'danger', 'message' => 'El nombre de usuario no puede estar vacío.'];
        } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
            $feedback = ['type' => 'danger', 'message' => 'Las nuevas contraseñas no coinciden.'];
        } else {
            $passwordHash = $userData['password'];
            if ($newPassword !== '') {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            try {
                write_user_file($newUsername, $passwordHash);
                $feedback = ['type' => 'success', 'message' => 'Los datos de acceso se actualizaron correctamente.'];
            } catch (Throwable $e) {
                $feedback = ['type' => 'danger', 'message' => 'No se pudo actualizar la cuenta. ' . $e->getMessage()];
            }
        }

        $_SESSION['account_feedback'] = $feedback;
        header('Location: admin.php?page=configuracion');
        exit;
} elseif (isset($_POST['save_template'])) {
        $config = load_config_file();
        $defaults = get_default_template_settings();

        $fonts = [
            'title' => trim($_POST['title_font'] ?? ''),
            'body' => trim($_POST['body_font'] ?? ''),
            'note' => trim($_POST['note_font'] ?? ''),
            'code' => trim($_POST['code_font'] ?? ''),
            'quote' => trim($_POST['quote_font'] ?? ''),
        ];

        foreach ($fonts as $key => $value) {
            if ($value === '') {
                $fonts[$key] = $defaults['fonts'][$key];
            }
        }

        $colorKeys = ['h1', 'h2', 'h3', 'intro', 'text', 'background', 'highlight', 'accent', 'brand', 'code_background', 'code_text'];
        $colors = [];
        foreach ($colorKeys as $colorKey) {
            $posted = trim($_POST['color_' . $colorKey] ?? '');
            if ($posted === '') {
                $posted = $defaults['colors'][$colorKey];
            }
            $colors[$colorKey] = $posted;
        }

        $footerMd = trim($_POST['footer_md'] ?? '');
        $footerNammuPosted = isset($_POST['footer_nammu']) ? 'on' : 'off';
        $footerLogoPosted = $_POST['footer_logo_position'] ?? $defaults['footer_logo'];
        if (!in_array($footerLogoPosted, ['none', 'top', 'bottom'], true)) {
            $footerLogoPosted = $defaults['footer_logo'];
        }
        $logoImage = trim($_POST['logo_image'] ?? '');
        $images = ['logo' => $logoImage];
        $homeContentPosted = $_POST['home_content'] ?? ($defaults['home']['content'] ?? 'blog');
        if (!in_array($homeContentPosted, ['blog', 'podcast', 'fediverse', 'itineraries'], true)) {
            $homeContentPosted = $defaults['home']['content'] ?? 'blog';
        }
        $homeColumnsPosted = isset($_POST['home_columns']) ? (int) $_POST['home_columns'] : $defaults['home']['columns'];
        if (!in_array($homeColumnsPosted, [1, 2, 3], true)) {
            $homeColumnsPosted = $defaults['home']['columns'];
        }
        $homeFirstRowEnabled = isset($_POST['home_first_row_enabled']) && $_POST['home_first_row_enabled'] === '1';
        $homeFirstRowColumns = isset($_POST['home_first_row_columns']) ? (int) $_POST['home_first_row_columns'] : ($defaults['home']['first_row_columns'] ?? $homeColumnsPosted);
        if (!in_array($homeFirstRowColumns, [1, 2, 3], true)) {
            $homeFirstRowColumns = $homeColumnsPosted;
        }
        $homeFirstRowFill = $_POST['home_first_row_fill'] ?? ($defaults['home']['first_row_fill'] ?? 'off');
        $homeFirstRowFill = $homeFirstRowFill === 'on' ? 'on' : 'off';
        $homeFirstRowAlign = $_POST['home_first_row_align'] ?? ($defaults['home']['first_row_align'] ?? 'left');
        if (!in_array($homeFirstRowAlign, ['left', 'center'], true)) {
            $homeFirstRowAlign = 'left';
        }
        $homeFirstRowStyle = $_POST['home_first_row_style'] ?? ($defaults['home']['first_row_style'] ?? 'inherit');
        if (!in_array($homeFirstRowStyle, ['inherit', 'boxed', 'flat'], true)) {
            $homeFirstRowStyle = 'inherit';
        }
        $homeAllToggle = isset($_POST['home_per_page_all']) && $_POST['home_per_page_all'] === '1';
        $homePerPageRaw = trim($_POST['home_per_page'] ?? '');
        if ($homeAllToggle || $homePerPageRaw === '') {
            $homePerPageValue = 'all';
        } else {
            $homePerPageInt = (int) $homePerPageRaw;
            if ($homePerPageInt < 1) {
                $homePerPageValue = $defaults['home']['per_page'];
            } else {
                $homePerPageValue = $homePerPageInt;
            }
        }
        $homeCardStylePosted = $_POST['home_card_style'] ?? $defaults['home']['card_style'];
        $homeCardStylePosted = in_array($homeCardStylePosted, ['full', 'square-right', 'square-tall-right', 'circle-right'], true)
            ? $homeCardStylePosted
            : $defaults['home']['card_style'];
        $homeFullImageModePosted = $_POST['home_card_full_mode'] ?? $defaults['home']['full_image_mode'];
        if (!in_array($homeFullImageModePosted, ['natural', 'crop'], true)) {
            $homeFullImageModePosted = $defaults['home']['full_image_mode'];
        }
        $homeBlocksModePosted = $_POST['home_blocks_mode'] ?? $defaults['home']['blocks'];
        if (!in_array($homeBlocksModePosted, ['boxed', 'flat'], true)) {
            $homeBlocksModePosted = $defaults['home']['blocks'];
        }
        $homeHeaderButtonsPosted = $_POST['home_header_buttons'] ?? ($defaults['home']['header_buttons'] ?? 'none');
        if (!in_array($homeHeaderButtonsPosted, ['home', 'both', 'none'], true)) {
            $homeHeaderButtonsPosted = $defaults['home']['header_buttons'] ?? 'none';
        }
        $homeDictionaryIntroPosted = (string) ($_POST['home_dictionary_intro'] ?? '');
        $homeHeaderTypePosted = $_POST['home_header_type'] ?? $defaults['home']['header']['type'];
        $allowedHeaderTypes = ['none', 'graphic', 'text', 'mixed'];
        if (!in_array($homeHeaderTypePosted, $allowedHeaderTypes, true)) {
            $homeHeaderTypePosted = $defaults['home']['header']['type'];
        }
        $homeHeaderImagePosted = trim($_POST['home_header_image'] ?? '');
        $homeHeaderModePosted = $_POST['home_header_graphic_mode'] ?? $defaults['home']['header']['mode'];
        $allowedHeaderModes = ['contain', 'cover'];
        if (!in_array($homeHeaderModePosted, $allowedHeaderModes, true)) {
            $homeHeaderModePosted = $defaults['home']['header']['mode'];
        }
        $homeHeaderTextStylePosted = $_POST['home_header_text_style'] ?? $defaults['home']['header']['text_style'];
        $allowedTextStyles = ['boxed', 'plain'];
        if (!in_array($homeHeaderTextStylePosted, $allowedTextStyles, true)) {
            $homeHeaderTextStylePosted = $defaults['home']['header']['text_style'];
        }
        $homeHeaderOrderPosted = $_POST['home_header_order'] ?? $defaults['home']['header']['order'];
        $allowedOrders = ['image-text', 'text-image'];
        if (!in_array($homeHeaderOrderPosted, $allowedOrders, true)) {
            $homeHeaderOrderPosted = $defaults['home']['header']['order'];
        }
        $headerTypeNeedsImage = in_array($homeHeaderTypePosted, ['graphic', 'mixed'], true);
        if (!$headerTypeNeedsImage) {
            $homeHeaderImagePosted = '';
            $homeHeaderModePosted = $defaults['home']['header']['mode'];
        }
        if ($homeHeaderTypePosted === 'mixed' && $homeHeaderImagePosted === '') {
            $homeHeaderTypePosted = 'text';
        }
        if (!in_array($homeHeaderTypePosted, ['text', 'mixed'], true)) {
            $homeHeaderTextStylePosted = $defaults['home']['header']['text_style'];
            $homeHeaderOrderPosted = $defaults['home']['header']['order'];
        }
        if ($homeCardStylePosted !== 'full') {
            $homeFullImageModePosted = $defaults['home']['full_image_mode'];
        }
        $cornerStylePosted = $_POST['global_corners'] ?? $defaults['global']['corners'];
        if (!in_array($cornerStylePosted, ['rounded', 'square'], true)) {
            $cornerStylePosted = $defaults['global']['corners'];
        }
        $searchDefaults = $defaults['search'] ?? ['mode' => 'single', 'position' => 'footer', 'floating' => 'off', 'fediverse_floating_cta' => 'on'];
        $searchModePosted = $_POST['search_mode'] ?? $searchDefaults['mode'];
        if (!in_array($searchModePosted, ['none', 'home', 'single', 'both'], true)) {
            $searchModePosted = $searchDefaults['mode'];
        }
        $searchPositionPosted = $_POST['search_position'] ?? $searchDefaults['position'];
        if (!in_array($searchPositionPosted, ['title', 'footer'], true)) {
            $searchPositionPosted = $searchDefaults['position'];
        }
        if ($searchModePosted === 'none') {
            $searchPositionPosted = $searchDefaults['position'];
        }
        $searchFloatingPosted = $_POST['search_floating'] ?? ($searchDefaults['floating'] ?? 'off');
        if (!in_array($searchFloatingPosted, ['off', 'on'], true)) {
            $searchFloatingPosted = $searchDefaults['floating'] ?? 'off';
        }
        $searchFediverseFloatingCtaPosted = $_POST['search_fediverse_floating_cta'] ?? ($searchDefaults['fediverse_floating_cta'] ?? 'on');
        if (!in_array($searchFediverseFloatingCtaPosted, ['off', 'on'], true)) {
            $searchFediverseFloatingCtaPosted = $searchDefaults['fediverse_floating_cta'] ?? 'on';
        }
        $subscriptionDefaults = $defaults['subscription'] ?? ['mode' => 'none', 'position' => 'footer', 'floating' => 'off'];
        $subscriptionModePosted = $_POST['subscription_mode'] ?? $subscriptionDefaults['mode'];
        if (!in_array($subscriptionModePosted, ['none', 'home', 'single', 'both'], true)) {
            $subscriptionModePosted = $subscriptionDefaults['mode'];
        }
        $subscriptionPositionPosted = $_POST['subscription_position'] ?? $subscriptionDefaults['position'];
        if (!in_array($subscriptionPositionPosted, ['title', 'footer'], true)) {
            $subscriptionPositionPosted = $subscriptionDefaults['position'];
        }
        if ($subscriptionModePosted === 'none') {
            $subscriptionPositionPosted = $subscriptionDefaults['position'];
        }
        $subscriptionFloatingPosted = $_POST['subscription_floating'] ?? ($subscriptionDefaults['floating'] ?? 'off');
        if (!in_array($subscriptionFloatingPosted, ['off', 'on'], true)) {
            $subscriptionFloatingPosted = $subscriptionDefaults['floating'] ?? 'off';
        }
        $entryTocDefaults = $defaults['entry']['toc'] ?? ['auto' => 'off', 'min_headings' => 3];
        $entryAutoPosted = $_POST['entry_toc_auto'] ?? ($entryTocDefaults['auto'] ?? 'off');
        if (!in_array($entryAutoPosted, ['on', 'off'], true)) {
            $entryAutoPosted = $entryTocDefaults['auto'] ?? 'off';
        }
        $entryMinPosted = (int) ($_POST['entry_toc_min'] ?? ($entryTocDefaults['min_headings'] ?? 3));
        if (!in_array($entryMinPosted, [2, 3, 4], true)) {
            $entryMinPosted = $entryTocDefaults['min_headings'] ?? 3;
        }

        $config['template'] = [
            'fonts' => $fonts,
            'colors' => $colors,
            'images' => $images,
            'footer' => $footerMd,
            'footer_logo' => $footerLogoPosted,
            'footer_nammu' => $footerNammuPosted,
            'global' => [
                'corners' => $cornerStylePosted,
            ],
            'home' => [
                'content' => $homeContentPosted,
                'columns' => $homeColumnsPosted,
                'first_row_enabled' => $homeFirstRowEnabled ? 'on' : 'off',
                'first_row_columns' => $homeFirstRowColumns,
                'first_row_fill' => $homeFirstRowFill,
                'first_row_align' => $homeFirstRowAlign,
                'first_row_style' => $homeFirstRowStyle,
                'per_page' => $homePerPageValue,
                'card_style' => $homeCardStylePosted,
                'full_image_mode' => $homeFullImageModePosted,
                'blocks' => $homeBlocksModePosted,
                'dictionary_intro' => $homeDictionaryIntroPosted,
                'header_buttons' => $homeHeaderButtonsPosted,
                'header' => [
                    'type' => $homeHeaderTypePosted,
                    'image' => $homeHeaderImagePosted,
                    'mode' => $homeHeaderModePosted,
                    'text_style' => $homeHeaderTextStylePosted,
                    'order' => $homeHeaderOrderPosted,
                ],
            ],
            'search' => [
                'mode' => $searchModePosted,
                'position' => $searchPositionPosted,
                'floating' => $searchFloatingPosted,
                'fediverse_floating_cta' => $searchFediverseFloatingCtaPosted,
            ],
            'subscription' => [
                'mode' => $subscriptionModePosted,
                'position' => $subscriptionPositionPosted,
                'floating' => $subscriptionFloatingPosted,
            ],
            'entry' => [
                'toc' => [
                    'auto' => $entryAutoPosted,
                    'min_headings' => $entryMinPosted,
                ],
            ],
        ];

        try {
            save_config_file($config);
            header('Location: admin.php?page=template&saved=1');
        } catch (Throwable $e) {
            $error = "Error guardando la plantilla: " . $e->getMessage();
            header('Location: admin.php?page=template&error=1');
        }
        exit;
    }
}

// Handle Gmail OAuth (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['gmail_auth']) && $_GET['gmail_auth'] === '1') {
        $config = get_settings();
        $mailing = $config['mailing'] ?? [];
        $gmailAddress = $mailing['gmail_address'] ?? '';
        $clientId = $mailing['client_id'] ?? '';
        $clientSecret = $mailing['client_secret'] ?? '';
        if ($gmailAddress === '' || $clientId === '' || $clientSecret === '') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Configura Gmail, Client ID y Client Secret antes de conectar.',
            ];
            header('Location: admin.php?page=configuracion#mailing');
            exit;
        }
        $redirectUri = admin_base_url() . '/admin.php?page=lista-correo&gmail_callback=1';
        $state = bin2hex(random_bytes(16));
        $_SESSION['gmail_oauth_state'] = $state;
        $scope = urlencode('https://mail.google.com/');
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth'
            . '?response_type=code'
            . '&client_id=' . urlencode($clientId)
            . '&redirect_uri=' . urlencode($redirectUri)
            . '&scope=' . $scope
            . '&access_type=offline'
            . '&prompt=consent'
            . '&state=' . urlencode($state)
            . '&login_hint=' . urlencode($gmailAddress);
        header('Location: ' . $authUrl);
        exit;
    } elseif (isset($_GET['gmail_callback']) && $_GET['gmail_callback'] === '1') {
        $expectedState = $_SESSION['gmail_oauth_state'] ?? '';
        $receivedState = $_GET['state'] ?? '';
        unset($_SESSION['gmail_oauth_state']);
        if ($expectedState === '' || $receivedState !== $expectedState) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Estado de OAuth inválido o caducado. Vuelve a iniciar la conexión.',
            ];
            header('Location: admin.php?page=lista-correo');
            exit;
        }
        if (isset($_GET['error'])) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Google canceló la conexión: ' . htmlspecialchars((string) $_GET['error']),
            ];
            header('Location: admin.php?page=lista-correo');
            exit;
        }
        $code = $_GET['code'] ?? '';
        if ($code === '') {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se recibió el código de Google.',
            ];
            header('Location: admin.php?page=lista-correo');
            exit;
        }
        $configRaw = load_config_file();
        $mailing = $configRaw['mailing'] ?? [];
        $clientId = $mailing['client_id'] ?? '';
        $clientSecret = $mailing['client_secret'] ?? '';
        $redirectUri = admin_base_url() . '/admin.php?page=lista-correo&gmail_callback=1';
        try {
            $tokens = admin_google_exchange_code($code, $clientId, $clientSecret, $redirectUri);
            admin_save_mailing_tokens($tokens);
            $configRaw['mailing']['status'] = 'connected';
            save_config_file($configRaw);
            $_SESSION['mailing_feedback'] = [
                'type' => 'success',
                'message' => 'Cuenta conectada con Google. Tokens guardados.',
            ];
        } catch (Throwable $e) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo completar la conexión: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=lista-correo');
        exit;
    } elseif (isset($_GET['gmail_disconnect']) && $_GET['gmail_disconnect'] === '1') {
        try {
            admin_delete_mailing_tokens();
            $config = load_config_file();
            if (isset($config['mailing'])) {
                $config['mailing']['status'] = 'pending';
                save_config_file($config);
            }
            $_SESSION['mailing_feedback'] = [
                'type' => 'success',
                'message' => 'Desconectado de Google. Se revocarán los envíos hasta volver a conectar.',
            ];
        } catch (Throwable $e) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo desconectar: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=lista-correo');
        exit;
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
    $refreshFediverseAvatarSnapshots = static function (array $config): void {
        if (!function_exists('nammu_actuality_rebuild_snapshot') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
            require_once NAMMU_ROOT . '/core/actualidad.php';
        }
        $baseUrl = trim((string) (($config['site_url'] ?? '') ?: nammu_base_url()));
        $siteTitle = trim((string) (($config['site_name'] ?? '') ?: 'Nammu Blog'));
        $siteDescription = trim((string) (($config['site_description'] ?? '') ?: ''));
        $siteLang = trim((string) (($config['site_lang'] ?? '') ?: 'es'));
        if (function_exists('nammu_actuality_rebuild_snapshot')) {
            nammu_actuality_rebuild_snapshot($baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
        }
        if (function_exists('nammu_fediverse_rebuild_light_snapshots')) {
            nammu_fediverse_rebuild_light_snapshots($config);
        }
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
    };
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['follow_fediverse_actor'])) {
        $fediverseActorInput = trim((string) ($_POST['fediverse_actor_input'] ?? ''));
        $followResult = nammu_fediverse_follow_actor($fediverseActorInput);
        $fediverseFeedback = [
            'type' => !empty($followResult['ok']) ? 'success' : 'danger',
            'message' => (string) ($followResult['message'] ?? ''),
        ];
        $fediverseRedirect = true;
        $fediverseRedirectState = ['actor_input' => empty($followResult['ok']) ? $fediverseActorInput : ''];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restart_fediverse_actor'])) {
        $actorId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $restartResult = nammu_fediverse_restart_follow_actor($actorId);
        $fediverseFeedback = [
            'type' => !empty($restartResult['ok']) ? 'success' : 'danger',
            'message' => (string) ($restartResult['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recache_fediverse_actor_avatar'])) {
        $actorId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $config = load_config_file();
        $recacheResult = function_exists('nammu_fediverse_enqueue_actor_avatar_recache')
            ? nammu_fediverse_enqueue_actor_avatar_recache($actorId, $config, true)
            : nammu_fediverse_recache_actor_avatar($actorId, $config);
        $fediverseFeedback = [
            'type' => !empty($recacheResult['ok']) ? 'success' : 'danger',
            'message' => (string) ($recacheResult['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recache_all_fediverse_actor_avatars'])) {
        $config = load_config_file();
        $recacheResult = function_exists('nammu_fediverse_enqueue_all_actor_avatar_recaches')
            ? nammu_fediverse_enqueue_all_actor_avatar_recaches($config)
            : nammu_fediverse_recache_all_actor_avatars($config);
        $fediverseFeedback = [
            'type' => !empty($recacheResult['ok']) ? 'success' : 'danger',
            'message' => (string) ($recacheResult['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unfollow_fediverse_actor'])) {
        $actorId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $ok = $actorId !== '' && nammu_fediverse_unfollow_actor($actorId);
        $fediverseFeedback = [
            'type' => $ok ? 'success' : 'danger',
            'message' => $ok ? 'Actor eliminado del Fediverso.' : 'No se pudo quitar ese actor.',
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_fediverse_follower'])) {
        $actorId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $config = load_config_file();
        $result = nammu_fediverse_block_actor($actorId, $config);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unblock_fediverse_actor'])) {
        $actorId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $result = nammu_fediverse_unblock_actor($actorId);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_fediverse_timeline'])) {
        $config = load_config_file();
        $stats = admin_run_scheduled_tasks();
        if (function_exists('nammu_fediverse_retry_pending_follower_accepts')) {
            $acceptStats = nammu_fediverse_retry_pending_follower_accepts($config);
            $stats['follow_accepts_checked'] = (int) ($acceptStats['checked'] ?? 0);
            $stats['follow_accepts_sent'] = (int) ($acceptStats['accepted'] ?? 0);
        }
        $fediverseFeedback = [
            'type' => 'info',
            'message' => 'Fediverso refrescado. Actores revisados: ' . (int) ($stats['fediverse_checked'] ?? 0) . '. Actividades nuevas: ' . (int) (($stats['fediverse_new'] ?? 0) + ($stats['fediverse_inbox_sync_new'] ?? 0)) . '. Hilos recientes actualizados: ' . (int) ($stats['fediverse_recent_threads_warmed'] ?? 0) . '. Seguidores revisados: ' . (int) ($stats['fediverse_followers_checked'] ?? 0) . '. Seguidores eliminados: ' . (int) ($stats['fediverse_followers_removed'] ?? 0) . '. Accept enviados: ' . (int) ($stats['follow_accepts_sent'] ?? 0) . '.',
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_fediverse_threads'])) {
        $config = load_config_file();
        $stats = admin_refresh_fediverse_threads($config, 20);
        $fediverseFeedback = [
            'type' => 'info',
            'message' => 'Hilos del Fediverso actualizados. Hilos precalentados: ' . (int) ($stats['threads_warmed'] ?? 0) . '. Accept enviados: ' . (int) ($stats['follow_accepts_sent'] ?? 0) . '.',
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rebuild_fediverse_timeline'])) {
        $config = load_config_file();
        $stats = admin_rebuild_fediverse_timeline($config);
        $fediverseFeedback = [
            'type' => 'info',
            'message' => 'Timeline del Fediverso reconstruido, incluyendo la parte local. Actores revisados: ' . (int) ($stats['checked'] ?? 0) . '. Actividades importadas: ' . (int) (($stats['new'] ?? 0) + ($stats['fediverse_inbox_sync_new'] ?? 0)) . '. Hilos precalentados: ' . (int) ($stats['threads_warmed'] ?? 0) . '. Accept enviados: ' . (int) ($stats['follow_accepts_sent'] ?? 0) . '.',
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inspect_fediverse_object'])) {
        $fediverseInspectUrl = trim((string) ($_POST['fediverse_inspect_url'] ?? ''));
        $config = load_config_file();
        $fediverseInspectResult = nammu_fediverse_inspect_object($fediverseInspectUrl, $config);
        $fediverseFeedback = [
            'type' => !empty($fediverseInspectResult['ok']) ? 'info' : 'danger',
            'message' => !empty($fediverseInspectResult['ok']) ? 'Inspección ActivityPub completada.' : (string) ($fediverseInspectResult['message'] ?? ''),
        ];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_fediverse_message'])) {
        $recipientId = trim((string) ($_POST['fediverse_message_recipient'] ?? ''));
        $messageText = trim((string) ($_POST['fediverse_message_text'] ?? ''));
        $fediverseMessageRecipient = $recipientId;
        $fediverseMessageText = $messageText;
        $config = load_config_file();
        $result = nammu_fediverse_send_private_message($recipientId, $messageText, $config);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
        $fediverseRedirectState = [
            'message_recipient' => $fediverseMessageRecipient,
            'message_text' => !empty($result['ok']) ? '' : $messageText,
        ];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_fediverse_private_reply'])) {
        $recipientId = trim((string) ($_POST['fediverse_message_actor_id'] ?? ''));
        $messageText = trim((string) ($_POST['fediverse_private_reply_text'] ?? ''));
        $replyToMessageId = trim((string) ($_POST['fediverse_reply_to_message_id'] ?? ''));
        $fediverseMessageRecipient = $recipientId;
        $fediverseMessageText = '';
        $config = load_config_file();
        $result = nammu_fediverse_send_private_message($recipientId, $messageText, $config, $replyToMessageId);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
        $fediverseRedirectState = [
            'message_recipient' => $fediverseMessageRecipient,
            'message_text' => !empty($result['ok']) ? '' : $messageText,
        ];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_like_item'])) {
        $recipientId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $objectUrl = trim((string) ($_POST['fediverse_object_url'] ?? ''));
        $config = load_config_file();
        $result = nammu_fediverse_send_like($recipientId, $objectUrl, $config);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_unlike_item'])) {
        $item = [
            'object_id' => trim((string) ($_POST['fediverse_object_url'] ?? '')),
            'url' => trim((string) ($_POST['fediverse_public_url'] ?? '')),
            'id' => trim((string) ($_POST['fediverse_item_id'] ?? '')),
        ];
        $config = load_config_file();
        $result = nammu_fediverse_send_undo_like_for_item($item, $config);
        if (!empty($result['ok']) && function_exists('nammu_fediverse_rebuild_snapshots')) {
            nammu_fediverse_rebuild_snapshots($config);
        }
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_boost_item'])) {
        $recipientId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $objectUrl = trim((string) ($_POST['fediverse_object_url'] ?? ''));
        $publicUrl = trim((string) ($_POST['fediverse_public_url'] ?? ''));
        $objectTitle = trim((string) ($_POST['fediverse_object_title'] ?? ''));
        $objectContent = trim((string) ($_POST['fediverse_object_content'] ?? ''));
        $objectImage = trim((string) ($_POST['fediverse_object_image'] ?? ''));
        $objectActorName = trim((string) ($_POST['fediverse_actor_name'] ?? ''));
        $objectActorIcon = trim((string) ($_POST['fediverse_actor_icon'] ?? ''));
        $objectActorUrl = trim((string) ($_POST['fediverse_actor_url'] ?? ''));
        $objectImages = json_decode((string) ($_POST['fediverse_object_images'] ?? '[]'), true);
        $objectImages = array_values(array_unique(array_filter(array_map('strval', is_array($objectImages) ? $objectImages : []))));
        $objectAttachments = json_decode((string) ($_POST['fediverse_object_attachments'] ?? '[]'), true);
        $objectAttachments = array_values(array_filter(array_map(static function ($attachment): ?array {
            if (!is_array($attachment)) {
                return null;
            }
            $url = trim((string) ($attachment['url'] ?? ''));
            if ($url === '') {
                return null;
            }
            return [
                'type' => strtolower(trim((string) ($attachment['type'] ?? 'document'))),
                'url' => $url,
                'name' => trim((string) ($attachment['name'] ?? '')),
                'media_type' => trim((string) ($attachment['media_type'] ?? ($attachment['mediaType'] ?? ''))),
                'image' => trim((string) ($attachment['image'] ?? '')),
                'summary' => trim((string) ($attachment['summary'] ?? '')),
            ];
        }, is_array($objectAttachments) ? $objectAttachments : [])));
        if ($objectImage !== '' && !in_array($objectImage, $objectImages, true)) {
            array_unshift($objectImages, $objectImage);
        }
        $resolvedObjectType = '';
        $config = load_config_file();
        if (!function_exists('nammu_fediverse_signed_fetch_json') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
            require_once NAMMU_ROOT . '/core/fediverso.php';
        }
        if (function_exists('nammu_fediverse_signed_fetch_json') && function_exists('nammu_fediverse_resolve_actor')) {
            $resolvedObject = nammu_fediverse_signed_fetch_json($objectUrl, $config);
            if (!is_array($resolvedObject)) {
                $resolvedObject = nammu_fediverse_fetch_json($objectUrl);
            }
            if (is_array($resolvedObject)) {
                $resolvedObjectType = strtolower(trim((string) ($resolvedObject['type'] ?? '')));
                $resolvedActorId = trim((string) (($resolvedObject['attributedTo'] ?? '') ?: ($resolvedObject['actor'] ?? '')));
                if ($resolvedActorId !== '') {
                    $resolvedActor = nammu_fediverse_resolve_actor($resolvedActorId, $config);
                    if (is_array($resolvedActor)) {
                        $resolvedActorName = trim((string) (($resolvedActor['name'] ?? '') ?: ($resolvedActor['preferredUsername'] ?? '') ?: ''));
                        $resolvedActorIcon = trim((string) ($resolvedActor['icon'] ?? ''));
                        $resolvedActorUrl = trim((string) (($resolvedActor['url'] ?? '') ?: ($resolvedActor['id'] ?? '')));
                        if ($resolvedActorName !== '') {
                            $objectActorName = $resolvedActorName;
                        }
                        if ($resolvedActorIcon !== '') {
                            $objectActorIcon = $resolvedActorIcon;
                        }
                        if ($resolvedActorUrl !== '') {
                            $objectActorUrl = $resolvedActorUrl;
                        }
                    }
                }
            }
        }
        if ($resolvedObjectType === 'note') {
            $objectTitle = '';
        }
        $result = nammu_fediverse_send_announce($recipientId, $objectUrl, $config);
        if (!empty($result['ok'])) {
            if (!function_exists('admin_send_social_broadcast_to_configured_networks') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
                require_once NAMMU_ROOT . '/core/admin-redes.php';
            }
            if (!function_exists('nammu_actuality_add_manual_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
                require_once NAMMU_ROOT . '/core/actualidad.php';
            }
            $baseUrl = rtrim((string) (($config['site_url'] ?? '') ?: nammu_base_url()), '/');
            $siteTitle = trim((string) (($config['site_name'] ?? '') ?: ''));
            $siteDescription = trim((string) ($config['site_description'] ?? ''));
            $siteLang = trim((string) ($config['site_lang'] ?? 'es'));
            $noteParts = [];
            if ($objectTitle !== '') {
                $noteParts[] = $objectTitle;
            }
            if ($objectContent !== '' && $objectContent !== $objectTitle) {
                $noteParts[] = $objectContent;
            }
            $noteText = trim(implode("\n\n", $noteParts));
            $displayUrl = function_exists('nammu_fediverse_canonical_public_object_url')
                ? nammu_fediverse_canonical_public_object_url($objectUrl, $publicUrl, $config)
                : ($publicUrl !== '' ? $publicUrl : $objectUrl);
            $originalPublicUrl = trim((string) ($displayUrl ?: ($publicUrl ?: $objectUrl)));
            if ($displayUrl !== '' && !str_contains($noteText, $displayUrl)) {
                $noteText = trim($noteText . "\n\n" . $displayUrl);
            }
            if ($noteText !== '' && function_exists('nammu_actuality_add_manual_item')) {
                $manualItem = nammu_actuality_add_manual_item($noteText, $baseUrl, $siteTitle, $objectImage, [
                    'via' => 'boost',
                    'images' => $objectImages,
                    'attachments' => $objectAttachments,
                    'boost_original_url' => $originalPublicUrl,
                    'boost_actor_name' => $objectActorName,
                    'boost_actor_icon' => $objectActorIcon,
                    'boost_actor_url' => $objectActorUrl,
                ]);
                if (function_exists('nammu_actuality_add_item_to_snapshots')) {
                    nammu_actuality_add_item_to_snapshots($manualItem);
                }
                nammu_fediverse_record_action('share', '', $objectUrl, [
                    'share_text' => $objectContent,
                    'title' => $objectTitle,
                    'via' => 'boost',
                    'image' => $objectImage,
                    'images' => $objectImages,
                    'attachments' => $objectAttachments,
                    'manual_item_id' => (string) ($manualItem['id'] ?? ''),
                    'public_url' => $originalPublicUrl,
                    'boost_actor_name' => $objectActorName,
                    'boost_actor_icon' => $objectActorIcon,
                    'boost_actor_url' => $objectActorUrl,
                ]);
                $result['message'] = rtrim((string) ($result['message'] ?? '')) . ' También publicada como nota.';
                if (function_exists('admin_enqueue_social_broadcast')) {
                    $socialTextParts = [];
                    if ($objectTitle !== '') {
                        $socialTextParts[] = '**' . $objectTitle . '**';
                    }
                    if ($objectContent !== '' && $objectContent !== $objectTitle) {
                        $socialTextParts[] = $objectContent;
                    }
                    if ($originalPublicUrl !== '') {
                        $socialTextParts[] = 'Via: ' . $originalPublicUrl;
                    }
                    $socialText = trim(implode("\n\n", array_filter($socialTextParts, static fn(string $part): bool => trim($part) !== '')));
                    $allConfiguredNetworks = function_exists('admin_social_broadcast_available_networks')
                        ? array_keys(admin_social_broadcast_available_networks(get_settings()))
                        : [];
                    // For boosts, external socials should point only to the original publication URL.
                    $queueResult = admin_enqueue_social_broadcast($socialText !== '' ? $socialText : $noteText, $objectImages, $allConfiguredNetworks, '');
                    if (!empty($queueResult['ok'])) {
                        $result['message'] .= ' Encolada para redes.';
                    }
                }
            }
        }
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_unboost_item'])) {
        $item = [
            'object_id' => trim((string) ($_POST['fediverse_object_url'] ?? '')),
            'url' => trim((string) ($_POST['fediverse_public_url'] ?? '')),
            'id' => trim((string) ($_POST['fediverse_item_id'] ?? '')),
        ];
        $config = load_config_file();
        $result = nammu_fediverse_send_undo_announce_for_item($item, $config);
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_reply_item'])) {
        $recipientId = trim((string) ($_POST['fediverse_actor_id'] ?? ''));
        $objectUrl = trim((string) ($_POST['fediverse_object_url'] ?? ''));
        $replyText = trim((string) ($_POST['fediverse_reply_text'] ?? ''));
        $replyAlsoAsNote = !empty($_POST['fediverse_reply_as_note']);
        $replyObjectImage = trim((string) ($_POST['fediverse_object_image'] ?? ''));
        $replyObjectImages = json_decode((string) ($_POST['fediverse_object_images'] ?? '[]'), true);
        $replyObjectImages = array_values(array_unique(array_filter(array_map('strval', is_array($replyObjectImages) ? $replyObjectImages : []))));
        $replyObjectAttachments = json_decode((string) ($_POST['fediverse_object_attachments'] ?? '[]'), true);
        $replyObjectAttachments = array_values(array_filter(array_map(static function ($attachment): ?array {
            if (!is_array($attachment)) {
                return null;
            }
            $url = trim((string) ($attachment['url'] ?? ''));
            if ($url === '') {
                return null;
            }
            return [
                'type' => strtolower(trim((string) ($attachment['type'] ?? 'document'))),
                'url' => $url,
                'name' => trim((string) ($attachment['name'] ?? '')),
                'media_type' => trim((string) ($attachment['media_type'] ?? ($attachment['mediaType'] ?? ''))),
                'image' => trim((string) ($attachment['image'] ?? '')),
                'summary' => trim((string) ($attachment['summary'] ?? '')),
            ];
        }, is_array($replyObjectAttachments) ? $replyObjectAttachments : [])));
        if ($replyObjectImage !== '' && !in_array($replyObjectImage, $replyObjectImages, true)) {
            array_unshift($replyObjectImages, $replyObjectImage);
        }
        $config = load_config_file();
        if ($recipientId === '') {
            $result = nammu_fediverse_send_local_reply($objectUrl, $replyText, $config);
        } else {
            $result = nammu_fediverse_send_reply($recipientId, $objectUrl, $replyText, $config);
        }
        if (!empty($result['ok']) && $replyAlsoAsNote) {
            if (!function_exists('nammu_actuality_add_manual_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
                require_once NAMMU_ROOT . '/core/actualidad.php';
            }
            $baseUrl = rtrim((string) (($config['site_url'] ?? '') ?: nammu_base_url()), '/');
            $siteTitle = trim((string) (($config['site_name'] ?? '') ?: ''));
            $siteDescription = trim((string) ($config['site_description'] ?? ''));
            $siteLang = trim((string) ($config['site_lang'] ?? 'es'));
            $noteText = $replyText;
            if ($objectUrl !== '' && !str_contains($noteText, $objectUrl)) {
                $noteText = trim($noteText . "\n\n" . $objectUrl);
            }
            if (function_exists('nammu_actuality_add_manual_item')) {
                $manualItem = nammu_actuality_add_manual_item($noteText, $baseUrl, $siteTitle, $replyObjectImage, [
                    'via' => 'reply',
                    'images' => $replyObjectImages,
                    'attachments' => $replyObjectAttachments,
                    'reply_target_url' => (string) (($result['object_url'] ?? '') ?: $objectUrl),
                    'reply_note_id' => (string) ($result['note_id'] ?? ''),
                    'reply_activity_id' => (string) ($result['activity_id'] ?? ''),
                ]);
                if (function_exists('nammu_actuality_rebuild_snapshot')) {
                    nammu_actuality_rebuild_snapshot($baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
                }
                if (!function_exists('admin_enqueue_social_broadcast') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
                    require_once NAMMU_ROOT . '/core/admin-redes.php';
                }
                if (is_array($manualItem) && function_exists('admin_enqueue_social_broadcast') && function_exists('admin_social_broadcast_available_networks')) {
                    $allConfiguredNetworks = array_keys(admin_social_broadcast_available_networks(get_settings()));
                    if (!empty($allConfiguredNetworks)) {
                        $fediverseUrl = function_exists('admin_social_broadcast_fediverse_url_for_actuality_item')
                            ? admin_social_broadcast_fediverse_url_for_actuality_item($manualItem)
                            : '';
                        admin_enqueue_social_broadcast($noteText, $replyObjectImages, $allConfiguredNetworks, $fediverseUrl);
                    }
                }
                $deliveryStats = nammu_fediverse_deliver_local_items($config);
                $result['message'] = rtrim((string) ($result['message'] ?? '')) . ' También publicada como nota. Entregas federadas: ' . (int) ($deliveryStats['delivered'] ?? 0) . '.';
            }
        }
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_delete_local_item'])) {
        $itemId = trim((string) ($_POST['fediverse_local_item_id'] ?? ''));
        $config = load_config_file();
        $result = nammu_fediverse_delete_local_item($itemId, $config);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_delete_reply_item'])) {
        $replyActionId = trim((string) ($_POST['fediverse_reply_action_id'] ?? ''));
        $config = load_config_file();
        $result = nammu_fediverse_delete_public_reply($replyActionId, $config);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_hide_incoming_reply'])) {
        $config = load_config_file();
        $reply = [
            'id' => trim((string) ($_POST['fediverse_incoming_reply_id'] ?? '')),
            'url' => trim((string) ($_POST['fediverse_incoming_reply_url'] ?? '')),
            'target_url' => trim((string) ($_POST['fediverse_incoming_reply_target'] ?? '')),
            'published' => trim((string) ($_POST['fediverse_incoming_reply_published'] ?? '')),
            'reply_text' => trim((string) ($_POST['fediverse_incoming_reply_text'] ?? '')),
            'actor_id' => trim((string) ($_POST['fediverse_incoming_reply_actor'] ?? '')),
        ];
        $result = nammu_fediverse_hide_incoming_reply($reply, $config);
        $fediverseFeedback = [
            'type' => !empty($result['ok']) ? 'success' : 'danger',
            'message' => (string) ($result['message'] ?? ''),
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_webmention'])) {
        $signature = trim((string) ($_POST['webmention_signature'] ?? ''));
        $deleted = function_exists('nammu_webmention_delete') ? nammu_webmention_delete($signature) : false;
        if (function_exists('nammu_fediverse_save_fragments_cache_store')) {
            nammu_fediverse_save_fragments_cache_store([]);
        }
        $fediverseFeedback = [
            'type' => $deleted ? 'success' : 'danger',
            'message' => $deleted ? 'Mención eliminada.' : 'No se pudo eliminar la mención.',
        ];
        $fediverseRedirect = true;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fediverse_share_note'])) {
        if (!function_exists('nammu_actuality_add_manual_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
            require_once NAMMU_ROOT . '/core/actualidad.php';
        }
        $objectUrl = trim((string) ($_POST['fediverse_object_url'] ?? ''));
        $shareText = trim((string) ($_POST['fediverse_share_text'] ?? ''));
        $shareTitle = trim((string) ($_POST['fediverse_object_title'] ?? ''));
        $shareObjectImage = trim((string) ($_POST['fediverse_object_image'] ?? ''));
        $shareObjectImages = json_decode((string) ($_POST['fediverse_object_images'] ?? '[]'), true);
        $shareObjectImages = array_values(array_unique(array_filter(array_map('strval', is_array($shareObjectImages) ? $shareObjectImages : []))));
        $shareObjectAttachments = json_decode((string) ($_POST['fediverse_object_attachments'] ?? '[]'), true);
        $shareObjectAttachments = array_values(array_filter(array_map(static function ($attachment): ?array {
            if (!is_array($attachment)) {
                return null;
            }
            $url = trim((string) ($attachment['url'] ?? ''));
            if ($url === '') {
                return null;
            }
            return [
                'type' => strtolower(trim((string) ($attachment['type'] ?? 'document'))),
                'url' => $url,
                'name' => trim((string) ($attachment['name'] ?? '')),
                'media_type' => trim((string) ($attachment['media_type'] ?? ($attachment['mediaType'] ?? ''))),
                'image' => trim((string) ($attachment['image'] ?? '')),
                'summary' => trim((string) ($attachment['summary'] ?? '')),
            ];
        }, is_array($shareObjectAttachments) ? $shareObjectAttachments : [])));
        if ($shareObjectImage !== '' && !in_array($shareObjectImage, $shareObjectImages, true)) {
            array_unshift($shareObjectImages, $shareObjectImage);
        }
        $config = load_config_file();
        $baseUrl = rtrim((string) (($config['site_url'] ?? '') ?: nammu_base_url()), '/');
        $siteTitle = trim((string) (($config['site_name'] ?? '') ?: ''));
        $siteDescription = trim((string) ($config['site_description'] ?? ''));
        $siteLang = trim((string) ($config['site_lang'] ?? 'es'));
        $noteText = $shareText !== '' ? $shareText : $shareTitle;
        if ($objectUrl !== '' && !str_contains($noteText, $objectUrl)) {
            $noteText = trim($noteText . "\n\n" . $objectUrl);
        }
        if ($noteText === '' || !function_exists('nammu_actuality_add_manual_item')) {
            $fediverseFeedback = [
                'type' => 'danger',
                'message' => 'No se pudo crear la nota compartida.',
            ];
            $fediverseRedirect = true;
        } else {
            $manualItem = nammu_actuality_add_manual_item($noteText, $baseUrl, $siteTitle, $shareObjectImage, [
                'images' => $shareObjectImages,
                'attachments' => $shareObjectAttachments,
            ]);
            if (function_exists('nammu_actuality_rebuild_snapshot')) {
                nammu_actuality_rebuild_snapshot($baseUrl, $config, $siteTitle, $siteDescription, $siteLang);
            }
            if (function_exists('nammu_fediverse_record_action')) {
                nammu_fediverse_record_action('share', '', $objectUrl, ['share_text' => $shareText, 'title' => $shareTitle]);
            }
            if (!function_exists('admin_enqueue_social_broadcast') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
                require_once NAMMU_ROOT . '/core/admin-redes.php';
            }
            if (is_array($manualItem) && function_exists('admin_enqueue_social_broadcast') && function_exists('admin_social_broadcast_available_networks')) {
                $allConfiguredNetworks = array_keys(admin_social_broadcast_available_networks(get_settings()));
                if (!empty($allConfiguredNetworks)) {
                    $fediverseUrl = function_exists('admin_social_broadcast_fediverse_url_for_actuality_item')
                        ? admin_social_broadcast_fediverse_url_for_actuality_item($manualItem)
                        : '';
                    admin_enqueue_social_broadcast($noteText, $shareObjectImages, $allConfiguredNetworks, $fediverseUrl);
                }
            }
            $deliveryStats = nammu_fediverse_deliver_local_items($config);
            $fediverseFeedback = [
                'type' => 'success',
                'message' => 'Nota compartida. Entregas federadas: ' . (int) ($deliveryStats['delivered'] ?? 0) . '.',
            ];
            $fediverseRedirect = true;
        }
    }
    if ($fediverseRedirect) {
        $_SESSION['fediverse_feedback'] = $fediverseFeedback;
        $_SESSION['fediverse_state'] = $fediverseRedirectState;
        $redirectTab = strtolower(trim((string) ($_POST['fediverse_tab'] ?? ($_GET['tab'] ?? 'home'))));
        if (!in_array($redirectTab, ['home', 'notifications', 'messages', 'mentions', 'network', 'settings'], true)) {
            $redirectTab = 'home';
        }
        $redirectUrl = 'admin.php?page=fediverso&tab=' . rawurlencode($redirectTab);
        if ($redirectTab === 'home') {
            $timelinePage = max(1, (int) ($_POST['timeline_page'] ?? ($_GET['timeline_page'] ?? 1)));
            if ($timelinePage > 1) {
                $redirectUrl .= '&timeline_page=' . $timelinePage;
            }
        }
        header('Location: ' . $redirectUrl);
        exit;
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
