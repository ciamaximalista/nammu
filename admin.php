<?php
session_start();

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/helpers.php';

// Load dependencies (optional)
$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
use Nammu\Core\Itinerary;
use Nammu\Core\ItineraryRepository;
use Nammu\Core\ItineraryTopic;
use Nammu\Core\MarkdownConverter;
use Nammu\Core\Post;
use Nammu\Core\RssGenerator;
use Symfony\Component\Yaml\Yaml;

// --- User Configuration ---
define('USER_FILE', __DIR__ . '/config/user.php');
define('CONTENT_DIR', __DIR__ . '/content');
define('ASSETS_DIR', __DIR__ . '/assets');
define('ITINERARIES_DIR', __DIR__ . '/itinerarios');
nammu_ensure_directory(ITINERARIES_DIR);

// --- Helper Functions ---

function get_all_posts_metadata() {
    $posts = [];
    $files = glob(CONTENT_DIR . '/*.md');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $metadata = parse_yaml_front_matter($content);
        $posts[] = [
            'filename' => basename($file),
            'metadata' => $metadata,
        ];
    }
    return $posts;
}

function parse_yaml_front_matter($content) {
    $metadata = [];
    $parts = preg_split('/---s*
/', $content, 3);
    if (count($parts) >= 3) {
        $yaml = $parts[1];
        $lines = explode("
", $yaml);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $metadata[trim($key)] = trim($value);
            }
        }
    }
    return $metadata;
}

function get_posts($page = 1, $per_page = 16, $templateFilter = 'single', string $searchTerm = '') {
    $settings = get_settings();
    $sort_order = $settings['sort_order'] ?? 'date';
    $templateFilter = in_array($templateFilter, ['single', 'page', 'draft'], true) ? $templateFilter : 'single';
    $normalizedSearch = '';
    if ($searchTerm !== '') {
        $normalizedSearch = function_exists('mb_strtolower')
            ? mb_strtolower($searchTerm, 'UTF-8')
            : strtolower($searchTerm);
    }

    $posts = [];
    $files = glob(CONTENT_DIR . '/*.md');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $metadata = parse_yaml_front_matter($content);
        $template = strtolower($metadata['Template'] ?? '');
        $status = strtolower($metadata['Status'] ?? 'published');
        $isEntry = in_array($template, ['single', 'post'], true);
        $isDraft = ($status === 'draft');
        if ($templateFilter === 'draft') {
            if (!$isDraft) {
                continue;
            }
        } else {
            if ($isDraft) {
                continue;
            }
            if ($templateFilter === 'single' && !$isEntry) {
                continue;
            }
            if ($templateFilter === 'page' && $template !== 'page') {
                continue;
            }
        }
        if ($normalizedSearch !== '') {
            $haystackParts = [
                $metadata['Title'] ?? '',
                $metadata['Description'] ?? '',
                $metadata['Category'] ?? '',
                basename($file),
            ];
            $haystack = function_exists('mb_strtolower')
                ? mb_strtolower(implode(' ', $haystackParts), 'UTF-8')
                : strtolower(implode(' ', $haystackParts));
            if (strpos($haystack, $normalizedSearch) === false) {
                continue;
            }
        }
        $date = $metadata['Date'] ?? '01/01/1970';
        $dt = DateTime::createFromFormat('d/m/Y', $date);
        if ($dt) {
            $timestamp = $dt->getTimestamp();
        } else {
            $timestamp = strtotime($date);
        }
        if ($timestamp === false) {
            $timestamp = 0;
        }

        $posts[] = [
            'filename' => basename($file),
            'title' => $metadata['Title'] ?? '',
            'description' => $metadata['Description'] ?? '',
            'date' => $date,
            'timestamp' => $timestamp,
            'status' => $isDraft ? 'draft' : 'published',
        ];
    }

    if ($sort_order === 'alpha') {
        usort($posts, function($a, $b) {
            return strcasecmp($a['title'], $b['title']);
        });
    } else { // Default to date
        usort($posts, function($a, $b) {
            if ($a['timestamp'] == $b['timestamp']) {
                return 0;
            }
            return ($a['timestamp'] < $b['timestamp']) ? 1 : -1;
        });
    }

    $total = count($posts);
    $pages = max(1, (int) ceil($total / $per_page));
    $offset = ($page - 1) * $per_page;
    return [
        'posts' => array_slice($posts, $offset, $per_page),
        'total' => $total,
        'pages' => $pages,
        'current_page' => min($page, $pages),
    ];
}

function get_post_content($filename) {
    $safeFilename = nammu_normalize_filename($filename);
    if ($safeFilename === '') {
        return null;
    }
    $filepath = CONTENT_DIR . '/' . $safeFilename;
    if (!file_exists($filepath)) {
        return null;
    }

    $content = file_get_contents($filepath);
    $parts = preg_split('/---s*
/', $content, 3);
    
    $metadata = [];
    if (count($parts) >= 3) {
        $yaml = $parts[1];
        $lines = explode("
", $yaml);
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $metadata[trim($key)] = trim($value);
            }
        }
    }

    return [
        'metadata' => $metadata,
        'content' => $parts[2] ?? '',
    ];
}

function nammu_allowed_media_extensions(): array {
    return ['jpg','jpeg','png','gif','webp','svg','mp4','webm','mov','m4v','ogv','ogg','pdf'];
}

function get_media_items($page = 1, $per_page = 24) {
    $extensions = nammu_allowed_media_extensions();
    $patterns = array_merge($extensions, array_map('strtoupper', $extensions));
    $pattern = ASSETS_DIR . '/*.{'.implode(',', $patterns).'}';
    $files = glob($pattern, GLOB_BRACE) ?: [];
    $items = [];
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $type = 'image';
        if (in_array($ext, ['mp4','webm','mov','m4v','ogv','ogg'], true)) {
            $type = 'video';
        } elseif ($ext === 'pdf') {
            $type = 'document';
        }
        if ($type === 'video') {
            $mime = admin_video_mime_from_extension($ext);
        } elseif ($type === 'document') {
            $mime = 'application/pdf';
        } else {
            $mime = admin_image_mime_from_extension($ext);
        }
        $items[] = [
            'path' => $file,
            'name' => basename($file),
            'relative' => str_replace(ASSETS_DIR . '/', '', $file),
            'type' => $type,
            'extension' => $ext,
            'mime' => $mime,
            'modified' => filemtime($file),
        ];
    }
    usort($items, static function ($a, $b) {
        return ($b['modified'] ?? 0) <=> ($a['modified'] ?? 0);
    });
    $total = count($items);
    $pages = $per_page > 0 ? max(1, (int) ceil($total / $per_page)) : 1;
    $offset = $per_page > 0 ? ($page - 1) * $per_page : 0;
    return [
        'items' => $per_page > 0 ? array_slice($items, $offset, $per_page) : $items,
        'total' => $total,
        'pages' => $pages,
        'current_page' => $page,
    ];
}

function admin_image_mime_from_extension(string $ext): string {
    $map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
    ];
    return $map[$ext] ?? 'image/' . $ext;
}

function admin_video_mime_from_extension(string $ext): string {
    $map = [
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'mov' => 'video/quicktime',
        'webm' => 'video/webm',
        'ogv' => 'video/ogg',
        'ogg' => 'video/ogg',
    ];
    return $map[$ext] ?? 'video/' . $ext;
}

function nammu_unique_asset_filename(string $filename): string {
    $dir = ASSETS_DIR;
    $info = pathinfo($filename);
    $base = $info['filename'] ?? 'asset';
    $ext = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
    $candidate = $base . $ext;
    $index = 2;
    while (is_file($dir . '/' . $candidate)) {
        $candidate = $base . '-' . $index . $ext;
        $index++;
    }
    return $candidate;
}

function get_assets($page = 1, $per_page = 40) {
    return get_media_items($page, $per_page);
}

function admin_itinerary_repository(): ItineraryRepository {
    static $repository = null;
    if ($repository === null) {
        if (!is_dir(ITINERARIES_DIR)) {
            @mkdir(ITINERARIES_DIR, 0755, true);
        }
        $repository = new ItineraryRepository(ITINERARIES_DIR);
    }
    return $repository;
}

function admin_regenerate_itinerary_feed(): void {
    try {
        $repository = admin_itinerary_repository();
        $itineraries = $repository->all();
        $baseUrl = nammu_base_url();

        $config = load_config_file();
        $siteTitle = trim((string) ($config['site_name'] ?? 'Nammu Blog'));
        $siteDescription = trim((string) ($config['social']['default_description'] ?? ''));

        $markdown = new MarkdownConverter();
        $posts = [];
        $urls = [];

        foreach ($itineraries as $itinerary) {
            if (!$itinerary instanceof Itinerary) {
                continue;
            }
            $metadata = $itinerary->getMetadata();
            $description = $itinerary->getDescription();
            if ($description === '') {
                $convertedDocument = $markdown->convertDocument($itinerary->getContent());
                $description = nammu_excerpt_text($convertedDocument['html'], 220);
            }
            $dateString = $metadata['Date'] ?? ($metadata['Updated'] ?? '');
            if (trim((string) $dateString) === '') {
                $indexPath = $itinerary->getDirectory() . '/index.md';
                $mtime = is_file($indexPath) ? @filemtime($indexPath) : false;
                $dateString = $mtime !== false ? gmdate('Y-m-d', $mtime) : gmdate('Y-m-d');
            }
            $virtualMeta = [
                'Title' => $itinerary->getTitle(),
                'Description' => $description,
                'Image' => $itinerary->getImage() ?? '',
                'Date' => $dateString,
            ];
            $virtualSlug = 'itinerary-feed-' . $itinerary->getSlug();
            $posts[] = new Post($virtualSlug, $virtualMeta, $itinerary->getContent());
            $path = '/itinerarios/' . rawurlencode($itinerary->getSlug());
            $urls[$virtualSlug] = $baseUrl !== '' ? $baseUrl . $path : $path;
        }

        usort($posts, static function (Post $a, Post $b): int {
            $dateA = $a->getDate();
            $dateB = $b->getDate();
            if ($dateA && $dateB) {
                return $dateB <=> $dateA;
            }
            if ($dateA) {
                return -1;
            }
            if ($dateB) {
                return 1;
            }
            return strcmp($a->getSlug(), $b->getSlug());
        });

        $feedContent = (new RssGenerator(
            $baseUrl,
            $siteTitle . ' — Itinerarios',
            'Itinerarios recientes'
        ))->generate(
            $posts,
            static function (Post $post) use ($urls): string {
                return $urls[$post->getSlug()] ?? '/';
            },
            $markdown,
            false
        );

        @file_put_contents(__DIR__ . '/itinerarios.xml', $feedContent);
    } catch (Throwable $e) {
        error_log('No se pudo regenerar itinerarios.xml: ' . $e->getMessage());
    }
}

/**
 * @return Itinerary[]
 */
function admin_list_itineraries(): array {
    try {
        return admin_itinerary_repository()->all();
    } catch (Throwable $e) {
        return [];
    }
}

function admin_load_itinerary(string $slug): ?Itinerary {
    $normalized = ItineraryRepository::normalizeSlug($slug);
    if ($normalized === '') {
        return null;
    }
    try {
        return admin_itinerary_repository()->find($normalized);
    } catch (Throwable $e) {
        return null;
    }
}

function admin_load_itinerary_topic(string $itinerarySlug, string $topicSlug): ?ItineraryTopic {
    $itineraryNormalized = ItineraryRepository::normalizeSlug($itinerarySlug);
    $topicNormalized = ItineraryRepository::normalizeSlug($topicSlug);
    if ($itineraryNormalized === '' || $topicNormalized === '') {
        return null;
    }
    try {
        return admin_itinerary_repository()->findTopic($itineraryNormalized, $topicNormalized);
    } catch (Throwable $e) {
        return null;
    }
}

function admin_get_itinerary_stats(Itinerary $itinerary): array {
    try {
        return admin_itinerary_repository()->getItineraryStats($itinerary->getSlug());
    } catch (Throwable $e) {
        return ['started' => 0, 'topics' => [], 'updated_at' => 0];
    }
}

function admin_itinerary_class_options(): array {
    return [
        'Libro' => 'Libro',
        'Curso' => 'Curso',
        'Colección de materiales' => 'Colección de materiales',
        'Otros' => 'Otros',
    ];
}

function admin_normalize_itinerary_class_label(?string $choice, ?string $custom): string {
    $choice = trim((string) $choice);
    $custom = trim((string) $custom);
    $options = admin_itinerary_class_options();
    if ($choice !== '' && isset($options[$choice])) {
        if ($choice === 'Otros') {
            return $custom !== '' ? $custom : 'Itinerario';
        }
        return $options[$choice];
    }
    if ($custom !== '') {
        return $custom;
    }
    return 'Itinerario';
}

function admin_itinerary_class_form_state(string $label): array {
    $label = trim($label);
    $options = admin_itinerary_class_options();
    if ($label === '' || $label === 'Itinerario') {
        return ['choice' => '', 'custom' => ''];
    }
    foreach ($options as $value => $text) {
        if ($value === 'Otros') {
            continue;
        }
        if ($label === $text) {
            return ['choice' => $value, 'custom' => ''];
        }
    }
    return ['choice' => 'Otros', 'custom' => $label];
}

function admin_itinerary_usage_logic_options(): array {
    return [
        'free' => 'El lector puede ir libremente al tema que quiera',
        'sequential' => 'Es necesario haber accedido a un tema para pasar al siguiente',
        'assessment' => 'Es necesario haber respondido correctamente a las autoevaluaciones para pasar al tema siguiente',
    ];
}

function admin_normalize_itinerary_usage_logic(?string $value): string {
    $value = strtolower(trim((string) $value));
    $options = admin_itinerary_usage_logic_options();
    if ($value !== '' && isset($options[$value])) {
        return $value;
    }
    return 'free';
}

function admin_parse_quiz_payload(string $payload): array {
    $payload = trim($payload);
    if ($payload === '') {
        return ['data' => [], 'error' => null];
    }
    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        return ['data' => [], 'error' => 'La autoevaluación enviada no es válida.'];
    }
    $sanitized = admin_sanitize_quiz_array($decoded);
    if (!empty($decoded['questions']) && empty($sanitized)) {
        return ['data' => [], 'error' => 'Revisa la autoevaluación: cada pregunta necesita texto y al menos una respuesta correcta.'];
    }
    return ['data' => $sanitized, 'error' => null];
}

function admin_sanitize_quiz_array(?array $quiz): array {
    if (!is_array($quiz)) {
        return [];
    }
    $questions = [];
    foreach ($quiz['questions'] ?? [] as $question) {
        if (!is_array($question)) {
            continue;
        }
        $text = trim((string) ($question['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $answers = [];
        foreach ($question['answers'] ?? [] as $answer) {
            if (!is_array($answer)) {
                continue;
            }
            $answerText = trim((string) ($answer['text'] ?? ''));
            if ($answerText === '') {
                continue;
            }
            $answers[] = [
                'text' => $answerText,
                'correct' => !empty($answer['correct']),
            ];
        }
        if (empty($answers)) {
            continue;
        }
        $hasCorrect = false;
        foreach ($answers as $answer) {
            if ($answer['correct']) {
                $hasCorrect = true;
                break;
            }
        }
        if (!$hasCorrect) {
            continue;
        }
        $questions[] = [
            'text' => $text,
            'answers' => $answers,
        ];
    }
    if (empty($questions)) {
        return [];
    }
    $minimum = (int) ($quiz['minimum_correct'] ?? count($questions));
    if ($minimum < 1) {
        $minimum = 1;
    }
    if ($minimum > count($questions)) {
        $minimum = count($questions);
    }
    return [
        'minimum_correct' => $minimum,
        'questions' => array_values($questions),
    ];
}

function admin_quiz_summary(array $quiz): string {
    if (empty($quiz['questions'])) {
        return '';
    }
    $questionCount = count($quiz['questions']);
    $minimum = (int) ($quiz['minimum_correct'] ?? $questionCount);
    if ($minimum < 1) {
        $minimum = 1;
    }
    if ($minimum > $questionCount) {
        $minimum = $questionCount;
    }
    $questionLabel = $questionCount === 1 ? 'pregunta' : 'preguntas';
    return $questionCount . ' ' . $questionLabel . ' · mínimo ' . $minimum . ' correctas';
}

function admin_quiz_json(array $quiz): string {
    if (empty($quiz['questions'])) {
        return '';
    }
    $payload = json_encode($quiz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $payload === false ? '' : $payload;
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

function get_settings() {
    $config = load_config_file();

    $defaults = get_default_template_settings();
    $templateConfig = $config['template'] ?? [];

    $sort_order = $config['pages_order_by'] ?? 'date';
    $googleFontsApi = $config['google_fonts_api'] ?? '';
    $authorName = $config['site_author'] ?? '';
    $blogName = $config['site_name'] ?? '';

    $fonts = array_merge($defaults['fonts'], $templateConfig['fonts'] ?? []);
    $colors = array_merge($defaults['colors'], $templateConfig['colors'] ?? []);
    $images = array_merge($defaults['images'], $templateConfig['images'] ?? []);
    $footer = $templateConfig['footer'] ?? $defaults['footer'];
    $global = array_merge($defaults['global'], $templateConfig['global'] ?? []);
    $cornerStyle = $global['corners'] ?? $defaults['global']['corners'];
    if (!in_array($cornerStyle, ['rounded', 'square'], true)) {
        $cornerStyle = $defaults['global']['corners'];
    }
    $global['corners'] = $cornerStyle;
    $footerLogo = $templateConfig['footer_logo'] ?? ($defaults['footer_logo'] ?? 'none');
    if (!in_array($footerLogo, ['none', 'top', 'bottom'], true)) {
        $footerLogo = $defaults['footer_logo'] ?? 'none';
    }
    $homeConfig = $templateConfig['home'] ?? [];
    $home = array_merge($defaults['home'], $homeConfig);
    $homeBlocks = $home['blocks'] ?? $defaults['home']['blocks'];
    if (!in_array($homeBlocks, ['boxed', 'flat'], true)) {
        $homeBlocks = $defaults['home']['blocks'];
    }
    $home['blocks'] = $homeBlocks;
    $fullImageMode = $home['full_image_mode'] ?? $defaults['home']['full_image_mode'];
    if (!in_array($fullImageMode, ['natural', 'crop'], true)) {
        $fullImageMode = $defaults['home']['full_image_mode'];
    }
    $home['full_image_mode'] = $fullImageMode;
    $headerConfig = $homeConfig['header'] ?? [];
    $home['header'] = array_merge($defaults['home']['header'], $headerConfig);
    $textStyle = $home['header']['text_style'] ?? $defaults['home']['header']['text_style'];
    if (!in_array($textStyle, ['boxed', 'plain'], true)) {
        $textStyle = $defaults['home']['header']['text_style'];
    }
    $home['header']['text_style'] = $textStyle;
    $orderStyle = $home['header']['order'] ?? $defaults['home']['header']['order'];
    if (!in_array($orderStyle, ['image-text', 'text-image'], true)) {
        $orderStyle = $defaults['home']['header']['order'];
    }
    $home['header']['order'] = $orderStyle;
    $searchDefaults = $defaults['search'] ?? ['mode' => 'single', 'position' => 'footer', 'floating' => 'off'];
    $searchConfig = array_merge($searchDefaults, $templateConfig['search'] ?? []);
    $searchMode = $searchConfig['mode'] ?? 'none';
    if (!in_array($searchMode, ['none', 'home', 'single', 'both'], true)) {
        $searchMode = $searchDefaults['mode'];
    }
    $searchPosition = $searchConfig['position'] ?? 'title';
    if (!in_array($searchPosition, ['title', 'footer'], true)) {
        $searchPosition = $searchDefaults['position'];
    }
    $searchFloating = $searchConfig['floating'] ?? ($searchDefaults['floating'] ?? 'off');
    if (!in_array($searchFloating, ['off', 'on'], true)) {
        $searchFloating = $searchDefaults['floating'] ?? 'off';
    }
    $searchConfig['mode'] = $searchMode;
    $searchConfig['position'] = $searchPosition;
    $searchConfig['floating'] = $searchFloating;
    $entryTocDefaults = $defaults['entry']['toc'] ?? ['auto' => 'off', 'min_headings' => 3];
    $entryTemplateToc = $templateConfig['entry']['toc'] ?? [];
    $entryAuto = $entryTemplateToc['auto'] ?? $entryTocDefaults['auto'];
    if (!in_array($entryAuto, ['on', 'off'], true)) {
        $entryAuto = $entryTocDefaults['auto'];
    }
    $entryMin = (int) ($entryTemplateToc['min_headings'] ?? $entryTocDefaults['min_headings']);
    if (!in_array($entryMin, [2, 3, 4], true)) {
        $entryMin = $entryTocDefaults['min_headings'];
    }
    $entry = [
        'toc' => [
            'auto' => $entryAuto,
            'min_headings' => $entryMin,
        ],
    ];

    $socialDefaults = [
        'default_description' => '',
        'home_image' => '',
        'twitter' => '',
        'facebook_app_id' => '',
    ];
    $social = array_merge($socialDefaults, $config['social'] ?? []);
    $userData = get_user_data();
    $account = [
        'username' => $userData['username'] ?? '',
    ];
    $telegram = admin_extract_telegram_settings($config);
    $whatsapp = admin_extract_social_settings('whatsapp', [
        'token' => '',
        'channel' => '',
        'recipient' => '',
        'auto_post' => 'off',
    ], $config);
    $facebook = admin_extract_social_settings('facebook', [
        'token' => '',
        'channel' => '',
        'recipient' => '',
        'auto_post' => 'off',
    ], $config);
    $twitter = admin_extract_social_settings('twitter', [
        'token' => '',
        'channel' => '',
        'recipient' => '',
        'auto_post' => 'off',
    ], $config);

    return [
        'sort_order' => $sort_order,
        'google_fonts_api' => $googleFontsApi,
        'site_author' => $authorName,
        'site_name' => $blogName,
        'template' => [
            'fonts' => $fonts,
            'colors' => $colors,
            'images' => $images,
            'footer' => $footer,
            'footer_logo' => $footerLogo,
            'global' => $global,
            'home' => $home,
            'search' => $searchConfig,
            'entry' => $entry,
        ],
        'social' => $social,
        'account' => $account,
        'telegram' => $telegram,
        'whatsapp' => $whatsapp,
        'facebook' => $facebook,
        'twitter' => $twitter,
        'entry' => $entry,
    ];
}

function is_logged_in() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

function get_user_data() {
    if (!file_exists(USER_FILE)) {
        return null;
    }
    return include USER_FILE;
}

function write_user_file(string $username, string $passwordHash): void {
    $dir = dirname(USER_FILE);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de configuración');
        }
    }

    $content = "<?php return ['username' => '" . addslashes($username) . "', 'password' => '" . $passwordHash . "'];";
    if (file_put_contents(USER_FILE, $content) === false) {
        throw new RuntimeException('No se pudo escribir el archivo de usuario');
    }
}

function register_user($username, $password) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    write_user_file($username, $hashed_password);
}

function verify_user($username, $password) {
    $user_data = get_user_data();
    if ($user_data && $user_data['username'] === $username && password_verify($password, $user_data['password'])) {
        return true;
    }
    return false;
}

function admin_base_url(): string {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $host = trim((string) $host);
    if ($host === '') {
        return '';
    }
    $scheme = 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $candidate = strtolower(trim($forwarded[0]));
        if (in_array($candidate, ['http', 'https'], true)) {
            $scheme = $candidate;
        }
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
        $candidate = strtolower((string) $_SERVER['REQUEST_SCHEME']);
        if (in_array($candidate, ['http', 'https'], true)) {
            $scheme = $candidate;
        }
    }
    $portSuffix = '';
    if (isset($_SERVER['SERVER_PORT']) && !str_contains($host, ':')) {
        $port = (int) $_SERVER['SERVER_PORT'];
        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $portSuffix = ':' . $port;
        }
    }
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName !== '' ? $scriptName : '/'));
    if ($dir === '/' || $dir === '.') {
        $dir = '';
    } else {
        $dir = rtrim($dir, '/');
    }
    $base = rtrim($scheme . '://' . $host . $portSuffix . $dir, '/');
    return $base;
}

function admin_public_post_url(string $slug): string {
    $base = admin_base_url();
    $path = '/' . ltrim($slug, '/');
    if ($base === '') {
        return $path;
    }
    return $base . $path;
}

function admin_public_itinerary_url(string $slug): string {
    $base = admin_base_url();
    $path = '/itinerarios/' . rawurlencode($slug);
    return $base === '' ? $path : $base . $path;
}

function admin_extract_social_settings(string $key, array $defaults, ?array $config = null): array {
    if ($config === null) {
        $config = load_config_file();
    }
    $stored = [];
    if (isset($config[$key]) && is_array($config[$key])) {
        $stored = $config[$key];
    }
    $values = array_merge($defaults, $stored);
    $values['token'] = trim((string) ($values['token'] ?? ''));
    $values['channel'] = trim((string) ($values['channel'] ?? ''));
    $values['recipient'] = trim((string) ($values['recipient'] ?? ''));
    $values['auto_post'] = ($values['auto_post'] ?? 'off') === 'on' ? 'on' : 'off';
    return $values;
}

function admin_extract_telegram_settings(?array $config = null): array {
    $defaults = [
        'token' => '',
        'channel' => '',
        'recipient' => '',
        'auto_post' => 'off',
    ];
    $telegram = admin_extract_social_settings('telegram', $defaults, $config);
    if ($telegram['channel'] !== '' && $telegram['channel'][0] !== '@' && !preg_match('/^-?\d+$/', $telegram['channel'])) {
        $telegram['channel'] = '@' . ltrim($telegram['channel'], '@');
    }
    return $telegram;
}

function admin_cached_social_settings(?string $key = null): array {
    static $cache = null;
    if ($cache === null) {
        $config = load_config_file();
        $cache = [
            'telegram' => admin_extract_telegram_settings($config),
            'whatsapp' => admin_extract_social_settings('whatsapp', [
                'token' => '',
                'channel' => '',
                'recipient' => '',
                'auto_post' => 'off',
            ], $config),
            'facebook' => admin_extract_social_settings('facebook', [
                'token' => '',
                'channel' => '',
                'recipient' => '',
                'auto_post' => 'off',
            ], $config),
            'twitter' => admin_extract_social_settings('twitter', [
                'token' => '',
                'channel' => '',
                'recipient' => '',
                'auto_post' => 'off',
            ], $config),
        ];
    }
    if ($key === null) {
        return $cache;
    }
    return $cache[$key] ?? [];
}

function admin_cached_telegram_settings(): array {
    return admin_cached_social_settings('telegram');
}

function admin_is_social_network_configured(string $network, array $settings): bool {
    switch ($network) {
        case 'telegram':
            return ($settings['token'] ?? '') !== '' && ($settings['channel'] ?? '') !== '';
        case 'whatsapp':
            return ($settings['token'] ?? '') !== '' && ($settings['channel'] ?? '') !== '' && ($settings['recipient'] ?? '') !== '';
        case 'facebook':
            return ($settings['token'] ?? '') !== '' && ($settings['channel'] ?? '') !== '';
        case 'twitter':
            return ($settings['token'] ?? '') !== '';
        default:
            return false;
    }
}

function admin_send_post_to_telegram(string $slug, string $title, string $description, array $telegramSettings): bool {
    $token = $telegramSettings['token'] ?? '';
    $channel = $telegramSettings['channel'] ?? '';
    if ($token === '' || $channel === '') {
        return false;
    }
    $message = admin_build_telegram_message($slug, $title, $description);
    return admin_send_telegram_message($token, $channel, $message, 'HTML');
}

function admin_telegram_escape(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_build_post_message(string $slug, string $title, string $description): string {
    $parts = [];
    $title = trim($title);
    $description = trim($description);
    if ($title !== '') {
        $parts[] = $title;
    }
    if ($description !== '') {
        $parts[] = $description;
    }
    $url = admin_public_post_url($slug);
    if ($url !== '') {
        $parts[] = $url;
    }
    if (empty($parts)) {
        $parts[] = 'Nueva publicación disponible';
    }
    return implode("\n\n", $parts);
}

function admin_build_telegram_message(string $slug, string $title, string $description): string {
    $parts = [];
    $titleTrim = trim($title);
    if ($titleTrim !== '') {
        $parts[] = '<b>' . admin_telegram_escape($titleTrim) . '</b>';
    }
    $descriptionTrim = trim($description);
    if ($descriptionTrim !== '') {
        $parts[] = admin_telegram_escape($descriptionTrim);
    }
    $url = admin_public_post_url($slug);
    if ($url !== '') {
        $parts[] = admin_telegram_escape($url);
    }
    if (empty($parts)) {
        $parts[] = admin_telegram_escape('Nueva publicación disponible');
    }
    return implode("\n\n", $parts);
}

function admin_send_whatsapp_post(string $slug, string $title, string $description, array $settings): bool {
    $token = $settings['token'] ?? '';
    $phoneId = $settings['channel'] ?? '';
    $recipient = $settings['recipient'] ?? '';
    if ($token === '' || $phoneId === '' || $recipient === '') {
        return false;
    }
    $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($phoneId) . '/messages';
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $recipient,
        'type' => 'text',
        'text' => [
            'body' => admin_build_post_message($slug, $title, $description),
        ],
    ];
    return admin_http_post_json($endpoint, $payload, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
}

function admin_send_facebook_post(string $slug, string $title, string $description, array $settings): bool {
    $token = $settings['token'] ?? '';
    $pageId = $settings['channel'] ?? '';
    if ($token === '' || $pageId === '') {
        return false;
    }
    $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($pageId) . '/feed';
    $params = [
        'message' => admin_build_post_message($slug, $title, $description),
        'access_token' => $token,
    ];
    return admin_http_post_form($endpoint, $params);
}

function admin_send_twitter_post(string $slug, string $title, string $description, array $settings): bool {
    $token = $settings['token'] ?? '';
    if ($token === '') {
        return false;
    }
    $endpoint = 'https://api.twitter.com/2/tweets';
    $text = admin_build_post_message($slug, $title, $description);
    if (function_exists('mb_strlen')) {
        if (mb_strlen($text, 'UTF-8') > 280) {
            $text = mb_substr($text, 0, 275, 'UTF-8') . '…';
        }
    } elseif (strlen($text) > 280) {
        $text = substr($text, 0, 275) . '…';
    }
    $payload = ['text' => $text];
    return admin_http_post_json($endpoint, $payload, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
}

function admin_http_post_json(string $url, array $payload, array $headers = []): bool {
    $body = json_encode($payload);
    $headers[] = 'Content-Length: ' . strlen((string) $body);
    return admin_http_post_body($url, $body, $headers);
}

function admin_http_post_form(string $url, array $params): bool {
    $body = http_build_query($params);
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($body),
    ];
    return admin_http_post_body($url, $body, $headers);
}

function admin_http_post_body(string $url, string $body, array $headers): bool {
    $responseBody = null;
    $httpCode = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $responseBody = curl_exec($ch);
        if ($responseBody !== false) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 10,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('#HTTP/\d\.\d\s+(\d+)#', $headerLine, $matches)) {
                    $httpCode = (int) $matches[1];
                    break;
                }
            }
        }
    }
    if ($responseBody === false || $responseBody === null) {
        return false;
    }
    if ($httpCode !== null) {
        return $httpCode >= 200 && $httpCode < 300;
    }
    return true;
}

function admin_send_telegram_message(string $token, string $chatId, string $text, ?string $parseMode = null): bool {
    $endpoint = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => false,
    ];
    if ($parseMode !== null) {
        $payload['parse_mode'] = $parseMode;
    }
    $body = http_build_query($payload);
    $responseBody = null;
    $httpCode = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $responseBody = curl_exec($ch);
        if ($responseBody !== false) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body),
                'content' => $body,
                'timeout' => 10,
            ],
        ]);
        $responseBody = @file_get_contents($endpoint, false, $context);
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('#HTTP/\d\.\d\s+(\d+)#', $headerLine, $matches)) {
                    $httpCode = (int) $matches[1];
                    break;
                }
            }
        }
    }
    if ($responseBody === false || $responseBody === null) {
        return false;
    }
    $decoded = json_decode($responseBody, true);
    if (is_array($decoded) && isset($decoded['ok'])) {
        return (bool) $decoded['ok'];
    }
    if ($httpCode !== null) {
        return $httpCode >= 200 && $httpCode < 300;
    }
    return false;
}

function admin_maybe_auto_post_to_social_networks(string $filename, string $title, string $description): void {
    $slug = pathinfo($filename, PATHINFO_FILENAME);
    if ($slug === '') {
        $slug = $filename;
    }
    $settings = admin_cached_social_settings();
    if (($settings['telegram']['auto_post'] ?? 'off') === 'on' && admin_is_social_network_configured('telegram', $settings['telegram'])) {
        admin_send_post_to_telegram($slug, $title, $description, $settings['telegram']);
    }
    if (($settings['whatsapp']['auto_post'] ?? 'off') === 'on' && admin_is_social_network_configured('whatsapp', $settings['whatsapp'])) {
        admin_send_whatsapp_post($slug, $title, $description, $settings['whatsapp']);
    }
    if (($settings['facebook']['auto_post'] ?? 'off') === 'on' && admin_is_social_network_configured('facebook', $settings['facebook'])) {
        admin_send_facebook_post($slug, $title, $description, $settings['facebook']);
    }
    if (($settings['twitter']['auto_post'] ?? 'off') === 'on' && admin_is_social_network_configured('twitter', $settings['twitter'])) {
        admin_send_twitter_post($slug, $title, $description, $settings['twitter']);
    }
}

function get_default_template_settings(): array {
    return [
        'fonts' => [
            'title' => 'Gabarito',
            'body' => 'Roboto',
            'code' => 'VT323',
            'quote' => 'Castoro',
        ],
        'colors' => [
            'h1' => '#1b8eed',
            'h2' => '#ea2f28',
            'h3' => '#1b1b1b',
            'intro' => '#f6f6f6',
            'text' => '#222222',
            'background' => '#ffffff',
            'highlight' => '#f3f6f9',
            'accent' => '#0a4c8a',
            'brand' => '#1b1b1b',
            'code_background' => '#000000',
            'code_text' => '#90ee90',
        ],
        'footer' => '',
        'footer_logo' => 'top',
        'images' => [
            'logo' => '',
        ],
        'global' => [
            'corners' => 'rounded',
        ],
        'home' => [
            'columns' => 2,
            'per_page' => 'all',
            'card_style' => 'full',
            'blocks' => 'boxed',
            'full_image_mode' => 'natural',
            'header' => [
                'type' => 'none',
                'image' => '',
                'mode' => 'contain',
                'text_style' => 'boxed',
                'order' => 'image-text',
            ],
        ],
        'search' => [
            'mode' => 'none',
            'position' => 'title',
            'floating' => 'off',
        ],
        'entry' => [
            'toc' => [
                'auto' => 'off',
                'min_headings' => 3,
            ],
        ],
    ];
}

function simple_yaml_unescape(string $value): string {
    $value = trim($value);
    if ($value === "''" || $value === '""') {
        return '';
    }
    if ($value !== '' && substr($value, -1) === $value[0]) {
        if ($value[0] === '"') {
            $inner = substr($value, 1, -1);
            $decoded = stripcslashes($inner);
            return str_replace('\\n', "\n", $decoded);
        }
        if ($value[0] === "'") {
            $inner = substr($value, 1, -1);
            $decoded = str_replace("''", "'", $inner);
            return str_replace('\\n', "\n", $decoded);
        }
    }
    return str_replace('\\n', "\n", $value);
}

function simple_yaml_parse(string $yaml): array {
    $lines = preg_split("/\r?\n/", $yaml);
    $result = [];
    $stack = [&$result];
    $indentStack = [0];

    foreach ($lines as $line) {
        if ($line === '' || trim($line) === '' || preg_match('/^\s*#/', $line)) {
            continue;
        }
        $indent = strlen($line) - strlen(ltrim($line, ' '));
        $trimmed = trim($line);
        if (!str_contains($trimmed, ':')) {
            continue;
        }
        [$rawKey, $rawValue] = explode(':', $trimmed, 2);
        $key = trim($rawKey);
        $value = ltrim($rawValue, " \t");

        while ($indent < end($indentStack)) {
            array_pop($stack);
            array_pop($indentStack);
        }

        $current = &$stack[count($stack) - 1];
        if ($value === '') {
            $current[$key] = [];
            $stack[] = &$current[$key];
            $indentStack[] = $indent + 2;
        } else {
            $current[$key] = simple_yaml_unescape($value);
        }
    }

    return $result;
}

function simple_yaml_escape(string $value): string {
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    if (str_contains($value, "\n")) {
        $value = str_replace("\n", '\\n', $value);
    }
    if ($value === '') {
        return "''";
    }
    if (preg_match('/[:#{}\[\],&*!|>\'"\n\r\t]/', $value) || $value[0] === ' ' || substr($value, -1) === ' ') {
        return "'" . str_replace("'", "''", $value) . "'";
    }
    return $value;
}

function simple_yaml_dump(array $data, int $level = 0): string {
    $lines = [];
    $indent = str_repeat('  ', $level);
    foreach ($data as $key => $value) {
        $key = (string) $key;
        if (is_array($value)) {
            if (empty($value)) {
                $lines[] = $indent . $key . ': {}';
                continue;
            }
            $lines[] = $indent . $key . ':';
            $nested = simple_yaml_dump($value, $level + 1);
            if ($nested !== '') {
                $lines[] = $nested;
            }
        } else {
            $lines[] = $indent . $key . ': ' . simple_yaml_escape((string) $value);
        }
    }
    return implode("\n", $lines);
}

function load_config_file(): array {
    $configFile = __DIR__ . '/config/config.yml';
    if (class_exists(Yaml::class) && is_file($configFile)) {
        try {
            $parsed = Yaml::parseFile($configFile);
            return is_array($parsed) ? $parsed : [];
        } catch (Exception $e) {
            return [];
        }
    }

    if (!is_file($configFile)) {
        return [];
    }

    $raw = file_get_contents($configFile);
    if ($raw === false) {
        return [];
    }

    $parsed = simple_yaml_parse($raw);
    return is_array($parsed) ? $parsed : [];
}

function save_config_file(array $config): void {
    $configFile = __DIR__ . '/config/config.yml';
    $dir = dirname($configFile);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de configuración');
        }
    }

    if (class_exists(Yaml::class)) {
        $yaml = Yaml::dump($config, 4, 2);
    } else {
        $yaml = simple_yaml_dump($config);
        if ($yaml !== '') {
            $yaml .= "\n";
        }
    }

    if (file_put_contents($configFile, $yaml) === false) {
        throw new RuntimeException('No se pudo escribir el archivo de configuración');
    }
}

function color_picker_value(string $value, string $fallback): string {
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value)) {
        return strtoupper($value);
    }
    return $fallback;
}

function format_date_for_input(?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return date('Y-m-d');
    }

    $raw = trim($raw);
    $knownFormats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
    foreach ($knownFormats as $format) {
        $dt = DateTime::createFromFormat($format, $raw);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    $timestamp = strtotime($raw);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }

    return date('Y-m-d');
}

function nammu_slugify(string $text): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
    $text = trim($text, '-');

    return $text;
}

function nammu_unique_filename(string $desired): string {
    $slug = nammu_slugify($desired);
    if ($slug === '') {
        $slug = 'entrada';
    }

    $base = $slug;
    $index = 1;
    while (file_exists(CONTENT_DIR . '/' . $slug . '.md')) {
        $slug = $base . '-' . $index;
        $index++;
    }

    return $slug;
}

function nammu_normalize_filename(string $filename, bool $ensureExtension = true): string {
    $clean = trim((string) $filename);
    $clean = str_replace(["\0", "\r", "\n"], '', $clean);
    $basename = basename($clean);
    if ($basename === '') {
        return '';
    }
    if ($ensureExtension && !str_ends_with(strtolower($basename), '.md')) {
        $basename .= '.md';
    }
    return $basename;
}

// --- Routing and Logic ---

$page = $_GET['page'] ?? 'login';
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
$itineraryFeedback = $_SESSION['itinerary_feedback'] ?? null;
if (!is_array($itineraryFeedback) || !isset($itineraryFeedback['message'], $itineraryFeedback['type'])) {
    $itineraryFeedback = null;
} else {
    unset($_SESSION['itinerary_feedback']);
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register'])) {
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
            header('Location: admin.php?page=publish');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } elseif (isset($_POST['logout'])) {
        session_destroy();
        header('Location: admin.php');
        exit;
    } elseif (isset($_POST['publish']) || isset($_POST['save_draft'])) {
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
        $type = $_POST['type'] ?? 'Entrada';
        $type = $type === 'Página' ? 'Página' : 'Entrada';
        $filenameInput = trim($_POST['filename'] ?? '');
        $isDraft = isset($_POST['save_draft']);
        $statusValue = $isDraft ? 'draft' : 'published';
        $slugPattern = '/^[a-z0-9-]+$/i';

        if ($filenameInput !== '' && !preg_match($slugPattern, $filenameInput)) {
            $error = 'El slug solo puede contener letras, números y guiones medios.';
        }

        if ($filenameInput === '' && $title !== '') {
            $filenameInput = $title;
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
                $file_content .= "Template: " . ($type === 'Página' ? 'page' : 'post') . "
";
                $file_content .= "Category: " . $category . "
";
                $file_content .= "Date: " . $date . "
";
                $file_content .= "Image: " . $image . "
";
                $file_content .= "Description: " . $description . "
";
                $file_content .= "Status: " . $statusValue . "
";
                $file_content .= "Ordo: " . $ordo . "
";
                $file_content .= "---

";
                $file_content .= $content;

                if (file_put_contents($filepath, $file_content) === false) {
                    $error = 'No se pudo guardar el contenido. Revisa los permisos de la carpeta content/.';
                } else {
                    if (!$isDraft && $type !== 'Página') {
                        admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description);
                    }
                    $redirectTemplate = $isDraft ? 'draft' : ($type === 'Página' ? 'page' : 'single');
                    header('Location: admin.php?page=edit&template=' . $redirectTemplate . '&created=' . urlencode($targetFilename));
                    exit;
                }
            }
        }
    } elseif (isset($_POST['update']) || isset($_POST['publish_draft_entry']) || isset($_POST['publish_draft_page']) || isset($_POST['convert_to_draft'])) {
        $existing_post_data = null;
        $filename = $_POST['filename'] ?? '';
        $title = $_POST['title'] ?? '';
        $category = $_POST['category'] ?? '';
        $date = $_POST['date'] ? date('Y-m-d', strtotime($_POST['date'])) : date('Y-m-d');
        $image = $_POST['image'] ?? '';
        $description = $_POST['description'] ?? '';
        $type = $_POST['type'] ?? null;
        $statusPosted = strtolower(trim($_POST['status'] ?? ''));
        $publishDraftAsEntry = isset($_POST['publish_draft_entry']);
        $publishDraftAsPage = isset($_POST['publish_draft_page']);
        $convertToDraft = isset($_POST['convert_to_draft']);

        // Preserve existing Ordo value on update
        $normalizedFilename = nammu_normalize_filename($filename);
        $previousStatus = 'published';
        if ($normalizedFilename !== '') {
            $existing_post_data = get_post_content($normalizedFilename);
            $ordo = $existing_post_data['metadata']['Ordo'] ?? '';
            $previousStatus = strtolower($existing_post_data['metadata']['Status'] ?? 'published');
            if ($type === null) {
                $currentTemplate = strtolower($existing_post_data['metadata']['Template'] ?? 'post');
                $type = $currentTemplate === 'page' ? 'Página' : 'Entrada';
            }
        } else {
            $ordo = '';
        }

        $type = $type === 'Página' ? 'Página' : 'Entrada';
        if ($publishDraftAsEntry) {
            $type = 'Entrada';
        } elseif ($publishDraftAsPage) {
            $type = 'Página';
        }
        $template = $type === 'Página' ? 'page' : 'post';
        if ($publishDraftAsEntry || $publishDraftAsPage) {
            $status = 'published';
        } elseif ($convertToDraft) {
            $status = 'draft';
        } else {
            $status = $statusPosted === 'draft' ? 'draft' : 'published';
        }

        $newFilenameInput = trim($_POST['new_filename'] ?? '');
        $originalSlugBase = $normalizedFilename !== '' ? pathinfo($normalizedFilename, PATHINFO_FILENAME) : '';
        $originalSlugNormalized = $originalSlugBase !== '' ? nammu_slugify($originalSlugBase) : '';
        $content = $_POST['content'] ?? '';

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
        }

        if ($targetFilename === '') {
            $error = 'No se pudo identificar el archivo a actualizar.';
        }

        if ($error === null) {
            $finalPath = CONTENT_DIR . '/' . $targetFilename;

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
            $file_content .= "Status: " . $status . "
";
            $file_content .= "Ordo: " . $ordo . "
";
            $file_content .= "---

";
            $file_content .= $content;

            $tempPath = tempnam(CONTENT_DIR, 'upd_');
            if ($tempPath === false) {
                $error = 'No se pudo crear un archivo temporal para actualizar el contenido.';
            } elseif (file_put_contents($tempPath, $file_content) === false) {
                @unlink($tempPath);
                $error = 'No se pudo guardar el contenido actualizado. Revisa los permisos de la carpeta content/.';
            } else {
                $writeSucceeded = false;
                if (@rename($tempPath, $finalPath)) {
                    $writeSucceeded = true;
                } else {
                    @unlink($tempPath);
                    if (file_put_contents($finalPath, $file_content) !== false) {
                        $writeSucceeded = true;
                    }
                }

                if ($writeSucceeded) {
                    $shouldAutoShare = false;
                    if ($template === 'post' && $status === 'published') {
                        if ($publishDraftAsEntry || $previousStatus === 'draft') {
                            $shouldAutoShare = true;
                        }
                    }
                    if ($shouldAutoShare) {
                        admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description);
                    }
                    if ($renameRequested && $normalizedFilename !== '' && $normalizedFilename !== $targetFilename) {
                        $previousPath = CONTENT_DIR . '/' . $normalizedFilename;
                        if ($previousPath !== $finalPath && is_file($previousPath)) {
                            @unlink($previousPath);
                        }
                    }
                    $redirectTemplate = $status === 'draft' ? 'draft' : ($template === 'page' ? 'page' : 'single');
                    header('Location: admin.php?page=edit&template=' . $redirectTemplate . '&updated=' . urlencode($targetFilename));
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
        $templateTarget = in_array($templateTarget, ['single', 'page', 'draft'], true) ? $templateTarget : 'single';
        $redirectTemplate = urlencode($templateTarget);
        $networkLabels = [
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
            'facebook' => 'Facebook',
            'twitter' => 'X',
        ];
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
                $template = strtolower($metadata['Template'] ?? 'post');
                if (in_array($template, ['single', 'post'], true)) {
                    $slug = pathinfo($filename, PATHINFO_FILENAME);
                    $slug = $slug !== '' ? $slug : $filename;
                    $title = $metadata['Title'] ?? $slug;
                    $description = $metadata['Description'] ?? '';
                    $allSocialSettings = admin_cached_social_settings();
                    $networkSettings = $allSocialSettings[$networkKey] ?? [];
                    if (!admin_is_social_network_configured($networkKey, $networkSettings)) {
                        $feedback['message'] = 'Configura correctamente ' . $networkLabels[$networkKey] . ' en la pestaña Configuración antes de enviar.';
                    } else {
                        $sent = false;
                        switch ($networkKey) {
                            case 'telegram':
                                $sent = admin_send_post_to_telegram($slug, $title, $description, $networkSettings);
                                break;
                            case 'whatsapp':
                                $sent = admin_send_whatsapp_post($slug, $title, $description, $networkSettings);
                                break;
                            case 'facebook':
                                $sent = admin_send_facebook_post($slug, $title, $description, $networkSettings);
                                break;
                            case 'twitter':
                                $sent = admin_send_twitter_post($slug, $title, $description, $networkSettings);
                                break;
                        }
                        if ($sent) {
                            $feedback = [
                                'type' => 'success',
                                'message' => 'La publicación se envió correctamente a ' . $networkLabels[$networkKey] . '.',
                            ];
                        } else {
                            $feedback['message'] = 'No se pudo enviar la publicación a ' . $networkLabels[$networkKey] . '. Comprueba las credenciales.';
                        }
                    }
                } else {
                    $feedback['message'] = 'Sólo las entradas pueden enviarse a redes sociales.';
                }
            }
        }
        $_SESSION['social_feedback'] = $feedback;
        header('Location: admin.php?page=edit&template=' . $redirectTemplate);
        exit;
    } elseif (isset($_POST['save_itinerary'])) {
        $title = trim($_POST['itinerary_title'] ?? '');
        $description = trim($_POST['itinerary_description'] ?? '');
        $image = trim($_POST['itinerary_image'] ?? '');
        $content = $_POST['itinerary_content'] ?? '';
        $classChoice = $_POST['itinerary_class'] ?? '';
        $classCustom = $_POST['itinerary_class_custom'] ?? '';
        $itineraryQuizPayload = $_POST['itinerary_quiz_payload'] ?? '';
        $usageLogicInput = $_POST['itinerary_usage_logic'] ?? '';
        $slugInput = trim($_POST['itinerary_slug'] ?? '');
        $originalSlugInput = trim($_POST['itinerary_original_slug'] ?? '');
        $mode = $_POST['itinerary_mode'] ?? '';
        if ($slugInput === '' && $title !== '') {
            $slugInput = $title;
        }
        $slug = ItineraryRepository::normalizeSlug($slugInput);
        $originalSlug = ItineraryRepository::normalizeSlug($originalSlugInput);
        $redirectBase = 'admin.php?page=itinerarios';
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
                    @mkdir($targetDir, 0755, true);
                }
            }
        }
        try {
            $classLabel = admin_normalize_itinerary_class_label($classChoice, $classCustom);
            $usageLogic = admin_normalize_itinerary_usage_logic($usageLogicInput);
            $saved = admin_itinerary_repository()->saveItinerary($slug, [
                'Title' => $title,
                'Description' => $description,
                'Image' => $image,
                'ItineraryClass' => $classLabel,
                'UsageLogic' => $usageLogic,
            ], $content, !empty($itineraryQuizResult['data']['questions']) ? $itineraryQuizResult['data'] : null);
            admin_regenerate_itinerary_feed();
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Itinerario guardado correctamente.'];
            header('Location: admin.php?page=itinerarios&itinerary=' . urlencode($saved->getSlug()));
            exit;
        } catch (Throwable $e) {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo guardar el itinerario: ' . $e->getMessage()];
            header('Location: ' . $redirectBase);
            exit;
        }
    } elseif (isset($_POST['save_itinerary_topic']) || isset($_POST['save_itinerary_topic_add'])) {
        $redirectToNewForm = isset($_POST['save_itinerary_topic_add']);
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
        $redirectBase = 'admin.php?page=itinerarios';
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
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Tema guardado correctamente.'];
            if ($redirectToNewForm) {
                header('Location: admin.php?page=itinerarios&itinerary=' . urlencode($itinerarySlug) . '&topic=new');
            } else {
                header('Location: admin.php?page=itinerarios&itinerary=' . urlencode($itinerarySlug) . '&topic=' . urlencode($topicSlug));
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
            admin_regenerate_itinerary_feed();
        } else {
            $_SESSION['itinerary_feedback'] = ['type' => 'danger', 'message' => 'No se pudo borrar la carpeta del itinerario. Revisa los permisos.'];
        }
        header('Location: ' . $redirectBase);
        exit;
    } elseif (isset($_POST['delete_itinerary_topic'])) {
        $itinerarySlug = ItineraryRepository::normalizeSlug($_POST['delete_topic_itinerary_slug'] ?? '');
        $topicSlug = ItineraryRepository::normalizeSlug($_POST['delete_topic_slug'] ?? '');
        $redirectBase = 'admin.php?page=itinerarios';
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
    } elseif (isset($_POST['delete_post'])) {
        $filename = $_POST['delete_filename'] ?? '';
        $filename = trim($filename);
        $templateTarget = $_POST['delete_template'] ?? 'single';
        $templateTarget = in_array($templateTarget, ['single', 'page', 'draft'], true) ? $templateTarget : 'single';
        $templateParam = urlencode($templateTarget);
        if ($filename !== '') {
            // Ensure only filenames from content directory are used
            $basename = basename($filename);
            $filepath = CONTENT_DIR . '/' . $basename;
            if (is_file($filepath)) {
                @unlink($filepath);
                header('Location: admin.php?page=edit&template=' . $templateParam . '&deleted=' . urlencode($basename));
                exit;
            }
        }
        header('Location: admin.php?page=edit&template=' . $templateParam . '&deleted=0');
        exit;
    } elseif (isset($_POST['upload_asset'])) {
        $filesField = $_FILES['asset_files'] ?? ($_FILES['asset_file'] ?? null);
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
                    $errorMessages[] = $originalName . ': supera el tamaño máximo permitido por el servidor.';
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
                    $successCount++;
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
        $_SESSION['asset_feedback'] = $feedback;
        header('Location: admin.php?page=resources');
        exit;
    } elseif (isset($_POST['save_edited_image'])) {
        $image_data = $_POST['image_data'] ?? '';
        $image_name = $_POST['image_name'] ?? '';

        if ($image_data && $image_name) {
            $image_name = preg_replace('/[^A-Za-z0-9\._-]/ ', '', basename($image_name));

            list($type, $image_data) = explode(';', $image_data);
            list(, $image_data)      = explode(',', $image_data);
            $image_data = base64_decode($image_data);

            $target_path = ASSETS_DIR . '/' . $image_name;

            file_put_contents($target_path, $image_data);
        }

        header('Location: admin.php?page=resources');
        exit;
    } elseif (isset($_POST['delete_asset'])) {
        $file_to_delete = $_POST['file_to_delete'] ?? '';
        if ($file_to_delete) {
            $filepath = ASSETS_DIR . '/' . $file_to_delete;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
        header('Location: admin.php?page=resources');
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

                file_put_contents(CONTENT_DIR . '/' . $sorted_post['filename'], $file_content);
                $ordo++;
            }
        }

        header('Location: admin.php?page=configuracion');
        exit;
    } elseif (isset($_POST['save_settings'])) {
        $sort_order = $_POST['sort_order'] ?? 'date';
        $sort_order = $sort_order === 'alpha' ? 'alpha' : 'date';
        $google_fonts_api = trim($_POST['google_fonts_api'] ?? '');
        $site_author = trim($_POST['site_author'] ?? '');
        $site_name = trim($_POST['site_name'] ?? '');

        $social_default_description = trim($_POST['social_default_description'] ?? '');
        $social_home_image = trim($_POST['social_home_image'] ?? '');
        $social_twitter = trim($_POST['social_twitter'] ?? '');
        if ($social_twitter !== '' && $social_twitter[0] === '@') {
            $social_twitter = substr($social_twitter, 1);
        }
        $social_facebook_app_id = trim($_POST['social_facebook_app_id'] ?? '');
        $telegram_token = trim($_POST['telegram_token'] ?? '');
        $telegram_channel = trim($_POST['telegram_channel'] ?? '');
        $telegram_auto = isset($_POST['telegram_auto']) ? 'on' : 'off';
        $whatsapp_token = trim($_POST['whatsapp_token'] ?? '');
        $whatsapp_channel = trim($_POST['whatsapp_channel'] ?? '');
        $whatsapp_recipient = trim($_POST['whatsapp_recipient'] ?? '');
        $whatsapp_auto = isset($_POST['whatsapp_auto']) ? 'on' : 'off';
        $facebook_token = trim($_POST['facebook_token'] ?? '');
        $facebook_channel = trim($_POST['facebook_channel'] ?? '');
        $facebook_auto = isset($_POST['facebook_auto']) ? 'on' : 'off';
        $twitter_token = trim($_POST['twitter_token'] ?? '');
        $twitter_channel = trim($_POST['twitter_channel'] ?? '');
        $twitter_auto = isset($_POST['twitter_auto']) ? 'on' : 'off';

        try {
            $config = load_config_file();

            $config['pages_order_by'] = $sort_order;
            $config['pages_order'] = $sort_order === 'date' ? 'desc' : 'asc';

            if ($google_fonts_api !== '') {
                $config['google_fonts_api'] = $google_fonts_api;
            } else {
                unset($config['google_fonts_api']);
            }

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

            $social = [
                'default_description' => $social_default_description,
                'home_image' => $social_home_image,
                'twitter' => $social_twitter,
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
            if ($whatsapp_token !== '' || $whatsapp_channel !== '' || $whatsapp_recipient !== '' || $whatsapp_auto === 'on') {
                $config['whatsapp'] = [
                    'token' => $whatsapp_token,
                    'channel' => $whatsapp_channel,
                    'recipient' => $whatsapp_recipient,
                    'auto_post' => $whatsapp_auto,
                ];
            } else {
                unset($config['whatsapp']);
            }
            if ($facebook_token !== '' || $facebook_channel !== '' || $facebook_auto === 'on') {
                $config['facebook'] = [
                    'token' => $facebook_token,
                    'channel' => $facebook_channel,
                    'auto_post' => $facebook_auto,
                ];
            } else {
                unset($config['facebook']);
            }
            if ($twitter_token !== '' || $twitter_channel !== '' || $twitter_auto === 'on') {
                $config['twitter'] = [
                    'token' => $twitter_token,
                    'channel' => $twitter_channel,
                    'auto_post' => $twitter_auto,
                ];
            } else {
                unset($config['twitter']);
            }

            save_config_file($config);

        } catch (Throwable $e) {
            $error = "Error guardando la configuración: " . $e->getMessage();
        }

        header('Location: admin.php?page=configuracion');
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
        $footerLogoPosted = $_POST['footer_logo_position'] ?? $defaults['footer_logo'];
        if (!in_array($footerLogoPosted, ['none', 'top', 'bottom'], true)) {
            $footerLogoPosted = $defaults['footer_logo'];
        }
        $logoImage = trim($_POST['logo_image'] ?? '');
        $images = ['logo' => $logoImage];
        $homeColumnsPosted = isset($_POST['home_columns']) ? (int) $_POST['home_columns'] : $defaults['home']['columns'];
        if (!in_array($homeColumnsPosted, [1, 2, 3], true)) {
            $homeColumnsPosted = $defaults['home']['columns'];
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
        $searchDefaults = $defaults['search'] ?? ['mode' => 'single', 'position' => 'footer', 'floating' => 'off'];
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
            'global' => [
                'corners' => $cornerStylePosted,
            ],
            'home' => [
                'columns' => $homeColumnsPosted,
                'per_page' => $homePerPageValue,
                'card_style' => $homeCardStylePosted,
                'full_image_mode' => $homeFullImageModePosted,
                'blocks' => $homeBlocksModePosted,
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

// If logged in, show admin panel
if (is_logged_in()) {
    $page = $_GET['page'] ?? 'publish';
} else {
    $page = $user_exists ? 'login' : 'register';
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

if (is_logged_in() && $page === 'itinerarios') {
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
            'quiz' => admin_quiz_json($itineraryQuiz),
            'quiz_summary' => admin_quiz_summary($itineraryQuiz),
            'mode' => 'existing',
        ];
    } else {
        $itineraryFormData['mode'] = 'new';
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
    }
}

$settings = get_settings();
$socialDefaults = [
    'default_description' => '',
    'home_image' => '',
    'twitter' => '',
    'facebook_app_id' => '',
];
$socialSettings = array_merge($socialDefaults, $settings['social'] ?? []);
$socialDefaultDescription = $socialSettings['default_description'] ?? '';
$socialHomeImage = $socialSettings['home_image'] ?? '';
$socialTwitter = $socialSettings['twitter'] ?? '';
$socialFacebookAppId = $socialSettings['facebook_app_id'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nammu</title>
    <link rel="icon" href="nammu.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Gabarito:wght@700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Roboto', sans-serif;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Gabarito', sans-serif;
        }
        .navbar-brand { color: #1b8eed !important; }
        .nav-link h1 { font-size: 1.2rem; color: #1b8eed; }
        h2 { color: #ea2f28; }
        .container {
            margin-top: 20px;
        }
        .auth-container, .admin-container {
            background-color: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 50px auto;
        }
        .admin-container {
            max-width: 90%;
        }
        .logo {
            display: block;
            margin: 0 auto 20px;
            max-width: 150px;
        }
        .itinerary-form-card {
            position: relative;
        }
        .itinerary-form-card .sticky-save,
        .itinerary-topics-card .sticky-save {
            position: sticky;
            bottom: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0) 0%, #fff 40%);
            padding-top: 1rem;
            text-align: right;
        }
        .topic-quiz-controls {
            gap: 0.5rem;
        }
        .topic-quiz-controls small {
            white-space: nowrap;
        }
        .topic-quiz-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
        }
        .topic-quiz-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1045;
        }
        .topic-quiz-modal__dialog {
            background: #fff;
            border-radius: 12px;
            max-width: 720px;
            width: 90%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 18px 45px rgba(0,0,0,0.3);
        }
        .topic-quiz-modal__header,
        .topic-quiz-modal__footer {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f0f2f5;
        }
        .topic-quiz-modal__footer {
            border-bottom: 0;
            border-top: 1px solid #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        .topic-quiz-modal__body {
            padding: 1.5rem;
            overflow-y: auto;
        }
        .topic-quiz-question {
            border: 1px solid #e3e8ef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #fafbfc;
        }
        .topic-quiz-question h5 {
            font-size: 1rem;
            margin-bottom: 0.75rem;
        }
        .topic-quiz-answers .form-group {
            margin-bottom: 0.5rem;
        }
        .topic-quiz-answers small {
            display: block;
            color: #6c757d;
        }
        .topic-quiz-answer {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .topic-quiz-answer input[type="checkbox"] {
            margin-top: 0.4rem;
        }
        .topic-quiz-question .btn-link {
            padding-left: 0;
        }
        .topic-quiz-modal.d-none,
        .topic-quiz-modal-backdrop.d-none {
            display: none;
        }

        <style>

                body {

                    background-color: #f0f2f5;

                    font-family: 'Roboto', sans-serif;

                }

                h1, h2, h3, h4, h5, h6 {

                    font-family: 'Gabarito', sans-serif;

                }

                .navbar-brand { color: #1b8eed !important; }

                .nav-link h1 { font-size: 1.2rem; color: #1b8eed; }

                h2 { color: #ea2f28; }

                .container {

                    margin-top: 20px;

                }

                .auth-container, .admin-container {

                    background-color: #fff;

                    padding: 40px;

                    border-radius: 10px;

                    box-shadow: 0 4px 8px rgba(0,0,0,0.1);

                    max-width: 500px;

                    margin: 50px auto;

                }

                .admin-container {

                    max-width: 90%;

                }

                .logo {

                    display: block;

                    margin: 0 auto 20px;

                    max-width: 150px;

                }

        
                #imageCanvas {

                    cursor: crosshair;

                }

                .home-layout-options {

                    display: flex;

                    flex-wrap: wrap;

                    gap: 1rem;

                    align-items: stretch;

                }

                .home-layout-option {

                    position: relative;

                    border: 2px solid #e0e4ea;

                    border-radius: 10px;

                    padding: 0.75rem 0.9rem;

                    display: flex;

                    flex-direction: column;

                    align-items: center;

                    gap: 0.6rem;

                    cursor: pointer;

                    transition: border-color 0.2s ease, box-shadow 0.2s ease;

                    background: #f9fbff;

                    min-width: 110px;

                }

                .home-layout-option input[type="radio"] {

                    position: absolute;

                    opacity: 0;

                    inset: 0;

                    pointer-events: none;

                }

                .home-layout-option.active {

                    border-color: #1b8eed;

                    box-shadow: 0 4px 12px rgba(27, 142, 237, 0.15);

                    background: #ffffff;

                }

                .home-layout-option .layout-caption {

                    font-size: 0.9rem;

                    color: #1b1b1b;

                    font-weight: 600;

                }

                .layout-figure {

                    width: 84px;

                    height: 60px;

                    border-radius: 8px;

                    border: 2px solid #d5dbe3;

                    background: linear-gradient(145deg, #f4f7fb, #e7ecf4);

                    padding: 6px;

                    display: grid;

                    gap: 4px;

                }

                .layout-figure.columns-1 {

                    grid-template-columns: 1fr;

                }

                .layout-figure.columns-2 {

                    grid-template-columns: repeat(2, 1fr);

                }

                .layout-figure.columns-3 {

                    grid-template-columns: repeat(3, 1fr);

                }

                .layout-figure .layout-cell {

                    border-radius: 4px;

                    background: rgba(27, 142, 237, 0.25);

                    border: 1px solid rgba(27, 142, 237, 0.35);

                }

                .home-header-options {

                    display: flex;

                    flex-wrap: wrap;

                    gap: 1rem;

                }

                .home-header-option {

                    position: relative;

                    display: flex;

                    align-items: center;

                    gap: 0.8rem;

                    padding: 0.75rem 1rem;

                    border: 2px solid #e0e4ea;

                    border-radius: 12px;

                    cursor: pointer;

                    background: #f9fbff;

                    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;

                    min-width: 230px;

                }

                .home-header-option input[type="radio"] {

                    position: absolute;

                    opacity: 0;

                    inset: 0;

                    pointer-events: none;

                }

                .home-header-option.active {

                    border-color: #1b8eed;

                    box-shadow: 0 4px 12px rgba(27, 142, 237, 0.15);

                    background: #ffffff;

                }

                .home-header-figure {

                    width: 80px;

                    height: 58px;

                    border-radius: 10px;

                    border: 2px solid #d5dbe3;

                    background: linear-gradient(160deg, #f4f7fb, #e7ecf4);

                    display: grid;

                    place-items: center;

                    overflow: hidden;

                }

                .home-header-figure span {

                    display: block;

                    width: 60px;

                    height: 34px;

                    border-radius: 6px;

                    background: rgba(27, 142, 237, 0.35);

                    border: 1px solid rgba(27, 142, 237, 0.5);

                }

                .home-header-figure.header-none span {

                    width: 36px;

                    height: 6px;

                    border-radius: 999px;

                    background: rgba(27, 142, 237, 0.4);

                    border: none;

                }

                .home-header-figure.header-mixed {

                    display: grid;

                    grid-template-rows: 1fr 1fr;

                    gap: 4px;

                }

                .home-header-figure.header-mixed span {

                    position: relative;

                    width: 70px;

                    height: 46px;

                    border-radius: 10px;

                    background: rgba(27, 142, 237, 0.2);

                    border: 1px solid rgba(27, 142, 237, 0.35);

                    overflow: hidden;

                }

                .home-header-figure.header-mixed span::before {

                    content: '';

                    position: absolute;

                    top: 4px;

                    left: 50%;

                    transform: translateX(-50%);

                    width: 48px;

                    height: 20px;

                    border-radius: 8px;

                    background: rgba(27, 142, 237, 0.35);

                    border: 1px solid rgba(27, 142, 237, 0.45);

                }

                .home-header-figure.header-mixed span::after {

                    content: '';

                    position: absolute;

                    bottom: 6px;

                    left: 10px;

                    width: 50px;

                    height: 12px;

                    border-radius: 6px;

                    background: rgba(27, 142, 237, 0.22);

                    border: 1px solid rgba(27, 142, 237, 0.3);

                }

                .home-header-figure.header-graphic.mode-contain span {

                    width: 70px;

                    height: 46px;

                    border-radius: 10px;

                    background: rgba(27, 142, 237, 0.35);

                    border: 1px solid rgba(27, 142, 237, 0.5);

                }

                .home-header-figure.header-graphic.mode-cover span {

                    width: 100%;

                    height: 46px;

                    border-radius: 8px;

                    background: rgba(27, 142, 237, 0.35);

                    border: 1px solid rgba(27, 142, 237, 0.5);

                }

                .home-header-figure.header-text span {

                    width: 70px;

                    height: 44px;

                    border-radius: 8px;

                    background: rgba(27, 142, 237, 0.15);

                    border: 1px dashed rgba(27, 142, 237, 0.35);

                }

                .home-blocks-figure {

                    width: 88px;

                    height: 62px;

                    border-radius: 12px;

                    border: 2px solid #d5dbe3;

                    background: linear-gradient(145deg, #f4f7fb, #e7ecf4);

                    display: grid;

                    grid-template-rows: repeat(4, 1fr);

                    grid-template-columns: repeat(2, 1fr);

                    gap: 4px;

                    padding: 6px;

                    position: relative;

                }

                .home-blocks-figure .block {

                    border-radius: 8px;

                    background: rgba(27, 142, 237, 0.35);

                    border: 1px solid rgba(27, 142, 237, 0.45);

                }

                .home-blocks-figure .block-thumb {

                    grid-column: 1;

                    grid-row: 1 / span 3;

                }

                .home-blocks-figure .block-line {

                    grid-column: 2;

                    background: rgba(27, 142, 237, 0.22);

                    border: 1px solid rgba(27, 142, 237, 0.3);

                }

                .home-blocks-figure .block-line.short {

                    grid-row: 4;

                    background: rgba(27, 142, 237, 0.18);

                }

                .home-blocks-figure.blocks-flat {

                    background: transparent;

                    border-style: dashed;

                    border-color: #d0d6df;

                }

                .home-blocks-figure.blocks-flat .block {

                    background: rgba(27, 142, 237, 0.22);

                    border-style: dashed;

                }

                .home-corner-figure {

                    width: 80px;

                    height: 60px;

                    border: 2px solid #d5dbe3;

                    border-radius: 12px;

                    background: linear-gradient(150deg, #f4f7fb, #e7ecf4);

                    display: grid;

                    place-items: center;

                }

                .home-corner-figure span {

                    display: block;

                    width: 54px;

                    height: 38px;

                    background: rgba(27, 142, 237, 0.3);

                    border: 1px solid rgba(27, 142, 237, 0.45);

                    border-radius: 12px;

                }

                .home-corner-figure.corner-square span {

                    border-radius: 0;

                }

                .home-header-text-figure {

                    width: 92px;

                    height: 62px;

                    border: 2px solid #d5dbe3;

                    border-radius: 12px;

                    background: linear-gradient(145deg, #f4f7fb, #e7ecf4);

                    display: grid;

                    grid-template-rows: repeat(3, 1fr);

                    gap: 6px;

                    padding: 10px;

                }

                .home-header-text-figure .text-line {

                    display: block;

                    border-radius: 8px;

                    background: rgba(27, 142, 237, 0.28);

                    border: 1px solid rgba(27, 142, 237, 0.35);

                }

                .home-header-text-figure .text-line.title {

                    height: 14px;

                }

                .home-header-text-figure .text-line.subtitle {

                    height: 10px;

                    background: rgba(27, 142, 237, 0.2);

                }

                .home-header-text-figure .text-line.tagline {

                    height: 12px;

                    background: rgba(27, 142, 237, 0.18);

                }

                .home-header-text-figure.text-header-plain {

                    background: transparent;

                    border-style: dashed;

                    border-color: #d0d6df;

                }

                .home-header-text-figure.text-header-plain .text-line {

                    background: rgba(27, 142, 237, 0.22);

                    border-style: dashed;

                }

                .home-header-option .home-header-text {

                    display: flex;

                    flex-direction: column;

                    gap: 0.2rem;

                }

                .home-header-option .home-header-text strong {

                    font-size: 0.95rem;

                    color: #1c2c3c;

                }

                .home-header-option .home-header-text small {

                    color: #607087;

                }

                .home-card-style-options {

                    display: flex;

                    flex-wrap: wrap;

                    gap: 1rem;

                }

                .home-card-style-option {

                    position: relative;

                    display: flex;

                    align-items: center;

                    gap: 0.85rem;

                    padding: 0.75rem 1rem;

                    border: 2px solid #e0e4ea;

                    border-radius: 12px;

                    cursor: pointer;

                    background: #f9fbff;

                    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;

                    min-width: 240px;

                }

                .home-card-style-option input[type="radio"] {

                    position: absolute;

                    opacity: 0;

                    inset: 0;

                    pointer-events: none;

                }

                .home-card-style-option.active {

                    border-color: #1b8eed;

                    box-shadow: 0 4px 12px rgba(27, 142, 237, 0.15);

                    background: #ffffff;

                }

                .home-card-style-option .card-style-figure {

                    width: 90px;

                    height: 60px;

                    border-radius: 10px;

                    border: 2px solid #d5dbe3;

                    background: linear-gradient(145deg, #f4f7fb, #e7ecf4);

                    display: grid;

                    grid-template-columns: 1fr 1fr;

                    gap: 6px;

                    padding: 6px;

                    position: relative;

                }

                .home-card-style-option .card-style-figure .card-thumb {

                    grid-row: 1 / span 2;

                    border-radius: 8px;

                    background: rgba(27, 142, 237, 0.35);

                    border: 1px solid rgba(27, 142, 237, 0.5);

                }

                .home-card-style-option .card-style-figure .card-lines {

                    display: grid;

                    grid-template-rows: repeat(3, 1fr);

                    gap: 4px;

                }

                .home-card-style-option .card-style-figure .line {

                    display: block;

                    border-radius: 999px;

                    background: rgba(27, 142, 237, 0.3);

                }

                .home-card-style-option .card-style-figure .line.primary {

                    background: rgba(27, 142, 237, 0.45);

                }

                .home-card-style-option .card-style-figure .line.meta {

                    background: rgba(27, 142, 237, 0.25);

                }

                .home-card-style-option .card-style-figure .line.body {

                    background: rgba(27, 142, 237, 0.35);

                }

                .home-card-style-option .full-image-mode-figure {

                    width: 104px;

                    height: 64px;

                    border-radius: 10px;

                    border: 2px solid #d5dbe3;

                    background: linear-gradient(145deg, #f4f7fb, #e7ecf4);

                    display: flex;

                    gap: 6px;

                    padding: 6px;

                    box-sizing: border-box;

                }

                .home-card-style-option .full-image-mode-figure .mode-column {

                    display: flex;

                    flex-direction: column;

                    justify-content: flex-end;

                    gap: 4px;

                    flex: 1;

                }

                .home-card-style-option .full-image-mode-figure .mode-thumb {

                    width: 100%;

                    background: rgba(27, 142, 237, 0.35);

                    border: 1px solid rgba(27, 142, 237, 0.5);

                    border-radius: 8px;

                    transition: all 0.2s ease;

                }

                .home-card-style-option .full-image-mode-figure .mode-thumb.primary {

                    background: rgba(27, 142, 237, 0.45);

                }

                .home-card-style-option .full-image-mode-figure.full-mode-natural .mode-thumb.primary {

                    height: 32px;

                }

                .home-card-style-option .full-image-mode-figure.full-mode-natural .mode-thumb.secondary {

                    height: 20px;

                }

                .home-card-style-option .full-image-mode-figure.full-mode-crop .mode-thumb.primary,

                .home-card-style-option .full-image-mode-figure.full-mode-crop .mode-thumb.secondary {

                    height: 28px;

                }

                .home-card-style-option .full-image-mode-figure .mode-line {

                    display: block;

                    height: 4px;

                    border-radius: 999px;

                    background: rgba(27, 142, 237, 0.28);

                }

                .home-card-style-option .full-image-mode-figure .mode-line.title {

                    background: rgba(27, 142, 237, 0.45);

                }

                .home-card-style-option .card-style-text {

                    display: flex;

                    flex-direction: column;

                    gap: 0.15rem;

                }

                .home-card-style-option .card-style-text strong {

                    font-size: 0.95rem;

                    color: #1c2c3c;

                }

                .home-card-style-option .card-style-text small {

                    color: #607087;

                }

                .search-mode-figure,
                .search-position-figure {
                    width: 90px;
                    height: 60px;
                    border-radius: 12px;
                    border: 2px solid #d5dbe3;
                    background: linear-gradient(145deg, #f4f7fb, #e7ecf4);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: relative;
                }
                .search-mode-figure .search-box,
                .search-position-figure .search-box {
                    width: 70px;
                    height: 26px;
                    border-radius: 999px;
                    background: #ffffff;
                    border: 1px solid rgba(27, 142, 237, 0.35);
                    display: flex;
                    align-items: center;
                    padding: 0 8px;
                    gap: 6px;
                }
                .search-mode-figure .icon,
                .search-position-figure .icon {
                    width: 16px;
                    height: 16px;
                    border: 2px solid rgba(27, 142, 237, 0.75);
                    border-radius: 50%;
                    position: relative;
                }
                .search-mode-figure .icon::after,
                .search-position-figure .icon::after {
                    content: '';
                    width: 8px;
                    height: 2px;
                    background: rgba(27, 142, 237, 0.75);
                    position: absolute;
                    right: -5px;
                    bottom: -2px;
                    transform: rotate(35deg);
                    border-radius: 999px;
                }
                .search-mode-figure .line,
                .search-position-figure .line {
                    flex: 1;
                    height: 4px;
                    background: rgba(27, 142, 237, 0.25);
                    border-radius: 999px;
                }
                .search-mode-figure.search-none .search-box {
                    opacity: 0.2;
                }
                .search-mode-figure.search-single::after {
                    content: '';
                    position: absolute;
                    bottom: 5px;
                    width: 28px;
                    height: 6px;
                    border-radius: 999px;
                    background: rgba(27,142,237,0.25);
                }
                .search-mode-figure.search-home::before,
                .search-mode-figure.search-both::before {
                    content: '';
                    position: absolute;
                    top: 8px;
                    left: 8px;
                    width: 24px;
                    height: 10px;
                    border-radius: 6px;
                    background: rgba(27,142,237,0.25);
                }
                .search-mode-figure.search-both::after {
                    content: '';
                    position: absolute;
                    bottom: 8px;
                    width: 24px;
                    height: 10px;
                    border-radius: 6px;
                    background: rgba(27,142,237,0.25);
                }
                .search-floating-figure {
                    width: 90px;
                    height: 60px;
                    border-radius: 12px;
                    border: 2px solid #d5dbe3;
                    background: linear-gradient(145deg, #f4f7fb, #e7ecf4);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                    position: relative;
                }
                .search-floating-figure .search-box {
                    width: 52px;
                    height: 20px;
                    border-radius: 999px;
                    background: #ffffff;
                    border: 1px solid rgba(27, 142, 237, 0.35);
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                }
                .search-floating-figure .icon {
                    width: 14px;
                    height: 14px;
                    border: 2px solid rgba(27, 142, 237, 0.75);
                    border-radius: 50%;
                    position: relative;
                }
                .search-floating-figure .icon::after {
                    content: '';
                    width: 7px;
                    height: 2px;
                    background: rgba(27, 142, 237, 0.75);
                    position: absolute;
                    right: -4px;
                    bottom: -2px;
                    transform: rotate(35deg);
                    border-radius: 999px;
                }
                .search-floating-figure .search-hint {
                    width: 70%;
                    height: 8px;
                    border-radius: 999px;
                    background: rgba(27,142,237,0.25);
                }
                .search-floating-figure.search-float-off .search-box,
                .search-floating-figure.search-float-off .search-hint {
                    opacity: 0.25;
                }
                .footer-logo-figure {
                    width: 90px;
                    height: 60px;
                    border-radius: 12px;
                    border: 2px solid #d5dbe3;
                    background: linear-gradient(145deg, #f4f7fb, #e7ecf4);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: relative;
                }
                .footer-logo-figure .footer-logo-area {
                    width: 68px;
                    height: 34px;
                    border-radius: 12px;
                    background: #ffffff;
                    border: 1px solid rgba(27,142,237,0.3);
                    position: absolute;
                    left: 50%;
                    top: 50%;
                    transform: translate(-50%, -50%);
                }
                .footer-logo-figure .footer-logo-dot {
                    width: 22px;
                    height: 22px;
                    border-radius: 50%;
                    background: rgba(27,142,237,0.6);
                    border: 2px solid #ffffff;
                    position: absolute;
                    left: 50%;
                    transform: translateX(-50%);
                    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
                }
                .footer-logo-figure.logo-top .footer-logo-area {
                    bottom: 10px;
                    top: auto;
                    transform: translateX(-50%);
                }
                .footer-logo-figure.logo-top .footer-logo-dot {
                    top: 8px;
                }
                .footer-logo-figure.logo-bottom .footer-logo-area {
                    top: 10px;
                    transform: translateX(-50%);
                }
                .footer-logo-figure.logo-bottom .footer-logo-dot {
                    bottom: 8px;
                }
                .footer-logo-figure.logo-none .footer-logo-dot {
                    display: none;
                }
                .footer-logo-figure.logo-none .footer-logo-area {
                    top: 50%;
                    transform: translate(-50%, -50%);
                }

                .pagination.pagination-break,
                .modal .pagination {

                    flex-wrap: wrap;

                    justify-content: center;

                }

                .pagination .page-break {

                    flex-basis: 100%;

                    height: 0;

                    list-style: none;

                }

                .card-style-full .card-thumb {

                    grid-column: 1 / -1;

                    grid-row: 1;

                    border-radius: 6px;

                    height: 100%;

                }

                .card-style-full .card-lines {

                    grid-column: 1 / -1;

                    grid-row: 2;

                }

                .card-style-square-right .card-thumb {

                    grid-column: 2;

                    width: 34px;

                    height: 34px;

                    display: block;

                    background: rgba(27, 142, 237, 0.35);

                    border: 1px solid rgba(27, 142, 237, 0.5);

                    justify-self: center;

                    align-self: center;

                    border-radius: 8px;

                }

                .card-style-square-right .card-lines {

                    grid-column: 1;

                }

                .card-style-square-tall-right {

                    grid-template-columns: 1fr 0.85fr;

                }

                .card-style-square-tall-right .card-thumb {

                    grid-column: 2;

                    grid-row: 1 / -1;

                    width: 34px;

                    height: calc(100% - 10px);

                    display: block;

                    background: rgba(27, 142, 237, 0.35);

                    border: 1px solid rgba(27, 142, 237, 0.5);

                    justify-self: center;

                    align-self: center;

                    border-radius: 10px;

                }

                .card-style-square-tall-right .card-lines {

                    grid-column: 1;

                    grid-row: 1 / -1;

                    display: flex;

                    flex-direction: column;

                    justify-content: space-between;

                }

                .card-style-circle-right .card-thumb {

                    grid-column: 2;

                    width: 34px;

                    height: 34px;

                    display: block;

                    background: rgba(27, 142, 237, 0.35);

                    border: 1px solid rgba(27, 142, 237, 0.5);

                    justify-self: center;

                    align-self: center;

                    border-radius: 50%;

                }

                .card-style-circle-right .card-lines {

                    grid-column: 1;

                }

                .card-style-circle-right .card-thumb + .card-lines {

                    align-self: center;

                }

                .header-graphic-figure {

                    width: 92px;

                    height: 58px;

                    border-radius: 12px;

                    border: 2px solid #d5dbe3;

                    background: linear-gradient(145deg, #f4f7fb, #e7ecf4);

                    display: grid;

                    place-items: center;

                    padding: 8px;

                }

                .header-graphic-figure .graphic-preview {

                    display: block;

                    border-radius: 10px;

                    background: rgba(27, 142, 237, 0.35);

                    border: 1px solid rgba(27, 142, 237, 0.45);

                    width: 100%;

                    height: 32px;

                }

                .header-graphic-figure.contain .graphic-preview {

                    width: 60%;

                }

                .header-graphic-figure.cover .graphic-preview {

                    width: 100%;

                }

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

                        <a class="navbar-brand" href="#"><img src="nammu.png" alt="Nammu Logo" style="max-width: 100px;"></a>
                        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Mostrar navegación">
                            <span class="navbar-toggler-icon"></span>
                        </button>

                        <div class="collapse navbar-collapse" id="adminNavbar">

                            <ul class="navbar-nav mr-auto">

                                <li class="nav-item <?= $page === 'publish' ? 'active' : '' ?>">

                                    <a class="nav-link" href="?page=publish"><h1>Publicar</h1></a>

                                </li>

                                <li class="nav-item <?= $page === 'edit' ? 'active' : '' ?>">

                                    <a class="nav-link" href="?page=edit"><h1>Editar</h1></a>

                                </li>

                                <li class="nav-item <?= $page === 'resources' ? 'active' : '' ?>">

                                    <a class="nav-link" href="?page=resources"><h1>Recursos</h1></a>

                                </li>

                                <li class="nav-item <?= $page === 'template' ? 'active' : '' ?>">

                                    <a class="nav-link" href="?page=template"><h1>Plantilla</h1></a>

                                </li>

                                <li class="nav-item <?= $page === 'itinerarios' ? 'active' : '' ?>">

                                    <a class="nav-link" href="?page=itinerarios"><h1>Itinerarios</h1></a>

                                </li>

                                <li class="nav-item <?= $page === 'configuracion' ? 'active' : '' ?>">

                                    <a class="nav-link" href="?page=configuracion"><h1>Configuración</h1></a>

                                </li>

                            </ul>

                            <form method="post" class="form-inline my-2 my-lg-0 ml-lg-3">

                                <button type="submit" name="logout" class="btn btn-outline-danger my-2 my-sm-0">Cerrar sesión</button>

                            </form>

                        </div>

                    </nav>

        

                    <div class="tab-content">

                        <?php if ($page === 'publish'): ?>

                            <div class="tab-pane active">

                                <h2>Publicar</h2>

                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>

                                <form method="post">

                                    <div class="form-group">

                                        <label for="title">Título</label>

                                        <input type="text" name="title" id="title" class="form-control" required>

                                    </div>

                                    <div class="form-group">

                                        <label for="type">Tipo</label>

                                        <select name="type" id="type" class="form-control">

                                            <option value="Entrada">Entrada</option>

                                            <option value="Página">Página</option>

                                        </select>

                                    </div>

                                    <div class="form-group">

                                        <label for="category">Categoría</label>

                                        <input type="text" name="category" id="category" class="form-control">

                                    </div>

                                    <div class="form-group">

                                        <label for="date">Fecha</label>

                                        <input type="date" name="date" id="date" class="form-control" value="<?= date('Y-m-d') ?>" required>

                                    </div>

                                    <div class="form-group">

                                        <label for="image">Imagen</label>

                                        <div class="input-group">

                                            <input type="text" name="image" id="image" class="form-control" readonly>

                                            <div class="input-group-append">

                                                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="image" data-target-prefix="">Seleccionar imagen</button>

                                            </div>

                                        </div>

                                    </div>

                                    <div class="form-group">

                                        <label for="description">Entradilla</label>

                                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>

                                    </div>

                                    <div class="form-group">

                                        <label for="content_publish">Contenido (Markdown)</label>
                                        <div class="btn-toolbar markdown-toolbar mb-2 flex-wrap" role="toolbar" aria-label="Atajos de Markdown" data-markdown-toolbar data-target="#content_publish">
                                            <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="bold" title="Negrita" aria-label="Negrita"><strong>B</strong></button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="italic" title="Cursiva" aria-label="Cursiva"><em>I</em></button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="strike" title="Tachado" aria-label="Tachado">S̶</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="code" title="Código en línea" aria-label="Código en línea">&lt;/&gt;</button>
                                            </div>
                                            <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="link" title="Enlace" aria-label="Enlace">Link</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="quote" title="Cita" aria-label="Cita">&gt;</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="sup" title="Superíndice" aria-label="Superíndice">x<sup>2</sup></button>
                                            </div>
                                            <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="ul" title="Lista" aria-label="Lista">•</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="ol" title="Lista numerada" aria-label="Lista numerada">1.</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="heading" title="Encabezado" aria-label="Encabezado">H2</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="code-block" title="Bloque de código" aria-label="Bloque de código">{ }</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="hr" title="Separador" aria-label="Separador">—</button>
                                            </div>
                                        </div>

                                        <textarea name="content" id="content_publish" class="form-control" rows="15" data-markdown-editor="1"></textarea>

                                        <button type="button" class="btn btn-secondary mt-2" data-toggle="modal" data-target="#imageModal" data-target-type="editor" data-target-editor="#content_publish">Insertar recurso</button>
                                        <small class="form-text text-muted mt-1">Inserta en la entrada imágenes, vídeos o archivos PDF.</small>

                                    </div>

                                    <div class="form-group">

                                        <label for="filename">Slug del post (nombre de archivo sin .md)</label>

                                        <input type="text"
                                               name="filename"
                                               id="filename"
                                               class="form-control"
                                               required
                                               pattern="[a-z0-9-]+"
                                               inputmode="text"
                                               autocomplete="off"
                                               autocapitalize="none"
                                               spellcheck="false"
                                               data-slug-input="1"
                                               placeholder="mi-slug">

                                    </div>

                                    <div class="mt-3">
                                        <button type="submit" name="publish" class="btn btn-primary mr-2">Publicar</button>
                                        <button type="submit" name="save_draft" value="1" class="btn btn-outline-secondary">Guardar como borrador</button>
                                    </div>

                                </form>

                            </div>

                        <?php elseif ($page === 'edit'): ?>

                            <div class="tab-pane active">

                                <?php
                                $templateFilter = $_GET['template'] ?? 'single';
                                $allowedFilters = ['single', 'page', 'draft'];
                                if (!in_array($templateFilter, $allowedFilters, true)) {
                                    $templateFilter = 'single';
                                }
                                $currentTypeLabel = [
                                    'single' => 'Entradas',
                                    'page' => 'Páginas',
                                    'draft' => 'Borradores',
                                ][$templateFilter];
                                $searchQuery = trim($_GET['q'] ?? '');
                                $searchQueryParam = $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '';
                                ?>

                <h2>Editar</h2>
                <?php if ($socialFeedback !== null): ?>
                    <div class="alert alert-<?= $socialFeedback['type'] === 'success' ? 'success' : 'warning' ?>">
                        <?= htmlspecialchars($socialFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                                <form class="form-inline mb-3 edit-search-form" method="get">
                                    <input type="hidden" name="page" value="edit">
                                    <input type="hidden" name="template" value="<?= htmlspecialchars($templateFilter, ENT_QUOTES, 'UTF-8') ?>">
                                    <label for="edit-search-input" class="sr-only">Buscar</label>
                                    <input type="search"
                                        class="form-control form-control-sm mr-2"
                                        id="edit-search-input"
                                        name="q"
                                        placeholder="Buscar por título, descripción o archivo"
                                        value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
                                        style="min-width: 220px;">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary mr-2">Buscar</button>
                                    <?php if ($searchQuery !== ''): ?>
                                        <a class="btn btn-sm btn-link" href="?page=edit&template=<?= htmlspecialchars($templateFilter, ENT_QUOTES, 'UTF-8') ?>">Limpiar</a>
                                    <?php endif; ?>
                                </form>

                                <div class="btn-group mb-3" role="group" aria-label="Filtrar por tipo">
                                    <a href="?page=edit&template=single<?= $searchQueryParam ?>" class="btn btn-sm btn-outline-primary <?= $templateFilter === 'single' ? 'active' : '' ?>">Entradas</a>
                                    <a href="?page=edit&template=page<?= $searchQueryParam ?>" class="btn btn-sm btn-outline-primary <?= $templateFilter === 'page' ? 'active' : '' ?>">Páginas</a>
                                    <a href="?page=edit&template=draft<?= $searchQueryParam ?>" class="btn btn-sm btn-outline-primary <?= $templateFilter === 'draft' ? 'active' : '' ?>">Borradores</a>
                                </div>

                                <p class="text-muted">Mostrando <?= strtolower($currentTypeLabel) ?>.</p>
                                <?php
                                $networkConfigs = [
                                    'telegram' => $settings['telegram'] ?? [],
                                    'whatsapp' => $settings['whatsapp'] ?? [],
                                    'facebook' => $settings['facebook'] ?? [],
                                    'twitter' => $settings['twitter'] ?? [],
                                ];
                                $networkLabels = [
                                    'telegram' => 'Telegram',
                                    'whatsapp' => 'WhatsApp',
                                    'facebook' => 'Facebook',
                                    'twitter' => 'X',
                                ];
                                ?>

                                <table class="table table-striped">

                                    <thead>

                                        <tr>

                                            <th>Título</th>

                                            <th>Descripción</th>

                                            <th>Fecha</th>

                                            <th>Nombre de archivo</th>

                                            <th class="text-center">Redes</th>

                                            <th></th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php

                                        $current_page = $_GET['p'] ?? 1;

                                        $posts_data = get_posts($current_page, 16, $templateFilter, $searchQuery);

                                        if (empty($posts_data['posts'])):
                                        ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No hay <?= strtolower($currentTypeLabel) ?> disponibles.</td>
                                            </tr>
                                        <?php
                                        else:
                                            foreach ($posts_data['posts'] as $post):
                                        ?>

                                            <tr>

                                                <td><?= htmlspecialchars($post['title']) ?></td>

                                                <td><?= htmlspecialchars($post['description']) ?></td>

                                                <td><?= htmlspecialchars($post['date']) ?></td>

                                                <?php
                                                $postSlug = pathinfo($post['filename'], PATHINFO_FILENAME);
                                                $postLink = admin_public_post_url($postSlug);
                                                ?>
                                                <td><a href="<?= htmlspecialchars($postLink, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($post['filename']) ?></a></td>

                                                <td class="text-center">
                                                    <?php
                                                    $availableNetworks = [];
                                                    foreach ($networkConfigs as $key => $cfg) {
                                                        if (admin_is_social_network_configured($key, $cfg)) {
                                                            $availableNetworks[] = $key;
                                                        }
                                                    }
                                                    ?>
                                                    <?php if (!empty($availableNetworks) && in_array($templateFilter, ['single', 'draft'], true)): ?>
                                                        <?php foreach ($availableNetworks as $networkKey): ?>
                                                            <form method="post" class="d-inline-block mb-1">
                                                                <input type="hidden" name="social_network" value="<?= htmlspecialchars($networkKey, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="social_filename" value="<?= htmlspecialchars($post['filename'], ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="social_template" value="<?= htmlspecialchars($templateFilter, ENT_QUOTES, 'UTF-8') ?>">
                                                                <button type="submit" name="send_social_post" class="btn btn-sm btn-outline-primary">
                                                                    <?= htmlspecialchars($networkLabels[$networkKey] ?? ucfirst($networkKey), ENT_QUOTES, 'UTF-8') ?>
                                                                </button>
                                                            </form>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="text-right">
                                                    <div class="d-flex flex-column align-items-end">
                                                        <a href="?page=edit-post&file=<?= urlencode($post['filename']) ?>" class="btn btn-sm btn-primary mb-2">Editar</a>
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-danger"
                                                                data-delete-file="<?= htmlspecialchars($post['filename'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-delete-title="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-delete-type="<?= htmlspecialchars($templateFilter, ENT_QUOTES, 'UTF-8') ?>"
                                                                data-toggle="modal"
                                                                data-target="#deletePostModal">
                                                            Borrar
                                                        </button>
                                                    </div>
                                                </td>

                                            </tr>

                                        <?php
                                            endforeach;
                                        endif;
                                        ?>

                                    </tbody>

                                </table>

        

                                <nav aria-label="Page navigation">

                                    <ul class="pagination pagination-break">

                                        <?php
                                        $pageGroupSize = 16;
                                        for ($i = 1; $i <= $posts_data['pages']; $i++): ?>

                                            <li class="page-item <?= $i == $posts_data['current_page'] ? 'active' : '' ?>">

                                                <a class="page-link" href="?page=edit&template=<?= urlencode($templateFilter) ?>&p=<?= $i ?><?= $searchQueryParam ?>"><?= $i ?></a>

                                            </li>

                                            <?php if ($i % $pageGroupSize === 0 && $i < $posts_data['pages']): ?>
                                                <li class="page-break"></li>
                                            <?php endif; ?>

                                        <?php endfor; ?>

                                    </ul>

                                </nav>

                            </div>

                        <?php elseif ($page === 'edit-post'): ?>

                            <div class="tab-pane active">

                                <?php

                                $requestedFile = $_GET['file'] ?? '';
                                $safeEditFilename = nammu_normalize_filename($requestedFile);

                                $post_data = $safeEditFilename !== '' ? get_post_content($safeEditFilename) : null;

                                if ($post_data):
                                    $currentTemplateValue = strtolower($post_data['metadata']['Template'] ?? 'post');
                                    $currentTypeValue = $currentTemplateValue === 'page' ? 'Página' : 'Entrada';
                                    $editHeading = $currentTypeValue === 'Página' ? 'Editar Página' : 'Editar Entrada';
                                    $currentStatusValue = strtolower($post_data['metadata']['Status'] ?? 'published');
                                    if (!in_array($currentStatusValue, ['draft', 'published'], true)) {
                                        $currentStatusValue = 'published';
                                    }
                                    $isDraftEditing = $currentStatusValue === 'draft';
                                ?>

                                <h2><?= $editHeading ?></h2>

                                    <form method="post">

                                    <input type="hidden" name="filename" value="<?= htmlspecialchars($safeEditFilename, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($currentStatusValue, ENT_QUOTES, 'UTF-8') ?>">

                                    <div class="form-group">

                                        <label for="title">Título</label>

                                        <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars($post_data['metadata']['Title'] ?? '') ?>" required>

                                    </div>

                                    <div class="form-group">

                                        <label for="type">Tipo</label>

                                        <select name="type" id="type" class="form-control">

                                            <option value="Entrada" <?= $currentTypeValue === 'Entrada' ? 'selected' : '' ?>>Entrada</option>

                                            <option value="Página" <?= $currentTypeValue === 'Página' ? 'selected' : '' ?>>Página</option>

                                        </select>

                                    </div>

                                    <div class="form-group">

                                        <label for="category">Categoría</label>

                                        <input type="text" name="category" id="category" class="form-control" value="<?= htmlspecialchars($post_data['metadata']['Category'] ?? '') ?>">

                                    </div>

                                    <div class="form-group">

                                        <label for="date">Fecha</label>

                                        <input type="date" name="date" id="date" class="form-control" value="<?= htmlspecialchars(format_date_for_input($post_data['metadata']['Date'] ?? null), ENT_QUOTES, 'UTF-8') ?>" required>

                                    </div>

                                    <div class="form-group">

                                        <label for="image">Imagen</label>

                                        <div class="input-group">

                                            <input type="text" name="image" id="image" class="form-control" value="<?= htmlspecialchars($post_data['metadata']['Image'] ?? '') ?>" readonly>

                                            <div class="input-group-append">

                                                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="image" data-target-prefix="">Seleccionar imagen</button>

                                            </div>

                                        </div>

                                    </div>

                                    <div class="form-group">

                                        <label for="description">Descripción</label>

                                        <textarea name="description" id="description" class="form-control" rows="3"><?= htmlspecialchars($post_data['metadata']['Description'] ?? '') ?></textarea>

                                    </div>

                                    <div class="form-group">

                                        <label for="content_edit">Contenido (Markdown)</label>
                                        <div class="btn-toolbar markdown-toolbar mb-2 flex-wrap" role="toolbar" aria-label="Atajos de Markdown" data-markdown-toolbar data-target="#content_edit">
                                            <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="bold" title="Negrita" aria-label="Negrita"><strong>B</strong></button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="italic" title="Cursiva" aria-label="Cursiva"><em>I</em></button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="strike" title="Tachado" aria-label="Tachado">S̶</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="code" title="Código en línea" aria-label="Código en línea">&lt;/&gt;</button>
                                            </div>
                                            <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="link" title="Enlace" aria-label="Enlace">Link</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="quote" title="Cita" aria-label="Cita">&gt;</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="sup" title="Superíndice" aria-label="Superíndice">x<sup>2</sup></button>
                                            </div>
                                            <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="ul" title="Lista" aria-label="Lista">•</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="ol" title="Lista numerada" aria-label="Lista numerada">1.</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="heading" title="Encabezado" aria-label="Encabezado">H2</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="code-block" title="Bloque de código" aria-label="Bloque de código">{ }</button>
                                                <button type="button" class="btn btn-outline-secondary" data-md-action="hr" title="Separador" aria-label="Separador">—</button>
                                            </div>
                                        </div>

                                        <textarea name="content" id="content_edit" class="form-control" rows="15" data-markdown-editor="1"><?= htmlspecialchars($post_data['content']) ?></textarea>

                                        <button type="button" class="btn btn-secondary mt-2" data-toggle="modal" data-target="#imageModal" data-target-type="editor" data-target-editor="#content_edit">Insertar recurso</button>
                                        <small class="form-text text-muted mt-1">Inserta en la entrada imágenes, vídeos o archivos PDF.</small>

                                    </div>

                                    <div class="form-group">
                                        <label for="new_filename">Slug del post (nombre de archivo sin .md)</label>
                                        <input type="text"
                                               name="new_filename"
                                               id="new_filename"
                                               class="form-control"
                                               pattern="[a-z0-9-]+"
                                               inputmode="text"
                                               autocomplete="off"
                                               autocapitalize="none"
                                               spellcheck="false"
                                               data-slug-input="1"
                                               value="<?= htmlspecialchars(pathinfo($safeEditFilename, PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8') ?>">
                                        <small class="form-text text-muted">Opcional. Úsalo para cambiar la URL del contenido.</small>
                                    </div>

                                    <div class="mt-3">
                                        <button type="submit" name="update" class="btn btn-primary">Actualizar</button>
                                        <?php if ($isDraftEditing): ?>
                                            <button type="submit" name="publish_draft_entry" value="1" class="btn btn-success ml-2">Publicar como entrada</button>
                                            <button type="submit" name="publish_draft_page" value="1" class="btn btn-success ml-2">Publicar como página</button>
                                        <?php elseif ($currentTypeValue === 'Entrada' && !$isDraftEditing): ?>
                                            <button type="submit" name="convert_to_draft" value="1" class="btn btn-outline-secondary ml-2">Pasar a borrador</button>
                                        <?php endif; ?>
                                    </div>

                                </form>

                                <?php else: ?>

                                    <div class="alert alert-danger">Contenido no encontrado.</div>

                                <?php endif; ?>

                            </div>

                        <?php elseif ($page === 'resources'): ?>

                            <div class="tab-pane active">

                                <h2>Recursos</h2>
                                <?php if ($assetFeedback !== null): ?>
                                    <div class="alert alert-<?= $assetFeedback['type'] === 'success' ? 'success' : 'warning' ?>">
                                        <?= htmlspecialchars($assetFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>

                                

                                <h4>Subir nuevo archivo</h4>

                                <form method="post" enctype="multipart/form-data">

                                    <div class="form-group">

                                        <input type="file" name="asset_files[]" id="asset_file" class="form-control-file" multiple>

                                    </div>

                                    <button type="submit" name="upload_asset" class="btn btn-primary">Subir</button>

                                </form>

        

                                <hr>

        

                                <h4>Archivos existentes</h4>

                                <div class="row">

                                    <?php

                                    $current_page = $_GET['p'] ?? 1;

                                    $media_data = get_media_items($current_page, 40);

                                    foreach ($media_data['items'] as $media):

                                        $relative_path = $media['relative'];
                                        $ext = $media['extension'];
                                        $is_image = $media['type'] === 'image';
                                        $is_video = $media['type'] === 'video';

                                    ?>

                                        <div class="col-md-3 mb-3">

                                            <div class="card">

                                                <?php if ($is_image): ?>

                                                    <img src="assets/<?= htmlspecialchars($relative_path) ?>" class="card-img-top" style="height: 150px; object-fit: cover;">

                                                <?php elseif ($is_video): ?>

                                                    <video class="card-img-top" style="height: 150px; object-fit: cover;" muted preload="metadata" controls>
                                                        <source src="assets/<?= htmlspecialchars($relative_path) ?>" type="<?= htmlspecialchars($media['mime'], ENT_QUOTES, 'UTF-8') ?>">
                                                    </video>

                                                <?php else: ?>

                                                    <div class="card-body text-center">

                                                        <i class="fas fa-file" style="font-size: 4rem;"></i>

                                                    </div>

                                                <?php endif; ?>

                                                <div class="card-body">

                                                    <p class="card-text" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($relative_path) ?></p>

                                                    <form method="post" onsubmit="return confirm('¿Estás seguro de que quieres borrar este archivo?');" style="display: inline-block;">

                                                        <input type="hidden" name="file_to_delete" value="<?= htmlspecialchars($relative_path) ?>">

                                                        <button type="submit" name="delete_asset" class="btn btn-sm btn-danger">Borrar</button>

                                                    </form>

                                                    <?php if ($is_image): ?>

                                                        <button type="button" class="btn btn-sm btn-primary edit-image-btn" data-image-path="assets/<?= htmlspecialchars($relative_path) ?>" data-image-name="<?= htmlspecialchars(pathinfo($relative_path, PATHINFO_FILENAME)) ?>">Editar</button>

                                                    <?php endif; ?>

                                                </div>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

        

                                <nav aria-label="Page navigation">

                                    <ul class="pagination pagination-break">

                                        <?php
                                        $pageGroupSize = 16;
                                        for ($i = 1; $i <= $media_data['pages']; $i++): ?>

                                            <li class="page-item <?= $i == $media_data['current_page'] ? 'active' : '' ?>">

                                                <a class="page-link" href="?page=resources&p=<?= $i ?>"><?= $i ?></a>

                                            </li>

                                            <?php if ($i % $pageGroupSize === 0 && $i < $media_data['pages']): ?>
                                                <li class="page-break"></li>
                                            <?php endif; ?>

                                        <?php endfor; ?>

                                    </ul>

                                </nav>

                            </div>

                        <?php elseif ($page === 'template'): ?>

                            <?php
                            $defaults = get_default_template_settings();
                            $templateSettings = $settings['template'] ?? $defaults;
                            $fontTitle = $templateSettings['fonts']['title'] ?? '';
                            $fontBody = $templateSettings['fonts']['body'] ?? '';
                            $fontCode = $templateSettings['fonts']['code'] ?? '';
                            $fontQuote = $templateSettings['fonts']['quote'] ?? '';
                            $entryTemplateToc = $templateSettings['entry']['toc'] ?? ($defaults['entry']['toc'] ?? ['auto' => 'off', 'min_headings' => 3]);
                            $entryTocAutoEnabled = ($entryTemplateToc['auto'] ?? 'off') === 'on';
                            $entryTocMinHeadings = (int) ($entryTemplateToc['min_headings'] ?? 3);
                            if (!in_array($entryTocMinHeadings, [2, 3, 4], true)) {
                                $entryTocMinHeadings = 3;
                            }
                            $templateColors = $templateSettings['colors'] ?? [];
                            $templateImages = $templateSettings['images'] ?? [];
                            $logoImage = $templateImages['logo'] ?? '';
                            $footerMd = $templateSettings['footer'] ?? '';
                            $footerLogoPosition = $templateSettings['footer_logo'] ?? $defaults['footer_logo'];
                            if (!in_array($footerLogoPosition, ['none', 'top', 'bottom'], true)) {
                                $footerLogoPosition = $defaults['footer_logo'];
                            }
                            $templateHome = $templateSettings['home'] ?? [];
                            $colorLabels = [
                                'h1' => 'Color H1',
                                'h2' => 'Color H2',
                                'h3' => 'Color H3',
                                'intro' => 'Color de Entradilla',
                                'text' => 'Color de Texto',
                                'background' => 'Color de Fondo',
                                'highlight' => 'Color de Cajas Destacadas',
                                'accent' => 'Color Destacado',
                                'brand' => 'Color de Cabecera',
                                'code_background' => 'Color de fondo de código',
                                'code_text' => 'Color del código',
                            ];
                            $templateGlobal = $templateSettings['global'] ?? $defaults['global'];
                            $homeColumns = (int)($templateHome['columns'] ?? $defaults['home']['columns']);
                            if ($homeColumns < 1 || $homeColumns > 3) {
                                $homeColumns = $defaults['home']['columns'];
                            }
                            $homePerPageValue = $templateHome['per_page'] ?? $defaults['home']['per_page'];
                            $homePerPageNumeric = is_numeric($homePerPageValue) ? (int) $homePerPageValue : '';
                            $homePerPageAll = !is_numeric($homePerPageValue) || $homePerPageValue === 'all';
                            $homeBlocksMode = $templateHome['blocks'] ?? $defaults['home']['blocks'];
                            if (!in_array($homeBlocksMode, ['boxed', 'flat'], true)) {
                                $homeBlocksMode = $defaults['home']['blocks'];
                            }
                            $cardStylesAllowed = ['full', 'square-right', 'square-tall-right', 'circle-right'];
                            $homeCardStyle = $templateHome['card_style'] ?? $defaults['home']['card_style'];
                            if (!in_array($homeCardStyle, $cardStylesAllowed, true)) {
                                $homeCardStyle = $defaults['home']['card_style'];
                            }
                            $homeFullImageMode = $templateHome['full_image_mode'] ?? $defaults['home']['full_image_mode'];
                            if (!in_array($homeFullImageMode, ['natural', 'crop'], true)) {
                                $homeFullImageMode = $defaults['home']['full_image_mode'];
                            }
                            $homeHeaderConfig = array_merge($defaults['home']['header'], $templateHome['header'] ?? []);
                            $homeHeaderTypes = ['none', 'graphic', 'text', 'mixed'];
                            $homeHeaderType = in_array($homeHeaderConfig['type'], $homeHeaderTypes, true) ? $homeHeaderConfig['type'] : $defaults['home']['header']['type'];
                            $homeHeaderImage = $homeHeaderConfig['image'] ?? '';
                            $homeHeaderModes = ['contain', 'cover'];
                            $homeHeaderMode = in_array($homeHeaderConfig['mode'], $homeHeaderModes, true) ? $homeHeaderConfig['mode'] : $defaults['home']['header']['mode'];
                            $textHeaderStyles = ['boxed', 'plain'];
                            $homeHeaderTextStyle = $homeHeaderConfig['text_style'] ?? $defaults['home']['header']['text_style'];
                            if (!in_array($homeHeaderTextStyle, $textHeaderStyles, true)) {
                                $homeHeaderTextStyle = $defaults['home']['header']['text_style'];
                            }
                            $homeHeaderOrder = $homeHeaderConfig['order'] ?? $defaults['home']['header']['order'];
                            if (!in_array($homeHeaderOrder, ['image-text', 'text-image'], true)) {
                                $homeHeaderOrder = $defaults['home']['header']['order'];
                            }
                            if (in_array($homeHeaderType, ['graphic', 'mixed'], true) && trim((string) $homeHeaderImage) === '') {
                                $homeHeaderType = $homeHeaderType === 'mixed' ? 'text' : 'none';
                                $homeHeaderMode = $defaults['home']['header']['mode'];
                            }
                            $allowedCorners = ['rounded', 'square'];
                            $globalCornerStyle = $templateGlobal['corners'] ?? $defaults['global']['corners'];
                            if (!in_array($globalCornerStyle, $allowedCorners, true)) {
                                $globalCornerStyle = $defaults['global']['corners'];
                            }
                            $searchDefaults = $defaults['search'] ?? ['mode' => 'single', 'position' => 'footer', 'floating' => 'off'];
                            $templateSearch = array_merge($searchDefaults, $templateSettings['search'] ?? []);
                            $searchModesAllowed = ['none', 'home', 'single', 'both'];
                            $searchPositionsAllowed = ['title', 'footer'];
                            $searchMode = in_array($templateSearch['mode'], $searchModesAllowed, true) ? $templateSearch['mode'] : $searchDefaults['mode'];
                            $searchPosition = in_array($templateSearch['position'], $searchPositionsAllowed, true) ? $templateSearch['position'] : $searchDefaults['position'];
                            $searchFloatingValue = $templateSearch['floating'] ?? $searchDefaults['floating'];
                            if (!in_array($searchFloatingValue, ['off', 'on'], true)) {
                                $searchFloatingValue = $searchDefaults['floating'];
                            }
                            $searchFloating = $searchFloatingValue;
                            ?>

                            <div class="tab-pane active">

                                <h2>Plantilla</h2>

                                <h3 class="mt-4">Colores y fuentes</h3>

                                <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
                                    <div class="alert alert-success">Configuración de plantilla guardada correctamente.</div>
                                <?php elseif (isset($_GET['error']) && $_GET['error'] === '1'): ?>
                                    <div class="alert alert-danger">No se pudieron guardar los cambios. Revisa los permisos del archivo de configuración.</div>
                                <?php endif; ?>

                                <form method="post" id="template-settings" data-google-fonts-key="<?= htmlspecialchars($settings['google_fonts_api'] ?? '') ?>">
                                    <h4 class="mt-3">Fuentes</h4>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="title_font">Fuente de Título</label>
                                            <select name="title_font" id="title_font" class="form-control" data-current-font="<?= htmlspecialchars($fontTitle) ?>">
                                                <option value="">Selecciona una fuente</option>
                                                <?php if ($fontTitle): ?>
                                                    <option value="<?= htmlspecialchars($fontTitle) ?>" selected><?= htmlspecialchars($fontTitle) ?> (actual)</option>
                                                <?php endif; ?>
                                            </select>
                                            <small class="form-text text-muted">Las opciones se cargan desde Google Fonts usando tu API Key.</small>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="body_font">Fuente de Texto</label>
                                            <select name="body_font" id="body_font" class="form-control" data-current-font="<?= htmlspecialchars($fontBody) ?>">
                                                <option value="">Selecciona una fuente</option>
                                                <?php if ($fontBody): ?>
                                                    <option value="<?= htmlspecialchars($fontBody) ?>" selected><?= htmlspecialchars($fontBody) ?> (actual)</option>
                                                <?php endif; ?>
                                            </select>
                                            <small class="form-text text-muted">Recuerda incluir todos los pesos necesarios desde Google Fonts.</small>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="code_font">Fuente para código</label>
                                            <select name="code_font" id="code_font" class="form-control" data-current-font="<?= htmlspecialchars($fontCode) ?>">
                                                <option value="">Selecciona una fuente</option>
                                                <?php if ($fontCode): ?>
                                                    <option value="<?= htmlspecialchars($fontCode) ?>" selected><?= htmlspecialchars($fontCode) ?> (actual)</option>
                                                <?php endif; ?>
                                            </select>
                                            <small class="form-text text-muted">Se aplicará a bloques `code` y `pre`.</small>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="quote_font">Fuente para citas</label>
                                            <select name="quote_font" id="quote_font" class="form-control" data-current-font="<?= htmlspecialchars($fontQuote) ?>">
                                                <option value="">Selecciona una fuente</option>
                                                <?php if ($fontQuote): ?>
                                                    <option value="<?= htmlspecialchars($fontQuote) ?>" selected><?= htmlspecialchars($fontQuote) ?> (actual)</option>
                                                <?php endif; ?>
                                            </select>
                                            <small class="form-text text-muted">Se utilizará en los bloques de cita (&gt;).</small>
                                        </div>
                                    </div>
                                    <div id="fonts-alert"></div>

                                    <h4 class="mt-4">Colores</h4>
                                    <p class="text-muted">Selecciona un color desde la paleta o escribe manualmente un valor hexadecimal, RGB, HSL, etc.</p>

                                    <div class="form-row">
                                        <?php foreach ($colorLabels as $colorKey => $label):
                                            $storedValue = $templateColors[$colorKey] ?? $defaults['colors'][$colorKey];
                                            $pickerValue = color_picker_value($storedValue, $defaults['colors'][$colorKey]);
                                        ?>
                                        <div class="form-group col-md-6" data-color-field="<?= $colorKey ?>">
                                            <label><?= $label ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text p-0">
                                                        <input type="color"
                                                            class="template-color-picker"
                                                            value="<?= htmlspecialchars($pickerValue) ?>"
                                                            aria-label="<?= $label ?> (selector)">
                                                    </span>
                                                </div>
                                                <input type="text"
                                                    class="form-control template-color-input"
                                                    name="color_<?= $colorKey ?>"
                                                    value="<?= htmlspecialchars($storedValue) ?>"
                                                    placeholder="#000000 o rgb(0,0,0)">
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <h4 class="mt-4">Imágenes de referencia</h4>
                                    <div class="form-group">
                                        <label for="logo_image">Logo del blog</label>
                                        <div class="input-group">
                                            <input type="text" name="logo_image" id="logo_image" class="form-control" value="<?= htmlspecialchars($logoImage, ENT_QUOTES, 'UTF-8') ?>" placeholder="assets/logo.png" readonly>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="logo_image" data-target-prefix="assets/">Seleccionar imagen</button>
                                                <button type="button" class="btn btn-outline-danger" id="clear-logo-image">Quitar</button>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Idealmente un PNG o SVG cuadrado. Se mostrará como un círculo flotante en las páginas internas y se usará como favicon cuando sea compatible.</small>
                                    </div>

                                    <h4 class="mt-4">Portada</h4>
                                    <p class="text-muted">Configura la rejilla de la portada y cuántas entradas se muestran por página.</p>
                                    <div class="form-group">
                                        <label>Columnas de la rejilla</label>
                                        <div class="home-layout-options">
                                            <?php foreach ([1, 2, 3] as $cols): ?>
                                                <?php $isActive = ($homeColumns === $cols); ?>
                                                <label class="home-layout-option <?= $isActive ? 'active' : '' ?>">
                                                    <input type="radio"
                                                        name="home_columns"
                                                        value="<?= $cols ?>"
                                                        <?= $isActive ? 'checked' : '' ?>>
                                                    <span class="layout-figure columns-<?= $cols ?>">
                                                        <?php for ($i = 0; $i < $cols; $i++): ?>
                                                            <span class="layout-cell"></span>
                                                        <?php endfor; ?>
                                                    </span>
                                                    <span class="layout-caption"><?= $cols ?> <?= $cols === 1 ? 'columna' : 'columnas' ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Estilo de las tarjetas</label>
                                        <div class="home-card-style-options">
                                            <?php
                                            $cardStyleOptions = [
                                                'full' => [
                                                    'label' => 'Imagen completa',
                                                    'caption' => 'Miniatura a todo el ancho, texto debajo',
                                                ],
                                                'square-right' => [
                                                    'label' => 'Cuadrada a la derecha',
                                                    'caption' => 'Miniatura cuadrada flotante',
                                                ],
                                                'square-tall-right' => [
                                                    'label' => 'Vertical a la derecha',
                                                    'caption' => 'Mismo ancho que la cuadrada, ocupa toda la altura',
                                                ],
                                                'circle-right' => [
                                                    'label' => 'Circular a la derecha',
                                                    'caption' => 'Miniatura circular flotante',
                                                ],
                                            ];
                                            ?>
                                            <?php foreach ($cardStyleOptions as $styleKey => $info): ?>
                                                <?php $styleActive = ($homeCardStyle === $styleKey); ?>
                                                <label class="home-card-style-option <?= $styleActive ? 'active' : '' ?>" data-card-style-option="1">
                                                    <input type="radio"
                                                        name="home_card_style"
                                                        value="<?= htmlspecialchars($styleKey, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $styleActive ? 'checked' : '' ?>>
                                                    <span class="card-style-figure card-style-<?= htmlspecialchars($styleKey, ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="card-thumb"></span>
                                                        <span class="card-lines">
                                                            <span class="line primary"></span>
                                                            <span class="line meta"></span>
                                                            <span class="line body"></span>
                                                        </span>
                                                    </span>
                                                    <span class="card-style-text">
                                                        <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="mt-3" data-full-image-options <?= $homeCardStyle === 'full' ? '' : 'style="display:none;"' ?>>
                                        <label>Imagen completa</label>
                                        <p class="text-muted">Selecciona cómo se ajustan las miniaturas cuando ocupan todo el ancho de la tarjeta.</p>
                                        <div class="home-card-style-options">
                                            <?php
                                            $fullImageModes = [
                                                'natural' => [
                                                    'label' => 'Respetar proporciones',
                                                    'caption' => 'Cada imagen mantiene su altura natural',
                                                    'figure' => 'full-mode-natural',
                                                ],
                                                'crop' => [
                                                    'label' => 'Recortar para igualar',
                                                    'caption' => 'Recorta para igualar la altura de todas las miniaturas',
                                                    'figure' => 'full-mode-crop',
                                                ],
                                            ];
                                            ?>
                                            <?php foreach ($fullImageModes as $modeKey => $info): ?>
                                                <?php $modeActive = ($homeFullImageMode === $modeKey); ?>
                                                <label class="home-card-style-option <?= $modeActive ? 'active' : '' ?>" data-full-image-mode="1">
                                                    <input type="radio"
                                                        name="home_card_full_mode"
                                                        value="<?= htmlspecialchars($modeKey, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $modeActive ? 'checked' : '' ?>>
                                                    <span class="full-image-mode-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="mode-column">
                                                            <span class="mode-thumb primary"></span>
                                                            <span class="mode-line title"></span>
                                                            <span class="mode-line text"></span>
                                                        </span>
                                                        <span class="mode-column">
                                                            <span class="mode-thumb secondary"></span>
                                                            <span class="mode-line title"></span>
                                                            <span class="mode-line text"></span>
                                                        </span>
                                                    </span>
                                                    <span class="card-style-text">
                                                        <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <h5 class="mt-4">Bloques de portada</h5>
                                    <p class="text-muted">Elige si las entradas se muestran dentro de una tarjeta o directamente sobre el fondo.</p>
                                    <div class="home-card-style-options">
                                        <?php
                                        $homeBlockOptions = [
                                            'boxed' => [
                                                'label' => 'Con cajas',
                                                'caption' => 'Cada entrada se muestra dentro de una tarjeta',
                                                'figure' => 'blocks-boxed',
                                            ],
                                            'flat' => [
                                                'label' => 'Sin cajas',
                                                'caption' => 'El contenido descansa sobre el fondo',
                                                'figure' => 'blocks-flat',
                                            ],
                                        ];
                                        ?>
                                        <?php foreach ($homeBlockOptions as $blocksKey => $info): ?>
                                            <?php $blocksActive = ($homeBlocksMode === $blocksKey); ?>
                                            <label class="home-card-style-option <?= $blocksActive ? 'active' : '' ?>" data-blocks-option="1">
                                                <input type="radio"
                                                    name="home_blocks_mode"
                                                    value="<?= htmlspecialchars($blocksKey, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $blocksActive ? 'checked' : '' ?>>
                                                <span class="home-blocks-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <span class="block block-thumb"></span>
                                                    <span class="block block-line"></span>
                                                    <span class="block block-line short"></span>
                                                </span>
                                                <span class="card-style-text">
                                                    <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                    <h5 class="mt-4">Esquinas de las cajas</h5>
                                    <p class="text-muted">Controla cómo se redondean las esquinas de tarjetas y bloques en todo el sitio.</p>
                                    <div class="home-card-style-options" data-corners-options>
                                        <?php
                                        $cornerOptions = [
                                            'rounded' => [
                                                'label' => 'Redondeadas',
                                                'caption' => 'Esquinas suaves y redondeadas',
                                                'figure' => 'corner-rounded',
                                            ],
                                            'square' => [
                                                'label' => 'Cuadradas',
                                                'caption' => 'Esquinas rectas en ángulo',
                                                'figure' => 'corner-square',
                                            ],
                                        ];
                                        ?>
                                        <?php foreach ($cornerOptions as $cornerKey => $info): ?>
                                            <?php $cornerActive = ($globalCornerStyle === $cornerKey); ?>
                                            <label class="home-card-style-option <?= $cornerActive ? 'active' : '' ?>" data-corners-option="1">
                                                <input type="radio"
                                                    name="global_corners"
                                                    value="<?= htmlspecialchars($cornerKey, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $cornerActive ? 'checked' : '' ?>>
                                                <span class="home-corner-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <span></span>
                                                </span>
                                                <span class="card-style-text">
                                                    <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                    <h5 class="mt-4">Cabecera</h5>
                                    <p class="text-muted">Selecciona cómo se mostrará la cabecera en la portada.</p>
                                    <div class="mt-3" data-header-text <?= in_array($homeHeaderType, ['text', 'mixed'], true) ? '' : 'style="display:none;"' ?>>
                                        <label>Estilo</label>
                                        <div class="home-card-style-options">
                                            <?php
                                            $textHeaderOptions = [
                                                'boxed' => [
                                                    'label' => 'Cabecera en caja',
                                                    'caption' => 'Con fondo destacado similar al post individual',
                                                    'figure' => 'text-header-boxed',
                                                ],
                                                'plain' => [
                                                    'label' => 'Sobre el fondo',
                                                    'caption' => 'Sin caja, directamente sobre el fondo de la portada',
                                                    'figure' => 'text-header-plain',
                                                ],
                                            ];
                                            ?>
                                            <?php foreach ($textHeaderOptions as $textKey => $info): ?>
                                                <?php $textActive = ($homeHeaderTextStyle === $textKey); ?>
                                                <label class="home-card-style-option <?= $textActive ? 'active' : '' ?>" data-header-text-option="1">
                                                    <input type="radio"
                                                        name="home_header_text_style"
                                                        value="<?= htmlspecialchars($textKey, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $textActive ? 'checked' : '' ?>>
                                                    <span class="home-header-text-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="text-line title"></span>
                                                        <span class="text-line subtitle"></span>
                                                        <span class="text-line tagline"></span>
                                                    </span>
                                                    <span class="card-style-text">
                                                        <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <label class="d-block mt-4 mb-2">Estructura de la cabecera</label>

                                    <div class="home-header-options" data-header-options>
                                        <?php
                                        $headerTypeOptions = [
                                            'none' => [
                                                'label' => 'Sin cabecera',
                                                'caption' => 'No se mostrará cabecera en la portada',
                                                'figure' => 'header-none',
                                            ],
                                            'graphic' => [
                                                'label' => 'Cabecera gráfica',
                                                'caption' => 'Mostrar una imagen a modo de cabecera',
                                                'figure' => 'header-graphic',
                                            ],
                                            'text' => [
                                                'label' => 'Cabecera de texto',
                                                'caption' => 'Usar cabecera similar a la de los artículos',
                                                'figure' => 'header-text',
                                            ],
                                            'mixed' => [
                                                'label' => 'Imagen + texto',
                                                'caption' => 'Combina imagen con la cabecera textual',
                                                'figure' => 'header-mixed',
                                            ],
                                        ];
                                        ?>
                                        <?php foreach ($headerTypeOptions as $typeKey => $info): ?>
                                            <?php $typeActive = ($homeHeaderType === $typeKey); ?>
                                            <label class="home-header-option <?= $typeActive ? 'active' : '' ?>">
                                                <input type="radio"
                                                    name="home_header_type"
                                                    value="<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $typeActive ? 'checked' : '' ?>>
                                                <span class="home-header-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <span></span>
                                                </span>
                                                <span class="home-header-text">
                                                    <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="mt-3" data-header-order <?= $homeHeaderType === 'mixed' ? '' : 'style="display:none;"' ?>>
                                        <label>Orden de la cabecera</label>
                                        <div class="home-card-style-options">
                                            <?php
                                            $headerOrderOptions = [
                                                'image-text' => [
                                                    'label' => 'Imagen arriba, texto abajo',
                                                    'caption' => 'La imagen precede al bloque textual',
                                                    'figure' => 'order-image-text',
                                                ],
                                                'text-image' => [
                                                    'label' => 'Texto arriba, imagen abajo',
                                                    'caption' => 'El bloque textual queda por encima de la imagen',
                                                    'figure' => 'order-text-image',
                                                ],
                                            ];
                                            ?>
                                            <?php foreach ($headerOrderOptions as $orderKey => $info): ?>
                                                <?php $orderActive = ($homeHeaderOrder === $orderKey); ?>
                                                <label class="home-card-style-option <?= $orderActive ? 'active' : '' ?>" data-header-order-option="1">
                                                    <input type="radio"
                                                        name="home_header_order"
                                                        value="<?= htmlspecialchars($orderKey, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $orderActive ? 'checked' : '' ?>>
                                                    <span class="home-header-order-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="order-block image"></span>
                                                        <span class="order-block text"></span>
                                                    </span>
                                                    <span class="card-style-text">
                                                        <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="mt-3" data-header-graphic <?= in_array($homeHeaderType, ['graphic', 'mixed'], true) ? '' : 'style="display:none;"' ?>>
                                        <div class="form-group">
                                            <label for="home_header_image">Imagen de cabecera</label>
                                            <div class="input-group">
                                                <input type="text" name="home_header_image" id="home_header_image" class="form-control" value="<?= htmlspecialchars($homeHeaderImage ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="assets/cabecera.jpg" readonly>
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="home_header_image" data-target-prefix="assets/">Seleccionar imagen</button>
                                                    <button type="button" class="btn btn-outline-danger" id="clear-header-image">Quitar</button>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">Selecciona una imagen desde Recursos para la cabecera de la portada.</small>
                                        </div>

                                            <div class="form-group" data-header-graphic-mode <?= in_array($homeHeaderType, ['graphic', 'mixed'], true) ? '' : 'style="display:none;"' ?>>
                                                <label>Estilo de la imagen</label>
                                                <div class="home-card-style-options">
                                                    <?php
                                                    $graphicModes = [
                                                        'contain' => [
                                                            'label' => 'Imagen centrada',
                                                            'caption' => '160px de alto, respeta la proporción original',
                                                            'figure' => 'contain',
                                                        ],
                                                        'cover' => [
                                                            'label' => 'Imagen a ancho completo',
                                                            'caption' => 'Recorta a 160px ocupando todo el ancho',
                                                            'figure' => 'cover',
                                                        ],
                                                    ];
                                                    ?>
                                                    <?php foreach ($graphicModes as $modeKey => $modeInfo): ?>
                                                        <?php $modeActive = ($homeHeaderMode === $modeKey); ?>
                                                        <label class="home-card-style-option <?= $modeActive ? 'active' : '' ?>" data-header-mode-option="1">
                                                            <input type="radio"
                                                                name="home_header_graphic_mode"
                                                                value="<?= htmlspecialchars($modeKey, ENT_QUOTES, 'UTF-8') ?>"
                                                                <?= $modeActive ? 'checked' : '' ?>>
                                                        <span class="header-graphic-figure <?= htmlspecialchars($modeInfo['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <span class="graphic-preview"></span>
                                                        </span>
                                                        <span class="card-style-text">
                                                            <strong><?= htmlspecialchars($modeInfo['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                            <small><?= htmlspecialchars($modeInfo['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                        </span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <h5 class="mt-4">Bucle</h5>
                                    <div class="form-group">
                                        <label for="home_per_page">Número de posts por página</label>
                                        <div class="input-group">
                                            <input type="number"
                                                min="1"
                                                class="form-control"
                                                name="home_per_page"
                                                id="home_per_page"
                                                value="<?= htmlspecialchars($homePerPageNumeric !== '' ? (string) $homePerPageNumeric : '', ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $homePerPageAll ? 'disabled' : '' ?>>
                                            <div class="input-group-append">
                                                <div class="input-group-text">
                                                    <input type="checkbox"
                                                        name="home_per_page_all"
                                                        id="home_per_page_all"
                                                        value="1"
                                                        <?= $homePerPageAll ? 'checked' : '' ?>>
                                                    <label for="home_per_page_all" class="mb-0 ml-2">Todos</label>
                                                </div>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Define cuántas entradas se muestran antes de paginar. Marca “Todos” para mostrar todas las entradas sin paginación.</small>
                                    </div>

                                    <h4 class="mt-4">Buscador</h4>
                                    <p class="text-muted">Decide si quieres mostrar una caja de búsqueda en el sitio y dónde se colocará.</p>
                                    <div class="home-card-style-options" data-search-mode-options>
                                        <?php
                                        $searchModeOptions = [
                                            'none' => [
                                                'label' => 'Sin caja de búsqueda',
                                                'caption' => 'No se muestra ningún buscador en el sitio',
                                                'figure' => 'search-none',
                                            ],
                                            'home' => [
                                                'label' => 'Sólo en la portada',
                                                'caption' => 'Una caja en la página principal',
                                                'figure' => 'search-home',
                                            ],
                                            'single' => [
                                                'label' => 'Sólo en las entradas',
                                                'caption' => 'Aparece en cada artículo',
                                                'figure' => 'search-single',
                                            ],
                                            'both' => [
                                                'label' => 'Portada y entradas',
                                                'caption' => 'Visible en todos los listados y artículos',
                                                'figure' => 'search-both',
                                            ],
                                        ];
                                        ?>
                                        <?php foreach ($searchModeOptions as $modeKey => $info): ?>
                                            <?php $modeActive = ($searchMode === $modeKey); ?>
                                            <label class="home-card-style-option <?= $modeActive ? 'active' : '' ?>" data-search-mode-option="1">
                                                <input type="radio"
                                                    name="search_mode"
                                                    value="<?= htmlspecialchars($modeKey, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $modeActive ? 'checked' : '' ?>>
                                                <span class="search-mode-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <span class="search-box">
                                                        <span class="icon"></span>
                                                        <span class="line"></span>
                                                    </span>
                                                </span>
                                                <span class="card-style-text">
                                                    <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-3" data-search-position <?= $searchMode === 'none' ? 'style="display:none;"' : '' ?>>
                                        <label>Ubicación de la caja</label>
                                        <div class="home-card-style-options">
                                            <?php
                                            $searchPositionOptions = [
                                                'title' => [
                                                    'label' => 'Bajo el título',
                                                    'caption' => 'Caja alineada con el encabezado principal',
                                                    'figure' => 'search-pos-title',
                                                ],
                                                'footer' => [
                                                    'label' => 'Sobre el footer',
                                                    'caption' => 'Bloque destacado antes del pie de página',
                                                    'figure' => 'search-pos-footer',
                                                ],
                                            ];
                                            ?>
                                            <?php foreach ($searchPositionOptions as $posKey => $info): ?>
                                                <?php $posActive = ($searchPosition === $posKey); ?>
                                                <label class="home-card-style-option <?= $posActive ? 'active' : '' ?>" data-search-position-option="1">
                                                    <input type="radio"
                                                        name="search_position"
                                                        value="<?= htmlspecialchars($posKey, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $posActive ? 'checked' : '' ?>>
                                                    <span class="search-position-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="search-box">
                                                            <span class="icon"></span>
                                                            <span class="line"></span>
                                                        </span>
                                                    </span>
                                                    <span class="card-style-text">
                                                        <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="mt-3" data-search-floating>
                                        <label>Buscador flotante</label>
                                        <p class="text-muted mb-2">Muestra una caja compacta flotando bajo el logotipo en páginas internas.</p>
                                        <div class="home-card-style-options">
                                            <?php
                                            $searchFloatingOptions = [
                                                'off' => [
                                                    'label' => 'Desactivado',
                                                    'caption' => 'No se muestra buscador flotante',
                                                    'figure' => 'search-float-off',
                                                ],
                                                'on' => [
                                                    'label' => 'Flotando en el margen',
                                                    'caption' => 'Caja ligera junto al logotipo en vistas interiores',
                                                    'figure' => 'search-float-on',
                                                ],
                                            ];
                                            ?>
                                            <?php foreach ($searchFloatingOptions as $floatKey => $info): ?>
                                                <?php $floatActive = ($searchFloating === $floatKey); ?>
                                                <label class="home-card-style-option <?= $floatActive ? 'active' : '' ?>" data-search-floating-option="1">
                                                    <input type="radio"
                                                        name="search_floating"
                                                        value="<?= htmlspecialchars($floatKey, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $floatActive ? 'checked' : '' ?>>
                                                    <span class="search-floating-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <span class="search-box">
                                                            <span class="icon"></span>
                                                        </span>
                                                        <span class="search-hint"></span>
                                                    </span>
                                                    <span class="card-style-text">
                                                        <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                        <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                    </div>

                                    <h4 class="mt-4">Entrada</h4>
                                    <p class="text-muted">Configura si las entradas mostrarán un índice de contenidos automáticamente.</p>
                                    <div class="form-group" data-entry-toc-toggle>
                                        <label class="d-block">Índice de contenidos por defecto</label>
                                        <div class="btn-group btn-group-sm btn-group-toggle" role="group" data-toggle="buttons">
                                            <label class="btn btn-outline-primary <?= $entryTocAutoEnabled ? 'active' : '' ?>">
                                                <input type="radio"
                                                    name="entry_toc_auto"
                                                    value="on"
                                                    class="sr-only"
                                                    autocomplete="off"
                                                    <?= $entryTocAutoEnabled ? 'checked' : '' ?>>
                                                Sí
                                            </label>
                                            <label class="btn btn-outline-primary <?= !$entryTocAutoEnabled ? 'active' : '' ?>">
                                                <input type="radio"
                                                    name="entry_toc_auto"
                                                    value="off"
                                                    class="sr-only"
                                                    autocomplete="off"
                                                    <?= !$entryTocAutoEnabled ? 'checked' : '' ?>>
                                                No
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group" data-entry-toc-options <?= $entryTocAutoEnabled ? '' : 'style="display:none;"' ?>>
                                        <label for="entry_toc_min">Mostrar a partir de</label>
                                        <select name="entry_toc_min" id="entry_toc_min" class="form-control" style="max-width: 240px;">
                                            <option value="2" <?= $entryTocMinHeadings === 2 ? 'selected' : '' ?>>2 encabezados</option>
                                            <option value="3" <?= $entryTocMinHeadings === 3 ? 'selected' : '' ?>>3 encabezados</option>
                                            <option value="4" <?= $entryTocMinHeadings === 4 ? 'selected' : '' ?>>4 o más encabezados</option>
                                        </select>
                                    </div>
                                    <small class="form-text text-muted mb-3">Si desactivas el índice automático, puedes insertarlo manualmente dentro de cada entrada usando las etiquetas <code>[toc]</code> o <code>[TOC]</code>.</small>

                                    </div>

                                    <h4 class="mt-4">Footer</h4>
                                    <p class="text-muted mb-2">Decide si quieres mostrar el logotipo del sitio en el pie de página.</p>
                                    <div class="home-card-style-options" data-footer-logo-options>
                                        <?php
                                        $footerLogoOptions = [
                                            'none' => [
                                                'label' => 'Sin logo',
                                                'caption' => 'Sólo se mostrará el contenido del footer',
                                                'figure' => 'logo-none',
                                            ],
                                            'top' => [
                                                'label' => 'Logo arriba',
                                                'caption' => 'El logotipo aparecerá centrado sobre el footer',
                                                'figure' => 'logo-top',
                                            ],
                                            'bottom' => [
                                                'label' => 'Logo abajo',
                                                'caption' => 'El logotipo aparecerá centrado bajo el footer',
                                                'figure' => 'logo-bottom',
                                            ],
                                        ];
                                        ?>
                                        <?php foreach ($footerLogoOptions as $logoKey => $info): ?>
                                            <?php $logoActive = ($footerLogoPosition === $logoKey); ?>
                                            <label class="home-card-style-option <?= $logoActive ? 'active' : '' ?>" data-footer-logo-option="1">
                                                <input type="radio"
                                                    name="footer_logo_position"
                                                    value="<?= htmlspecialchars($logoKey, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $logoActive ? 'checked' : '' ?>>
                                                <span class="footer-logo-figure <?= htmlspecialchars($info['figure'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <span class="footer-logo-area"></span>
                                                    <span class="footer-logo-dot"></span>
                                                </span>
                                                <span class="card-style-text">
                                                    <strong><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <small><?= htmlspecialchars($info['caption'], ENT_QUOTES, 'UTF-8') ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="text-muted mt-3">Este contenido se mostrará al final de cada página. Introduce HTML directamente (por ejemplo, &lt;strong&gt;...&lt;/strong&gt; o enlaces con &lt;a&gt; ).</p>
                                    <div class="form-group">
                                        <label for="footer_md">Contenido del footer (HTML)</label>
                                        <textarea name="footer_md" id="footer_md" rows="6" class="form-control" placeholder="Bloque de contacto, enlaces legales..."><?= htmlspecialchars($footerMd ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>

                                    <button type="submit" name="save_template" class="btn btn-primary">Guardar plantilla</button>
                                </form>

                            </div>

                        <?php elseif ($page === 'itinerarios'): ?>

                            <div class="tab-pane active">
                                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                                    <h2 class="mb-0">Itinerarios</h2>
                                    <div class="btn-group">
                                        <a class="btn btn-outline-secondary" href="?page=itinerarios">Refrescar</a>
                                        <a class="btn btn-primary" href="?page=itinerarios&new=1">Nuevo itinerario</a>
                                    </div>
                                </div>

                                <?php if ($itineraryFeedback): ?>
                                    <div class="alert alert-<?= htmlspecialchars($itineraryFeedback['type'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($itineraryFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>

                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h3 class="h5 mb-3">Listado de itinerarios</h3>
                                        <?php if (empty($itinerariesList)): ?>
                                            <p class="text-muted mb-0">Todavía no hay itinerarios registrados.</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Título</th>
                                                            <th>Descripción</th>
                                                            <th>Temas</th>
                                                            <th>Slug público</th>
                                                            <th class="text-right">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($itinerariesList as $itineraryItem): ?>
                                                            <?php
                                                                $rawStats = admin_get_itinerary_stats($itineraryItem);
                                                                $topicStatsList = array_values($rawStats['topics'] ?? []);
                                                                usort($topicStatsList, static function (array $a, array $b): int {
                                                                    return (int) ($a['number'] ?? 0) <=> (int) ($b['number'] ?? 0);
                                                                });
                                                                $presentationReaders = (int) ($rawStats['started'] ?? 0);
                                                                $topicOneStarters = 0;
                                                                foreach ($topicStatsList as $topicStat) {
                                                                    $topicNumber = (int) ($topicStat['number'] ?? 0);
                                                                    if ($topicNumber === 1) {
                                                                        $topicOneStarters = (int) ($topicStat['count'] ?? 0);
                                                                        break;
                                                                    }
                                                                }
                                                                if ($topicOneStarters === 0 && !empty($topicStatsList)) {
                                                                    $topicOneStarters = (int) ($topicStatsList[0]['count'] ?? 0);
                                                                }
                                                                $statsPayload = [
                                                                    'started' => $topicOneStarters,
                                                                    'presentation_readers' => $presentationReaders,
                                                                    'topics' => array_map(static function (array $topic): array {
                                                                        return [
                                                                            'number' => (int) ($topic['number'] ?? 0),
                                                                            'title' => $topic['title'] ?? ($topic['slug'] ?? ''),
                                                                            'count' => (int) ($topic['count'] ?? 0),
                                                                        ];
                                                                    }, $topicStatsList),
                                                                ];
                                                                $statsJson = htmlspecialchars(
                                                                    json_encode($statsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                );
                                                            ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($itineraryItem->getTitle(), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td><?= htmlspecialchars($itineraryItem->getDescription(), ENT_QUOTES, 'UTF-8') ?></td>
                                                                <td><?= $itineraryItem->getTopicCount() ?></td>
                                                                <td>
                                                                    <a href="<?= htmlspecialchars(admin_public_itinerary_url($itineraryItem->getSlug()), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                        /itinerarios/<?= htmlspecialchars($itineraryItem->getSlug(), ENT_QUOTES, 'UTF-8') ?>
                                                                    </a>
                                                                </td>
                                                                <td class="text-right">
                                                                    <div class="text-right">
                                                                        <div class="mb-2">
                                                                            <button
                                                                                type="button"
                                                                                class="btn btn-sm btn-outline-info mb-2"
                                                                                data-toggle="modal"
                                                                                data-target="#itineraryStatsModal"
                                                                                data-itinerary-title="<?= htmlspecialchars($itineraryItem->getTitle(), ENT_QUOTES, 'UTF-8') ?>"
                                                                                data-itinerary-slug="<?= htmlspecialchars($itineraryItem->getSlug(), ENT_QUOTES, 'UTF-8') ?>"
                                                                                data-itinerary-stats="<?= $statsJson ?>"
                                                                            >Estadísticas</button>
                                                                        </div>
                                                                        <div class="mb-2">
                                                                            <a class="btn btn-sm btn-outline-primary" href="?page=itinerarios&itinerary=<?= urlencode($itineraryItem->getSlug()) ?>#itinerary-form">Editar</a>
                                                                        </div>
                                                                        <form method="post" class="d-inline-block" onsubmit="return confirm('¿Seguro que deseas borrar este itinerario? Esta acción eliminará todos sus temas.');">
                                                                            <input type="hidden" name="delete_itinerary_slug" value="<?= htmlspecialchars($itineraryItem->getSlug(), ENT_QUOTES, 'UTF-8') ?>">
                                                                            <button type="submit" name="delete_itinerary" class="btn btn-sm btn-outline-danger mt-1">Borrar</button>
                                                                        </form>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12">
                                        <div id="itinerary-form" class="card mb-4">
                                            <div class="card-body itinerary-form-card">
                                                <form method="post">
                                                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
                                                        <h3 class="h5 mb-0"><?= $itineraryFormData['mode'] === 'existing' ? 'Editar itinerario' : 'Nuevo itinerario' ?></h3>
                                                        <button type="submit" name="save_itinerary" class="btn btn-primary">Guardar itinerario</button>
                                                    </div>
                                                    <input type="hidden" name="itinerary_original_slug" value="<?= htmlspecialchars($itineraryFormData['slug'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="itinerary_mode" value="<?= htmlspecialchars($itineraryFormData['mode'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <div class="form-group">
                                                        <label for="itinerary_title">Título</label>
                                                        <input type="text" name="itinerary_title" id="itinerary_title" class="form-control" value="<?= htmlspecialchars($itineraryFormData['title'], ENT_QUOTES, 'UTF-8') ?>" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="itinerary_description">Descripción</label>
                                                        <textarea name="itinerary_description" id="itinerary_description" class="form-control" rows="3"><?= htmlspecialchars($itineraryFormData['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                                    </div>
                                                    <?php
                                                        $itineraryUsageLogic = $itineraryFormData['usage_logic'] ?? 'free';
                                                        $usageOptions = admin_itinerary_usage_logic_options();
                                                    ?>
                                                    <div class="form-group">
                                                        <label class="d-block">Lógica de uso</label>
                                                        <p class="text-muted small mb-2">Define cómo debe avanzar el lector a través de los temas de este itinerario.</p>
                                                        <?php foreach ($usageOptions as $value => $label): ?>
                                                            <?php $usageFieldId = 'itinerary_usage_logic_' . $value; ?>
                                                            <div class="form-check">
                                                                <input
                                                                    class="form-check-input"
                                                                    type="radio"
                                                                    name="itinerary_usage_logic"
                                                                    id="<?= htmlspecialchars($usageFieldId, ENT_QUOTES, 'UTF-8') ?>"
                                                                    value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                                                                    <?= $itineraryUsageLogic === $value ? 'checked' : '' ?>
                                                                >
                                                                <label class="form-check-label" for="<?= htmlspecialchars($usageFieldId, ENT_QUOTES, 'UTF-8') ?>">
                                                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                        <small class="form-text text-muted">Si eliges las dos últimas opciones, informaremos al lector de que se usarán cookies.</small>
                                                    </div>
                                                    <?php $itineraryClassChoice = $itineraryFormData['class_choice'] ?? ''; ?>
                                                    <div class="form-group">
                                                        <label for="itinerary_class">Clase de itinerario</label>
                                                        <select name="itinerary_class" id="itinerary_class" class="form-control" data-itinerary-class-select>
                                                            <option value="" <?= $itineraryClassChoice === '' ? 'selected' : '' ?>>Selecciona una opción</option>
                                                            <?php foreach (admin_itinerary_class_options() as $value => $label): ?>
                                                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $itineraryClassChoice === $value ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <small class="form-text text-muted">Este texto aparecerá como subtítulo del itinerario.</small>
                                                    </div>
                                                    <div class="form-group <?= $itineraryClassChoice === 'Otros' ? '' : 'd-none' ?>" data-itinerary-class-custom-wrapper>
                                                        <label for="itinerary_class_custom">Especifica la clase</label>
                                                        <input type="text" name="itinerary_class_custom" id="itinerary_class_custom" class="form-control" value="<?= htmlspecialchars($itineraryFormData['class_custom'], ENT_QUOTES, 'UTF-8') ?>" maxlength="80" placeholder="Ej. Programa especial" <?= $itineraryClassChoice === 'Otros' ? 'required' : '' ?>>
                                                        <small class="form-text text-muted">Texto corto (máx. 80 caracteres) que sustituye la etiqueta “Itinerario”.</small>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="itinerary_slug">Slug</label>
                                                        <input type="text" name="itinerary_slug" id="itinerary_slug" class="form-control" data-slug-input="1" pattern="[a-z0-9-]+" title="Usa solo letras minúsculas, números y guiones (-)" value="<?= htmlspecialchars($itineraryFormData['slug'], ENT_QUOTES, 'UTF-8') ?>" placeholder="mi-itinerario">
                                                        <small class="form-text text-muted">Usaremos este valor para la carpeta y la URL pública.</small>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="itinerary_image">Imagen destacada</label>
                                                        <div class="input-group">
                                                            <input type="text" name="itinerary_image" id="itinerary_image" class="form-control" readonly value="<?= htmlspecialchars($itineraryFormData['image'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <div class="input-group-append">
                                                                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="itinerary_image" data-target-prefix="">Seleccionar imagen</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="itinerary_content">Presentación del itinerario</label>
                                                        <div class="btn-toolbar markdown-toolbar mb-2 flex-wrap" role="toolbar" aria-label="Atajos de Markdown" data-markdown-toolbar data-target="#itinerary_content">
                                                            <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                                                <button type="button" class="btn btn-outline-secondary" data-md-action="bold"><strong>B</strong></button>
                                                                <button type="button" class="btn btn-outline-secondary" data-md-action="italic"><em>I</em></button>
                                                                <button type="button" class="btn btn-outline-secondary" data-md-action="strike">S̶</button>
                                                                <button type="button" class="btn btn-outline-secondary" data-md-action="code">&lt;/&gt;</button>
                                                            </div>
                                                            <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                                                <button type="button" class="btn btn-outline-secondary" data-md-action="link">Link</button>
                                                                <button type="button" class="btn btn-outline-secondary" data-md-action="quote">&gt;</button>
                                                                <button type="button" class="btn btn-outline-secondary" data-md-action="sup">x<sup>2</sup></button>
                                                            </div>
                                                            <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                                                <button type="button" class="btn btn-outline-secondary" data-md-action="ul">•</button>
                                                                <button type="button" class="btn btn-outline-secondary" data-md-action="ol">1.</button>
                                                                <button type="button" class="btn btn-outline-secondary" data-md-action="heading">H2</button>
                                                                <button type="button" class="btn btn-outline-secondary" data-md-action="code-block">{ }</button>
                                                                <button type="button" class="btn btn-outline-secondary" data-md-action="hr">—</button>
                                                            </div>
                                                        </div>
                                                        <textarea name="itinerary_content" id="itinerary_content" class="form-control" rows="10" data-markdown-editor="itinerary"><?= htmlspecialchars($itineraryFormData['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                                        <button type="button" class="btn btn-secondary mt-2" data-toggle="modal" data-target="#imageModal" data-target-type="editor" data-target-editor="#itinerary_content">Insertar recurso</button>
                                                        <div class="d-flex flex-wrap align-items-center gap-2 mt-2 topic-quiz-controls">
                                                            <input type="hidden" name="itinerary_quiz_payload" id="itinerary_quiz_payload" value="<?= htmlspecialchars($itineraryFormData['quiz'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <button
                                                                type="button"
                                                                class="btn btn-outline-secondary"
                                                                data-quiz-trigger
                                                                data-quiz-target="#itinerary_quiz_payload"
                                                                data-quiz-summary="#itinerary_quiz_summary"
                                                                data-quiz-title="Autoevaluación de la presentación"
                                                            >
                                                                <?= ($itineraryFormData['quiz'] ?? '') !== '' ? 'Editar autoevaluación' : 'Añadir autoevaluación' ?>
                                                            </button>
                                                            <small class="text-muted mb-0" id="itinerary_quiz_summary" data-quiz-summary="itinerary_quiz_payload">
                                                                <?= htmlspecialchars($itineraryFormData['quiz_summary'], ENT_QUOTES, 'UTF-8') ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="sticky-save">
                                                        <button type="submit" name="save_itinerary" class="btn btn-primary">Guardar itinerario</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-4 mt-lg-0">
                                        <div class="card mb-4">
                                            <div class="card-body itinerary-topics-card">
                                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                                    <h3 class="h5 mb-0">
                                                        <?php if ($selectedItinerary): ?>
                                                            Temas de <?= htmlspecialchars($selectedItinerary->getTitle(), ENT_QUOTES, 'UTF-8') ?>
                                                        <?php else: ?>
                                                            Temas del itinerario
                                                        <?php endif; ?>
                                                    </h3>
                                                    <?php if ($selectedItinerary): ?>
                                                        <a class="btn btn-outline-primary" href="?page=itinerarios&itinerary=<?= urlencode($selectedItinerary->getSlug()) ?>&topic=new#topic-form">Nuevo tema</a>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-outline-primary disabled" disabled title="Guarda el itinerario para poder añadir temas">Nuevo tema</button>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!$selectedItinerary): ?>
                                                    <p class="text-muted mb-0">Selecciona o crea un itinerario para gestionar sus temas.</p>
                                                <?php elseif (empty($selectedItinerary->getTopics())): ?>
                                                    <p class="text-muted mb-0">Añade tu primer tema para comenzar el itinerario.</p>
                                                <?php else: ?>
                                                    <?php foreach ($selectedItinerary->getTopics() as $topicItem): ?>
                                                        <div class="card mb-3">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                                    <div>
                                                                        <span class="badge badge-secondary mb-2">Tema <?= (int) $topicItem->getNumber() ?></span>
                                                                        <h4 class="h6 mb-1"><?= htmlspecialchars($topicItem->getTitle(), ENT_QUOTES, 'UTF-8') ?></h4>
                                                                        <?php if ($topicItem->getDescription() !== ''): ?>
                                                                            <p class="mb-2 text-muted"><?= htmlspecialchars($topicItem->getDescription(), ENT_QUOTES, 'UTF-8') ?></p>
                                                                        <?php endif; ?>
                                                                        <a href="<?= htmlspecialchars(admin_public_itinerary_url($selectedItinerary->getSlug()) . '/' . rawurlencode($topicItem->getSlug()), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Ver tema</a>
                                                                    </div>
                                                                    <div class="text-right">
                                                                        <div class="mb-2">
                                                                            <a class="btn btn-sm btn-outline-primary" href="?page=itinerarios&itinerary=<?= urlencode($selectedItinerary->getSlug()) ?>&topic=<?= urlencode($topicItem->getSlug()) ?>#topic-form">Editar</a>
                                                                        </div>
                                                                        <form method="post" class="d-inline-block" onsubmit="return confirm('¿Seguro que deseas borrar este tema del itinerario?');">
                                                                            <input type="hidden" name="delete_topic_itinerary_slug" value="<?= htmlspecialchars($selectedItinerary->getSlug(), ENT_QUOTES, 'UTF-8') ?>">
                                                                            <input type="hidden" name="delete_topic_slug" value="<?= htmlspecialchars($topicItem->getSlug(), ENT_QUOTES, 'UTF-8') ?>">
                                                                            <button type="submit" name="delete_itinerary_topic" class="btn btn-sm btn-outline-danger mt-1">Borrar</button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if ($selectedItinerary): ?>
                                            <div id="topic-form" class="card mb-4">
                                                <div class="card-body itinerary-topics-card">
                                                    <h3 class="h5 mb-3"><?= $topicFormData['mode'] === 'existing' ? 'Editar tema' : 'Nuevo tema' ?></h3>
                                                    <form method="post">
                                                        <input type="hidden" name="topic_itinerary_slug" value="<?= htmlspecialchars($selectedItinerary->getSlug(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="topic_original_slug" value="<?= htmlspecialchars($topicFormData['slug'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="topic_mode" value="<?= htmlspecialchars($topicFormData['mode'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <div class="form-group">
                                                            <label for="topic_title">Título del tema</label>
                                                            <input type="text" name="topic_title" id="topic_title" class="form-control" value="<?= htmlspecialchars($topicFormData['title'], ENT_QUOTES, 'UTF-8') ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="topic_description">Descripción</label>
                                                            <textarea name="topic_description" id="topic_description" class="form-control" rows="3"><?= htmlspecialchars($topicFormData['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="topic_slug">Slug</label>
                                                            <input type="text" name="topic_slug" id="topic_slug" class="form-control" data-slug-input="1" pattern="[a-z0-9-]+" title="Usa solo letras minúsculas, números y guiones (-)" value="<?= htmlspecialchars($topicFormData['slug'], ENT_QUOTES, 'UTF-8') ?>" placeholder="tema-1">
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="topic_number">Tema nº</label>
                                                            <select name="topic_number" id="topic_number" class="form-control">
                                                                <?php foreach ($topicNumberOptions as $option): ?>
                                                                    <option value="<?= $option ?>" <?= $option == $topicFormData['number'] ? 'selected' : '' ?>><?= $option ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="topic_image">Imagen asociada</label>
                                                            <div class="input-group">
                                                                <input type="text" name="topic_image" id="topic_image" class="form-control" readonly value="<?= htmlspecialchars($topicFormData['image'], ENT_QUOTES, 'UTF-8') ?>">
                                                                <div class="input-group-append">
                                                                    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="topic_image" data-target-prefix="">Seleccionar imagen</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="topic_content">Presentación del tema</label>
                                                            <div class="btn-toolbar markdown-toolbar mb-2 flex-wrap" role="toolbar" aria-label="Atajos de Markdown" data-markdown-toolbar data-target="#topic_content">
                                                                <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                                                    <button type="button" class="btn btn-outline-secondary" data-md-action="bold"><strong>B</strong></button>
                                                                    <button type="button" class="btn btn-outline-secondary" data-md-action="italic"><em>I</em></button>
                                                                    <button type="button" class="btn btn-outline-secondary" data-md-action="strike">S̶</button>
                                                                    <button type="button" class="btn btn-outline-secondary" data-md-action="code">&lt;/&gt;</button>
                                                                </div>
                                                                <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                                                    <button type="button" class="btn btn-outline-secondary" data-md-action="link">Link</button>
                                                                    <button type="button" class="btn btn-outline-secondary" data-md-action="quote">&gt;</button>
                                                                    <button type="button" class="btn btn-outline-secondary" data-md-action="sup">x<sup>2</sup></button>
                                                                </div>
                                                                <div class="btn-group btn-group-sm mr-1 mb-1" role="group">
                                                                    <button type="button" class="btn btn-outline-secondary" data-md-action="ul">•</button>
                                                                    <button type="button" class="btn btn-outline-secondary" data-md-action="ol">1.</button>
                                                                    <button type="button" class="btn btn-outline-secondary" data-md-action="heading">H2</button>
                                                                    <button type="button" class="btn btn-outline-secondary" data-md-action="code-block">{ }</button>
                                                                    <button type="button" class="btn btn-outline-secondary" data-md-action="hr">—</button>
                                                                </div>
                                                            </div>
                                                            <textarea name="topic_content" id="topic_content" class="form-control" rows="10" data-markdown-editor="itinerary-topic"><?= htmlspecialchars($topicFormData['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                                            <div class="d-flex flex-wrap align-items-center gap-2 mt-2 topic-quiz-controls">
                                                                <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="editor" data-target-editor="#topic_content">Nuevo recurso</button>
                                                                <input type="hidden" name="topic_quiz_payload" id="topic_quiz_payload" value="<?= htmlspecialchars($topicFormData['quiz'], ENT_QUOTES, 'UTF-8') ?>">
                                                                <?php $quizHasData = $topicFormData['quiz'] !== ''; ?>
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-outline-secondary"
                                                                    data-quiz-trigger
                                                                    data-quiz-target="#topic_quiz_payload"
                                                                    data-quiz-summary="#topic_quiz_summary"
                                                                    data-quiz-title="Autoevaluación del tema"
                                                                >
                                                                    <?= $quizHasData ? 'Editar autoevaluación' : 'Añadir autoevaluación' ?>
                                                                </button>
                                                                <small class="text-muted mb-0" id="topic_quiz_summary" data-quiz-summary="topic_quiz_payload"><?= htmlspecialchars($topicFormData['quiz_summary'], ENT_QUOTES, 'UTF-8') ?></small>
                                                            </div>
                                                        </div>
                                                        <div class="mt-3">
                                                            <button type="submit" name="save_itinerary_topic" class="btn btn-primary">Guardar tema</button>
                                                            <button type="submit" name="save_itinerary_topic_add" class="btn btn-secondary ml-2">Añadir nuevo tema</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                        <?php elseif ($page === 'configuracion'): ?>
                            <div class="tab-pane active">

                                <h2>Configuración</h2>

                                <?php if ($accountFeedback !== null): ?>
                                    <div class="alert alert-<?= $accountFeedback['type'] === 'success' ? 'success' : 'danger' ?>">
                                        <?= htmlspecialchars($accountFeedback['message'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                                <?php
                                $telegramSettings = $settings['telegram'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
                                $telegramAutoEnabled = ($telegramSettings['auto_post'] ?? 'off') === 'on';
                                $whatsappSettings = $settings['whatsapp'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
                                $whatsappAutoEnabled = ($whatsappSettings['auto_post'] ?? 'off') === 'on';
                                $facebookSettings = $settings['facebook'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
                                $facebookAutoEnabled = ($facebookSettings['auto_post'] ?? 'off') === 'on';
                                $twitterSettings = $settings['twitter'] ?? ['token' => '', 'channel' => '', 'auto_post' => 'off'];
                                $twitterAutoEnabled = ($twitterSettings['auto_post'] ?? 'off') === 'on';
                                ?>

                                <form method="post">

                                    <div class="form-group">
                                        <label class="d-block">Modo de funcionamiento</label>
                                        <div class="btn-group btn-group-sm btn-group-toggle" role="group" data-toggle="buttons">
                                            <?php
                                            $modeIsAlpha = ($settings['sort_order'] ?? 'date') === 'alpha';
                                            ?>
                                            <label class="btn btn-outline-primary <?= !$modeIsAlpha ? 'active' : '' ?>">
                                                <input type="radio"
                                                    name="sort_order"
                                                    id="sort_order_date"
                                                    value="date"
                                                    class="sr-only"
                                                    autocomplete="off"
                                                    <?= !$modeIsAlpha ? 'checked' : '' ?>>
                                                Modo Blog
                                            </label>
                                            <label class="btn btn-outline-primary <?= $modeIsAlpha ? 'active' : '' ?>">
                                                <input type="radio"
                                                    name="sort_order"
                                                    id="sort_order_alpha"
                                                    value="alpha"
                                                    class="sr-only"
                                                    autocomplete="off"
                                                    <?= $modeIsAlpha ? 'checked' : '' ?>>
                                                Modo Diccionario
                                            </label>
                                        </div>
                                        <small class="form-text text-muted">El modo blog ordena por fecha, el modo diccionario agrupa las entradas por orden alfabético.</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="site_author">Nombre del autor u organización</label>
                                        <input type="text" name="site_author" id="site_author" class="form-control" value="<?= htmlspecialchars($settings['site_author'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Fundación Repoblación">
                                    </div>

                                    <div class="form-group">
                                        <label for="site_name">Nombre del blog</label>
                                        <input type="text" name="site_name" id="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Memoria">
                                    </div>

                                    <div class="form-group">

                                        <label for="google_fonts_api">API Key de Google Fonts</label>

                                        <input type="text" name="google_fonts_api" id="google_fonts_api" class="form-control" value="<?= htmlspecialchars($settings['google_fonts_api'] ?? '') ?>" placeholder="AIza...">

                                        <small class="form-text text-muted">Introduce tu clave API para cargar fuentes personalizadas desde Google Fonts.</small>

                                    </div>

                                    <h4 class="mt-4">Integración con Redes Sociales</h4>

                                    <div class="form-group">
                                        <label for="social_default_description">Descripción por defecto</label>
                                        <textarea name="social_default_description" id="social_default_description" class="form-control" rows="3" placeholder="Resumen que aparecerá al compartir la portada en redes sociales."><?= htmlspecialchars($socialDefaultDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label for="social_home_image">Imagen de la portada para redes sociales</label>
                                        <div class="input-group">
                                            <input type="text" name="social_home_image" id="social_home_image" class="form-control" value="<?= htmlspecialchars($socialHomeImage, ENT_QUOTES, 'UTF-8') ?>" placeholder="assets/imagen-portada.jpg" readonly>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#imageModal" data-target-type="field" data-target-input="social_home_image" data-target-prefix="assets/">Seleccionar imagen</button>
                                                <button type="button" class="btn btn-outline-danger" id="clear-social-image">Quitar</button>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Se utilizará como imagen por defecto para la portada y cuando una entrada no tenga imagen destacada.</small>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="social_twitter">Usuario de Twitter / X</label>
                                            <input type="text" name="social_twitter" id="social_twitter" class="form-control" value="<?= htmlspecialchars($socialTwitter, ENT_QUOTES, 'UTF-8') ?>" placeholder="usuario">
                                            <small class="form-text text-muted">Introduce el usuario sin la @ inicial.</small>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="social_facebook_app_id">Facebook App ID</label>
                                            <input type="text" name="social_facebook_app_id" id="social_facebook_app_id" class="form-control" value="<?= htmlspecialchars($socialFacebookAppId, ENT_QUOTES, 'UTF-8') ?>" placeholder="Opcional">
                                        </div>
                                    </div>

                                    <h4 class="mt-4">Telegram (opcional)</h4>
                                    <p class="text-muted">Conecta un bot y un canal/grupo para compartir automáticamente las nuevas entradas.</p>
                                    <div class="form-group">
                                        <label for="telegram_token">Token del bot</label>
                                        <input type="text" name="telegram_token" id="telegram_token" class="form-control" value="<?= htmlspecialchars($telegramSettings['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="123456789:ABCDef...">
                                        <small class="form-text text-muted">Creado con @BotFather. Nunca compartas este token en público.</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="telegram_channel">Canal o grupo</label>
                                        <input type="text" name="telegram_channel" id="telegram_channel" class="form-control" value="<?= htmlspecialchars($telegramSettings['channel'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="@nombre_canal">
                                        <small class="form-text text-muted">Usa el @ del canal o el ID numérico del grupo donde el bot es administrador.</small>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input type="checkbox" class="form-check-input" name="telegram_auto" id="telegram_auto" value="1" <?= $telegramAutoEnabled ? 'checked' : '' ?>>
                                        <label for="telegram_auto" class="form-check-label">Enviar automáticamente cada nueva entrada publicada</label>
                                    </div>

                                    <h4 class="mt-4">WhatsApp (opcional)</h4>
                                    <p class="text-muted">Usa la API de WhatsApp Business Cloud para avisar a tus contactos o grupos.</p>
                                    <div class="form-group">
                                        <label for="whatsapp_token">Token del bot o app</label>
                                        <input type="text" name="whatsapp_token" id="whatsapp_token" class="form-control" value="<?= htmlspecialchars($whatsappSettings['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Token de acceso">
                                        <small class="form-text text-muted">Token generado en Meta Developers para tu número de WhatsApp Business.</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="whatsapp_channel">ID del número de WhatsApp Business</label>
                                        <input type="text" name="whatsapp_channel" id="whatsapp_channel" class="form-control" value="<?= htmlspecialchars($whatsappSettings['channel'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: 123456789012345">
                                        <small class="form-text text-muted">Identificador del número conectado en la API de WhatsApp Cloud (phone number ID).</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="whatsapp_recipient">Número destino</label>
                                        <input type="text" name="whatsapp_recipient" id="whatsapp_recipient" class="form-control" value="<?= htmlspecialchars($whatsappSettings['recipient'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: 34600111222">
                                        <small class="form-text text-muted">Número (con prefijo internacional, sin +) al que se enviará el mensaje.</small>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input type="checkbox" class="form-check-input" name="whatsapp_auto" id="whatsapp_auto" value="1" <?= $whatsappAutoEnabled ? 'checked' : '' ?>>
                                        <label for="whatsapp_auto" class="form-check-label">Enviar automáticamente cada nueva entrada publicada</label>
                                    </div>

                                    <h4 class="mt-4">Facebook (opcional)</h4>
                                    <p class="text-muted">Comparte tus entradas en una página o grupo usando la Graph API.</p>
                                    <div class="form-group">
                                        <label for="facebook_token">Token de acceso</label>
                                        <input type="text" name="facebook_token" id="facebook_token" class="form-control" value="<?= htmlspecialchars($facebookSettings['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="EAABsb...">
                                        <small class="form-text text-muted">Usa un token con permisos para publicar en la página objetivo.</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="facebook_channel">ID de página o grupo</label>
                                        <input type="text" name="facebook_channel" id="facebook_channel" class="form-control" value="<?= htmlspecialchars($facebookSettings['channel'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="1234567890">
                                        <small class="form-text text-muted">Puedes obtenerlo desde la configuración avanzada de la página.</small>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input type="checkbox" class="form-check-input" name="facebook_auto" id="facebook_auto" value="1" <?= $facebookAutoEnabled ? 'checked' : '' ?>>
                                        <label for="facebook_auto" class="form-check-label">Enviar automáticamente cada nueva entrada publicada</label>
                                    </div>

                                    <h4 class="mt-4">Twitter / X (opcional)</h4>
                                    <p class="text-muted">Publica un tweet con el título y enlace de cada entrada.</p>
                                    <div class="form-group">
                                        <label for="twitter_token">Token / Bearer</label>
                                        <input type="text" name="twitter_token" id="twitter_token" class="form-control" value="<?= htmlspecialchars($twitterSettings['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Bearer ...">
                                        <small class="form-text text-muted">Generado desde el portal de desarrolladores de Twitter.</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="twitter_channel">Usuario o ID de cuenta</label>
                                        <input type="text" name="twitter_channel" id="twitter_channel" class="form-control" value="<?= htmlspecialchars($twitterSettings['channel'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="@usuario">
                                        <small class="form-text text-muted">El bot publicará en esta cuenta.</small>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input type="checkbox" class="form-check-input" name="twitter_auto" id="twitter_auto" value="1" <?= $twitterAutoEnabled ? 'checked' : '' ?>>
                                        <label for="twitter_auto" class="form-check-label">Enviar automáticamente cada nueva entrada publicada</label>
                                    </div>

                                    <button type="submit" name="save_settings" class="btn btn-primary">Guardar</button>

                                </form>

                                <?php
                                $accountSettings = $settings['account'] ?? [];
                                $currentUsername = $accountSettings['username'] ?? '';
                                ?>

                                <hr class="my-5">
                                <h3>Cuenta de acceso</h3>
                                <p class="text-muted">Actualiza las credenciales utilizadas para acceder al panel. Necesitas confirmar la contraseña actual.</p>

                                <form method="post" autocomplete="off">
                                    <div class="form-group">
                                        <label for="new_username">Nombre de usuario</label>
                                        <input type="text" name="new_username" id="new_username" class="form-control" value="<?= htmlspecialchars($currentUsername, ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="current_password">Contraseña actual</label>
                                        <input type="password" name="current_password" id="current_password" class="form-control" required>
                                        <small class="form-text text-muted">Se utiliza para verificar que eres la persona autorizada.</small>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="new_password">Nueva contraseña</label>
                                            <input type="password" name="new_password" id="new_password" class="form-control" autocomplete="new-password" placeholder="Deja en blanco para mantener la actual">
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="confirm_password">Confirmar nueva contraseña</label>
                                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" autocomplete="new-password" placeholder="Repite la nueva contraseña">
                                        </div>
                                    </div>
                                    <button type="submit" name="update_account" class="btn btn-outline-primary">Actualizar cuenta</button>
                                </form>

                                

                                                    </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>

        

        </div>

        

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

                        <h5 class="modal-title" id="imageModalLabel">Seleccionar Imagen</h5>

                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">

                            <span aria-hidden="true">&times;</span>

                        </button>

                    </div>

                    <div class="modal-body">

                        <div class="row image-gallery">

                            <?php

                            $media_data = get_media_items(1, 1000); // Load all media for now

                            foreach ($media_data['items'] as $media):

                                $media_name = $media['name'];
                                $media_relative = $media['relative'];
                                $media_type = $media['type'];
                                $media_mime = $media['mime'];
                                $media_src = 'assets/' . $media_relative;

                            ?>

                                <div class="col-md-3 mb-3 gallery-item">

                                    <?php if ($media_type === 'image'): ?>
                                        <img src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover; cursor: pointer;" data-media-name="<?= htmlspecialchars($media_name, ENT_QUOTES, 'UTF-8') ?>" data-media-type="image" data-media-src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" data-media-mime="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>">
                                    <?php elseif ($media_type === 'video'): ?>
                                        <div class="video-thumb-wrapper" data-media-name="<?= htmlspecialchars($media_name, ENT_QUOTES, 'UTF-8') ?>" data-media-type="video" data-media-src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" data-media-mime="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>" style="cursor: pointer; position: relative;">
                                            <video class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover; pointer-events: none;" muted preload="metadata">
                                                <source src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" type="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>">
                                            </video>
                                            <span class="badge badge-dark video-badge" style="position: absolute; bottom: 8px; right: 12px;">Video</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="doc-thumb-wrapper" data-media-name="<?= htmlspecialchars($media_name, ENT_QUOTES, 'UTF-8') ?>" data-media-type="pdf" data-media-src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" data-media-mime="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>" style="cursor: pointer; border: 1px dashed rgba(0,0,0,0.2); border-radius: var(--nammu-radius-md, 12px); padding: 2.5rem 1rem; text-align: center;">
                                            <i class="fas fa-file-pdf" style="font-size: 3rem; color: #c62828;"></i>
                                            <div class="small mt-2 text-muted">PDF</div>
                                        </div>
                                    <?php endif; ?>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <nav aria-label="Page navigation">

                            <ul class="pagination pagination-break" id="image-pagination">

                            </ul>

                        </nav>

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
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('template-settings');
            if (!form) {
                return;
            }

            const apiKey = form.dataset.googleFontsKey || '';
            const titleSelect = document.getElementById('title_font');
            const bodySelect = document.getElementById('body_font');
            const codeSelect = document.getElementById('code_font');
            const quoteSelect = document.getElementById('quote_font');
            const fontsAlert = document.getElementById('fonts-alert');

            const currentTitleFont = titleSelect ? titleSelect.dataset.currentFont || '' : '';
            const currentBodyFont = bodySelect ? bodySelect.dataset.currentFont || '' : '';
            const currentCodeFont = codeSelect ? codeSelect.dataset.currentFont || '' : '';
            const currentQuoteFont = quoteSelect ? quoteSelect.dataset.currentFont || '' : '';

            function fillSelect(selectElement, fonts, current) {
                if (!selectElement) return;
                selectElement.innerHTML = '';
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Selecciona una fuente';
                selectElement.appendChild(placeholder);

                let currentFound = false;
                const orderedFonts = Array.isArray(fonts) ? fonts.slice().sort(function(a, b) {
                    return (a.family || '').localeCompare(b.family || '', 'es', { sensitivity: 'base' });
                }) : [];

                orderedFonts.forEach(function(font) {
                    if (!font || !font.family) {
                        return;
                    }
                    const option = document.createElement('option');
                    option.value = font.family;
                    option.textContent = font.family;
                    if (font.family === current) {
                        option.selected = true;
                        currentFound = true;
                    }
                    selectElement.appendChild(option);
                });

                if (current && !currentFound) {
                    const option = document.createElement('option');
                    option.value = current;
                    option.textContent = current + ' (actual)';
                    option.selected = true;
                    selectElement.appendChild(option);
                }
            }

            if (apiKey && (titleSelect || bodySelect || codeSelect || quoteSelect)) {
                fetch('https://www.googleapis.com/webfonts/v1/webfonts?key=' + encodeURIComponent(apiKey) + '&sort=popularity')
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('No se pudo cargar Google Fonts (HTTP ' + response.status + ')');
                        }
                        return response.json();
                    })
                    .then(function(data) {
                        const fonts = data && Array.isArray(data.items) ? data.items : [];
                        fillSelect(titleSelect, fonts, currentTitleFont);
                        fillSelect(bodySelect, fonts, currentBodyFont);
                        fillSelect(codeSelect, fonts, currentCodeFont);
                        fillSelect(quoteSelect, fonts, currentQuoteFont);
                    })
                    .catch(function(error) {
                        if (fontsAlert) {
                            fontsAlert.innerHTML = '<div class="alert alert-warning mt-3">No se pudieron cargar las fuentes desde Google Fonts. Verifica tu API Key en Configuración.<br><small>' + error.message + '</small></div>';
                        }
                    });
            } else if (fontsAlert) {
                fontsAlert.innerHTML = '<div class="alert alert-info mt-3">Configura tu API Key de Google Fonts en la pestaña Configuración para elegir fuentes personalizadas.</div>';
            }

            form.querySelectorAll('[data-color-field]').forEach(function(container) {
                const picker = container.querySelector('.template-color-picker');
                const input = container.querySelector('.template-color-input');
                if (!picker || !input) {
                    return;
                }

                picker.addEventListener('input', function() {
                    input.value = picker.value;
                });

                input.addEventListener('input', function() {
                    const value = input.value.trim();
                    if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value)) {
                        picker.value = value;
                    }
                });
            });

            var clearLogoBtn = document.getElementById('clear-logo-image');
            if (clearLogoBtn) {
                clearLogoBtn.addEventListener('click', function() {
                    var logoInput = document.getElementById('logo_image');
                    if (logoInput) {
                        logoInput.value = '';
                    }
                });
            }

            var clearSocialBtn = document.getElementById('clear-social-image');
            if (clearSocialBtn) {
                clearSocialBtn.addEventListener('click', function() {
                    var socialInput = document.getElementById('social_home_image');
                    if (socialInput) {
                        socialInput.value = '';
                    }
                });
            }

            var layoutOptions = form.querySelectorAll('.home-layout-option');
            function refreshLayoutSelection() {
                layoutOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            layoutOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshLayoutSelection);
                }
            });
            refreshLayoutSelection();

            var cardStyleOptions = form.querySelectorAll('.home-card-style-option[data-card-style-option]');
            var fullImageOptionsContainer = form.querySelector('[data-full-image-options]');
            var fullImageModeOptions = form.querySelectorAll('.home-card-style-option[data-full-image-mode]');
            var searchModeOptions = form.querySelectorAll('.home-card-style-option[data-search-mode-option]');
            var searchPositionOptions = form.querySelectorAll('.home-card-style-option[data-search-position-option]');
            var searchFloatingOptions = form.querySelectorAll('.home-card-style-option[data-search-floating-option]');
            var footerLogoOptions = form.querySelectorAll('.home-card-style-option[data-footer-logo-option]');
            var searchPositionContainer = form.querySelector('[data-search-position]');
            function refreshCardStyleSelection() {
                var activeStyle = '';
                cardStyleOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                    if (radio && radio.checked) {
                        activeStyle = radio.value;
                    }
                });
                if (fullImageOptionsContainer) {
                    fullImageOptionsContainer.style.display = activeStyle === 'full' ? '' : 'none';
                }
            }
            cardStyleOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshCardStyleSelection);
                }
            });
            refreshCardStyleSelection();
            function refreshFullImageModeSelection() {
                fullImageModeOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            fullImageModeOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshFullImageModeSelection);
                }
            });
            refreshFullImageModeSelection();

            function refreshSearchModeSelection() {
                var activeMode = 'none';
                searchModeOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    var checked = radio && radio.checked;
                    if (checked && radio) {
                        activeMode = radio.value;
                    }
                    option.classList.toggle('active', checked);
                });
                if (searchPositionContainer) {
                    searchPositionContainer.style.display = activeMode === 'none' ? 'none' : '';
                }
            }
            function refreshSearchPositionSelection() {
                searchPositionOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            function refreshSearchFloatingSelection() {
                searchFloatingOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            function refreshFooterLogoSelection() {
                footerLogoOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            searchModeOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', function() {
                        refreshSearchModeSelection();
                        refreshSearchPositionSelection();
                    });
                }
            });
            searchPositionOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshSearchPositionSelection);
                }
            });
            refreshSearchModeSelection();
            refreshSearchPositionSelection();
            searchFloatingOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshSearchFloatingSelection);
                }
            });
            refreshSearchFloatingSelection();
            footerLogoOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshFooterLogoSelection);
                }
            });
            refreshFooterLogoSelection();

            var blocksOptions = form.querySelectorAll('.home-card-style-option[data-blocks-option]');
            function refreshBlocksSelection() {
                blocksOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            blocksOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshBlocksSelection);
                }
            });
            refreshBlocksSelection();

            var entryTocToggle = form.querySelector('[data-entry-toc-toggle]');
            var entryTocOptions = form.querySelector('[data-entry-toc-options]');
            if (entryTocToggle && entryTocOptions) {
                function refreshEntryTocOptions() {
                    var checked = entryTocToggle.querySelector('input[name="entry_toc_auto"]:checked');
                    var shouldShow = checked && checked.value === 'on';
                    entryTocOptions.style.display = shouldShow ? '' : 'none';
                }
                entryTocToggle.querySelectorAll('input[name="entry_toc_auto"]').forEach(function(radio) {
                    radio.addEventListener('change', refreshEntryTocOptions);
                });
                refreshEntryTocOptions();
            }

            var postsInput = document.getElementById('home_per_page');
            var postsAllToggle = document.getElementById('home_per_page_all');
            if (postsInput && postsAllToggle) {
                if (!postsInput.dataset.lastValue) {
                    postsInput.dataset.lastValue = postsInput.value || '';
                }
                postsInput.addEventListener('input', function() {
                    postsInput.dataset.lastValue = postsInput.value;
                });
                function syncPostsInputState() {
                    if (postsAllToggle.checked) {
                        if (postsInput.value !== '') {
                            postsInput.dataset.lastValue = postsInput.value;
                        }
                        postsInput.value = '';
                        postsInput.setAttribute('disabled', 'disabled');
                    } else {
                        postsInput.removeAttribute('disabled');
                        if (postsInput.value === '' && postsInput.dataset.lastValue) {
                            postsInput.value = postsInput.dataset.lastValue;
                        }
                    }
                }
                postsAllToggle.addEventListener('change', syncPostsInputState);
                syncPostsInputState();
            }

            var headerOptions = form.querySelectorAll('.home-header-option');
            var headerGraphicContainer = form.querySelector('[data-header-graphic]');
            var headerGraphicModeContainer = form.querySelector('[data-header-graphic-mode]');
            var headerTextContainer = form.querySelector('[data-header-text]');
            var headerOrderContainer = form.querySelector('[data-header-order]');
            var headerTypeInputs = form.querySelectorAll('input[name="home_header_type"]');

            function getHeaderGraphicModeOptions() {
                if (!headerGraphicModeContainer) {
                    return [];
                }
                return Array.prototype.slice.call(headerGraphicModeContainer.querySelectorAll('.home-card-style-option[data-header-mode-option]'));
            }

            function getHeaderTextOptions() {
                if (!headerTextContainer) {
                    return [];
                }
                return Array.prototype.slice.call(headerTextContainer.querySelectorAll('.home-card-style-option[data-header-text-option]'));
            }

            function getHeaderOrderOptions() {
                if (!headerOrderContainer) {
                    return [];
                }
                return Array.prototype.slice.call(headerOrderContainer.querySelectorAll('.home-card-style-option[data-header-order-option]'));
            }

            function refreshHeaderSelection() {
                var activeType = 'none';
                headerOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    var checked = radio && radio.checked;
                    option.classList.toggle('active', checked);
                    if (checked && radio) {
                        activeType = radio.value;
                    }
                });

                var showImageConfig = (activeType === 'graphic' || activeType === 'mixed');
                var showTextConfig = (activeType === 'text' || activeType === 'mixed');
                if (headerGraphicContainer) {
                    headerGraphicContainer.style.display = showImageConfig ? '' : 'none';
                }
                if (headerGraphicModeContainer) {
                    headerGraphicModeContainer.style.display = showImageConfig ? '' : 'none';
                }
                if (headerTextContainer) {
                    headerTextContainer.style.display = showTextConfig ? '' : 'none';
                }
                if (headerOrderContainer) {
                    headerOrderContainer.style.display = activeType === 'mixed' ? '' : 'none';
                }

                refreshHeaderModeSelection();
                refreshHeaderTextSelection();
                refreshHeaderOrderSelection();
            }

            headerTypeInputs.forEach(function(input) {
                input.addEventListener('change', refreshHeaderSelection);
            });

            function refreshHeaderModeSelection() {
                getHeaderGraphicModeOptions().forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }

            getHeaderGraphicModeOptions().forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshHeaderModeSelection);
                }
            });

            function refreshHeaderTextSelection() {
                getHeaderTextOptions().forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }

            getHeaderTextOptions().forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshHeaderTextSelection);
                }
            });

            function refreshHeaderOrderSelection() {
                getHeaderOrderOptions().forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }

            getHeaderOrderOptions().forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshHeaderOrderSelection);
                }
            });

            refreshHeaderSelection();

            var cornerOptions = form.querySelectorAll('.home-card-style-option[data-corners-option]');
            function refreshCornerSelection() {
                cornerOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            cornerOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshCornerSelection);
                }
            });
            refreshCornerSelection();

            var clearHeaderBtn = document.getElementById('clear-header-image');
            if (clearHeaderBtn) {
                clearHeaderBtn.addEventListener('click', function() {
                    var headerInput = document.getElementById('home_header_image');
                    if (headerInput) {
                        headerInput.value = '';
                    }
                });
            }
        });
        </script>
<?php endif; ?>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var toolbars = document.querySelectorAll('[data-markdown-toolbar]');
            if (!toolbars.length) {
                return;
            }

            toolbars.forEach(function(toolbar) {
                var targetSelector = toolbar.getAttribute('data-target');
                var textarea = null;
                if (targetSelector) {
                    textarea = document.querySelector(targetSelector);
                }
                if (!textarea) {
                    var sibling = toolbar.nextElementSibling;
                    if (sibling && sibling.tagName === 'TEXTAREA') {
                        textarea = sibling;
                    }
                }
                if (!textarea) {
                    toolbar.querySelectorAll('button').forEach(function(btn) {
                        btn.disabled = true;
                    });
                    return;
                }

                textarea.addEventListener('keydown', function(event) {
                    if (!(event.ctrlKey || event.metaKey)) {
                        return;
                    }
                    var key = event.key ? event.key.toLowerCase() : '';
                    var action = null;
                    if (key === 'b') {
                        action = 'bold';
                    } else if (key === 'i') {
                        action = 'italic';
                    } else if (key === 'k') {
                        action = 'link';
                    }
                    if (action) {
                        event.preventDefault();
                        applyMarkdownAction(textarea, action);
                    }
                });

                toolbar.addEventListener('click', function(event) {
                    var button = findActionButton(event.target, toolbar);
                    if (!button) {
                        return;
                    }
                    event.preventDefault();
                    var action = button.getAttribute('data-md-action');
                    if (action) {
                        applyMarkdownAction(textarea, action);
                    }
                });
            });

            function findActionButton(element, container) {
                if (!element) {
                    return null;
                }
                if (typeof element.closest === 'function') {
                    var closest = element.closest('button[data-md-action]');
                    if (closest && container.contains(closest)) {
                        return closest;
                    }
                }
                while (element && element !== container) {
                    if (element.matches && element.matches('button[data-md-action]')) {
                        return element;
                    }
                    element = element.parentElement;
                }
                return null;
            }

            function applyMarkdownAction(textarea, action) {
                switch (action) {
                    case 'bold':
                        wrapSelection(textarea, '**', '**', 'Texto en negrita');
                        break;
                    case 'italic':
                        wrapSelection(textarea, '*', '*', 'Texto en cursiva');
                        break;
                    case 'strike':
                        wrapSelection(textarea, '~~', '~~', 'Texto tachado');
                        break;
                    case 'code':
                        wrapSelection(textarea, '`', '`', 'codigo');
                        break;
                    case 'sup':
                        wrapSelection(textarea, '^', '^', 'superíndice');
                        break;
                    case 'link':
                        insertLink(textarea);
                        break;
                    case 'quote':
                        applyLinePrefix(textarea, '> ', 'Texto citado');
                        break;
                    case 'ul':
                        applyUnorderedList(textarea);
                        break;
                    case 'ol':
                        applyOrderedList(textarea);
                        break;
                    case 'heading':
                        insertHeading(textarea);
                        break;
                    case 'code-block':
                        insertCodeBlock(textarea);
                        break;
                    case 'hr':
                        insertHorizontalRule(textarea);
                        break;
                    default:
                        break;
                }
            }

            function getRange(textarea) {
                var start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
                var end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : start;
                return {
                    start: start,
                    end: end,
                    text: textarea.value.slice(start, end)
                };
            }

            function replaceSelection(textarea, replacement, selectionStartOffset, selectionEndOffset) {
                var previousScrollTop = textarea.scrollTop;
                var value = textarea.value;
                var range = getRange(textarea);
                textarea.value = value.slice(0, range.start) + replacement + value.slice(range.end);
                var selStart = range.start + (typeof selectionStartOffset === 'number' ? selectionStartOffset : replacement.length);
                var selEnd = range.start + (typeof selectionEndOffset === 'number' ? selectionEndOffset : replacement.length);
                setSelection(textarea, selStart, selEnd, previousScrollTop);
            }

            function setSelection(textarea, start, end, previousScrollTop) {
                var scrollTop = typeof previousScrollTop === 'number' ? previousScrollTop : textarea.scrollTop;
                textarea.focus();
                if (typeof textarea.setSelectionRange === 'function') {
                    textarea.setSelectionRange(start, end);
                }
                textarea.scrollTop = scrollTop;
                triggerInput(textarea);
            }

            function triggerInput(textarea) {
                try {
                    var event = new Event('input', { bubbles: true });
                    textarea.dispatchEvent(event);
                } catch (err) {
                    var legacyEvent = document.createEvent('Event');
                    legacyEvent.initEvent('input', true, true);
                    textarea.dispatchEvent(legacyEvent);
                }
            }

            function wrapSelection(textarea, before, after, placeholder) {
                var range = getRange(textarea);
                var selected = range.text || placeholder;
                var replacement = before + selected + after;
                replaceSelection(textarea, replacement, before.length, before.length + selected.length);
            }

            function applyLinePrefix(textarea, prefix, placeholder) {
                var range = getRange(textarea);
                var text = range.text || placeholder;
                var lines = text.split(/\r?\n/);
                var transformed = lines.map(function(line) {
                    var clean = line.replace(/^\s*>+\s?/, '');
                    if (clean.trim() === '' && text === placeholder) {
                        clean = placeholder;
                    }
                    return prefix + clean;
                }).join('\n');
                replaceSelection(textarea, transformed, 0, transformed.length);
            }

            function applyUnorderedList(textarea) {
                var range = getRange(textarea);
                var text = range.text || 'Elemento de lista';
                var lines = text.split(/\r?\n/);
                var transformed = lines.map(function(line) {
                    var clean = line.replace(/^\s*([-*+]|\d+\.)\s*/, '').trim();
                    if (clean === '') {
                        clean = 'Elemento de lista';
                    }
                    return '- ' + clean;
                }).join('\n');
                replaceSelection(textarea, transformed, 0, transformed.length);
            }

            function applyOrderedList(textarea) {
                var range = getRange(textarea);
                var text = range.text || 'Elemento de lista';
                var lines = text.split(/\r?\n/);
                var counter = 1;
                var transformed = lines.map(function(line) {
                    var clean = line.replace(/^\s*\d+\.?\s*/, '').trim();
                    if (clean === '') {
                        clean = 'Elemento ' + counter;
                    }
                    var current = counter + '. ' + clean;
                    counter += 1;
                    return current;
                }).join('\n');
                replaceSelection(textarea, transformed, 0, transformed.length);
            }

            function insertHeading(textarea) {
                var range = getRange(textarea);
                var text = range.text || 'Título de sección';
                var parts = text.split(/\r?\n/);
                var firstLine = parts.shift() || 'Título de sección';
                firstLine = firstLine.replace(/^#{1,6}\s*/, '');
                var heading = '## ' + firstLine;
                if (parts.length) {
                    parts.unshift(heading);
                    var replacement = parts.join('\n');
                    replaceSelection(textarea, replacement, 3, heading.length);
                } else {
                    replaceSelection(textarea, heading, 3, heading.length);
                }
            }

            function insertCodeBlock(textarea) {
                var range = getRange(textarea);
                var text = range.text || 'Tu código aquí';
                var replacement = '```\n' + text + '\n```\n';
                replaceSelection(textarea, replacement, 4, 4 + text.length);
            }

            function insertHorizontalRule(textarea) {
                var insertText = '\n\n---\n\n';
                replaceSelection(textarea, insertText, insertText.length, insertText.length);
            }

            function insertLink(textarea) {
                var range = getRange(textarea);
                var label = range.text || 'Texto del enlace';
                var defaultUrl = '';
                if (range.text && /^https?:\/\//i.test(range.text.trim())) {
                    defaultUrl = range.text.trim();
                }
                var url = window.prompt('Introduce la URL del enlace', defaultUrl || 'https://');
                if (!url) {
                    return;
                }
                var replacement = '[' + label + '](' + url + ')';
                replaceSelection(textarea, replacement, 1, 1 + label.length);
            }
        });
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

        $(document).ready(function() {

            var imageTargetMode = '';
            var imageTargetInput = '';
            var imageTargetPrefix = '';
            var imageTargetEditor = '';

        

            $('#imageModal').on('show.bs.modal', function (event) {

                var button = $(event.relatedTarget);

                imageTargetMode = button.data('target-type') || '';
                imageTargetInput = button.data('target-input') || '';
                imageTargetPrefix = button.data('target-prefix') || '';
                imageTargetEditor = button.data('target-editor') || '';

            });

        

            var currentPage = 1;

            var itemsPerPage = 8;

            var galleryItems = $('.image-gallery .gallery-item');

            var totalItems = galleryItems.length;

            var totalPages = Math.ceil(totalItems / itemsPerPage);

        

            function showPage(page) {

                galleryItems.hide();

                galleryItems.slice((page - 1) * itemsPerPage, page * itemsPerPage).show();

            }

        

            function setupPagination() {

                var pagination = $('#image-pagination');

                pagination.empty();

                var groupSize = 16;

                for (var i = 1; i <= totalPages; i++) {

                    var li = $('<li class="page-item"><a class="page-link" href="#">' + i + '</a></li>');

                    if (i === currentPage) {

                        li.addClass('active');

                    }

                    li.on('click', function(e) {

                        e.preventDefault();

                        currentPage = parseInt($(this).text());

                        showPage(currentPage);

                        setupPagination();

                    });

                    pagination.append(li);

                    if (i % groupSize === 0 && i !== totalPages) {

                        pagination.append('<li class="page-break"></li>');

                    }

                }

            }

        

            showPage(1);

            setupPagination();

        

            $('.image-gallery').on('click', '[data-media-name]', function() {

                var $media = $(this);
                var mediaName = $media.data('mediaName');
                var mediaType = $media.data('mediaType') || 'image';
                var mediaSrc = $media.data('mediaSrc') || '';
                var mediaMime = $media.data('mediaMime') || '';

                if (!mediaName) {
                    return;
                }

                if (imageTargetMode === 'field') {

                    if (mediaType !== 'image') {
                        alert('Solo puedes seleccionar imágenes para este campo.');
                        return;
                    }
                    var targetId = imageTargetInput || 'image';
                    var $input = $('#' + targetId);
                    if ($input.length) {
                        var prefix = imageTargetPrefix || '';
                        $input.val(prefix + mediaName);
                    }

                } else if (imageTargetMode === 'editor') {

                    insertMediaInContent(mediaType, mediaSrc, mediaMime);

                }

                $('#imageModal').modal('hide');

            });

            function insertMediaInContent(type, source, mime) {
                if (!source) {
                    source = '';
                }
                var contentTextArea = null;
                if (imageTargetEditor) {
                    try {
                        contentTextArea = document.querySelector(imageTargetEditor);
                    } catch (selectorError) {
                        contentTextArea = null;
                    }
                }
                if (!contentTextArea && document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
                    contentTextArea = document.activeElement;
                }
                if (!contentTextArea) {
                    contentTextArea = document.querySelector('[data-markdown-editor]') || document.getElementById('content');
                }
                if (!contentTextArea) {
                    return;
                }
                var snippet = '';
                if (type === 'video') {
                    var safeSource = source;
                    var sourceTag = mime ? '        <source src="' + safeSource + '" type="' + mime + '">' : '        <source src="' + safeSource + '">';
                    snippet = '\n\n<div class="embedded-video">\n    <video controls preload="metadata">\n' + sourceTag + '\n    </video>\n</div>\n\n';
                } else if (type === 'pdf') {
                    var hasHash = source.indexOf('#') !== -1;
                    var pdfBase = source.split('#')[0];
                    var defaultParams = 'page=1&zoom=page-fit&spread=0&toolbar=0&navpanes=0&scrollbar=0&statusbar=0&pagemode=none';
                    var pdfSrc = hasHash ? source : pdfBase + '#' + defaultParams;
                    var pdfHref = pdfBase;
                    snippet = '\n\n<div class="embedded-pdf">\n    <iframe src="' + pdfSrc + '" title="Documento PDF" loading="lazy" allowfullscreen></iframe>\n    <div class="embedded-pdf__actions" aria-label="Acciones del PDF">\n        <a class="embedded-pdf__action" href="' + pdfHref + '" download>Descargar PDF</a>\n        <a class="embedded-pdf__action" href="' + pdfHref + '" target="_blank" rel="noopener">Ver a pantalla completa</a>\n    </div>\n</div>\n\n';
                } else {
                    snippet = '![](' + source + ')';
                }
                insertTextAtCursor(contentTextArea, snippet);
            }

            function insertTextAtCursor(textarea, text) {
                if (!textarea) {
                    return;
                }
                var start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : textarea.value.length;
                var end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : start;
                var value = textarea.value;
                textarea.value = value.substring(0, start) + text + value.substring(end);
                var cursorPosition = start + text.length;
                if (typeof textarea.setSelectionRange === 'function') {
                    textarea.focus();
                    textarea.setSelectionRange(cursorPosition, cursorPosition);
                }
                try {
                    var event = new Event('input', { bubbles: true });
                    textarea.dispatchEvent(event);
                } catch (evtError) {
                    var legacy = document.createEvent('Event');
                    legacy.initEvent('input', true, true);
                    textarea.dispatchEvent(legacy);
                }
            }

        

                // --- New Custom Image Editor Logic (v2 - Fixed Selection) ---

        

                const canvas = document.getElementById('imageCanvas');

        

                const ctx = canvas.getContext('2d');

        

                const brightnessSlider = document.getElementById('brightness');

        

                const contrastSlider = document.getElementById('contrast');

        

                const saturationSlider = document.getElementById('saturation');

        

                const cropBtn = document.getElementById('cropBtn');

        

                const pixelateBtn = document.getElementById('pixelateBtn');

        

                const resetFiltersBtn = document.getElementById('resetFiltersBtn');

        

                const saveBtn = document.getElementById('save-image-btn');

        

            

        

                let originalImage = new Image();

        

                let selection = null;

        

                let isSelecting = false;

        

                

        

                originalImage.crossOrigin = "Anonymous";

        

            

        

                // --- Drawing Functions ---

        

            

        

                function draw() {

        

                    // Clear canvas

        

                    ctx.clearRect(0, 0, canvas.width, canvas.height);

        

            

        

                    // Apply filters

        

                    const brightness = brightnessSlider.value;

        

                    const contrast = contrastSlider.value;

        

                    const saturation = saturationSlider.value;

        

                    ctx.filter = `brightness(${brightness}%) contrast(${contrast}%) saturate(${saturation}%)`;

        

                    

        

                    // Draw the image

        

                    ctx.drawImage(originalImage, 0, 0, canvas.width, canvas.height);

        

            

        

                    // Draw the selection rectangle (if it exists)

        

                    if (selection) {

        

                        drawSelection();

        

                    }

        

                }

        

            

        

                function drawSelection() {

        

                    ctx.save();

        

                    ctx.filter = 'none'; // Ensure selection rect is not filtered

        

                    ctx.setLineDash([5, 5]);

        

                    ctx.strokeStyle = 'red';

        

                    const { startX, startY, endX, endY } = selection;

        

                    ctx.strokeRect(startX, startY, endX - startX, endY - startY);

        

                    ctx.restore();

        

                }

        

            

        

                function resetFilters() {

        

                    brightnessSlider.value = 100;

        

                    contrastSlider.value = 100;

        

                    saturationSlider.value = 100;

        

                    draw();

        

                }

        

            

        

                // --- Mouse Coordinate Handling ---

        

                

        

                function getCanvasMousePos(e) {

        

                    const rect = canvas.getBoundingClientRect();

        

                    const scaleX = canvas.width / rect.width;

        

                    const scaleY = canvas.height / rect.height;

        

                    return {

        

                        x: (e.clientX - rect.left) * scaleX,

        

                        y: (e.clientY - rect.top) * scaleY

        

                    };

        

                }

        

            

        

                canvas.addEventListener('mousedown', (e) => {

        

                    isSelecting = true;

        

                    const pos = getCanvasMousePos(e);

        

                    selection = {

        

                        startX: pos.x,

        

                        startY: pos.y,

        

                        endX: pos.x,

        

                        endY: pos.y

        

                    };

        

                });

        

            

        

                canvas.addEventListener('mousemove', (e) => {

        

                    if (isSelecting) {

        

                        const pos = getCanvasMousePos(e);

        

                        selection.endX = pos.x;

        

                        selection.endY = pos.y;

        

                        draw();

        

                    }

        

                });

        

            

        

                canvas.addEventListener('mouseup', (e) => {

        

                    isSelecting = false;

        

                    draw();

        

                });

        

                

        

                // --- Editor Button Functions ---

        

            

        

                cropBtn.addEventListener('click', () => {

        

                    if (!selection) {

        

                        alert("Por favor, selecciona un área primero.");

        

                        return;

        

                    }

        

                    const { startX, startY, endX, endY } = selection;

        

                    const width = Math.abs(endX - startX);

        

                    const height = Math.abs(endY - startY);

        

                    const left = Math.min(startX, endX);

        

                    const top = Math.min(startY, endY);

        

            

        

                    if (width === 0 || height === 0) {

        

                        selection = null;

        

                        draw();

        

                        return;

        

                    }

        

            

        

                    const croppedImage = new Image();

        

                    croppedImage.onload = () => {

        

                        canvas.width = width;

        

                        canvas.height = height;

        

                        originalImage = croppedImage;

        

                        selection = null;

        

                        resetFilters();

        

                    }

        

                    

        

                    const tempCanvas = document.createElement('canvas');

        

                    tempCanvas.width = canvas.width;

        

                    tempCanvas.height = canvas.height;

        

                    const tempCtx = tempCanvas.getContext('2d');

        

                    // Draw the UNFILTERED original image to the temp canvas

        

                    tempCtx.drawImage(originalImage, 0, 0, canvas.width, canvas.height);

        

                    

        

                    const tempCanvas2 = document.createElement('canvas');

        

                    tempCanvas2.width = width;

        

                    tempCanvas2.height = height;

        

                    const tempCtx2 = tempCanvas2.getContext('2d');

        

                    tempCtx2.drawImage(tempCanvas, left, top, width, height, 0, 0, width, height);

        

            

        

                    croppedImage.src = tempCanvas2.toDataURL();

        

                });

        

            

        

                pixelateBtn.addEventListener('click', () => {

        

                    if (!selection) {

        

                        alert("Por favor, selecciona un área primero.");

        

                        return;

        

                    }

        

                    const pixelSize = 10;

        

                    const { startX, startY, endX, endY } = selection;

        

                    const left = Math.min(startX, endX);

        

                    const top = Math.min(startY, endY);

        

                    const width = Math.abs(endX - startX);

        

                    const height = Math.abs(endY - startY);

        

            

        

                    // Draw on the unfiltered version

        

                    ctx.filter = 'none';

        

                    ctx.drawImage(originalImage, 0, 0, canvas.width, canvas.height);

        

            

        

                    for (let y = top; y < top + height; y += pixelSize) {

        

                        for (let x = left; x < left + width; x += pixelSize) {

        

                            const blockWidth = Math.min(pixelSize, left + width - x);

        

                            const blockHeight = Math.min(pixelSize, top + height - y);

        

                            const imageData = ctx.getImageData(x, y, blockWidth, blockHeight);

        

                            const data = imageData.data;

        

                            let r = 0, g = 0, b = 0;

        

                            let count = data.length / 4;

        

                            for (let i = 0; i < data.length; i += 4) {

        

                                r += data[i];

        

                                g += data[i+1];

        

                                b += data[i+2];

        

                            }

        

                            

        

                            ctx.fillStyle = `rgb(${Math.floor(r/count)}, ${Math.floor(g/count)}, ${Math.floor(b/count)})`;

        

                            ctx.fillRect(x, y, pixelSize, pixelSize);

        

                        }

        

                    }

        

                    originalImage.src = canvas.toDataURL(); // Update the base image

        

                    selection = null;

        

                    draw(); // Redraw with filters

        

                });

        

            

        

                // --- Load & Save ---

        

            

        

                $(document).on('click', '.edit-image-btn', function() {

        

                    var imagePath = $(this).data('image-path');

        

                    var imageName = $(this).data('image-name');

        

                    $('#new-image-name').val(imageName + '-edited.png');

        

                    

        

                    originalImage.onload = () => {

        

                        canvas.width = originalImage.naturalWidth;

        

                        canvas.height = originalImage.naturalHeight;

        

                        selection = null;

        

                        resetFilters();

        

                    };

        

                    originalImage.src = imagePath + '?t=' + new Date().getTime(); // Prevent caching

        

                    $('#imageEditorModal').modal('show');

        

                });

        

            

        

                saveBtn.addEventListener('click', function() {

        

                    var imageName = $('#new-image-name').val();

        

                    if (!imageName) {

        

                        alert('Por favor, introduce un nombre para el archivo.');

        

                        return;

        

                    }

        

                    

        

                    // Draw final image with filters before saving

        

                    draw();

        

            

        

                    var imageData = canvas.toDataURL('image/png');

        

            

        

                    var form = $('<form action="admin.php?page=resources" method="post"></form>');

        

                    form.append('<input type="hidden" name="save_edited_image" value="1">');

        

                    form.append($('<input type="hidden" name="image_name">').val(imageName));

        

                    form.append($('<input type="hidden" name="image_data">').val(imageData));

        

                    

        

                    $('body').append(form);

        

                    form.submit();

        

                });

        

            

        

                brightnessSlider.addEventListener('input', draw);

        

                contrastSlider.addEventListener('input', draw);

        

                saturationSlider.addEventListener('input', draw);

        

                resetFiltersBtn.addEventListener('click', resetFilters);

        });

        </script>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var classSelect = document.querySelector('[data-itinerary-class-select]');
            var customWrapper = document.querySelector('[data-itinerary-class-custom-wrapper]');
            var customInput = document.getElementById('itinerary_class_custom');
            if (!classSelect || !customWrapper) {
                return;
            }
            var toggleCustomField = function() {
                if (classSelect.value === 'Otros') {
                    customWrapper.classList.remove('d-none');
                    if (customInput) {
                        customInput.setAttribute('required', 'required');
                    }
                } else {
                    customWrapper.classList.add('d-none');
                    if (customInput) {
                        customInput.removeAttribute('required');
                    }
                }
            };
            classSelect.addEventListener('change', toggleCustomField);
            toggleCustomField();
        });
        </script>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var slugInputs = document.querySelectorAll('[data-slug-input]');
            if (!slugInputs.length) {
                return;
            }
            function sanitizeSlug(value, trimEdges) {
                var normalized = (value || '').toString().toLowerCase();
                normalized = normalized.replace(/[^a-z0-9-]+/g, '-');
                normalized = normalized.replace(/-{2,}/g, '-');
                if (trimEdges) {
                    normalized = normalized.replace(/^-+/, '').replace(/-+$/, '');
                } else {
                    normalized = normalized.replace(/^-+/, '');
                }
                return normalized;
            }
            slugInputs.forEach(function(input) {
                var applySanitizedValue = function() {
                    var sanitized = sanitizeSlug(input.value, false);
                    if (input.value !== sanitized) {
                        input.value = sanitized;
                    }
                };
                var applyTrimmedValue = function() {
                    var sanitized = sanitizeSlug(input.value, true);
                    if (input.value !== sanitized) {
                        input.value = sanitized;
                    }
                };
                input.addEventListener('input', applySanitizedValue);
                input.addEventListener('blur', applyTrimmedValue);
            });
        });
        </script>

        <script>
        $(function() {
            var statsModal = $('#itineraryStatsModal');
            if (!statsModal.length) {
                return;
            }
            statsModal.on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var statsRaw = button && button.attr('data-itinerary-stats') ? button.attr('data-itinerary-stats') : '{}';
                var title = button && button.data('itinerary-title') ? button.data('itinerary-title') : 'Itinerario';
                var slug = button && button.data('itinerary-slug') ? button.data('itinerary-slug') : '';
                var stats;
                try {
                    stats = JSON.parse(statsRaw);
                } catch (err) {
                    stats = {started: 0, topics: []};
                }
                stats = stats || {};
                var started = parseInt(stats.started, 10);
                if (isNaN(started) || started < 0) {
                    started = 0;
                }
                statsModal.find('[data-stats-title]').text(title);
                var presentationReaders = parseInt(stats.presentation_readers, 10);
                if (isNaN(presentationReaders) || presentationReaders < 0) {
                    presentationReaders = started;
                }
                statsModal.find('[data-stats-started]').text('Leyeron la presentación del itinerario ' + presentationReaders + ' usuarios reales');
                var note = statsModal.find('[data-stats-note]');
                if (note.length) {
                    note.text('Puedes poner a cero todas las métricas de "' + title + '". Esta acción no se puede deshacer.');
                }
                var resetForm = statsModal.find('[data-reset-stats-form]');
                var resetSlugInput = statsModal.find('[data-reset-stats-slug]');
                var resetBtn = statsModal.find('[data-reset-stats-button]');
                if (resetSlugInput.length) {
                    resetSlugInput.val(slug || '');
                }
                if (resetBtn.length) {
                    resetBtn.prop('disabled', !slug);
                }
                var tbody = statsModal.find('[data-stats-table-body]');
                tbody.empty();
                var topics = Array.isArray(stats.topics) ? stats.topics : [];
                if (!topics.length) {
                    tbody.append('<tr><td colspan="3" class="text-muted">Todavía no hay usuarios con progreso registrado.</td></tr>');
                    return;
                }
                topics.forEach(function(topic) {
                    var number = parseInt(topic.number, 10);
                    if (isNaN(number)) {
                        number = 0;
                    }
                    var label = number > 0 ? 'Tema ' + number : 'Tema';
                    if (topic.title) {
                        label += ' — ' + topic.title;
                    }
                    var count = parseInt(topic.count, 10);
                    if (isNaN(count) || count < 0) {
                        count = 0;
                    }
                    var percentLabel = started > 0 ? ((count / started) * 100).toFixed(1) + '%' : '—';
                    var row = $('<tr></tr>');
                    row.append($('<td></td>').text(label));
                    row.append($('<td></td>').text(count));
                row.append($('<td></td>').text(percentLabel));
                tbody.append(row);
            });
            statsModal.find('[data-reset-stats-form]').on('submit', function() {
                return window.confirm('¿Seguro que quieres poner a cero las estadísticas de este itinerario? Esta acción eliminará todos los conteos registrados.');
            });
        });
        });
        </script>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var quizModal = document.querySelector('[data-topic-quiz-modal]');
            var quizBackdrop = document.querySelector('[data-topic-quiz-backdrop]');
            var triggers = document.querySelectorAll('[data-quiz-trigger]');
            if (!quizModal || !quizBackdrop || !triggers.length) {
                return;
            }
            var minInput = quizModal.querySelector('[data-topic-quiz-min]');
            var questionsWrapper = quizModal.querySelector('[data-topic-quiz-questions]');
            var addQuestionBtn = quizModal.querySelector('[data-topic-quiz-add-question]');
            var saveBtn = quizModal.querySelector('[data-topic-quiz-save]');
            var clearBtn = quizModal.querySelector('[data-topic-quiz-clear]');
            var closeButtons = quizModal.querySelectorAll('[data-topic-quiz-close]');
            var modalTitle = quizModal.querySelector('#topicQuizModalTitle');
            var activeContext = {
                input: null,
                summary: null,
                trigger: null
            };

            function parseQuizValue(value) {
                var payload = (value || '').trim();
                if (payload === '') {
                    return {minimum_correct: 1, questions: []};
                }
                try {
                    var parsed = JSON.parse(payload);
                    if (parsed && Array.isArray(parsed.questions)) {
                        var min = parseInt(parsed.minimum_correct, 10);
                        if (!min || min < 1) {
                            min = parsed.questions.length || 1;
                        }
                        return {
                            minimum_correct: Math.min(parsed.questions.length || 1, min),
                            questions: parsed.questions
                        };
                    }
                } catch (err) {
                    console.warn('No se pudo leer la autoevaluación almacenada', err);
                }
                return {minimum_correct: 1, questions: []};
            }

            function updateSummary(input, summaryEl, trigger) {
                if (!input) {
                    return;
                }
                var state = parseQuizValue(input.value);
                if (summaryEl) {
                    if (!state.questions.length) {
                        summaryEl.textContent = '';
                    } else {
                        var label = state.questions.length === 1 ? 'pregunta' : 'preguntas';
                        summaryEl.textContent = state.questions.length + ' ' + label + ' · mínimo ' + state.minimum_correct + ' correctas';
                    }
                }
                if (trigger) {
                    trigger.textContent = state.questions.length ? 'Editar autoevaluación' : 'Añadir autoevaluación';
                }
            }

            function toggleModal(show) {
                if (show) {
                    quizModal.classList.remove('d-none');
                    quizBackdrop.classList.remove('d-none');
                    quizModal.setAttribute('aria-hidden', 'false');
                } else {
                    quizModal.classList.add('d-none');
                    quizBackdrop.classList.add('d-none');
                    quizModal.setAttribute('aria-hidden', 'true');
                }
            }

            function addAnswer(container, answerData) {
                var row = document.createElement('div');
                row.className = 'topic-quiz-answer';
                row.setAttribute('data-quiz-answer', '1');

                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'mr-2';
                checkbox.checked = !!(answerData && answerData.correct);

                var input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control';
                input.placeholder = 'Respuesta posible';
                input.value = answerData && answerData.text ? answerData.text : '';
                input.setAttribute('data-quiz-answer-text', '1');

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-link text-danger btn-sm';
                removeBtn.textContent = 'Quitar';
                removeBtn.addEventListener('click', function() {
                    row.remove();
                });

                row.appendChild(checkbox);
                row.appendChild(input);
                row.appendChild(removeBtn);
                container.appendChild(row);
            }

            function addQuestion(questionData) {
                var block = document.createElement('div');
                block.className = 'topic-quiz-question';
                block.setAttribute('data-quiz-question', '1');

                var header = document.createElement('div');
                header.className = 'd-flex justify-content-between align-items-center mb-2';

                var title = document.createElement('h5');
                title.className = 'mb-0';
                title.textContent = 'Pregunta';
                header.appendChild(title);

                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-link text-danger btn-sm';
                removeBtn.textContent = 'Quitar';
                removeBtn.addEventListener('click', function() {
                    block.remove();
                });
                header.appendChild(removeBtn);

                var questionInput = document.createElement('input');
                questionInput.type = 'text';
                questionInput.className = 'form-control mb-3';
                questionInput.placeholder = 'Enunciado de la pregunta';
                questionInput.value = questionData && questionData.text ? questionData.text : '';
                questionInput.setAttribute('data-quiz-question-text', '1');

                var answersWrapper = document.createElement('div');
                answersWrapper.className = 'topic-quiz-answers';
                answersWrapper.setAttribute('data-quiz-answers', '1');

                var answers = questionData && Array.isArray(questionData.answers) ? questionData.answers : [];
                if (!answers.length) {
                    addAnswer(answersWrapper);
                    addAnswer(answersWrapper);
                } else {
                    answers.forEach(function(answer) {
                        addAnswer(answersWrapper, answer);
                    });
                }

                var addAnswerBtn = document.createElement('button');
                addAnswerBtn.type = 'button';
                addAnswerBtn.className = 'btn btn-outline-primary btn-sm mt-2';
                addAnswerBtn.textContent = 'Añadir respuesta';
                addAnswerBtn.addEventListener('click', function() {
                    addAnswer(answersWrapper);
                });

                block.appendChild(header);
                block.appendChild(questionInput);
                block.appendChild(answersWrapper);
                block.appendChild(addAnswerBtn);
                questionsWrapper.appendChild(block);
            }

            function loadQuestionsIntoModal(state) {
                questionsWrapper.innerHTML = '';
                var quizState = state || {minimum_correct: 1, questions: []};
                var questions = quizState.questions.length ? quizState.questions : [];
                minInput.value = quizState.minimum_correct || 1;
                if (!questions.length) {
                    addQuestion();
                    return;
                }
                questions.forEach(function(question) {
                    addQuestion(question);
                });
            }

            function collectQuizData() {
                var questionBlocks = questionsWrapper.querySelectorAll('[data-quiz-question]');
                var quizQuestions = [];
                var hasError = false;
                questionBlocks.forEach(function(block) {
                    if (hasError) {
                        return;
                    }
                    var textInput = block.querySelector('[data-quiz-question-text]');
                    var questionText = textInput ? textInput.value.trim() : '';
                    if (questionText === '') {
                        hasError = true;
                        alert('Todas las preguntas necesitan un enunciado.');
                        return;
                    }
                    var answers = [];
                    var answerNodes = block.querySelectorAll('[data-quiz-answer]');
                    answerNodes.forEach(function(answerNode) {
                        var answerTextInput = answerNode.querySelector('[data-quiz-answer-text]');
                        var answerText = answerTextInput ? answerTextInput.value.trim() : '';
                        if (answerText === '') {
                            return;
                        }
                        var checkbox = answerNode.querySelector('input[type="checkbox"]');
                        answers.push({
                            text: answerText,
                            correct: checkbox ? checkbox.checked : false
                        });
                    });
                    if (!answers.length) {
                        hasError = true;
                        alert('Cada pregunta necesita al menos una respuesta.');
                        return;
                    }
                    var hasCorrect = answers.some(function(answer) { return answer.correct; });
                    if (!hasCorrect) {
                        hasError = true;
                        alert('Cada pregunta necesita al menos una respuesta marcada como correcta.');
                        return;
                    }
                    quizQuestions.push({
                        text: questionText,
                        answers: answers
                    });
                });
                if (hasError) {
                    return null;
                }
                if (!quizQuestions.length) {
                    return {minimum_correct: 0, questions: []};
                }
                var minimum = parseInt(minInput.value, 10);
                if (!minimum || minimum < 1) {
                    minimum = quizQuestions.length;
                }
                if (minimum > quizQuestions.length) {
                    minimum = quizQuestions.length;
                }
                return {
                    minimum_correct: minimum,
                    questions: quizQuestions
                };
            }

            function saveQuiz() {
                if (!activeContext.input) {
                    toggleModal(false);
                    return;
                }
                var data = collectQuizData();
                if (!data) {
                    return;
                }
                if (!data.questions.length) {
                    activeContext.input.value = '';
                } else {
                    activeContext.input.value = JSON.stringify(data);
                }
                updateSummary(activeContext.input, activeContext.summary, activeContext.trigger);
                toggleModal(false);
            }

            function clearQuiz() {
                if (!activeContext.input) {
                    toggleModal(false);
                    return;
                }
                activeContext.input.value = '';
                updateSummary(activeContext.input, activeContext.summary, activeContext.trigger);
                toggleModal(false);
            }

            if (addQuestionBtn) {
                addQuestionBtn.addEventListener('click', function() {
                    addQuestion();
                });
            }
            if (saveBtn) {
                saveBtn.addEventListener('click', saveQuiz);
            }
            if (clearBtn) {
                clearBtn.addEventListener('click', clearQuiz);
            }
            closeButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    toggleModal(false);
                });
            });
            quizBackdrop.addEventListener('click', function() {
                toggleModal(false);
            });

            triggers.forEach(function(trigger) {
                var targetSelector = trigger.getAttribute('data-quiz-target');
                var summarySelector = trigger.getAttribute('data-quiz-summary');
                var targetInput = targetSelector ? document.querySelector(targetSelector) : null;
                var summaryEl = summarySelector ? document.querySelector(summarySelector) : null;
                if (!targetInput) {
                    return;
                }
                trigger.addEventListener('click', function() {
                    activeContext.input = targetInput;
                    activeContext.summary = summaryEl;
                    activeContext.trigger = trigger;
                    if (modalTitle) {
                        modalTitle.textContent = trigger.getAttribute('data-quiz-title') || 'Autoevaluación';
                    }
                    loadQuestionsIntoModal(parseQuizValue(targetInput.value));
                    toggleModal(true);
                });
                updateSummary(targetInput, summaryEl, trigger);
            });
        });
        </script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var deleteModal = $('#deletePostModal');
            if (!deleteModal.length) {
                return;
            }
            deleteModal.on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var filename = button.data('delete-file') || '';
                var title = button.data('delete-title') || filename;
                var type = button.data('delete-type') || 'single';
                var modal = $(this);
                modal.find('[data-delete-post-title]').text(title || '(sin título)');
                modal.find('[data-delete-post-file]').text(filename || '');
                modal.find('#delete-post-filename').val(filename);
                if (['single', 'page', 'draft'].indexOf(type) === -1) {
                    type = 'single';
                }
                modal.find('#delete-post-template').val(type);
            });
        });
        </script>

        </body>

        </html>
