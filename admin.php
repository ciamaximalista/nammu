<?php
session_start();

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/postal.php';
require_once __DIR__ . '/core/admin-nisaba.php';

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
define('MEDIA_TAGS_FILE', __DIR__ . '/config/media-tags.json');
define('MAILING_SUBSCRIBERS_FILE', __DIR__ . '/config/mailing-subscribers.json');
define('MAILING_SECRET_FILE', __DIR__ . '/config/mailing-secret.key');
nammu_ensure_directory(ITINERARIES_DIR);
if (function_exists('nammu_publish_scheduled_posts')) {
    nammu_publish_scheduled_posts(CONTENT_DIR);
}
if (function_exists('nammu_process_scheduled_notifications_queue')) {
    nammu_process_scheduled_notifications_queue();
}

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
    $templateFilter = in_array($templateFilter, ['single', 'page', 'draft', 'newsletter', 'podcast'], true) ? $templateFilter : 'single';
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
        if ($templateFilter === 'newsletter') {
            if ($template !== 'newsletter') {
                continue;
            }
        } elseif ($templateFilter === 'podcast') {
            if ($template !== 'podcast') {
                continue;
            }
        } elseif ($templateFilter === 'draft') {
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
            if ($template === 'newsletter') {
                continue;
            }
            if ($template === 'podcast') {
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
            'publish_at' => $metadata['PublishAt'] ?? '',
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
    return [
        'jpg','jpeg','png','gif','webp','svg',
        'mp4','webm','mov','m4v','ogv','ogg',
        'mp3','wav','flac','m4a','aac','oga',
        'pdf','epub','doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp','md','txt','rtf'
    ];
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
        $documentExts = ['pdf','doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp','md','txt','rtf'];
        if (in_array($ext, ['mp4','webm','mov','m4v','ogv','ogg'], true)) {
            $type = 'video';
        } elseif (in_array($ext, ['mp3','wav','flac','m4a','aac','oga'], true)) {
            $type = 'audio';
        } elseif (in_array($ext, $documentExts, true)) {
            $type = 'document';
        }
        if ($type === 'video') {
            $mime = admin_video_mime_from_extension($ext);
        } elseif ($type === 'audio') {
            $mime = admin_audio_mime_from_extension($ext);
        } elseif ($type === 'document') {
            $mime = admin_document_mime_from_extension($ext);
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

function normalize_media_tag_key(string $relative): string {
    $normalized = trim((string) $relative);
    $normalized = str_replace(['\\', '\r', '\n'], ['/', '', ''], $normalized);
    $normalized = str_replace(['../', '..\\'], '', $normalized);
    $normalized = ltrim($normalized, '/');
    if (substr($normalized, 0, 7) === 'assets/') {
        $normalized = substr($normalized, 7);
    }
    return trim($normalized);
}

function load_media_tags(bool $forceReload = false): array {
    static $cache = null;
    if ($cache !== null && !$forceReload) {
        return $cache;
    }
    if (!is_file(MEDIA_TAGS_FILE)) {
        $cache = [];
        return $cache;
    }
    $raw = @file_get_contents(MEDIA_TAGS_FILE);
    if ($raw === false) {
        $cache = [];
        return $cache;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $cache = [];
        return $cache;
    }
    $clean = [];
    foreach ($decoded as $key => $list) {
        $normalizedKey = normalize_media_tag_key((string) $key);
        if ($normalizedKey === '') {
            continue;
        }
        $cleanList = [];
        if (is_array($list)) {
            foreach ($list as $tag) {
                if (!is_string($tag)) {
                    continue;
                }
                $tag = nammu_normalize_tag($tag);
                if ($tag === '') {
                    continue;
                }
                if (!in_array($tag, $cleanList, true)) {
                    $cleanList[] = $tag;
                }
            }
        }
        if (!empty($cleanList)) {
            $clean[$normalizedKey] = array_values($cleanList);
        }
    }
    $cache = $clean;
    return $cache;
}

function save_media_tags(array $tags): void {
    $normalized = [];
    foreach ($tags as $key => $list) {
        $normalizedKey = normalize_media_tag_key((string) $key);
        if ($normalizedKey === '') {
            continue;
        }
        if (!is_array($list)) {
            continue;
        }
        $cleanList = [];
        foreach ($list as $tag) {
            if (!is_string($tag)) {
                continue;
            }
            $tag = nammu_normalize_tag($tag);
            if ($tag === '') {
                continue;
            }
            if (!in_array($tag, $cleanList, true)) {
                $cleanList[] = $tag;
            }
        }
        if (!empty($cleanList)) {
            $normalized[$normalizedKey] = array_values($cleanList);
        }
    }
    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = '{}';
    }
    @file_put_contents(MEDIA_TAGS_FILE, $json);
    load_media_tags(true);
}

function parse_media_tags_input(?string $input): array {
    if ($input === null) {
        return [];
    }
    $parts = preg_split('/[,\n]/', $input) ?: [];
    $tags = [];
    foreach ($parts as $part) {
        $normalized = nammu_normalize_tag($part);
        if ($normalized === '' || in_array($normalized, $tags, true)) {
            continue;
        }
        $tags[] = $normalized;
    }
    return $tags;
}

function nammu_normalize_tag(string $tag): string {
    $clean = trim($tag);
    if ($clean === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        $clean = mb_strtolower($clean, 'UTF-8');
    } else {
        $clean = strtolower($clean);
    }
    return $clean;
}

function update_media_tags_entry(string $relative, array $tags): void {
    $key = normalize_media_tag_key($relative);
    if ($key === '') {
        return;
    }
    $current = load_media_tags();
    $clean = [];
    foreach ($tags as $tag) {
        $tag = nammu_normalize_tag((string) $tag);
        if ($tag === '' || in_array($tag, $clean, true)) {
            continue;
        }
        $clean[] = $tag;
    }
    if (empty($clean)) {
        if (isset($current[$key])) {
            unset($current[$key]);
            save_media_tags($current);
        }
        return;
    }
    $current[$key] = $clean;
    save_media_tags($current);
}

function delete_media_tags_entry(string $relative): void {
    $key = normalize_media_tag_key($relative);
    if ($key === '') {
        return;
    }
    $current = load_media_tags();
    if (isset($current[$key])) {
        unset($current[$key]);
        save_media_tags($current);
    }
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

function admin_audio_mime_from_extension(string $ext): string {
    $map = [
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'flac' => 'audio/flac',
        'm4a' => 'audio/mp4',
        'aac' => 'audio/aac',
        'oga' => 'audio/ogg',
        'ogg' => 'audio/ogg',
    ];
    return $map[$ext] ?? 'audio/' . $ext;
}

function admin_document_mime_from_extension(string $ext): string {
    $map = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'md' => 'text/markdown',
        'rtf' => 'application/rtf',
        'txt' => 'text/plain',
    ];
    return $map[$ext] ?? 'application/octet-stream';
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
        $siteLang = $config['site_lang'] ?? 'es';
        if (!is_string($siteLang) || $siteLang === '') {
            $siteLang = 'es';
        }

        $markdown = new MarkdownConverter();
        $posts = [];
        $urls = [];

        foreach ($itineraries as $itinerary) {
            if (!$itinerary instanceof Itinerary) {
                continue;
            }
            if (method_exists($itinerary, 'isDraft') && $itinerary->isDraft()) {
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

        $itinerariesIndexUrl = ($baseUrl !== '' ? rtrim($baseUrl, '/') : '') . '/itinerarios';
        $itinerariesFeedUrl = ($baseUrl !== '' ? rtrim($baseUrl, '/') : '') . '/itinerarios.xml';
        $feedContent = (new RssGenerator(
            $baseUrl,
            $siteTitle . ' — Itinerarios',
            'Itinerarios recientes',
            $itinerariesIndexUrl,
            $itinerariesFeedUrl,
            $siteLang
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

function admin_regenerate_podcast_feed(): void {
    try {
        $baseUrl = nammu_base_url();
        $config = load_config_file();
        $feed = nammu_generate_podcast_feed($baseUrl, $config);
        @file_put_contents(__DIR__ . '/podcast.xml', $feed);
    } catch (Throwable $e) {
        error_log('No se pudo regenerar podcast.xml: ' . $e->getMessage());
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

function admin_next_itinerary_order(): int {
    $list = admin_list_itineraries();
    $max = 0;
    foreach ($list as $index => $item) {
        if ($item instanceof Itinerary) {
            $meta = $item->getMetadata();
            $value = (int) ($meta['Order'] ?? 0);
            if ($value <= 0) {
                $value = $index + 1;
            }
            if ($value > $max) {
                $max = $value;
            }
        }
    }
    return $max + 1;
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
    $siteUrl = $config['site_url'] ?? '';
    $siteLang = $config['site_lang'] ?? 'es';
    if (!is_string($siteLang) || $siteLang === '') {
        $siteLang = 'es';
    }

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
    $subscriptionDefaults = $defaults['subscription'] ?? ['mode' => 'none', 'position' => 'footer', 'floating' => 'off'];
    $subscriptionConfig = array_merge($subscriptionDefaults, $templateConfig['subscription'] ?? []);
    $subscriptionMode = $subscriptionConfig['mode'] ?? 'none';
    if (!in_array($subscriptionMode, ['none', 'home', 'single', 'both'], true)) {
        $subscriptionMode = $subscriptionDefaults['mode'];
    }
    $subscriptionPosition = $subscriptionConfig['position'] ?? 'footer';
    if (!in_array($subscriptionPosition, ['title', 'footer'], true)) {
        $subscriptionPosition = $subscriptionDefaults['position'];
    }
    $subscriptionFloating = $subscriptionConfig['floating'] ?? ($subscriptionDefaults['floating'] ?? 'off');
    if (!in_array($subscriptionFloating, ['off', 'on'], true)) {
        $subscriptionFloating = $subscriptionDefaults['floating'] ?? 'off';
    }
    $subscriptionConfig['mode'] = $subscriptionMode;
    $subscriptionConfig['position'] = $subscriptionPosition;
    $subscriptionConfig['floating'] = $subscriptionFloating;
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
    $instagram = admin_extract_social_settings('instagram', [
        'token' => '',
        'channel' => '',
        'recipient' => '',
        'auto_post' => 'off',
    ], $config);
    $mailingDefaults = [
        'provider' => 'gmail',
        'gmail_address' => '',
        'client_id' => '',
        'client_secret' => '',
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 465,
        'auth_method' => 'oauth2',
        'security' => 'ssl',
        'status' => 'disconnected',
        'auto_posts' => 'off',
        'auto_itineraries' => 'off',
        'auto_podcast' => 'off',
        'auto_newsletter' => 'off',
        'format' => 'html',
    ];
    $mailing = array_merge($mailingDefaults, $config['mailing'] ?? []);
    $mailingHasFlag = is_array($config['mailing'] ?? null) && array_key_exists('auto_newsletter', $config['mailing']);
    if (!$mailingHasFlag && ($mailing['gmail_address'] ?? '') !== '') {
        $mailing['auto_newsletter'] = 'on';
    }
    $mailingHasPodcastFlag = is_array($config['mailing'] ?? null) && array_key_exists('auto_podcast', $config['mailing']);
    if (!$mailingHasPodcastFlag) {
        $mailing['auto_podcast'] = 'on';
    }
    $postalDefaults = [
        'enabled' => 'off',
    ];
    $postal = array_merge($postalDefaults, $config['postal'] ?? []);
    $adsDefaults = [
        'enabled' => 'off',
        'scope' => 'home',
        'text' => '',
        'image' => '',
        'link' => '',
        'link_label' => '',
        'push_enabled' => 'off',
        'push_posts' => 'off',
        'push_itineraries' => 'off',
    ];
    $ads = array_merge($adsDefaults, $config['ads'] ?? []);
    if (!in_array($ads['scope'], ['home', 'all'], true)) {
        $ads['scope'] = $adsDefaults['scope'];
    }
    $searchConsole = $config['search_console'] ?? [];
    $bingDefaults = [
        'site_url' => '',
        'client_id' => '',
        'client_secret' => '',
        'refresh_token' => '',
        'access_token' => '',
        'access_expires_at' => 0,
        'api_key' => '',
    ];
    $bingWebmaster = array_merge($bingDefaults, $config['bing_webmaster'] ?? []);
    $indexnowDefaults = [
        'enabled' => 'off',
        'key' => '',
        'key_file' => '',
    ];
    $indexnow = array_merge($indexnowDefaults, $config['indexnow'] ?? []);
    $nisaba = $config['nisaba'] ?? [];

    return [
        'sort_order' => $sort_order,
        'google_fonts_api' => $googleFontsApi,
        'site_author' => $authorName,
        'site_name' => $blogName,
        'site_url' => $siteUrl,
        'site_lang' => $siteLang,
        'search_console' => $searchConsole,
        'bing_webmaster' => $bingWebmaster,
        'template' => [
            'fonts' => $fonts,
            'colors' => $colors,
            'images' => $images,
            'footer' => $footer,
            'footer_logo' => $footerLogo,
            'global' => $global,
            'home' => $home,
            'search' => $searchConfig,
            'subscription' => $subscriptionConfig,
            'entry' => $entry,
        ],
        'social' => $social,
        'account' => $account,
        'telegram' => $telegram,
        'whatsapp' => $whatsapp,
        'facebook' => $facebook,
        'twitter' => $twitter,
        'instagram' => $instagram,
        'mailing' => $mailing,
        'postal' => $postal,
        'ads' => $ads,
        'indexnow' => $indexnow,
        'nisaba' => $nisaba,
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

function admin_gsc_query(string $accessToken, string $property, string $startDate, string $endDate, array $dimensions = [], int $rowLimit = 10): array {
    $property = trim($property);
    if ($property === '') {
        throw new RuntimeException('Propiedad de Search Console no válida.');
    }
    $siteUrl = rawurlencode($property);
    $payload = [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'rowLimit' => $rowLimit,
    ];
    if (!empty($dimensions)) {
        $payload['dimensions'] = $dimensions;
    }
    $body = json_encode($payload);
    if ($body === false) {
        throw new RuntimeException('No se pudo preparar la consulta de Search Console.');
    }
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
            'content' => $body,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ];
    $url = 'https://www.googleapis.com/webmasters/v3/sites/' . $siteUrl . '/searchAnalytics/query';
    $resp = @file_get_contents($url, false, stream_context_create($opts));
    $decoded = json_decode((string) $resp, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respuesta inválida de Search Console.');
    }
    if (isset($decoded['error'])) {
        $message = is_array($decoded['error']) ? ($decoded['error']['message'] ?? 'Error en Search Console') : 'Error en Search Console';
        throw new RuntimeException($message);
    }
    return $decoded;
}

function admin_gsc_get(string $accessToken, string $url): array {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$accessToken}\r\n",
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ];
    $resp = @file_get_contents($url, false, stream_context_create($opts));
    $decoded = json_decode((string) $resp, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respuesta inválida de Search Console.');
    }
    if (isset($decoded['error'])) {
        $message = is_array($decoded['error']) ? ($decoded['error']['message'] ?? 'Error en Search Console') : 'Error en Search Console';
        throw new RuntimeException($message);
    }
    return $decoded;
}

function admin_bing_api_get(string $method, array $params): array {
    $bingAccessToken = null;
    if (function_exists('admin_bing_get_access_token')) {
        try {
            $bingAccessToken = admin_bing_get_access_token();
        } catch (Throwable $e) {
            $bingAccessToken = null;
        }
    }
    $cleanParams = [];
    foreach ($params as $key => $value) {
        $lower = strtolower((string) $key);
        if ($lower === '') {
            continue;
        }
        if (!array_key_exists($lower, $cleanParams)) {
            $cleanParams[$lower] = ['key' => $key, 'value' => $value];
            continue;
        }
        if (in_array($key, ['ApiKey', 'SiteUrl', 'StartDate', 'EndDate'], true)) {
            $cleanParams[$lower] = ['key' => $key, 'value' => $value];
        }
    }
    $params = [];
    foreach ($cleanParams as $entry) {
        $params[$entry['key']] = $entry['value'];
    }
    $useApiKey = false;
    foreach (['apikey', 'apiKey', 'ApiKey'] as $key) {
        if (isset($params[$key]) && trim((string) $params[$key]) !== '') {
            $useApiKey = true;
            break;
        }
    }
    $targets = [
        ['base' => 'https://ssl.bing.com/webmaster/api.svc/json/', 'style' => 'path'],
        ['base' => 'https://www.bing.com/webmaster/api.svc/json/', 'style' => 'path'],
        ['base' => 'https://ssl.bing.com/webmasters/api.svc/json/', 'style' => 'path'],
        ['base' => 'https://www.bing.com/webmasters/api.svc/json/', 'style' => 'path'],
        ['base' => 'https://ssl.bing.com/webmaster/api.svc/', 'style' => 'path'],
        ['base' => 'https://www.bing.com/webmaster/api.svc/', 'style' => 'path'],
        ['base' => 'https://ssl.bing.com/webmaster/api.svc/json', 'style' => 'query'],
        ['base' => 'https://www.bing.com/webmaster/api.svc/json', 'style' => 'query'],
        ['base' => 'https://ssl.bing.com/', 'style' => 'root'],
        ['base' => 'https://www.bing.com/', 'style' => 'root'],
    ];
    $method = ltrim($method, '/');
    $lastError = null;
    foreach ($targets as $target) {
        $base = $target['base'];
        $style = $target['style'];
        if ($style === 'query') {
            $url = $base . '?method=' . urlencode($method) . '&' . http_build_query($params);
        } elseif ($style === 'root') {
            $url = $base . $method . '?' . http_build_query($params);
        } else {
            $url = $base . $method . '?' . http_build_query($params);
        }
        $redirects = 0;
        $respText = '';
        $status = '';
        $location = '';
        $finalUrl = $url;

        if (function_exists('curl_init')) {
            $ch = curl_init($finalUrl);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
                curl_setopt($ch, CURLOPT_TIMEOUT, 12);
                $headers = [
                    'Accept: application/json',
                    'User-Agent: Nammu/1.0',
                ];
                if (!$useApiKey && is_string($bingAccessToken) && $bingAccessToken !== '') {
                    $headers[] = 'Authorization: Bearer ' . $bingAccessToken;
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $resp = curl_exec($ch);
                $respText = is_string($resp) ? trim($resp) : '';
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if (is_int($statusCode) && $statusCode > 0) {
                    $status = (string) $statusCode;
                }
                $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                if (is_string($effectiveUrl) && $effectiveUrl !== '') {
                    $finalUrl = $effectiveUrl;
                }
                curl_close($ch);
            }
        }

        if ($respText === '') {
            while ($redirects <= 3) {
                $headers = "Accept: application/json\r\nUser-Agent: Nammu/1.0\r\n";
                if (!$useApiKey && is_string($bingAccessToken) && $bingAccessToken !== '') {
                    $headers .= "Authorization: Bearer " . $bingAccessToken . "\r\n";
                }
                $opts = [
                    'http' => [
                        'method' => 'GET',
                        'timeout' => 12,
                        'ignore_errors' => true,
                        'header' => $headers,
                    ],
                ];
                $resp = @file_get_contents($finalUrl, false, stream_context_create($opts));
                $respText = is_string($resp) ? trim($resp) : '';
                $status = '';
                $location = '';
                if (!empty($http_response_header) && is_array($http_response_header)) {
                    $statusLine = $http_response_header[0] ?? '';
                    if (is_string($statusLine) && preg_match('/\\s(\\d{3})\\s/', $statusLine, $match)) {
                        $status = $match[1];
                    }
                    foreach ($http_response_header as $headerLine) {
                        if (stripos((string) $headerLine, 'Location:') === 0) {
                            $location = trim(substr((string) $headerLine, strlen('Location:')));
                            break;
                        }
                    }
                }
                if ($status !== '' && preg_match('/^3\\d\\d$/', $status) && $location !== '') {
                    $redirects++;
                    if (str_starts_with($location, '/')) {
                        $parts = parse_url($finalUrl);
                        $scheme = $parts['scheme'] ?? 'https';
                        $host = $parts['host'] ?? '';
                        $location = $host !== '' ? $scheme . '://' . $host . $location : $location;
                    }
                    $finalUrl = $location;
                    continue;
                }
                break;
            }
        }
        $decoded = json_decode($respText, true);
        if (!is_array($decoded)) {
            if ($respText !== '' && str_starts_with($respText, '<')) {
                $lower = strtolower($respText);
                if (strpos($lower, '<html') !== false || strpos($lower, '<!doctype html') !== false) {
                    $decoded = null;
                } else {
                    $xml = @simplexml_load_string($respText);
                    if ($xml !== false) {
                        $json = json_encode($xml);
                        $decoded = is_string($json) ? json_decode($json, true) : null;
                    }
                }
            }
        }
        if (!is_array($decoded)) {
            $snippet = $respText !== '' ? mb_substr($respText, 0, 160, 'UTF-8') : '';
            $details = [];
            if ($status !== '') {
                $details[] = 'HTTP ' . $status;
            }
            if ($location !== '') {
                $details[] = 'Location: ' . $location;
            }
            if ($snippet !== '') {
                $details[] = $snippet;
            }
            $detailText = !empty($details) ? ' (' . implode(' — ', $details) . ')' : '';
            $lastError = new RuntimeException('Respuesta inválida de Bing Webmaster Tools' . $detailText . '.');
            if (isset($GLOBALS['bing_debug_log']) && is_array($GLOBALS['bing_debug_log'])) {
                $GLOBALS['bing_debug_log'][] = [
                    'method' => $method,
                    'url' => $finalUrl,
                    'status' => $status,
                    'location' => $location,
                    'snippet' => $snippet,
                ];
            }
            continue;
        }
        if (isset($GLOBALS['bing_debug_log']) && is_array($GLOBALS['bing_debug_log'])) {
            $GLOBALS['bing_debug_log'][] = [
                'method' => $method,
                'url' => $finalUrl,
                'status' => $status,
                'location' => $location,
                'snippet' => $respText !== '' ? mb_substr($respText, 0, 160, 'UTF-8') : '',
            ];
        }
        $payload = $decoded['d'] ?? $decoded;
        if (is_array($payload)) {
            $errorCode = $payload['ErrorCode'] ?? $payload['errorCode'] ?? null;
            $errorMessage = $payload['ErrorMessage'] ?? $payload['message'] ?? $payload['Message'] ?? '';
            if ($errorCode !== null && (int) $errorCode !== 0) {
                $message = trim((string) $errorMessage);
                $lastError = new RuntimeException($message !== '' ? $message : 'Error en Bing Webmaster Tools.');
                continue;
            }
        }
        return $payload;
    }
    if (class_exists('SoapClient')) {
        try {
            $soapPayload = admin_bing_api_soap($method, $params);
            if (is_array($soapPayload)) {
                if (isset($GLOBALS['bing_debug_log']) && is_array($GLOBALS['bing_debug_log'])) {
                    $GLOBALS['bing_debug_log'][] = [
                        'method' => $method,
                        'url' => 'soap:' . $method,
                        'status' => '200',
                        'location' => '',
                        'snippet' => 'SOAP ok',
                    ];
                }
                return $soapPayload;
            }
        } catch (Throwable $e) {
            if (isset($GLOBALS['bing_debug_log']) && is_array($GLOBALS['bing_debug_log'])) {
                $GLOBALS['bing_debug_log'][] = [
                    'method' => $method,
                    'url' => 'soap:' . $method,
                    'status' => '',
                    'location' => '',
                    'snippet' => $e->getMessage(),
                ];
            }
            $lastError = $e;
        }
    }
    throw $lastError ?? new RuntimeException('No se pudo conectar con Bing Webmaster Tools.');
}

function admin_bing_api_soap(string $method, array $params): array {
    $wsdl = 'https://ssl.bing.com/webmaster/api.svc?wsdl';
    $soapParams = [];
    foreach ($params as $key => $value) {
        $soapParams[$key] = $value;
    }
    if (isset($soapParams['apikey']) && !isset($soapParams['ApiKey'])) {
        $soapParams['ApiKey'] = $soapParams['apikey'];
    }
    if (isset($soapParams['apiKey']) && !isset($soapParams['ApiKey'])) {
        $soapParams['ApiKey'] = $soapParams['apiKey'];
    }
    if (isset($soapParams['siteUrl']) && !isset($soapParams['SiteUrl'])) {
        $soapParams['SiteUrl'] = $soapParams['siteUrl'];
    }
    if (isset($soapParams['startDate']) && !isset($soapParams['StartDate'])) {
        $soapParams['StartDate'] = $soapParams['startDate'];
    }
    if (isset($soapParams['endDate']) && !isset($soapParams['EndDate'])) {
        $soapParams['EndDate'] = $soapParams['endDate'];
    }
    $client = new SoapClient($wsdl, [
        'trace' => false,
        'exceptions' => true,
        'cache_wsdl' => WSDL_CACHE_BOTH,
    ]);
    $result = $client->__soapCall($method, [$soapParams]);
    if (is_object($result) || is_array($result)) {
        return json_decode(json_encode($result), true) ?? [];
    }
    return [];
}

function admin_bing_request_with_dates(string $method, array $baseParams, string $startDate, string $endDate): array {
    $startTs = strtotime($startDate);
    $endTs = strtotime($endDate);
    if ($startTs === false || $endTs === false) {
        throw new RuntimeException('Fechas no válidas para Bing Webmaster Tools.');
    }
    $formats = ['Y-m-d', 'm/d/Y'];
    $lastError = null;
    $apiKey = $baseParams['apikey'] ?? $baseParams['apiKey'] ?? $baseParams['ApiKey'] ?? '';
    $siteUrl = $baseParams['siteUrl'] ?? $baseParams['SiteUrl'] ?? '';
    if (is_string($siteUrl)) {
        $siteUrl = trim($siteUrl);
        if ($siteUrl !== '' && str_starts_with($siteUrl, 'http') && !str_ends_with($siteUrl, '/')) {
            $siteUrl .= '/';
        }
    }
    foreach ($formats as $format) {
        $startValue = date($format, $startTs);
        $endValue = date($format, $endTs);
        $params = [
            'siteUrl' => $siteUrl,
            'startDate' => $startValue,
            'endDate' => $endValue,
        ];
        if ($apiKey !== '') {
            $params['ApiKey'] = $apiKey;
        }
        try {
            return admin_bing_api_get($method, array_filter($params, static fn($value) => $value !== '' && $value !== null));
        } catch (Throwable $e) {
            $lastError = $e;
        }
        if ($apiKey !== '' && $lastError instanceof Throwable) {
            $errorText = $lastError->getMessage();
            if (stripos($errorText, 'invalidapikey') !== false || stripos($errorText, 'invalid api key') !== false) {
                $params['apikey'] = $apiKey;
                unset($params['ApiKey']);
                try {
                    return admin_bing_api_get($method, array_filter($params, static fn($value) => $value !== '' && $value !== null));
                } catch (Throwable $e) {
                    $lastError = $e;
                }
            }
        }
    }
    throw $lastError ?? new RuntimeException('No se pudo conectar con Bing Webmaster Tools.');
}

function admin_bing_request_with_dates_multi(array $methods, array $baseParams, string $startDate, string $endDate): array {
    $lastError = null;
    foreach ($methods as $method) {
        try {
            return admin_bing_request_with_dates($method, $baseParams, $startDate, $endDate);
        } catch (Throwable $e) {
            $lastError = $e;
        }
    }
    throw $lastError ?? new RuntimeException('No se pudo conectar con Bing Webmaster Tools.');
}

function admin_bing_oauth_redirect_uri(): string {
    $base = admin_base_url();
    if ($base === '') {
        return '/admin.php?bing_oauth=callback';
    }
    return $base . '/admin.php?bing_oauth=callback';
}

function admin_bing_fetch_token(array $payload): array {
    $urls = [
        'https://www.bing.com/webmasters/oauth/token',
        'https://ssl.bing.com/webmasters/oauth/token',
    ];
    $body = http_build_query($payload);
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        'User-Agent: Nammu/1.0',
    ];
    $lastError = null;
    foreach ($urls as $url) {
        $response = '';
        $status = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                $resp = curl_exec($ch);
                $response = is_string($resp) ? $resp : '';
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            }
        }
        if ($response === '') {
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'timeout' => 15,
                    'ignore_errors' => true,
                    'header' => implode("\r\n", $headers) . "\r\n",
                    'content' => $body,
                ],
            ];
            $resp = @file_get_contents($url, false, stream_context_create($opts));
            $response = is_string($resp) ? $resp : '';
            if (!empty($http_response_header) && is_array($http_response_header)) {
                $statusLine = $http_response_header[0] ?? '';
                if (is_string($statusLine) && preg_match('/\\s(\\d{3})\\s/', $statusLine, $match)) {
                    $status = (int) $match[1];
                }
            }
        }
        $decoded = json_decode(trim($response), true);
        if (!is_array($decoded)) {
            $snippet = $response !== '' ? mb_substr(trim($response), 0, 160, 'UTF-8') : '';
            $detail = $snippet !== '' ? ' (' . $snippet . ')' : '';
            $lastError = new RuntimeException('Respuesta inválida del token OAuth de Bing' . $detail . '.');
            continue;
        }
        if ($status >= 400) {
            $message = $decoded['error_description'] ?? $decoded['error'] ?? 'Error en el token OAuth de Bing.';
            $lastError = new RuntimeException($message);
            continue;
        }
        return $decoded;
    }
    throw $lastError ?? new RuntimeException('No se pudo obtener el token OAuth de Bing.');
}

function admin_bing_get_access_token(bool $forceRefresh = false): ?string {
    $config = load_config_file();
    $bing = $config['bing_webmaster'] ?? [];
    $accessToken = $bing['access_token'] ?? '';
    $expiresAt = (int) ($bing['access_expires_at'] ?? 0);
    if ($accessToken !== '' && $expiresAt > time() + 60 && !$forceRefresh) {
        return $accessToken;
    }
    $refreshToken = $bing['refresh_token'] ?? '';
    $clientId = $bing['client_id'] ?? '';
    $clientSecret = $bing['client_secret'] ?? '';
    if ($refreshToken === '' || $clientId === '' || $clientSecret === '') {
        return $accessToken !== '' ? $accessToken : null;
    }
    $payload = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
        'redirect_uri' => admin_bing_oauth_redirect_uri(),
    ];
    $token = admin_bing_fetch_token($payload);
    $newAccess = (string) ($token['access_token'] ?? '');
    if ($newAccess === '') {
        return null;
    }
    $bing['access_token'] = $newAccess;
    if (!empty($token['refresh_token'])) {
        $bing['refresh_token'] = (string) $token['refresh_token'];
    }
    $expiresIn = (int) ($token['expires_in'] ?? 0);
    if ($expiresIn > 0) {
        $bing['access_expires_at'] = time() + $expiresIn;
    }
    $config['bing_webmaster'] = $bing;
    save_config_file($config);
    return $newAccess;
}

function admin_public_post_url(string $slug): string {
    $base = admin_base_url();
    $path = '/' . ltrim($slug, '/');
    if ($base === '') {
        return $path;
    }
    return $base . $path;
}

function admin_public_asset_url(string $path): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $base = admin_base_url();
    if (function_exists('nammu_resolve_asset')) {
        $resolved = nammu_resolve_asset($path, $base);
        if (is_string($resolved) && $resolved !== '') {
            return $resolved;
        }
    }
    $normalized = ltrim($path, '/');
    if (str_starts_with($normalized, 'assets/')) {
        $normalized = substr($normalized, 7);
    }
    $relative = '/assets/' . $normalized;
    return $base !== '' ? $base . $relative : $relative;
}

function admin_public_itinerary_url(string $slug): string {
    $base = admin_base_url();
    $path = '/itinerarios/' . rawurlencode($slug);
    return $base === '' ? $path : $base . $path;
}

function admin_indexnow_endpoints(): array {
    return [
        'https://api.indexnow.org/indexnow',
        'https://indexnow.amazonbot.amazon/indexnow',
        'https://www.bing.com/indexnow',
        'https://searchadvisor.naver.com/indexnow',
        'https://yandex.com/indexnow',
        'https://indexnow.yep.com/indexnow',
    ];
}

function admin_indexnow_key_filename(string $key): string {
    return 'indexnow-' . $key . '.txt';
}

function admin_indexnow_key_path(string $key, string $filename = ''): string {
    $filename = $filename !== '' ? $filename : admin_indexnow_key_filename($key);
    return __DIR__ . '/' . $filename;
}

function admin_indexnow_normalize_site_base(string $base): string {
    $base = trim($base);
    if ($base === '') {
        return '';
    }
    $parts = parse_url($base);
    if (!is_array($parts)) {
        return rtrim($base, '/');
    }
    $scheme = $parts['scheme'] ?? '';
    $host = $parts['host'] ?? '';
    if ($scheme === '' || $host === '') {
        return rtrim($base, '/');
    }
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    return rtrim($scheme . '://' . $host . $port, '/');
}

function admin_indexnow_key_url(string $filename, string $baseOverride = ''): string {
    $base = trim($baseOverride);
    if ($base === '') {
        $base = admin_base_url();
    }
    $base = admin_indexnow_normalize_site_base($base);
    $path = '/' . ltrim($filename, '/');
    return $base === '' ? $path : $base . $path;
}

function admin_indexnow_log_path(): string {
    return __DIR__ . '/config/indexnow-log.json';
}

function admin_indexnow_load_log(): array {
    $path = admin_indexnow_log_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function admin_indexnow_save_log(array $payload): void {
    $path = admin_indexnow_log_path();
    nammu_ensure_directory(dirname($path));
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return;
    }
    file_put_contents($path, $json);
}

function admin_indexnow_prepare_config(array &$config): array {
    if (!isset($config['indexnow']) || !is_array($config['indexnow'])) {
        $config['indexnow'] = [];
    }
    $indexnow = $config['indexnow'];
    $updated = false;

    $key = trim((string) ($indexnow['key'] ?? ''));
    if ($key === '') {
        try {
            $key = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $key = '';
        }
        if ($key !== '') {
            $indexnow['key'] = $key;
            $updated = true;
        }
    }

    $keyFile = trim((string) ($indexnow['key_file'] ?? ''));
    if ($keyFile === '' && $key !== '') {
        $keyFile = admin_indexnow_key_filename($key);
        $indexnow['key_file'] = $keyFile;
        $updated = true;
    }

    $config['indexnow'] = $indexnow;

    $keyPath = $key !== '' && $keyFile !== '' ? admin_indexnow_key_path($key, $keyFile) : '';
    $fileOk = false;
    if ($key !== '' && $keyFile !== '') {
        $current = is_file($keyPath) ? trim((string) file_get_contents($keyPath)) : '';
        if ($current === $key) {
            $fileOk = true;
        } elseif (@file_put_contents($keyPath, $key) !== false) {
            $fileOk = true;
        }
    }

    $siteBase = admin_indexnow_normalize_site_base(trim((string) ($config['site_url'] ?? '')));
    $keyUrl = $keyFile !== '' ? admin_indexnow_key_url($keyFile, $siteBase) : '';

    return [
        'key' => $key,
        'key_file' => $keyFile,
        'key_path' => $keyPath,
        'key_url' => $keyUrl,
        'file_ok' => $fileOk,
        'updated' => $updated,
    ];
}

function admin_indexnow_status(): array {
    $config = load_config_file();
    $enabled = (($config['indexnow']['enabled'] ?? 'off') === 'on');
    $key = trim((string) ($config['indexnow']['key'] ?? ''));
    $keyFile = trim((string) ($config['indexnow']['key_file'] ?? ''));
    if ($keyFile === '' && $key !== '') {
        $keyFile = admin_indexnow_key_filename($key);
    }
    $keyPath = $key !== '' && $keyFile !== '' ? admin_indexnow_key_path($key, $keyFile) : '';
    $fileOk = $key !== '' && $keyFile !== '' && is_file($keyPath)
        && trim((string) file_get_contents($keyPath)) === $key;
    $siteBase = admin_indexnow_normalize_site_base(trim((string) ($config['site_url'] ?? '')));
    $keyUrl = $keyFile !== '' ? admin_indexnow_key_url($keyFile, $siteBase) : '';

    return [
        'enabled' => $enabled,
        'key' => $key,
        'key_file' => $keyFile,
        'key_path' => $keyPath,
        'key_url' => $keyUrl,
        'file_ok' => $fileOk,
    ];
}

function admin_maybe_send_indexnow(array $urls): void {
    $urls = array_values(array_unique(array_filter(array_map(static function ($url) {
        $url = trim((string) $url);
        return preg_match('#^https?://#i', $url) ? $url : '';
    }, $urls))));
    if (empty($urls)) {
        return;
    }

    $config = load_config_file();
    if (($config['indexnow']['enabled'] ?? 'off') !== 'on') {
        return;
    }

    $status = admin_indexnow_prepare_config($config);
    if (!empty($status['updated'])) {
        save_config_file($config);
    }

    $key = $status['key'] ?? '';
    $keyUrl = $status['key_url'] ?? '';
    if ($key === '') {
        return;
    }

    $host = '';
    $siteBase = admin_indexnow_normalize_site_base(trim((string) ($config['site_url'] ?? '')));
    $base = $siteBase !== '' ? rtrim($siteBase, '/') : admin_base_url();
    if ($base !== '') {
        $host = parse_url($base, PHP_URL_HOST) ?: '';
    }
    if ($host === '' && !empty($urls[0])) {
        $host = parse_url($urls[0], PHP_URL_HOST) ?: '';
    }
    if ($host === '') {
        return;
    }
    if ($siteBase !== '' && $status['key_file'] ?? '' !== '') {
        $keyUrl = admin_indexnow_key_url($status['key_file'], $siteBase);
    }

    $siteHost = $host !== '' ? strtolower($host) : '';
    $normalizedUrls = [];
    foreach ($urls as $url) {
        $parsed = parse_url($url);
        if (!is_array($parsed)) {
            continue;
        }
        $urlHost = strtolower((string) ($parsed['host'] ?? ''));
        $path = $parsed['path'] ?? '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        if ($siteHost !== '' && $urlHost !== '' && $urlHost !== $siteHost) {
            $url = rtrim($base, '/') . $path . $query;
        } elseif ($siteHost !== '' && $urlHost === '') {
            $url = rtrim($base, '/') . $path . $query;
        }
        $normalizedUrls[] = $url;
    }
    if (!empty($normalizedUrls)) {
        $urls = $normalizedUrls;
    }

    $headers = ['Content-Type: application/json; charset=UTF-8'];
    $errors = [];
    $responses = [];
    $keyLocationHost = $keyUrl !== '' ? parse_url($keyUrl, PHP_URL_HOST) : '';
    $payloadHost = $keyLocationHost !== '' ? $keyLocationHost : $host;
    $payloadBase = [
        'host' => $payloadHost,
        'key' => $key,
        'keyLocation' => $keyUrl,
        'urlList' => $urls,
    ];
    foreach (admin_indexnow_endpoints() as $endpoint) {
        $payload = $payloadBase;
        $body = json_encode($payload);
        if ($body === false) {
            continue;
        }
        $httpCode = 0;
        $responseBody = admin_http_post_body_response($endpoint, $body, $headers, $httpCode);
        $responseText = is_string($responseBody) ? trim($responseBody) : '';
        $responseSnippet = $responseText !== '' ? mb_substr($responseText, 0, 240, 'UTF-8') : '';
        $decoded = null;
        if ($responseText !== '') {
            $decoded = json_decode($responseText, true);
        }
        $hasErrorPayload = is_array($decoded) && (isset($decoded['error']) || isset($decoded['errors']));
        $ok = $httpCode >= 200 && $httpCode < 300 && !$hasErrorPayload;

        if (!$ok && $endpoint === 'https://indexnow.amazonbot.amazon/indexnow' && $keyUrl !== '') {
            $altKeyUrl = $keyUrl;
            if (str_starts_with($altKeyUrl, 'https://')) {
                $altKeyUrl = 'http://' . substr($altKeyUrl, strlen('https://'));
            } elseif (str_starts_with($altKeyUrl, 'http://')) {
                $altKeyUrl = 'https://' . substr($altKeyUrl, strlen('http://'));
            }
            if ($altKeyUrl !== $keyUrl) {
                $altHost = parse_url($altKeyUrl, PHP_URL_HOST) ?: $payloadHost;
                $altPayload = [
                    'host' => $altHost,
                    'key' => $key,
                    'keyLocation' => $altKeyUrl,
                    'urlList' => $urls,
                ];
                $altBody = json_encode($altPayload);
                if ($altBody !== false) {
                    $altCode = 0;
                    $altResp = admin_http_post_body_response($endpoint, $altBody, $headers, $altCode);
                    $altText = is_string($altResp) ? trim($altResp) : '';
                    $altDecoded = $altText !== '' ? json_decode($altText, true) : null;
                    $altHasError = is_array($altDecoded) && (isset($altDecoded['error']) || isset($altDecoded['errors']));
                    if ($altCode >= 200 && $altCode < 300 && !$altHasError) {
                        $httpCode = $altCode;
                        $responseText = $altText;
                        $responseSnippet = $altText !== '' ? mb_substr($altText, 0, 240, 'UTF-8') : '';
                        $decoded = $altDecoded;
                        $hasErrorPayload = $altHasError;
                        $ok = true;
                    }
                }
            }
        }
        if (!$ok && $endpoint === 'https://indexnow.amazonbot.amazon/indexnow') {
            $firstUrl = $urls[0] ?? '';
            if ($firstUrl !== '' && $key !== '') {
                $fallbackUrl = $endpoint . '?url=' . rawurlencode($firstUrl) . '&key=' . rawurlencode($key);
                $fallbackCode = 0;
                $fallbackResp = admin_http_post_body_response($fallbackUrl, '', [], $fallbackCode, 'GET');
                $fallbackText = is_string($fallbackResp) ? trim($fallbackResp) : '';
                $fallbackDecoded = $fallbackText !== '' ? json_decode($fallbackText, true) : null;
                $fallbackHasError = is_array($fallbackDecoded) && (isset($fallbackDecoded['error']) || isset($fallbackDecoded['errors']));
                if ($fallbackCode >= 200 && $fallbackCode < 300 && !$fallbackHasError) {
                    $httpCode = $fallbackCode;
                    $responseText = $fallbackText;
                    $responseSnippet = $fallbackText !== '' ? mb_substr($fallbackText, 0, 240, 'UTF-8') : '';
                    $decoded = $fallbackDecoded;
                    $hasErrorPayload = $fallbackHasError;
                    $ok = true;
                }
            }
        }

        $responses[] = [
            'endpoint' => $endpoint,
            'status' => (int) $httpCode,
            'ok' => $ok,
        ];
        if (!$ok) {
            $message = '';
            if (is_array($decoded)) {
                if (isset($decoded['error'])) {
                    $message = is_array($decoded['error']) ? (string) ($decoded['error']['message'] ?? '') : (string) $decoded['error'];
                } elseif (isset($decoded['errors']) && is_array($decoded['errors'])) {
                    $firstError = $decoded['errors'][0] ?? null;
                    if (is_array($firstError)) {
                        $message = (string) ($firstError['message'] ?? '');
                    }
                }
            }
            if ($message === '' && $responseSnippet !== '') {
                $message = $responseSnippet;
            }
            $errors[] = [
                'endpoint' => $endpoint,
                'status' => (int) $httpCode,
                'message' => $message,
            ];
        }
    }
    admin_indexnow_save_log([
        'timestamp' => time(),
        'errors' => $errors,
        'urls' => $urls,
        'responses' => $responses,
    ]);
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
    if (isset($values['api_key'])) {
        $values['api_key'] = trim((string) $values['api_key']);
    }
    if (isset($values['api_secret'])) {
        $values['api_secret'] = trim((string) $values['api_secret']);
    }
    if (isset($values['access_token'])) {
        $values['access_token'] = trim((string) $values['access_token']);
    }
    if (isset($values['access_secret'])) {
        $values['access_secret'] = trim((string) $values['access_secret']);
    }
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
                'api_key' => '',
                'api_secret' => '',
                'access_token' => '',
                'access_secret' => '',
            ], $config),
            'instagram' => admin_extract_social_settings('instagram', [
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
        case 'instagram':
            return ($settings['token'] ?? '') !== '' && ($settings['channel'] ?? '') !== '';
        default:
            return false;
    }
}

function admin_get_telegram_follower_count(array $settings): ?int {
    $token = trim((string) ($settings['token'] ?? ''));
    $channel = trim((string) ($settings['channel'] ?? ''));
    if ($token === '' || $channel === '') {
        return null;
    }
    $endpoint = 'https://api.telegram.org/bot' . $token . '/getChatMemberCount?chat_id=' . rawurlencode($channel);
    $payload = admin_http_get_json($endpoint);
    if (!is_array($payload) || empty($payload['ok'])) {
        return null;
    }
    return isset($payload['result']) ? (int) $payload['result'] : null;
}

function admin_get_facebook_follower_count(array $settings): ?int {
    $token = trim((string) ($settings['token'] ?? ''));
    $channel = trim((string) ($settings['channel'] ?? ''));
    if ($token === '' || $channel === '') {
        return null;
    }
    $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($channel)
        . '?fields=followers_count&access_token=' . rawurlencode($token);
    $payload = admin_http_get_json($endpoint);
    if (!is_array($payload)) {
        return null;
    }
    if (isset($payload['followers_count'])) {
        return (int) $payload['followers_count'];
    }
    return null;
}

function admin_get_twitter_follower_count(array $settings): ?int {
    $token = trim((string) ($settings['token'] ?? ''));
    $channelRaw = trim((string) ($settings['channel'] ?? ''));
    if ($token === '' || $channelRaw === '') {
        return null;
    }
    $channel = ltrim($channelRaw, '@');
    $headers = ['Authorization: Bearer ' . $token];
    if (preg_match('/^\d+$/', $channel)) {
        $endpoint = 'https://api.twitter.com/2/users/' . rawurlencode($channel) . '?user.fields=public_metrics';
        $payload = admin_http_get_json($endpoint, $headers);
    } else {
        $endpoint = 'https://api.twitter.com/2/users/by/username/' . rawurlencode($channel) . '?user.fields=public_metrics';
        $payload = admin_http_get_json($endpoint, $headers);
    }
    if (!is_array($payload) || !isset($payload['data']['public_metrics']['followers_count'])) {
        return null;
    }
    return (int) $payload['data']['public_metrics']['followers_count'];
}

function admin_send_post_to_telegram(string $slug, string $title, string $description, array $telegramSettings, string $urlOverride = '', string $imageUrl = ''): bool {
    $token = $telegramSettings['token'] ?? '';
    $channel = $telegramSettings['channel'] ?? '';
    if ($token === '' || $channel === '') {
        return false;
    }
    $targetUrl = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    $trackedUrl = admin_add_utm_params($targetUrl, [
        'utm_source' => 'telegram',
        'utm_medium' => 'social',
    ]);
    $message = admin_build_telegram_message($slug, $title, $description, $trackedUrl);
    $imageUrl = trim($imageUrl);
    if ($imageUrl !== '' && preg_match('#^https?://#i', $imageUrl)) {
        return admin_send_telegram_photo($token, $channel, $imageUrl, $message);
    }
    return admin_send_telegram_message($token, $channel, $message, 'HTML');
}

function admin_telegram_escape(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_build_post_message(string $slug, string $title, string $description, string $urlOverride = '', string $imageUrl = ''): string {
    $parts = [];
    $title = trim($title);
    $description = trim($description);
    if ($title !== '') {
        $parts[] = $title;
    }
    if ($description !== '') {
        $parts[] = $description;
    }
    $url = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    if ($url !== '') {
        $parts[] = $url;
    }
    $imageUrl = trim($imageUrl);
    if ($imageUrl !== '' && preg_match('#^https?://#i', $imageUrl)) {
        $parts[] = $imageUrl;
    }
    if (empty($parts)) {
        $parts[] = 'Nueva publicación disponible';
    }
    return implode("\n\n", $parts);
}

function admin_build_telegram_message(string $slug, string $title, string $description, string $urlOverride = ''): string {
    $parts = [];
    $titleTrim = trim($title);
    if ($titleTrim !== '') {
        $parts[] = '<b>' . admin_telegram_escape($titleTrim) . '</b>';
    }
    $descriptionTrim = trim($description);
    if ($descriptionTrim !== '') {
        $parts[] = admin_telegram_escape($descriptionTrim);
    }
    $url = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    if ($url !== '') {
        $parts[] = admin_telegram_escape($url);
    }
    if (empty($parts)) {
        $parts[] = admin_telegram_escape('Nueva publicación disponible');
    }
    return implode("\n\n", $parts);
}

function admin_send_whatsapp_post(string $slug, string $title, string $description, array $settings, string $urlOverride = '', string $imageUrl = ''): bool {
    $token = $settings['token'] ?? '';
    $phoneId = $settings['channel'] ?? '';
    $recipient = $settings['recipient'] ?? '';
    if ($token === '' || $phoneId === '' || $recipient === '') {
        return false;
    }
    $targetUrl = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    $trackedUrl = admin_add_utm_params($targetUrl, [
        'utm_source' => 'whatsapp',
        'utm_medium' => 'social',
    ]);
    $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($phoneId) . '/messages';
    $imageUrl = trim($imageUrl);
    if ($imageUrl !== '' && preg_match('#^https?://#i', $imageUrl)) {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'image',
            'image' => [
                'link' => $imageUrl,
                'caption' => admin_build_post_message($slug, $title, $description, $trackedUrl),
            ],
        ];
        return admin_http_post_json($endpoint, $payload, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
    }
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $recipient,
        'type' => 'text',
        'text' => [
            'body' => admin_build_post_message($slug, $title, $description, $trackedUrl, $imageUrl),
        ],
    ];
    return admin_http_post_json($endpoint, $payload, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
}

function admin_send_facebook_post(string $slug, string $title, string $description, array $settings, string $urlOverride = '', string $imageUrl = ''): bool {
    $token = $settings['token'] ?? '';
    $pageId = $settings['channel'] ?? '';
    if ($token === '' || $pageId === '') {
        return false;
    }
    $targetUrl = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    $trackedUrl = admin_add_utm_params($targetUrl, [
        'utm_source' => 'facebook',
        'utm_medium' => 'social',
    ]);
    $imageUrl = trim($imageUrl);
    if ($imageUrl !== '' && preg_match('#^https?://#i', $imageUrl)) {
        $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($pageId) . '/photos';
        $params = [
            'url' => $imageUrl,
            'caption' => admin_build_post_message($slug, $title, $description, $trackedUrl),
            'access_token' => $token,
        ];
        return admin_http_post_form($endpoint, $params);
    }
    $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($pageId) . '/feed';
    $params = [
        'message' => admin_build_post_message($slug, $title, $description, $trackedUrl, $imageUrl),
        'access_token' => $token,
    ];
    return admin_http_post_form($endpoint, $params);
}

function admin_send_twitter_post(string $slug, string $title, string $description, array $settings, string $urlOverride = '', string $imageUrl = ''): bool {
    $token = $settings['token'] ?? '';
    $imageUrl = trim($imageUrl);
    $targetUrl = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    $trackedUrl = admin_add_utm_params($targetUrl, [
        'utm_source' => 'twitter',
        'utm_medium' => 'social',
    ]);
    if ($imageUrl !== '' && admin_twitter_has_media_credentials($settings)) {
        $mediaId = admin_twitter_upload_media($imageUrl, $settings);
        if ($mediaId !== '') {
            $endpoint = 'https://api.twitter.com/2/tweets';
            $text = admin_build_post_message($slug, $title, $description, $trackedUrl);
            if (function_exists('mb_strlen')) {
                if (mb_strlen($text, 'UTF-8') > 280) {
                    $text = mb_substr($text, 0, 275, 'UTF-8') . '…';
                }
            } elseif (strlen($text) > 280) {
                $text = substr($text, 0, 275) . '…';
            }
            $payload = [
                'text' => $text,
                'media' => [
                    'media_ids' => [$mediaId],
                ],
            ];
            return admin_twitter_post_json_oauth1($endpoint, $payload, $settings);
        }
        return false;
    }
    if ($token === '') {
        return false;
    }
    $endpoint = 'https://api.twitter.com/2/tweets';
    $text = admin_build_post_message($slug, $title, $description, $trackedUrl, $imageUrl);
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

function admin_twitter_has_media_credentials(array $settings): bool {
    return trim((string) ($settings['api_key'] ?? '')) !== ''
        && trim((string) ($settings['api_secret'] ?? '')) !== ''
        && trim((string) ($settings['access_token'] ?? '')) !== ''
        && trim((string) ($settings['access_secret'] ?? '')) !== '';
}

function admin_twitter_upload_media(string $imageUrl, array $settings): string {
    $binary = admin_http_get_binary($imageUrl);
    if ($binary === '') {
        return '';
    }
    $endpoint = 'https://upload.twitter.com/1.1/media/upload.json';
    $boundary = '----Nammu' . bin2hex(random_bytes(8));
    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($binary);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
    $body = '--' . $boundary . "\r\n";
    $body .= 'Content-Disposition: form-data; name="media"; filename="podcast-image"' . "\r\n";
    $body .= 'Content-Type: ' . $mime . "\r\n\r\n";
    $body .= $binary . "\r\n";
    $body .= '--' . $boundary . "--\r\n";
    $authHeader = admin_twitter_oauth_header('POST', $endpoint, $settings);
    $headers = [
        'Authorization: ' . $authHeader,
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Content-Length: ' . strlen($body),
    ];
    $response = admin_http_post_raw($endpoint, $body, $headers);
    if ($response === '') {
        return '';
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return '';
    }
    return (string) ($decoded['media_id_string'] ?? '');
}

function admin_twitter_post_json_oauth1(string $endpoint, array $payload, array $settings): bool {
    $authHeader = admin_twitter_oauth_header('POST', $endpoint, $settings);
    return admin_http_post_json($endpoint, $payload, [
        'Authorization: ' . $authHeader,
        'Content-Type: application/json',
    ]);
}

function admin_twitter_oauth_header(string $method, string $url, array $settings): string {
    $oauth = [
        'oauth_consumer_key' => trim((string) ($settings['api_key'] ?? '')),
        'oauth_nonce' => bin2hex(random_bytes(16)),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => (string) time(),
        'oauth_token' => trim((string) ($settings['access_token'] ?? '')),
        'oauth_version' => '1.0',
    ];
    $baseParams = [];
    foreach ($oauth as $key => $value) {
        $baseParams[rawurlencode($key)] = rawurlencode($value);
    }
    ksort($baseParams);
    $paramString = [];
    foreach ($baseParams as $key => $value) {
        $paramString[] = $key . '=' . $value;
    }
    $baseUrl = $url;
    $baseString = strtoupper($method) . '&' . rawurlencode($baseUrl) . '&' . rawurlencode(implode('&', $paramString));
    $signingKey = rawurlencode((string) ($settings['api_secret'] ?? '')) . '&' . rawurlencode((string) ($settings['access_secret'] ?? ''));
    $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
    $oauth['oauth_signature'] = $signature;
    $headerParts = [];
    foreach ($oauth as $key => $value) {
        $headerParts[] = $key . '="' . rawurlencode($value) . '"';
    }
    return 'OAuth ' . implode(', ', $headerParts);
}

function admin_http_get_binary(string $url): string {
    $opts = [
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ];
    $data = @file_get_contents($url, false, stream_context_create($opts));
    return is_string($data) ? $data : '';
}

function admin_http_post_raw(string $url, string $body, array $headers): string {
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ];
    $response = @file_get_contents($url, false, stream_context_create($opts));
    return is_string($response) ? $response : '';
}

function admin_build_instagram_caption(string $slug, string $title, string $description, string $urlOverride = ''): string {
    $parts = [];
    $titleTrim = trim($title);
    if ($titleTrim !== '') {
        $parts[] = $titleTrim;
    }
    $descriptionTrim = trim($description);
    if ($descriptionTrim !== '') {
        $parts[] = $descriptionTrim;
    }
    $url = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    if ($url !== '') {
        $parts[] = $url;
    }
    return implode("\n\n", $parts);
}

function admin_send_instagram_post(string $slug, string $title, string $image, array $settings, string $description = '', string $urlOverride = ''): bool {
    $token = trim((string) ($settings['token'] ?? ''));
    $accountId = trim((string) ($settings['channel'] ?? ''));
    if ($token === '' || $accountId === '') {
        return false;
    }
    $imageTrim = trim($image);
    if ($imageTrim === '') {
        return false;
    }
    $baseUrl = admin_base_url();
    $imageUrl = function_exists('nammu_resolve_asset') ? nammu_resolve_asset($imageTrim, $baseUrl) : '';
    if ($imageUrl === null || $imageUrl === '') {
        return false;
    }
    $targetUrl = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    $trackedUrl = admin_add_utm_params($targetUrl, [
        'utm_source' => 'instagram',
        'utm_medium' => 'social',
    ]);
    $caption = admin_build_instagram_caption($slug, $title, $description, $trackedUrl);
    $createEndpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($accountId) . '/media';
    $createResponse = admin_http_post_form_json($createEndpoint, [
        'image_url' => $imageUrl,
        'caption' => $caption,
        'access_token' => $token,
    ]);
    if (!is_array($createResponse) || empty($createResponse['id'])) {
        return false;
    }
    $creationId = (string) $createResponse['id'];
    $publishEndpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($accountId) . '/media_publish';
    $publishResponse = admin_http_post_form_json($publishEndpoint, [
        'creation_id' => $creationId,
        'access_token' => $token,
    ]);
    return is_array($publishResponse) && !empty($publishResponse['id']);
}

function admin_http_post_json(string $url, array $payload, array $headers = []): bool {
    $body = json_encode($payload);
    $headers[] = 'Content-Length: ' . strlen((string) $body);
    return admin_http_post_body($url, $body, $headers);
}

function admin_http_post_form_json(string $url, array $params): ?array {
    $body = http_build_query($params);
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($body),
    ];
    $responseBody = admin_http_post_body_response($url, $body, $headers);
    if ($responseBody === null) {
        return null;
    }
    $decoded = json_decode($responseBody, true);
    return is_array($decoded) ? $decoded : null;
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
    $responseBody = admin_http_post_body_response($url, $body, $headers, $httpCode);
    if ($responseBody === null) {
        return false;
    }
    if ($httpCode !== null) {
        return $httpCode >= 200 && $httpCode < 300;
    }
    return true;
}

function admin_http_post_body_response(string $url, string $body, array $headers, ?int &$httpCode = null): ?string {
    $responseBody = null;
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
        return null;
    }
    return $responseBody;
}

function admin_http_get_json(string $url, array $headers = []): ?array {
    $responseBody = null;
    $httpCode = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
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
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
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
        return null;
    }
    if ($httpCode !== null && ($httpCode < 200 || $httpCode >= 300)) {
        return null;
    }
    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        return null;
    }
    return $decoded;
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

function admin_send_telegram_photo(string $token, string $chatId, string $photoUrl, string $caption): bool {
    $endpoint = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendPhoto';
    $payload = [
        'chat_id' => $chatId,
        'photo' => $photoUrl,
        'caption' => $caption,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => false,
    ];
    return admin_http_post_form($endpoint, $payload);
}

function admin_maybe_auto_post_to_social_networks(string $filename, string $title, string $description, string $image = '', string $urlOverride = '', string $imageUrl = ''): void {
    $slug = pathinfo($filename, PATHINFO_FILENAME);
    if ($slug === '') {
        $slug = $filename;
    }
    $settings = admin_cached_social_settings();
    if (($settings['telegram']['auto_post'] ?? 'off') === 'on' && admin_is_social_network_configured('telegram', $settings['telegram'])) {
        admin_send_post_to_telegram($slug, $title, $description, $settings['telegram'], $urlOverride, $imageUrl);
    }
    if (($settings['whatsapp']['auto_post'] ?? 'off') === 'on' && admin_is_social_network_configured('whatsapp', $settings['whatsapp'])) {
        admin_send_whatsapp_post($slug, $title, $description, $settings['whatsapp'], $urlOverride, $imageUrl);
    }
    if (($settings['facebook']['auto_post'] ?? 'off') === 'on' && admin_is_social_network_configured('facebook', $settings['facebook'])) {
        admin_send_facebook_post($slug, $title, $description, $settings['facebook'], $urlOverride, $imageUrl);
    }
    if (($settings['twitter']['auto_post'] ?? 'off') === 'on' && admin_is_social_network_configured('twitter', $settings['twitter'])) {
        admin_send_twitter_post($slug, $title, $description, $settings['twitter'], $urlOverride, $imageUrl);
    }
    if (($settings['instagram']['auto_post'] ?? 'off') === 'on' && admin_is_social_network_configured('instagram', $settings['instagram'])) {
        if (trim($image) !== '') {
            admin_send_instagram_post($slug, $title, $image, $settings['instagram'], $description, $urlOverride);
        }
    }
}

function admin_maybe_enqueue_push_notification(string $type, string $title, string $description, string $url, string $image = ''): void {
    if (!function_exists('nammu_enqueue_push_notification')) {
        return;
    }
    $settings = get_settings();
    $push = $settings['ads'] ?? [];
    if (($push['push_enabled'] ?? 'off') !== 'on') {
        return;
    }
    if ($type === 'post' && ($push['push_posts'] ?? 'off') !== 'on') {
        return;
    }
    if ($type === 'itinerary' && ($push['push_itineraries'] ?? 'off') !== 'on') {
        return;
    }
    $payload = [
        'title' => $title,
        'body' => $description,
        'url' => $url,
        'icon' => $image,
    ];
    $result = function_exists('nammu_send_push_notification') ? nammu_send_push_notification($payload) : ['skipped' => true];
    if (!empty($result['skipped'])) {
        nammu_enqueue_push_notification($payload);
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
        'footer_nammu' => 'on',
        'images' => [
            'logo' => '',
        ],
        'global' => [
            'corners' => 'rounded',
        ],
        'home' => [
            'columns' => 2,
            'first_row_enabled' => 'off',
            'first_row_columns' => 2,
            'first_row_fill' => 'off',
            'first_row_align' => 'left',
            'first_row_style' => 'inherit',
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
        'subscription' => [
            'mode' => 'none',
            'position' => 'footer',
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

function admin_normalize_email(string $email): string {
    $email = strtolower(trim($email));
    return $email;
}

function admin_normalize_csv_header(string $header): string {
    $header = trim($header);
    if ($header === '') {
        return '';
    }
    $header = preg_replace('/^\xEF\xBB\xBF/u', '', $header);
    $header = mb_strtolower($header, 'UTF-8');
    $replacements = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n',
    ];
    $header = strtr($header, $replacements);
    $header = preg_replace('/[^a-z0-9]+/', ' ', $header);
    return trim($header);
}

function admin_postal_csv_column_map(array $headers): array {
    $map = [];
    foreach ($headers as $index => $header) {
        $key = admin_normalize_csv_header((string) $header);
        if ($key === '') {
            continue;
        }
        if (in_array($key, ['email', 'correo', 'correo electronico', 'e mail'], true)) {
            $map['email'] = $index;
        } elseif (in_array($key, ['nombre', 'nombre y apellidos', 'nombre completo'], true)) {
            $map['name'] = $index;
        } elseif (in_array($key, ['direccion', 'direccion postal', 'domicilio'], true)) {
            $map['address'] = $index;
        } elseif (in_array($key, ['poblacion', 'ciudad', 'localidad'], true)) {
            $map['city'] = $index;
        } elseif (in_array($key, ['codigo postal', 'cp', 'postal code', 'codigo'], true)) {
            $map['postal_code'] = $index;
        } elseif (in_array($key, ['provincia', 'region', 'provincia region', 'provincia region'], true)) {
            $map['region'] = $index;
        } elseif (in_array($key, ['pais', 'pais de residencia', 'country'], true)) {
            $map['country'] = $index;
        }
    }
    return $map;
}

function admin_maybe_add_to_mailing_list(string $email): void {
    $normalized = admin_normalize_email($email);
    if ($normalized === '') {
        return;
    }
    try {
        $subscribers = admin_load_mailing_subscriber_entries();
    } catch (Throwable $e) {
        return;
    }
    foreach ($subscribers as $subscriber) {
        if (admin_normalize_email((string) ($subscriber['email'] ?? '')) === $normalized) {
            return;
        }
    }
    $subscribers[] = [
        'email' => $normalized,
        'prefs' => admin_mailing_default_prefs(),
    ];
    try {
        admin_save_mailing_subscriber_entries($subscribers);
    } catch (Throwable $e) {
        return;
    }
}

function admin_parse_hex_color(string $hex, int &$r, int &$g, int &$b): bool {
    $value = ltrim(trim($hex), '#');
    if (strlen($value) === 3) {
        $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
    }
    if (strlen($value) !== 6 || !ctype_xdigit($value)) {
        return false;
    }
    $r = hexdec(substr($value, 0, 2));
    $g = hexdec(substr($value, 2, 2));
    $b = hexdec(substr($value, 4, 2));
    return true;
}

function admin_pick_contrast_color(string $backgroundHex, string $light = '#ffffff', string $dark = '#111111'): string {
    $r = $g = $b = 0;
    if (!admin_parse_hex_color($backgroundHex, $r, $g, $b)) {
        return $dark;
    }
    $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
    return $yiq >= 160 ? $dark : $light;
}

function admin_mailing_default_prefs(): array {
    return [
        'posts' => true,
        'itineraries' => true,
        'podcast' => true,
        'newsletter' => true,
    ];
}

function admin_mailing_normalize_prefs(array $prefs): array {
    $defaults = admin_mailing_default_prefs();
    $normalized = [];
    $hasPodcastKey = array_key_exists('podcast', $prefs);
    foreach ($defaults as $key => $default) {
        $value = $prefs[$key] ?? $default;
        if (is_string($value)) {
            $value = strtolower($value) !== 'off' && $value !== '0' && $value !== '';
        } else {
            $value = (bool) $value;
        }
        $normalized[$key] = $value;
    }
    if (!$hasPodcastKey) {
        $normalized['podcast'] = !empty($normalized['posts']) || !empty($normalized['itineraries']);
    }
    return $normalized;
}

function admin_mailing_normalize_entry($entry): ?array {
    if (is_string($entry)) {
        $email = admin_normalize_email($entry);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return [
            'email' => $email,
            'prefs' => admin_mailing_default_prefs(),
        ];
    }
    if (!is_array($entry)) {
        return null;
    }
    $email = admin_normalize_email((string) ($entry['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $prefsRaw = $entry['prefs'] ?? [];
    $prefs = is_array($prefsRaw) ? admin_mailing_normalize_prefs($prefsRaw) : admin_mailing_default_prefs();
    return [
        'email' => $email,
        'prefs' => $prefs,
    ];
}

function admin_load_mailing_subscriber_entries(): array {
    $file = MAILING_SUBSCRIBERS_FILE;
    if (!is_file($file)) {
        try {
            admin_save_mailing_subscriber_entries([]);
        } catch (Throwable $e) {
            return [];
        }
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $unique = [];
    foreach ($decoded as $entry) {
        $normalized = admin_mailing_normalize_entry($entry);
        if ($normalized === null) {
            continue;
        }
        $email = $normalized['email'];
        if (!isset($unique[$email])) {
            $unique[$email] = $normalized;
            continue;
        }
        $existingPrefs = $unique[$email]['prefs'] ?? admin_mailing_default_prefs();
        $incomingPrefs = $normalized['prefs'] ?? admin_mailing_default_prefs();
        foreach ($incomingPrefs as $key => $value) {
            $existingPrefs[$key] = ($existingPrefs[$key] ?? false) || $value;
        }
        $unique[$email]['prefs'] = $existingPrefs;
    }
    return array_values($unique);
}

function admin_save_mailing_subscriber_entries(array $entries): void {
    $file = MAILING_SUBSCRIBERS_FILE;
    $dir = dirname($file);
    if (!nammu_ensure_directory($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de configuración para la lista de correo');
    }
    $unique = [];
    foreach ($entries as $entry) {
        $normalized = admin_mailing_normalize_entry($entry);
        if ($normalized === null) {
            continue;
        }
        $email = $normalized['email'];
        $unique[$email] = $normalized;
    }
    $payload = json_encode(array_values($unique), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo serializar la lista de suscriptores');
    }
    if (file_put_contents($file, $payload, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo escribir el archivo de suscriptores');
    }
    @chmod($file, 0664);
}

function admin_load_mailing_subscribers(): array {
    $entries = admin_load_mailing_subscriber_entries();
    $emails = [];
    foreach ($entries as $entry) {
        $email = admin_normalize_email((string) ($entry['email'] ?? ''));
        if ($email !== '') {
            $emails[] = $email;
        }
    }
    return $emails;
}

function admin_save_mailing_subscribers(array $subscribers): void {
    $entries = [];
    foreach ($subscribers as $subscriber) {
        $normalized = admin_mailing_normalize_entry($subscriber);
        if ($normalized === null) {
            continue;
        }
        $entries[] = $normalized;
    }
    admin_save_mailing_subscriber_entries($entries);
}

function admin_mailing_recipients_for_type(string $type, array $settings): array {
    $type = strtolower(trim($type));
    $allowed = ['posts', 'itineraries', 'podcast', 'newsletter'];
    if (!in_array($type, $allowed, true)) {
        return [];
    }
    $entries = admin_load_mailing_subscriber_entries();
    $recipients = [];
    foreach ($entries as $entry) {
        $prefs = $entry['prefs'] ?? admin_mailing_default_prefs();
        if (!empty($prefs[$type])) {
            $recipients[] = $entry['email'];
        }
    }
    return $recipients;
}

function admin_mailing_type_for_template(string $template): string {
    $template = strtolower(trim($template));
    if ($template === 'itinerario') {
        return 'itineraries';
    }
    if ($template === 'podcast') {
        return 'podcast';
    }
    if ($template === 'newsletter') {
        return 'newsletter';
    }
    return 'posts';
}

function admin_load_mailing_tokens(): array {
    $file = __DIR__ . '/config/mailing-tokens.json';
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function admin_save_mailing_tokens(array $tokens): void {
    $file = __DIR__ . '/config/mailing-tokens.json';
    $dir = dirname($file);
    if (!nammu_ensure_directory($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de configuración para tokens de correo');
    }
    $payload = json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo serializar los tokens de correo');
    }
    if (file_put_contents($file, $payload, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo escribir el archivo de tokens de correo');
    }
    @chmod($file, 0660);
}

function admin_delete_mailing_tokens(): void {
    $file = __DIR__ . '/config/mailing-tokens.json';
    if (is_file($file)) {
        @unlink($file);
    }
}

function admin_mailing_secret(): string {
    $file = MAILING_SECRET_FILE;
    if (!is_file($file)) {
        $dir = dirname($file);
        nammu_ensure_directory($dir);
        $secret = bin2hex(random_bytes(32));
        file_put_contents($file, $secret);
        @chmod($file, 0640);
        return $secret;
    }
    $secret = trim((string) file_get_contents($file));
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
        file_put_contents($file, $secret);
    }
    return $secret;
}

function admin_mailing_unsubscribe_token(string $email): string {
    $secret = admin_mailing_secret();
    return hash_hmac('sha256', strtolower(trim($email)), $secret);
}

function admin_mailing_unsubscribe_link(string $email): string {
    $token = admin_mailing_unsubscribe_token($email);
    return admin_base_url() . '/unsubscribe.php?email=' . urlencode($email) . '&token=' . urlencode($token);
}

function admin_google_refresh_access_token(string $clientId, string $clientSecret, string $refreshToken): array {
    $postData = http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ];
    $context = stream_context_create($opts);
    $raw = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
    if ($raw === false) {
        $status = '';
        if (isset($http_response_header[0])) {
            $status = ' (' . $http_response_header[0] . ')';
        }
        throw new RuntimeException('No se pudo refrescar el token con Google' . $status);
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respuesta inesperada al refrescar token.');
    }
    if (isset($decoded['error'])) {
        $message = is_string($decoded['error']) ? $decoded['error'] : 'Error de OAuth';
        $desc = isset($decoded['error_description']) ? ' (' . $decoded['error_description'] . ')' : '';
        throw new RuntimeException($message . $desc);
    }
    $now = time();
    if (isset($decoded['expires_in'])) {
        $decoded['expires_at'] = $now + (int) $decoded['expires_in'];
    }
    return $decoded;
}

function admin_gmail_send_message(string $from, string $to, string $subject, string $textBody, string $htmlBody, string $accessToken, ?string $fromName = null): array {
    $boundary = '=_NammuMailer_' . bin2hex(random_bytes(8));
    $displayName = $fromName && trim($fromName) !== '' ? trim($fromName) : '';
    $displayName = str_replace(['"', "\r", "\n"], '', $displayName);
    $encodedName = $displayName !== '' && function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($displayName, 'UTF-8', 'Q', "\r\n")
        : ($displayName !== '' ? '=?UTF-8?B?' . base64_encode($displayName) . '?=' : '');
    $fromHeader = $encodedName !== '' ? $encodedName . ' <' . $from . '>' : $from;
    $subjectHeader = function_exists('mb_encode_mimeheader')
        ? mb_encode_mimeheader($subject, 'UTF-8', 'Q', "\r\n")
        : '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $unsubscribe = admin_mailing_unsubscribe_link($to);
    $headers = [
        'From: ' . $fromHeader,
        'Reply-To: ' . $fromHeader,
        'To: ' . $to,
        'Subject: ' . $subjectHeader,
        'MIME-Version: 1.0',
        'List-Unsubscribe: <' . $unsubscribe . '>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $body = [];
    $body[] = '--' . $boundary;
    $body[] = 'Content-Type: text/plain; charset=UTF-8';
    $body[] = 'Content-Transfer-Encoding: 7bit';
    $body[] = '';
    $body[] = $textBody;
    $body[] = '--' . $boundary;
    $body[] = 'Content-Type: text/html; charset=UTF-8';
    $body[] = 'Content-Transfer-Encoding: 7bit';
    $body[] = '';
    $body[] = $htmlBody;
    $body[] = '--' . $boundary . '--';
    $rawMessage = implode("\r\n", array_merge($headers, [''], $body));
    $payload = json_encode(['raw' => rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=')]);
    if ($payload === false) {
        throw new RuntimeException('No se pudo preparar el mensaje.');
    }
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 12,
            'ignore_errors' => true,
        ],
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', false, $context);
    if ($response === false) {
        $status = isset($http_response_header[0]) ? $http_response_header[0] : 'sin respuesta';
        return [false, 'HTTP ' . $status];
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || !isset($decoded['id'])) {
        if (isset($decoded['error']['message'])) {
            return [false, 'Error Gmail: ' . $decoded['error']['message']];
        }
        return [false, 'Respuesta inesperada al enviar correo'];
    }
    return [true, null];
}

function admin_is_mailing_ready(array $settings): bool {
    $mailing = $settings['mailing'] ?? [];
    $gmail = $mailing['gmail_address'] ?? '';
    $clientId = $mailing['client_id'] ?? '';
    $clientSecret = $mailing['client_secret'] ?? '';
    $tokens = admin_load_mailing_tokens();
    return $gmail !== '' && $clientId !== '' && $clientSecret !== '' && !empty($tokens['refresh_token']);
}

function admin_send_mailing_broadcast(string $subject, string $textBody, string $htmlBody, array $subscribers, array $mailingConfig, ?callable $bodyBuilder = null, ?string $fromName = null): array {
    $gmail = $mailingConfig['gmail_address'] ?? '';
    $clientId = $mailingConfig['client_id'] ?? '';
    $clientSecret = $mailingConfig['client_secret'] ?? '';
    $tokens = admin_load_mailing_tokens();
    $refresh = $tokens['refresh_token'] ?? '';
    if ($gmail === '' || $clientId === '' || $clientSecret === '' || $refresh === '') {
        throw new RuntimeException('Falta configuración o tokens de Gmail.');
    }
    $refreshed = admin_google_refresh_access_token($clientId, $clientSecret, $refresh);
    $accessToken = $refreshed['access_token'] ?? '';
    if ($accessToken === '') {
        throw new RuntimeException('No se obtuvo access_token al refrescar.');
    }
    if ($fromName && $gmail) {
        admin_gmail_update_display_name($gmail, $fromName, $accessToken);
    }
    $tokens['access_token'] = $accessToken;
    if (isset($refreshed['expires_at'])) {
        $tokens['expires_at'] = $refreshed['expires_at'];
    }
    admin_save_mailing_tokens($tokens);
    $ok = 0;
    $fail = 0;
    $lastError = null;
    foreach ($subscribers as $to) {
        $textToSend = $textBody;
        $htmlToSend = $htmlBody;
        if ($bodyBuilder !== null) {
            [$textToSend, $htmlToSend] = $bodyBuilder($to);
        }
        [$sent, $err] = admin_gmail_send_message($gmail, $to, $subject, $textToSend, $htmlToSend, $accessToken, $fromName);
        if ($sent) {
            $ok++;
        } else {
            $fail++;
            if ($err !== null) {
                $lastError = $err;
            }
        }
    }
    return ['sent' => $ok, 'failed' => $fail, 'error' => $lastError];
}
function admin_google_exchange_code(string $code, string $clientId, string $clientSecret, string $redirectUri): array {
    $postData = http_build_query([
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
        'access_type' => 'offline',
        'prompt' => 'consent',
    ]);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData,
            'timeout' => 12,
        ],
    ];
    $context = stream_context_create($opts);
    $raw = @file_get_contents('https://oauth2.googleapis.com/token', false, $context);
    if ($raw === false) {
        throw new RuntimeException('No se pudo contactar con Google OAuth.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Respuesta inesperada al intercambiar el código de Google.');
    }
    if (isset($decoded['error'])) {
        $message = is_string($decoded['error']) ? $decoded['error'] : 'Error de OAuth';
        $desc = isset($decoded['error_description']) ? ' (' . $decoded['error_description'] . ')' : '';
        throw new RuntimeException($message . $desc);
    }
    if (empty($decoded['refresh_token'])) {
        throw new RuntimeException('Google no devolvió refresh_token. Vuelve a intentar con “Forzar consentimiento”.');
    }
    $now = time();
    $decoded['received_at'] = $now;
    if (isset($decoded['expires_in'])) {
        $decoded['expires_at'] = $now + (int) $decoded['expires_in'];
    }
    return $decoded;
}

function admin_add_utm_params(string $url, array $params): string {
    if ($url === '') {
        return $url;
    }
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }
    $query = [];
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    foreach ($params as $key => $value) {
        $key = (string) $key;
        $value = (string) $value;
        if ($key === '' || $value === '' || isset($query[$key])) {
            continue;
        }
        $query[$key] = $value;
    }
    $userInfo = '';
    if (!empty($parts['user'])) {
        $userInfo = $parts['user'];
        if (!empty($parts['pass'])) {
            $userInfo .= ':' . $parts['pass'];
        }
        $userInfo .= '@';
    }
    $base = '';
    if (!empty($parts['scheme'])) {
        $base .= $parts['scheme'] . '://';
    }
    if (!empty($parts['host'])) {
        $base .= $userInfo . $parts['host'];
        if (!empty($parts['port'])) {
            $base .= ':' . $parts['port'];
        }
    }
    $base .= $parts['path'] ?? '';
    $queryString = http_build_query($query);
    $fragment = $parts['fragment'] ?? '';
    $rebuilt = $base;
    if ($queryString !== '') {
        $rebuilt .= '?' . $queryString;
    }
    if ($fragment !== '') {
        $rebuilt .= '#' . $fragment;
    }
    return $rebuilt !== '' ? $rebuilt : $url;
}

function admin_prepare_mailing_payload(string $template, array $settings, string $title, string $description, string $link, string $imagePath): array {
    $mailingConfig = $settings['mailing'] ?? [];
    $format = $mailingConfig['format'] ?? 'html';
    $isHtml = $format !== 'text';
    $subject = $title;
    $blogName = $settings['site_name'] ?? 'Tu blog';
    $authorName = $settings['site_author'] ?? 'Autor';
    $siteBase = rtrim($settings['site_url'] ?? '', '/');
    $baseForAssets = $siteBase !== '' ? $siteBase : rtrim(admin_base_url(), '/');
    $imageUrl = '';
    if ($imagePath !== '') {
        if (preg_match('#^https?://#i', $imagePath)) {
            $imageUrl = $imagePath;
        } else {
            $normalizedImage = ltrim($imagePath, '/');
            $normalizedImage = str_replace(['../', '..\\', './', '.\\'], '', $normalizedImage);
            $candidates = [];
            $candidates[] = $normalizedImage;
            if (!str_starts_with($normalizedImage, 'assets/')) {
                $candidates[] = 'assets/' . $normalizedImage;
            }
            foreach ($candidates as $cand) {
                $local = __DIR__ . '/' . $cand;
                if (is_file($local) || is_file(__DIR__ . '/' . ltrim($cand, '/'))) {
                    $imageUrl = $baseForAssets . '/' . $cand;
                    break;
                }
            }
            if ($imageUrl === '' && !empty($candidates)) {
                $imageUrl = $baseForAssets . '/' . $candidates[0];
            }
        }
    }
    $logoPath = $settings['template']['images']['logo'] ?? '';
    $logoUrl = '';
    if ($logoPath !== '') {
        if (preg_match('#^https?://#i', $logoPath)) {
            $logoUrl = $logoPath;
        } else {
            $normalizedLogo = ltrim($logoPath, '/');
            $normalizedLogo = str_replace(['../', '..\\', './', '.\\'], '', $normalizedLogo);
            $logoUrl = $baseForAssets . '/' . $normalizedLogo;
        }
    }
    $colors = $settings['template']['colors'] ?? [];
    $colorBackground = $colors['background'] ?? '#ffffff';
    $colorText = $colors['text'] ?? '#222222';
    $colorHighlight = $colors['highlight'] ?? '#f3f6f9';
    $colorAccent = $colors['accent'] ?? '#0a4c8a';
    $colorH1 = $colors['h1'] ?? $colorAccent;
    $colorH2 = $colors['h2'] ?? $colorText;
    $headerBg = $colorH1;
    $ctaColor = $colorH1;
    $outerBg = $colorHighlight;
    $cardBg = $colorBackground;
    $footerBg = $colorHighlight;
    $border = $colorAccent;
    $headerText = admin_pick_contrast_color($headerBg, '#ffffff', '#111111');
    $ctaText = admin_pick_contrast_color($ctaColor, '#ffffff', '#111111');
    $footerText = admin_pick_contrast_color($footerBg, '#ffffff', $colorText);
    $titleFont = $settings['template']['fonts']['title'] ?? 'Arial';
    $bodyFont = $settings['template']['fonts']['body'] ?? 'Arial';
    $fontsUrl = '';
    $fontFamilies = [];
    foreach ([$titleFont, $bodyFont] as $fontCandidate) {
        $clean = trim((string) $fontCandidate);
        if ($clean !== '') {
            $fontFamilies[] = str_replace(' ', '+', $clean) . ':wght@400;600;700';
        }
    }
    if (!empty($fontFamilies)) {
        $fontsUrl = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', array_unique($fontFamilies)) . '&display=swap';
    }
    $titleFontCss = htmlspecialchars($titleFont, ENT_QUOTES, 'UTF-8');
    $bodyFontCss = htmlspecialchars($bodyFont, ENT_QUOTES, 'UTF-8');
    $ctaLabel = $template === 'itinerario'
        ? 'Comienza este itinerario'
        : ($template === 'page'
            ? 'Ver esta página'
            : ($template === 'podcast' ? 'Escuchar el episodio' : 'Sigue leyendo'));
    $ctaText = $ctaLabel;
    $fromName = $authorName !== '' ? $authorName : $blogName;

    $trackedLink = admin_add_utm_params($link, [
        'utm_source' => 'email',
        'utm_medium' => 'avisos',
        'utm_campaign' => $template,
    ]);
    $buildText = function (string $recipientEmail) use ($authorName, $blogName, $title, $description, $trackedLink, $ctaText) {
        $lines = [];
        $lines[] = '**** ' . ($authorName !== '' ? $authorName : $blogName) . ' ****';
        $lines[] = '**** ' . $blogName . ' ****';
        $lines[] = '';
        $lines[] = '== ' . $title . ' ==';
        $lines[] = '';
        if ($description !== '') {
            $lines[] = $description;
            $lines[] = '';
        }
        $lines[] = $ctaText . ': ' . $trackedLink;
        $lines[] = '';
        $lines[] = '-----------';
        $lines[] = 'Recibes este email porque estás suscrito a las comunicaciones de ' . $blogName . '. Puedes darte de baja pulsando aquí: ' . admin_mailing_unsubscribe_link($recipientEmail);
        return implode("\n", $lines);
    };

    $buildHtml = function (string $recipientEmail) use ($authorName, $blogName, $title, $description, $trackedLink, $imageUrl, $logoUrl, $headerBg, $headerText, $ctaColor, $ctaText, $outerBg, $cardBg, $colorText, $colorH2, $footerBg, $footerText, $border, $ctaLabel, $fontsUrl, $titleFontCss, $bodyFontCss) {
        $safeUnsub = htmlspecialchars(admin_mailing_unsubscribe_link($recipientEmail), ENT_QUOTES, 'UTF-8');
        $html = [];
        if ($fontsUrl !== '') {
            $html[] = '<link rel="stylesheet" href="' . htmlspecialchars($fontsUrl, ENT_QUOTES, 'UTF-8') . '">';
        }
        $html[] = '<style>h1,h2,h3,h4,h5,h6{font-family:' . $titleFontCss . ', Arial, sans-serif;} body,p,a,div,span{font-family:' . $bodyFontCss . ', Arial, sans-serif;}</style>';
        $html[] = '<div style="font-family:' . $bodyFontCss . ', Arial, sans-serif; background:' . htmlspecialchars($outerBg, ENT_QUOTES, 'UTF-8') . '; padding:24px; color:' . htmlspecialchars($colorText, ENT_QUOTES, 'UTF-8') . ';">';
        $html[] = '  <div style="max-width:720px; margin:0 auto; background:' . htmlspecialchars($cardBg, ENT_QUOTES, 'UTF-8') . '; border:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33; border-radius:12px; overflow:hidden;">';
        $html[] = '    <div style="background:' . htmlspecialchars($headerBg, ENT_QUOTES, 'UTF-8') . '; color:' . htmlspecialchars($headerText, ENT_QUOTES, 'UTF-8') . '; padding:18px 22px; text-align:center;">';
        if ($logoUrl !== '') {
            $html[] = '      <div style="margin-bottom:10px;"><img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="width:64px; height:64px; object-fit:cover; border-radius:50%; box-shadow:0 4px 12px rgba(0,0,0,0.15); background:' . htmlspecialchars($cardBg, ENT_QUOTES, 'UTF-8') . ';"></div>';
        }
        $html[] = '      <div style="font-size:14px; opacity:0.9; margin-bottom:4px;">' . htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '      <div style="font-size:20px; font-weight:700;">' . htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '    </div>';
        $html[] = '    <div style="padding:22px;">';
        $html[] = '      <h2 style="margin:0 0 20px 0; font-size:38px; line-height:1.15; color:' . htmlspecialchars($colorH2, ENT_QUOTES, 'UTF-8') . '; font-family:' . $titleFontCss . ', Arial, sans-serif;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
        if ($imageUrl !== '') {
            $html[] = '      <div style="margin:0 0 14px 0;"><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="width:100%; display:block; border-radius:12px; border:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33;"></div>';
        }
        if ($description !== '') {
            $html[] = '      <p style="margin:0 0 20px 0; line-height:1.75; font-size:20px; color:' . htmlspecialchars($colorText, ENT_QUOTES, 'UTF-8') . '; font-family:' . $bodyFontCss . ', Arial, sans-serif;">' . nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) . '</p>';
        }
        $html[] = '      <p style="margin:0 0 16px 0;">';
        $html[] = '        <a href="' . htmlspecialchars($trackedLink, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; background:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . '; color:' . htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8') . '; padding:14px 18px; border-radius:10px; text-decoration:none; font-weight:600;">' . htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') . '</a>';
        $html[] = '      </p>';
        $html[] = '    </div>';
        $html[] = '    <div style="padding:16px 22px; background:' . htmlspecialchars($footerBg, ENT_QUOTES, 'UTF-8') . '; border-top:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33; font-size:13px; color:' . htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') . '; opacity:0.8;">';
        $html[] = '      <p style="margin:0 0 6px 0;">Recibes este email porque estás suscrito a las comunicaciones de ' . htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') . '.</p>';
        $html[] = '      <p style="margin:0;"><a href="' . $safeUnsub . '" style="color:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . ';">Puedes darte de baja pulsando aquí</a>.</p>';
        $html[] = '    </div>';
        $html[] = '  </div>';
        $html[] = '</div>';
        return implode('', $html);
    };

    $bodyBuilder = function (string $recipientEmail) use ($isHtml, $buildText, $buildHtml) {
        if ($isHtml) {
            $text = $buildText($recipientEmail);
            $html = $buildHtml($recipientEmail);
            return [$text, $html];
        }
        $text = $buildText($recipientEmail);
        $html = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        return [$text, $html];
    };

    return [
        'subject' => $subject,
        'bodyBuilder' => $bodyBuilder,
        'fromName' => $fromName,
        'mailingConfig' => $mailingConfig,
    ];
}

function admin_prepare_newsletter_payload(array $settings, string $title, string $contentHtml, string $contentText, string $imagePath): array {
    $mailingConfig = $settings['mailing'] ?? [];
    $format = $mailingConfig['format'] ?? 'html';
    $isHtml = $format !== 'text';
    $subject = $title;
    $blogName = $settings['site_name'] ?? 'Tu blog';
    $authorName = $settings['site_author'] ?? 'Autor';
    $siteBase = rtrim($settings['site_url'] ?? '', '/');
    $baseForAssets = $siteBase !== '' ? $siteBase : rtrim(admin_base_url(), '/');
    $link = $siteBase !== '' ? $siteBase : rtrim(admin_base_url(), '/');
    $imageUrl = '';
    if ($imagePath !== '') {
        if (preg_match('#^https?://#i', $imagePath)) {
            $imageUrl = $imagePath;
        } else {
            $normalizedImage = ltrim($imagePath, '/');
            $normalizedImage = str_replace(['../', '..\\', './', '.\\'], '', $normalizedImage);
            $candidates = [];
            $candidates[] = $normalizedImage;
            if (!str_starts_with($normalizedImage, 'assets/')) {
                $candidates[] = 'assets/' . $normalizedImage;
            }
            foreach ($candidates as $cand) {
                $local = __DIR__ . '/' . $cand;
                if (is_file($local) || is_file(__DIR__ . '/' . ltrim($cand, '/'))) {
                    $imageUrl = $baseForAssets . '/' . $cand;
                    break;
                }
            }
            if ($imageUrl === '' && !empty($candidates)) {
                $imageUrl = $baseForAssets . '/' . $candidates[0];
            }
        }
    }
    $logoPath = $settings['template']['images']['logo'] ?? '';
    $logoUrl = '';
    if ($logoPath !== '') {
        if (preg_match('#^https?://#i', $logoPath)) {
            $logoUrl = $logoPath;
        } else {
            $normalizedLogo = ltrim($logoPath, '/');
            $normalizedLogo = str_replace(['../', '..\\', './', '.\\'], '', $normalizedLogo);
            $logoUrl = $baseForAssets . '/' . $normalizedLogo;
        }
    }
    $colors = $settings['template']['colors'] ?? [];
    $colorBackground = $colors['background'] ?? '#ffffff';
    $colorText = $colors['text'] ?? '#222222';
    $colorHighlight = $colors['highlight'] ?? '#f3f6f9';
    $colorAccent = $colors['accent'] ?? '#0a4c8a';
    $colorH1 = $colors['h1'] ?? $colorAccent;
    $colorH2 = $colors['h2'] ?? $colorText;
    $headerBg = $colorH1;
    $ctaColor = $colorH1;
    $outerBg = $colorHighlight;
    $cardBg = $colorBackground;
    $footerBg = $colorHighlight;
    $border = $colorAccent;
    $headerText = admin_pick_contrast_color($headerBg, '#ffffff', '#111111');
    $ctaText = admin_pick_contrast_color($ctaColor, '#ffffff', '#111111');
    $footerText = admin_pick_contrast_color($footerBg, '#ffffff', $colorText);
    $titleFont = $settings['template']['fonts']['title'] ?? 'Arial';
    $bodyFont = $settings['template']['fonts']['body'] ?? 'Arial';
    $fontsUrl = '';
    $fontFamilies = [];
    foreach ([$titleFont, $bodyFont] as $fontCandidate) {
        $clean = trim((string) $fontCandidate);
        if ($clean !== '') {
            $fontFamilies[] = str_replace(' ', '+', $clean) . ':wght@400;600;700';
        }
    }
    if (!empty($fontFamilies)) {
        $fontsUrl = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', array_unique($fontFamilies)) . '&display=swap';
    }
    $titleFontCss = htmlspecialchars($titleFont, ENT_QUOTES, 'UTF-8');
    $bodyFontCss = htmlspecialchars($bodyFont, ENT_QUOTES, 'UTF-8');
    $fromName = $authorName !== '' ? $authorName : $blogName;

    $buildText = function (string $recipientEmail) use ($authorName, $blogName, $title, $contentText, $link) {
        $lines = [];
        $lines[] = '**** ' . ($authorName !== '' ? $authorName : $blogName) . ' ****';
        $lines[] = '**** ' . $blogName . ' ****';
        $lines[] = '';
        $lines[] = '== ' . $title . ' ==';
        $lines[] = '';
        if ($contentText !== '') {
            $lines[] = $contentText;
            $lines[] = '';
        }
        if ($link !== '') {
            $lines[] = 'Visita el sitio: ' . $link;
            $lines[] = '';
        }
        $lines[] = '-----------';
        $lines[] = 'Recibes este email porque estás suscrito a las comunicaciones de ' . $blogName . '. Puedes darte de baja pulsando aquí: ' . admin_mailing_unsubscribe_link($recipientEmail);
        return implode("\n", $lines);
    };

    $buildHtml = function (string $recipientEmail) use ($authorName, $blogName, $title, $contentHtml, $link, $imageUrl, $logoUrl, $headerBg, $headerText, $ctaColor, $ctaText, $outerBg, $cardBg, $colorText, $colorH2, $footerBg, $footerText, $border, $fontsUrl, $titleFontCss, $bodyFontCss) {
        $safeUnsub = htmlspecialchars(admin_mailing_unsubscribe_link($recipientEmail), ENT_QUOTES, 'UTF-8');
        $html = [];
        if ($fontsUrl !== '') {
            $html[] = '<link rel="stylesheet" href="' . htmlspecialchars($fontsUrl, ENT_QUOTES, 'UTF-8') . '">';
        }
        $html[] = '<style>h1,h2,h3,h4,h5,h6{font-family:' . $titleFontCss . ', Arial, sans-serif;} body,p,a,div,span{font-family:' . $bodyFontCss . ', Arial, sans-serif;} a{color:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . ';}</style>';
        $html[] = '<div style="font-family:' . $bodyFontCss . ', Arial, sans-serif; background:' . htmlspecialchars($outerBg, ENT_QUOTES, 'UTF-8') . '; padding:24px; color:' . htmlspecialchars($colorText, ENT_QUOTES, 'UTF-8') . ';">';
        $html[] = '  <div style="max-width:720px; margin:0 auto; background:' . htmlspecialchars($cardBg, ENT_QUOTES, 'UTF-8') . '; border:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33; border-radius:12px; overflow:hidden;">';
        $html[] = '    <div style="background:' . htmlspecialchars($headerBg, ENT_QUOTES, 'UTF-8') . '; color:' . htmlspecialchars($headerText, ENT_QUOTES, 'UTF-8') . '; padding:18px 22px; text-align:center;">';
        if ($logoUrl !== '') {
            $html[] = '      <div style="margin-bottom:10px;"><img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="width:64px; height:64px; object-fit:cover; border-radius:50%; box-shadow:0 4px 12px rgba(0,0,0,0.15); background:' . htmlspecialchars($cardBg, ENT_QUOTES, 'UTF-8') . ';"></div>';
        }
        $html[] = '      <div style="font-size:14px; opacity:0.9; margin-bottom:4px;">' . htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '      <div style="font-size:20px; font-weight:700;">' . htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '    </div>';
        $html[] = '    <div style="padding:22px;">';
        $html[] = '      <h2 style="margin:0 0 20px 0; font-size:32px; line-height:1.15; color:' . htmlspecialchars($colorH2, ENT_QUOTES, 'UTF-8') . '; font-family:' . $titleFontCss . ', Arial, sans-serif;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>';
        if ($imageUrl !== '') {
            $html[] = '      <div style="margin:0 0 14px 0;"><img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="width:100%; display:block; border-radius:12px; border:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33;"></div>';
        }
        if ($contentHtml !== '') {
            $html[] = '      <div style="margin:0; line-height:1.75; font-size:18px; color:' . htmlspecialchars($colorText, ENT_QUOTES, 'UTF-8') . '; font-family:' . $bodyFontCss . ', Arial, sans-serif;">' . $contentHtml . '</div>';
        }
        if ($link !== '') {
            $html[] = '      <p style="margin:20px 0 0 0;">';
            $html[] = '        <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; background:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . '; color:' . htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8') . '; padding:14px 18px; border-radius:10px; text-decoration:none; font-weight:600;">Visitar el sitio</a>';
            $html[] = '      </p>';
        }
        $html[] = '    </div>';
        $html[] = '    <div style="padding:16px 22px; background:' . htmlspecialchars($footerBg, ENT_QUOTES, 'UTF-8') . '; border-top:1px solid ' . htmlspecialchars($border, ENT_QUOTES, 'UTF-8') . '33; font-size:13px; color:' . htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8') . '; opacity:0.8;">';
        $html[] = '      <p style="margin:0 0 6px 0;">Recibes este email porque estás suscrito a las comunicaciones de ' . htmlspecialchars($blogName, ENT_QUOTES, 'UTF-8') . '.</p>';
        $html[] = '      <p style="margin:0;"><a href="' . $safeUnsub . '" style="color:' . htmlspecialchars($ctaColor, ENT_QUOTES, 'UTF-8') . ';">Puedes darte de baja pulsando aquí</a>.</p>';
        $html[] = '    </div>';
        $html[] = '  </div>';
        $html[] = '</div>';
        return implode('', $html);
    };

    $bodyBuilder = function (string $recipientEmail) use ($isHtml, $buildText, $buildHtml) {
        if ($isHtml) {
            $text = $buildText($recipientEmail);
            $html = $buildHtml($recipientEmail);
            return [$text, $html];
        }
        $text = $buildText($recipientEmail);
        $html = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        return [$text, $html];
    };

    return [
        'subject' => $subject,
        'bodyBuilder' => $bodyBuilder,
        'fromName' => $fromName,
        'mailingConfig' => $mailingConfig,
    ];
}

function admin_gmail_update_display_name(string $sendAsEmail, string $displayName, string $accessToken): void {
    $sendAsEmail = trim($sendAsEmail);
    $displayName = trim($displayName);
    if ($sendAsEmail === '' || $displayName === '') {
        return;
    }
    $payload = json_encode([
        'displayName' => $displayName,
        'replyToAddress' => $sendAsEmail,
        'treatAsAlias' => false,
    ]);
    if ($payload === false) {
        return;
    }
    $targets = [$sendAsEmail, 'me'];
    foreach ($targets as $target) {
        $url = 'https://gmail.googleapis.com/gmail/v1/users/me/settings/sendAs/' . rawurlencode($target);
        $opts = [
            'http' => [
                'method' => 'PATCH',
                'header' => "Authorization: Bearer {$accessToken}\r\nContent-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ];
        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);
        if ($result !== false && isset($http_response_header[0]) && str_contains($http_response_header[0], '200')) {
            break;
        }
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

/**
 * Guarda automáticamente el contenido del editor (publicar/editar) cuando una acción va a recargar la página.
 */
function admin_autosave_from_payload($jsonPayload): array {
    $result = ['saved' => false, 'filename' => '', 'message' => ''];
    if (!is_string($jsonPayload) || trim($jsonPayload) === '') {
        return $result;
    }

    $decoded = json_decode($jsonPayload, true);
    if (!is_array($decoded)) {
        return $result;
    }
    $context = $decoded['context'] ?? '';
    if ($context !== '' && !in_array($context, ['edit', 'publish'], true)) {
        // Solo autosalvamos posts y páginas; otros contextos se restauran en cliente
        return $result;
    }
    $fields = $decoded['fields'] ?? null;
    if (!is_array($fields)) {
        return $result;
    }

    $title = trim((string) ($fields['title'] ?? ''));
    $description = trim((string) ($fields['description'] ?? ''));
    $content = (string) ($fields['content'] ?? '');
    if ($title === '' && $description === '' && trim($content) === '') {
        $result['message'] = 'No se guardó ningún borrador porque el editor está vacío.';
        return $result;
    }

    $category = trim((string) ($fields['category'] ?? ''));
    $dateInput = (string) ($fields['date'] ?? '');
    $timestamp = $dateInput !== '' ? strtotime($dateInput) : time();
    if ($timestamp === false) {
        $timestamp = time();
    }
    $date = date('Y-m-d', $timestamp);
    $image = trim((string) ($fields['image'] ?? ''));
    $type = ($fields['type'] ?? '') === 'Página' ? 'Página' : 'Entrada';
    $template = $type === 'Página' ? 'page' : 'post';
    $statusInput = strtolower(trim((string) ($fields['status'] ?? '')));
    $status = in_array($statusInput, ['draft', 'published'], true) ? $statusInput : 'draft';

    $existingFilename = nammu_normalize_filename((string) ($fields['filename'] ?? ''));
    $isExistingFile = $existingFilename !== '' && is_file(CONTENT_DIR . '/' . $existingFilename);
    $targetFilename = $existingFilename;
    $ordo = 0;

    if ($isExistingFile) {
        $existing = get_post_content($existingFilename);
        $existingMeta = $existing['metadata'] ?? [];
        $ordo = (int) ($existingMeta['Ordo'] ?? 0);
        if (!in_array($status, ['draft', 'published'], true)) {
            $status = strtolower($existingMeta['Status'] ?? 'draft');
            if ($status !== 'draft' && $status !== 'published') {
                $status = 'draft';
            }
        }
        $template = $type === 'Página' ? 'page' : ($template === 'page' ? 'page' : 'post');
    } else {
        $status = 'draft';
        $slugPattern = '/^[a-z0-9-]+$/i';
        $slugCandidate = trim((string) ($fields['filename'] ?? ''));
        $slug = '';
        if ($slugCandidate !== '' && preg_match($slugPattern, $slugCandidate)) {
            $slug = nammu_slugify($slugCandidate);
        }
        if ($slug === '' && $title !== '') {
            $slug = nammu_slugify($title);
        }
        if ($slug === '') {
            $slug = 'borrador';
        }
        $uniqueSlug = nammu_unique_filename($slug);
        $targetFilename = nammu_normalize_filename($uniqueSlug . '.md');

        $allPosts = get_all_posts_metadata();
        $maxOrdo = 0;
        foreach ($allPosts as $post) {
            $metaOrdo = isset($post['metadata']['Ordo']) ? (int) $post['metadata']['Ordo'] : 0;
            if ($metaOrdo > $maxOrdo) {
                $maxOrdo = $metaOrdo;
            }
        }
        $ordo = $maxOrdo + 1;
    }

    if ($targetFilename === '') {
        $result['message'] = 'No se pudo determinar el archivo para guardar el borrador.';
        return $result;
    }

    $file_content = "---\n";
    $file_content .= "Title: " . $title . "\n";
    $file_content .= "Template: " . $template . "\n";
    $file_content .= "Category: " . $category . "\n";
    $file_content .= "Date: " . $date . "\n";
    $file_content .= "Image: " . $image . "\n";
    $file_content .= "Description: " . $description . "\n";
    $file_content .= "Status: " . $status . "\n";
    $file_content .= "Ordo: " . $ordo . "\n";
    $file_content .= "---\n\n";
    $file_content .= $content;

    $finalPath = CONTENT_DIR . '/' . $targetFilename;
    $tempPath = tempnam(CONTENT_DIR, 'autosave_');
    if ($tempPath === false) {
        $result['message'] = 'No se pudo crear el archivo temporal para el borrador.';
        return $result;
    }
    if (file_put_contents($tempPath, $file_content) === false) {
        @unlink($tempPath);
        $result['message'] = 'No se pudieron guardar los cambios antes de recargar la página.';
        return $result;
    }

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
        $result['saved'] = true;
        $result['filename'] = $targetFilename;
        $result['message'] = $isExistingFile
            ? 'Cambios guardados automáticamente antes de recargar la página.'
            : 'Borrador guardado automáticamente antes de recargar la página.';
    } else {
        $result['message'] = 'No se pudieron guardar los cambios antes de recargar la página.';
    }

    return $result;
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
        header('Location: admin.php?page=configuracion');
        exit;
    }
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
        if ($error === null && $filename !== '') {
            $markdown = new MarkdownConverter();
            $contentHtml = $markdown->toHtml($content);
            $contentText = trim(strip_tags($contentHtml));
            $payload = admin_prepare_newsletter_payload($settings, $title, $contentHtml, $contentText, $image);
            try {
                admin_send_mailing_broadcast($payload['subject'], '', '', $subscribers, $payload['mailingConfig'], $payload['bodyBuilder'], $payload['fromName']);
            } catch (Throwable $e) {
                $error = 'No se pudo enviar la newsletter. Revisa la configuración de correo.';
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
                if (file_put_contents($filepath, $file_content) === false) {
                    $error = 'No se pudo guardar la newsletter. Revisa los permisos de la carpeta content/.';
                } else {
                    $_SESSION['mailing_feedback'] = [
                        'type' => 'success',
                        'message' => 'Newsletter enviada correctamente.',
                    ];
                    header('Location: admin.php?page=edit&template=newsletter&created=' . urlencode($targetFilename));
                    exit;
                }
            }
        }
    } elseif (isset($_POST['publish']) || isset($_POST['save_draft'])) {
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
        $audioLengthInput = trim($_POST['audio_length'] ?? '');
        $audioDuration = trim($_POST['audio_duration'] ?? '');
        $type = $_POST['type'] ?? 'Entrada';
        if ($type === 'Página') {
            $type = 'Página';
        } elseif ($type === 'Podcast') {
            $type = 'Podcast';
        } elseif ($type === 'Newsletter') {
            $type = 'Newsletter';
        } else {
            $type = 'Entrada';
        }
        $filenameInput = trim($_POST['filename'] ?? '');
        $isDraft = isset($_POST['save_draft']);
        $statusValue = $isDraft ? 'draft' : 'published';
        $publishAtValue = '';
        $slugPattern = '/^[a-z0-9-]+$/i';

        if ($filenameInput !== '' && !preg_match($slugPattern, $filenameInput)) {
            $error = 'El slug solo puede contener letras, números y guiones medios.';
        }

        if ($filenameInput === '' && $title !== '') {
            $filenameInput = nammu_slugify($title);
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

                if (file_put_contents($filepath, $file_content) === false) {
                    $error = 'No se pudo guardar el contenido. Revisa los permisos de la carpeta content/.';
                } else {
                    if (!$isDraft && $type === 'Entrada') {
                        admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description, $image);
                        $settings = get_settings();
                        $mailing = $settings['mailing'] ?? [];
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        $link = admin_public_post_url($slug);
                        if (($mailing['auto_posts'] ?? 'off') === 'on' && admin_is_mailing_ready($settings)) {
                            $subscribers = admin_mailing_recipients_for_type('posts', $settings);
                            if (!empty($subscribers)) {
                                $payload = admin_prepare_mailing_payload('single', $settings, $title, $description, $link, $image);
                                try {
                                    admin_send_mailing_broadcast($payload['subject'], '', '', $subscribers, $payload['mailingConfig'], $payload['bodyBuilder'], $payload['fromName']);
                                } catch (Throwable $e) {
                                    // ignore mailing errors on publish
                                }
                            }
                        }
                        admin_maybe_enqueue_push_notification('post', $title, $description, $link, $image);
                    }
                    if (!$isDraft && $type === 'Podcast') {
                        $audioUrl = admin_public_asset_url($audio);
                        $imageUrl = admin_public_asset_url($image);
                        if ($audioUrl !== '') {
                            admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description, $image, $audioUrl, $imageUrl);
                        }
                        $settings = get_settings();
                        $mailing = $settings['mailing'] ?? [];
                        if (($mailing['auto_podcast'] ?? 'off') === 'on' && admin_is_mailing_ready($settings) && $audioUrl !== '') {
                            $subscribers = admin_mailing_recipients_for_type('podcast', $settings);
                            if (!empty($subscribers)) {
                                $payload = admin_prepare_mailing_payload('podcast', $settings, $title, $description, $audioUrl, $image);
                                try {
                                    admin_send_mailing_broadcast($payload['subject'], '', '', $subscribers, $payload['mailingConfig'], $payload['bodyBuilder'], $payload['fromName']);
                                } catch (Throwable $e) {
                                    // ignore mailing errors on publish
                                }
                            }
                        }
                    }
                    if ($type === 'Podcast') {
                        admin_regenerate_podcast_feed();
                    }
                    if (!$isDraft) {
                        $indexnowUrls = [];
                        $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                        if (in_array($type, ['Entrada', 'Página'], true) && $slug !== '') {
                            $indexnowUrls[] = admin_public_post_url($slug);
                        } elseif ($type === 'Podcast' && $audioUrl !== '') {
                            $indexnowUrls[] = $audioUrl;
                        }
                        if (!empty($indexnowUrls)) {
                            admin_maybe_send_indexnow($indexnowUrls);
                        }
                    }
                    $redirectTemplate = $isDraft ? 'draft' : ($type === 'Página' ? 'page' : ($type === 'Podcast' ? 'podcast' : ($type === 'Newsletter' ? 'newsletter' : 'single')));
                    header('Location: admin.php?page=edit&template=' . $redirectTemplate . '&created=' . urlencode($targetFilename));
                    exit;
                }
            }
        }
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
        $payload = admin_prepare_newsletter_payload($settings, $title, $contentHtml, $contentText, $image);
        try {
            admin_send_mailing_broadcast($payload['subject'], '', '', $subscribers, $payload['mailingConfig'], $payload['bodyBuilder'], $payload['fromName']);
        } catch (Throwable $e) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudo enviar la newsletter. Revisa la configuración de correo.',
            ];
            header('Location: admin.php?page=edit-post&file=' . urlencode($editFilename));
            exit;
        }
        $filenameBase = nammu_slugify($title !== '' ? $title : 'newsletter');
        if ($filenameBase === '') {
            $filenameBase = 'newsletter';
        }
        $filename = nammu_unique_filename($filenameBase);
        $targetFilename = nammu_normalize_filename($filename . '.md');
        if ($targetFilename !== '') {
            $all_posts = get_all_posts_metadata();
            $max_ordo = 0;
            foreach ($all_posts as $post) {
                if (isset($post['metadata']['Ordo']) && (int)$post['metadata']['Ordo'] > $max_ordo) {
                    $max_ordo = (int)$post['metadata']['Ordo'];
                }
            }
            $ordo = $max_ordo + 1;
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
            @file_put_contents($filepath, $file_content);
        }
        $_SESSION['mailing_feedback'] = [
            'type' => 'success',
            'message' => 'Newsletter enviada correctamente.',
        ];
        header('Location: admin.php?page=edit&template=newsletter&created=' . urlencode($targetFilename));
        exit;
    } elseif (isset($_POST['update']) || isset($_POST['publish_draft_entry']) || isset($_POST['publish_draft_page']) || isset($_POST['publish_draft_podcast']) || isset($_POST['convert_to_draft'])) {
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
        $audioLengthInput = trim($_POST['audio_length'] ?? '');
        $audioDuration = trim($_POST['audio_duration'] ?? '');
        $type = $_POST['type'] ?? null;
        $statusPosted = strtolower(trim($_POST['status'] ?? ''));
        $publishDraftAsEntry = isset($_POST['publish_draft_entry']);
        $publishDraftAsPage = isset($_POST['publish_draft_page']);
        $publishDraftAsPodcast = isset($_POST['publish_draft_podcast']);
        $convertToDraft = isset($_POST['convert_to_draft']);

        // Preserve existing Ordo value on update
        $normalizedFilename = nammu_normalize_filename($filename);
        $previousStatus = 'published';
        if ($normalizedFilename !== '') {
            $existing_post_data = get_post_content($normalizedFilename);
            $ordo = $existing_post_data['metadata']['Ordo'] ?? '';
            $previousStatus = strtolower($existing_post_data['metadata']['Status'] ?? 'published');
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
        $template = $type === 'Página' ? 'page' : ($type === 'Podcast' ? 'podcast' : ($type === 'Newsletter' ? 'newsletter' : 'post'));
        if ($publishDraftAsEntry || $publishDraftAsPage || $publishDraftAsPodcast) {
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
        $audioLength = '';
        if ($type === 'Podcast') {
            if ($audio === '') {
                $error = 'El archivo mp3 del podcast es obligatorio.';
            }
            if ($audio !== '' && !preg_match('/\.mp3$/i', $audio)) {
                $error = 'El audio del podcast debe ser un archivo mp3.';
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
                    if ($shouldAutoShare) {
                        admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description, $image);
                    }
                    if ($shouldAutoSharePodcast) {
                        $audioUrl = admin_public_asset_url($audio);
                        $imageUrl = admin_public_asset_url($image);
                        if ($audioUrl !== '') {
                            admin_maybe_auto_post_to_social_networks($targetFilename, $title, $description, $image, $audioUrl, $imageUrl);
                        }
                        $settings = get_settings();
                        $mailing = $settings['mailing'] ?? [];
                        if (($mailing['auto_podcast'] ?? 'off') === 'on' && admin_is_mailing_ready($settings) && $audioUrl !== '') {
                            $subscribers = admin_mailing_recipients_for_type('podcast', $settings);
                            if (!empty($subscribers)) {
                                $payload = admin_prepare_mailing_payload('podcast', $settings, $title, $description, $audioUrl, $image);
                                try {
                                    admin_send_mailing_broadcast($payload['subject'], '', '', $subscribers, $payload['mailingConfig'], $payload['bodyBuilder'], $payload['fromName']);
                                } catch (Throwable $e) {
                                    // ignore mailing errors on publish
                                }
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
                                try {
                                    admin_send_mailing_broadcast($payload['subject'], '', '', $subscribers, $payload['mailingConfig'], $payload['bodyBuilder'], $payload['fromName']);
                                } catch (Throwable $e) {
                                    // ignore mailing errors on publish
                                }
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
                    if ($template === 'podcast') {
                        admin_regenerate_podcast_feed();
                    }
                    if ($status === 'published') {
                        $shouldIndexnow = $previousStatus === 'published' || $previousStatus === 'draft' || $publishDraftAsEntry || $publishDraftAsPage || $publishDraftAsPodcast || $renameRequested;
                        if ($shouldIndexnow) {
                            $indexnowUrls = [];
                            $slug = pathinfo($targetFilename, PATHINFO_FILENAME);
                            if (in_array($template, ['post', 'page'], true) && $slug !== '') {
                                $indexnowUrls[] = admin_public_post_url($slug);
                            } elseif ($template === 'podcast') {
                                $audioUrl = admin_public_asset_url($audio);
                                if ($audioUrl !== '') {
                                    $indexnowUrls[] = $audioUrl;
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
        $networkLabels = [
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
            'facebook' => 'Facebook',
            'twitter' => 'X',
            'instagram' => 'Instagram',
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
                $template = strtolower(trim((string) ($metadata['Template'] ?? 'post')));
                if (in_array($template, ['single', 'post', 'podcast'], true)) {
                    $slug = pathinfo($filename, PATHINFO_FILENAME);
                    $slug = $slug !== '' ? $slug : $filename;
                    $title = $metadata['Title'] ?? $slug;
                    $description = $metadata['Description'] ?? '';
                    $image = $metadata['Image'] ?? '';
                    $customUrl = '';
                    $imageUrl = '';
                    if ($template === 'podcast') {
                        $audioPath = (string) ($metadata['Audio'] ?? '');
                        $customUrl = admin_public_asset_url($audioPath);
                        $imagePath = (string) ($metadata['Image'] ?? '');
                        $imageUrl = admin_public_asset_url($imagePath);
                        if ($customUrl === '') {
                            $feedback['message'] = 'No se encontró el mp3 del podcast para compartir.';
                            $_SESSION['social_feedback'] = $feedback;
                            header('Location: admin.php?page=edit&template=' . $redirectTemplate);
                            exit;
                        }
                    }
                    $allSocialSettings = admin_cached_social_settings();
                    $networkSettings = $allSocialSettings[$networkKey] ?? [];
                    if (!admin_is_social_network_configured($networkKey, $networkSettings)) {
                        $feedback['message'] = 'Configura correctamente ' . $networkLabels[$networkKey] . ' en la pestaña Difusión antes de enviar.';
                    } else {
                        $sent = false;
                        $customError = null;
                        switch ($networkKey) {
                            case 'telegram':
                                $sent = admin_send_post_to_telegram($slug, $title, $description, $networkSettings, $customUrl, $imageUrl);
                                break;
                            case 'whatsapp':
                                $sent = admin_send_whatsapp_post($slug, $title, $description, $networkSettings, $customUrl, $imageUrl);
                                break;
                            case 'facebook':
                                $sent = admin_send_facebook_post($slug, $title, $description, $networkSettings, $customUrl, $imageUrl);
                                break;
                            case 'twitter':
                                $sent = admin_send_twitter_post($slug, $title, $description, $networkSettings, $customUrl, $imageUrl);
                                break;
                            case 'instagram':
                                if (trim($image) === '') {
                                    $customError = 'Instagram requiere una imagen destacada para enviar la publicación.';
                                    break;
                                }
                                $sent = admin_send_instagram_post($slug, $title, $image, $networkSettings, $description, $customUrl);
                                break;
                        }
                        if ($sent) {
                            $feedback = [
                                'type' => 'success',
                                'message' => 'La publicación se envió correctamente a ' . $networkLabels[$networkKey] . '.',
                            ];
                        } elseif ($customError !== null) {
                            $feedback['message'] = $customError;
                        } else {
                            $feedback['message'] = 'No se pudo enviar la publicación a ' . $networkLabels[$networkKey] . '. Comprueba las credenciales.';
                        }
                    }
                } else {
                    $feedback['message'] = 'Sólo las entradas y podcasts pueden enviarse a redes sociales.';
                }
            }
        }
        $_SESSION['social_feedback'] = $feedback;
        header('Location: admin.php?page=edit&template=' . $redirectTemplate);
        exit;
    } elseif (isset($_POST['send_social_itinerary'])) {
        $networkKey = $_POST['social_network'] ?? '';
        $itinerarySlug = ItineraryRepository::normalizeSlug($_POST['itinerary_slug'] ?? '');
        $networkLabels = [
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp',
            'facebook' => 'Facebook',
            'twitter' => 'X',
            'instagram' => 'Instagram',
        ];
        $feedback = [
            'type' => 'danger',
            'message' => 'No se pudo encontrar el itinerario solicitado.',
        ];
        if (!isset($networkLabels[$networkKey])) {
            $feedback = ['type' => 'danger', 'message' => 'Red social no válida.'];
        } elseif ($itinerarySlug !== '') {
            $itinerary = admin_load_itinerary($itinerarySlug);
            if ($itinerary) {
                $title = $itinerary->getTitle();
                $description = $itinerary->getDescription();
                $image = $itinerary->getImage() ?? '';
                $customUrl = admin_public_itinerary_url($itinerary->getSlug());
                $imageUrl = admin_public_asset_url($image);
                $allSocialSettings = admin_cached_social_settings();
                $networkSettings = $allSocialSettings[$networkKey] ?? [];
                if (!admin_is_social_network_configured($networkKey, $networkSettings)) {
                    $feedback['message'] = 'Configura correctamente ' . $networkLabels[$networkKey] . ' en la pestaña Difusión antes de enviar.';
                } else {
                    $sent = false;
                    $customError = null;
                    switch ($networkKey) {
                        case 'telegram':
                            $sent = admin_send_post_to_telegram($itinerarySlug, $title, $description, $networkSettings, $customUrl, $imageUrl);
                            break;
                        case 'whatsapp':
                            $sent = admin_send_whatsapp_post($itinerarySlug, $title, $description, $networkSettings, $customUrl, $imageUrl);
                            break;
                        case 'facebook':
                            $sent = admin_send_facebook_post($itinerarySlug, $title, $description, $networkSettings, $customUrl, $imageUrl);
                            break;
                        case 'twitter':
                            $sent = admin_send_twitter_post($itinerarySlug, $title, $description, $networkSettings, $customUrl, $imageUrl);
                            break;
                        case 'instagram':
                            if (trim($image) === '') {
                                $customError = 'Instagram requiere una imagen destacada para enviar la publicación.';
                                break;
                            }
                            $sent = admin_send_instagram_post($itinerarySlug, $title, $image, $networkSettings, $description, $customUrl);
                            break;
                    }
                    if ($sent) {
                        $feedback = [
                            'type' => 'success',
                            'message' => 'El itinerario se envió correctamente a ' . $networkLabels[$networkKey] . '.',
                        ];
                    } elseif ($customError !== null) {
                        $feedback['message'] = $customError;
                    } else {
                        $feedback['message'] = 'No se pudo enviar el itinerario a ' . $networkLabels[$networkKey] . '. Comprueba las credenciales.';
                    }
                }
            }
        }
        $_SESSION['itinerary_feedback'] = $feedback;
        header('Location: admin.php?page=itinerarios');
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
                    @mkdir($targetDir, 0755, true);
                }
            }
        }
        if ($orderInput <= 0) {
            $orderInput = admin_next_itinerary_order();
        }
        try {
            $classLabel = admin_normalize_itinerary_class_label($classChoice, $classCustom);
            $usageLogic = admin_normalize_itinerary_usage_logic($usageLogicInput);
            $statusValue = strtolower(trim((string) $statusInput)) === 'draft' ? 'draft' : 'published';
            $saved = admin_itinerary_repository()->saveItinerary($slug, [
                'Title' => $title,
                'Description' => $description,
                'Image' => $image,
                'ItineraryClass' => $classLabel,
                'UsageLogic' => $usageLogic,
                'Status' => $statusValue,
                'Order' => $orderInput,
            ], $content, !empty($itineraryQuizResult['data']['questions']) ? $itineraryQuizResult['data'] : null);
            admin_regenerate_itinerary_feed();
            $shouldAutoMail = $statusValue === 'published' && ($mode === 'new' || $previousStatus === 'draft');
            if ($shouldAutoMail) {
                $settings = get_settings();
                $mailing = $settings['mailing'] ?? [];
                if (($mailing['auto_itineraries'] ?? 'off') === 'on' && admin_is_mailing_ready($settings)) {
                    $subscribers = admin_mailing_recipients_for_type('itineraries', $settings);
                    if (!empty($subscribers)) {
                        $link = admin_public_itinerary_url($saved->getSlug());
                        $payload = admin_prepare_mailing_payload('itinerario', $settings, $title, $description, $link, $image);
                        try {
                            admin_send_mailing_broadcast($payload['subject'], '', '', $subscribers, $payload['mailingConfig'], $payload['bodyBuilder'], $payload['fromName']);
                        } catch (Throwable $e) {
                            // ignore mailing errors on publish
                        }
                    }
                }
                $link = admin_public_itinerary_url($saved->getSlug());
                admin_maybe_enqueue_push_notification('itinerary', $title, $description, $link, $image);
                $imageUrl = admin_public_asset_url($image);
                admin_maybe_auto_post_to_social_networks($saved->getSlug(), $title, $description, $image, $link, $imageUrl);
                admin_maybe_send_indexnow([$link]);
            }
            $_SESSION['itinerary_feedback'] = ['type' => 'success', 'message' => 'Itinerario guardado correctamente.'];
            header('Location: admin.php?page=itinerario&itinerary=' . urlencode($saved->getSlug()));
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
            if ($itinerary->isPublished()) {
                $topicUrl = admin_public_itinerary_url($itinerarySlug) . '/' . rawurlencode($topicSlug);
                admin_maybe_send_indexnow([$topicUrl]);
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
            admin_regenerate_itinerary_feed();
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
        $templateTarget = in_array($templateTarget, ['single', 'page', 'draft', 'newsletter', 'podcast'], true) ? $templateTarget : 'single';
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
            admin_regenerate_itinerary_feed();
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
                    $savedAssets[] = [
                        'name' => $targetName,
                        'src' => 'assets/' . $targetName,
                    ];
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

            file_put_contents($target_path, $image_data);
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

                file_put_contents(CONTENT_DIR . '/' . $sorted_post['filename'], $file_content);
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
    } elseif (isset($_POST['save_settings'])) {
        $sort_order = $_POST['sort_order'] ?? 'date';
        $sort_order = $sort_order === 'alpha' ? 'alpha' : 'date';
        $site_author = trim($_POST['site_author'] ?? '');
        $site_name = trim($_POST['site_name'] ?? '');
        $site_url = trim($_POST['site_url'] ?? '');
        $site_lang = trim($_POST['site_lang'] ?? 'es');
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
        $nisaba_url = trim($_POST['nisaba_url'] ?? '');
        try {
            $config = load_config_file();
            if ($nisaba_url !== '') {
                $config['nisaba'] = [
                    'url' => $nisaba_url,
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
    } elseif (isset($_POST['save_twitter_media'])) {
        $twitter_api_key = trim($_POST['twitter_api_key'] ?? '');
        $twitter_api_secret = trim($_POST['twitter_api_secret'] ?? '');
        $twitter_access_token = trim($_POST['twitter_access_token'] ?? '');
        $twitter_access_secret = trim($_POST['twitter_access_secret'] ?? '');
        try {
            $config = load_config_file();
            $twitter = $config['twitter'] ?? [];
            if (!is_array($twitter)) {
                $twitter = [];
            }
            $twitter['api_key'] = $twitter_api_key;
            $twitter['api_secret'] = $twitter_api_secret;
            $twitter['access_token'] = $twitter_access_token;
            $twitter['access_secret'] = $twitter_access_secret;
            $hasCoreTwitter = isset($twitter['token'], $twitter['channel']) || ($twitter['auto_post'] ?? 'off') === 'on';
            $hasMediaTwitter = ($twitter_api_key !== '' || $twitter_api_secret !== '' || $twitter_access_token !== '' || $twitter_access_secret !== '');
            if ($hasCoreTwitter || $hasMediaTwitter) {
                $config['twitter'] = $twitter;
            } else {
                unset($config['twitter']);
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $error = "Error guardando Twitter / X: " . $e->getMessage();
        }
        header('Location: admin.php?page=anuncios#twitter-media');
        exit;
    } elseif (isset($_POST['save_social'])) {
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
        $instagram_token = trim($_POST['instagram_token'] ?? '');
        $instagram_channel = trim($_POST['instagram_channel'] ?? '');
        $instagram_auto = isset($_POST['instagram_auto']) ? 'on' : 'off';
        $podcast_spotify = trim($_POST['podcast_spotify'] ?? '');
        $podcast_ivoox = trim($_POST['podcast_ivoox'] ?? '');
        $podcast_apple = trim($_POST['podcast_apple'] ?? '');
        $podcast_google = trim($_POST['podcast_google'] ?? '');
        $podcast_youtube_music = trim($_POST['podcast_youtube_music'] ?? '');

        try {
            $config = load_config_file();

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
            if ($instagram_token !== '' || $instagram_channel !== '' || $instagram_auto === 'on') {
                $config['instagram'] = [
                    'token' => $instagram_token,
                    'channel' => $instagram_channel,
                    'auto_post' => $instagram_auto,
                ];
            } else {
                unset($config['instagram']);
            }
            $podcastServices = [
                'spotify' => $podcast_spotify,
                'ivoox' => $podcast_ivoox,
                'apple' => $podcast_apple,
                'google' => $podcast_google,
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
        } catch (Throwable $e) {
            $error = "Error guardando la configuración: " . $e->getMessage();
        }

        header('Location: admin.php?page=configuracion');
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
        $email = admin_normalize_email($_POST['subscriber_email'] ?? '');
        $redirect = 'admin.php?page=lista-correo#suscriptores';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Introduce un correo válido para suscribir.',
            ];
            header('Location: ' . $redirect);
            exit;
        }
        try {
            $subscribers = admin_load_mailing_subscriber_entries();
            $exists = false;
            foreach ($subscribers as $subscriber) {
                if (admin_normalize_email((string) ($subscriber['email'] ?? '')) === $email) {
                    $exists = true;
                    break;
                }
            }
            if ($exists) {
                $_SESSION['mailing_feedback'] = [
                    'type' => 'info',
                    'message' => 'Esa dirección ya está en la lista.',
                ];
            } else {
                $subscribers[] = [
                    'email' => $email,
                    'prefs' => admin_mailing_default_prefs(),
                ];
                admin_save_mailing_subscriber_entries($subscribers);
                $_SESSION['mailing_feedback'] = [
                    'type' => 'success',
                    'message' => 'Suscriptor añadido correctamente.',
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
        $payload = admin_prepare_mailing_payload($template, $settings, $title, $description, $link, $imagePath);
        try {
            $result = admin_send_mailing_broadcast($payload['subject'], '', '', $subscribers, $payload['mailingConfig'], $payload['bodyBuilder'], $payload['fromName']);
            $message = 'Aviso enviado. OK: ' . $result['sent'] . ' / Fallos: ' . $result['failed'];
            if (!empty($result['error'])) {
                $message .= ' (' . $result['error'] . ')';
            }
            $type = $result['failed'] > 0 ? 'warning' : 'success';
            $_SESSION['mailing_feedback'] = [
                'type' => $type,
                'message' => $message,
            ];
        } catch (Throwable $e) {
            $_SESSION['mailing_feedback'] = [
                'type' => 'danger',
                'message' => 'Error enviando a la lista: ' . $e->getMessage(),
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
} else {
    $page = $user_exists ? 'login' : 'register';
}
$isItineraryAdminPage = in_array($page, ['itinerarios', 'itinerario', 'itinerario-tema'], true);

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
    'twitter' => '',
    'facebook_app_id' => '',
];
$socialSettings = array_merge($socialDefaults, $settings['social'] ?? []);
$socialDefaultDescription = $socialSettings['default_description'] ?? '';
$socialHomeImage = $socialSettings['home_image'] ?? '';
$socialTwitter = $socialSettings['twitter'] ?? '';
$socialFacebookAppId = $socialSettings['facebook_app_id'] ?? '';
$nisabaConfig = $settings['nisaba'] ?? [];
$nisabaUrl = $nisabaConfig['url'] ?? '';
$nisabaEnabled = $nisabaUrl !== '' && function_exists('admin_nisaba_fetch_notes');
$nisabaFeedUrl = $nisabaEnabled ? admin_nisaba_feed_url($nisabaUrl) : '';
$nisabaPages = ['publish', 'edit', 'edit-post', 'itinerario', 'itinerario-tema'];
$nisabaModalEnabled = $nisabaEnabled && in_array($page, $nisabaPages, true);
$nisabaNotes = $nisabaModalEnabled ? admin_nisaba_fetch_notes($nisabaUrl, 7) : [];

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
        .nisaba-icon {
            width: 16px;
            height: 16px;
            display: block;
        }
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
        select.form-control {
            height: auto;
            min-height: calc(1.5em + .75rem + 2px);
            line-height: 1.4;
            padding-top: 0.45rem;
            padding-bottom: 0.45rem;
        }
        select.form-control option {
            line-height: 1.4;
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

                                <li class="nav-item <?= $page === 'edit' ? 'active' : '' ?>">
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
                                    <button type="submit" name="logout" class="btn btn-outline-danger my-2 my-sm-0">Cerrar sesión</button>
                                    <a href="index.php" class="btn btn-outline-secondary btn-sm mt-2">Ir al blog</a>
                                </div>
                            </form>

                        </div>

                    </nav>

        

                    <div class="tab-content">

                        <?php if ($page === 'dashboard'): ?>

                            <?php include __DIR__ . '/core/admin-page-dashboard.php'; ?>

<?php elseif ($page === 'publish'): ?>

                            <?php include __DIR__ . '/core/admin-page-publish.php'; ?>

<?php elseif ($page === 'edit' || $page === 'edit-post'): ?>

    <?php include __DIR__ . '/core/admin-page-edit.php'; ?>

<?php elseif ($page === 'resources'): ?>

    <?php include __DIR__ . '/core/admin-page-resources.php'; ?>
    
<?php elseif ($page === 'template'): ?>

    <?php include __DIR__ . '/core/admin-page-template.php'; ?>

<?php elseif ($page === 'itinerarios'): ?>

    <?php include __DIR__ . '/core/admin-page-itinerarios.php'; ?>


<?php elseif ($page === 'itinerario'): ?>

    <?php include __DIR__ . '/core/admin-page-itinerario.php'; ?>

<?php elseif ($page === 'itinerario-tema'): ?>

    <?php include __DIR__ . '/core/admin-page-itinerario-tema.php'; ?>

<?php elseif ($page === 'lista-correo'): ?>

    <?php include __DIR__ . '/core/admin-page-lista-correo.php'; ?>

<?php elseif ($page === 'correo-postal'): ?>

    <?php include __DIR__ . '/core/admin-page-correo-postal.php'; ?>

<?php elseif ($page === 'anuncios'): ?>

    <?php include __DIR__ . '/core/admin-page-anuncios.php'; ?>

<?php elseif ($page === 'configuracion'): ?>

    <?php include __DIR__ . '/core/admin-page-configuracion.php'; ?>

<?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>

        
        
        </div>

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
                                <p class="text-muted mb-3">Selecciona las notas de los últimos 7 días que quieres insertar.</p>
                                <?php foreach ($nisabaNotes as $index => $note): ?>
                                    <?php
                                    $noteId = 'nisaba-note-' . $index;
                                    $noteTitle = $note['title'] ?? '';
                                    $noteLink = $note['link'] ?? '';
                                    $noteContent = $note['content'] ?? '';
                                    $noteDisplay = $note['display_content'] ?? '';
                                    $noteDateLabel = isset($note['timestamp']) ? date('d/m/y', (int) $note['timestamp']) : '';
                                    ?>
                                    <div class="border rounded p-3 mb-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox"
                                                   class="custom-control-input nisaba-note-toggle"
                                                   id="<?= htmlspecialchars($noteId, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-nisaba-item="1"
                                                   data-note-title="<?= htmlspecialchars($noteTitle, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-note-link="<?= htmlspecialchars($noteLink, ENT_QUOTES, 'UTF-8') ?>"
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

                        <h5 class="modal-title" id="imageModalLabel">Seleccionar Imagen</h5>

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

                        <div class="row image-gallery">

                            <?php

                            $media_data = get_media_items(1, 1000); // Load all media for now
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
                                        <img src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover; cursor: pointer;" data-media-name="<?= htmlspecialchars($media_name, ENT_QUOTES, 'UTF-8') ?>" data-media-type="image" data-media-src="<?= htmlspecialchars($media_src, ENT_QUOTES, 'UTF-8') ?>" data-media-mime="<?= htmlspecialchars($media_mime, ENT_QUOTES, 'UTF-8') ?>" data-media-tags="<?= htmlspecialchars($media_tags_text, ENT_QUOTES, 'UTF-8') ?>">
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
                            <input type="hidden" name="redirect_search" id="tagsModalRedirectSearch" value="<?= htmlspecialchars($resourceSearchTerm, ENT_QUOTES, 'UTF-8') ?>">
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
            var subscriptionModeOptions = form.querySelectorAll('.home-card-style-option[data-subscription-mode-option]');
            var subscriptionPositionOptions = form.querySelectorAll('.home-card-style-option[data-subscription-position-option]');
            var subscriptionFloatingOptions = form.querySelectorAll('.home-card-style-option[data-subscription-floating-option]');
            var footerLogoOptions = form.querySelectorAll('.home-card-style-option[data-footer-logo-option]');
            var searchPositionContainer = form.querySelector('[data-search-position]');
            var subscriptionPositionContainer = form.querySelector('[data-subscription-position]');
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

            function refreshSubscriptionModeSelection() {
                var activeMode = 'none';
                subscriptionModeOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    var checked = radio && radio.checked;
                    if (checked && radio) {
                        activeMode = radio.value;
                    }
                    option.classList.toggle('active', checked);
                });
                if (subscriptionPositionContainer) {
                    subscriptionPositionContainer.style.display = activeMode === 'none' ? 'none' : '';
                }
            }
            function refreshSubscriptionPositionSelection() {
                subscriptionPositionOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            function refreshSubscriptionFloatingSelection() {
                subscriptionFloatingOptions.forEach(function(option) {
                    var radio = option.querySelector('input[type="radio"]');
                    option.classList.toggle('active', radio && radio.checked);
                });
            }
            subscriptionModeOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', function() {
                        refreshSubscriptionModeSelection();
                        refreshSubscriptionPositionSelection();
                    });
                }
            });
            subscriptionPositionOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshSubscriptionPositionSelection);
                }
            });
            subscriptionFloatingOptions.forEach(function(option) {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.addEventListener('change', refreshSubscriptionFloatingSelection);
                }
            });
            refreshSubscriptionModeSelection();
            refreshSubscriptionPositionSelection();
            refreshSubscriptionFloatingSelection();

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

            var firstRowToggle = document.getElementById('home_first_row_enabled');
            var firstRowOptions = document.querySelector('[data-first-row-options]');
            var firstRowFill = document.querySelector('[data-first-row-fill]');
            var firstRowAlign = document.querySelector('[data-first-row-align]');
            var firstRowStyle = document.querySelector('[data-first-row-style]');
            function toggleFirstRowOptions() {
                var show = firstRowToggle && firstRowToggle.checked;
                if (firstRowOptions) {
                    firstRowOptions.style.display = show ? '' : 'none';
                }
                if (firstRowFill) {
                    firstRowFill.style.display = show ? '' : 'none';
                }
                if (firstRowAlign) {
                    var colsRadio = firstRowOptions ? firstRowOptions.querySelector('input[name="home_first_row_columns"]:checked') : null;
                    var showAlign = show && colsRadio && parseInt(colsRadio.value, 10) === 1;
                    firstRowAlign.style.display = showAlign ? '' : 'none';
                }
                if (firstRowStyle) {
                    var colsRadioStyle = firstRowOptions ? firstRowOptions.querySelector('input[name="home_first_row_columns"]:checked') : null;
                    var showStyle = show && colsRadioStyle && parseInt(colsRadioStyle.value, 10) === 1;
                    firstRowStyle.style.display = showStyle ? '' : 'none';
                }
                if (!show && firstRowOptions) {
                    var mainChecked = form.querySelector('input[name="home_columns"]:checked');
                    var firstRowRadios = firstRowOptions.querySelectorAll('input[name="home_first_row_columns"]');
                    if (mainChecked) {
                        firstRowRadios.forEach(function(radio) {
                            var isActive = radio.value === mainChecked.value;
                            radio.checked = isActive;
                            var label = radio.closest('.home-layout-option');
                            if (label) {
                                label.classList.toggle('active', isActive);
                            }
                        });
                    }
                }
            }
            if (firstRowToggle) {
                firstRowToggle.addEventListener('change', toggleFirstRowOptions);
            }
            if (firstRowOptions) {
                var firstRowRadios = firstRowOptions.querySelectorAll('input[name="home_first_row_columns"]');
                firstRowRadios.forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        firstRowOptions.querySelectorAll('.home-layout-option').forEach(function(opt) {
                            var r = opt.querySelector('input[type="radio"]');
                            opt.classList.toggle('active', r && r.checked);
                        });
                        toggleFirstRowOptions();
                    });
                });
            }
            toggleFirstRowOptions();

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

            var firstRowToggle = form.querySelector('#home_first_row_enabled');
            var firstRowOptions = form.querySelector('[data-first-row-options]');
            var firstRowFill = form.querySelector('[data-first-row-fill]');
            function toggleFirstRowOptions() {
                var show = firstRowToggle && firstRowToggle.checked;
                if (firstRowOptions) {
                    firstRowOptions.style.display = show ? '' : 'none';
                }
                if (firstRowFill) {
                    firstRowFill.style.display = show ? '' : 'none';
                }
                if (!show && firstRowOptions) {
                    var mainChecked = form.querySelector('input[name=\"home_columns\"]:checked');
                    var firstRowRadios = firstRowOptions.querySelectorAll('input[name=\"home_first_row_columns\"]');
                    if (mainChecked) {
                        firstRowRadios.forEach(function(radio) {
                            var isActive = radio.value === mainChecked.value;
                            radio.checked = isActive;
                            var label = radio.closest('.home-layout-option');
                            if (label) {
                                label.classList.toggle('active', isActive);
                            }
                        });
                    }
                }
            }
            if (firstRowToggle) {
                firstRowToggle.addEventListener('change', toggleFirstRowOptions);
            }
            if (firstRowOptions) {
                var firstRowRadios = firstRowOptions.querySelectorAll('input[name=\"home_first_row_columns\"]');
                firstRowRadios.forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        firstRowOptions.querySelectorAll('.home-layout-option').forEach(function(opt) {
                            var r = opt.querySelector('input[type=\"radio\"]');
                            opt.classList.toggle('active', r && r.checked);
                        });
                    });
                });
            }
            toggleFirstRowOptions();
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
                    case 'table':
                        insertTable(textarea);
                        break;
                    case 'callout':
                        openCalloutModal(textarea);
                        break;
                    case 'nisaba':
                        openNisabaModal(textarea);
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

            function insertTable(textarea) {
                var rowsInput = window.prompt('Número de filas (sin contar cabecera):', '3');
                var colsInput = window.prompt('Número de columnas:', '3');
                var rows = parseInt(rowsInput, 10);
                var cols = parseInt(colsInput, 10);
                if (!rows || rows < 1 || !cols || cols < 1) {
                    return;
                }
                var headerCells = [];
                for (var c = 1; c <= cols; c++) {
                    headerCells.push('Columna ' + c);
                }
                var header = '| ' + headerCells.join(' | ') + ' |\n';
                var separator = '| ' + headerCells.map(function() { return '---'; }).join(' | ') + ' |\n';
                var body = '';
                for (var r = 1; r <= rows; r++) {
                    var rowCells = [];
                    for (var cc = 1; cc <= cols; cc++) {
                        rowCells.push('Dato ' + r + '.' + cc);
                    }
                    body += '| ' + rowCells.join(' | ') + ' |\n';
                }
                var tableMarkdown = '\n' + header + separator + body + '\n';
                replaceSelection(textarea, tableMarkdown, tableMarkdown.length, tableMarkdown.length);
            }

            function openCalloutModal(textarea) {
                ensureCalloutModal();
                var target = textarea;
                if (!target || target.tagName !== 'TEXTAREA') {
                    if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
                        target = document.activeElement;
                    } else if (lastFocusedTextarea && lastFocusedTextarea.tagName === 'TEXTAREA') {
                        target = lastFocusedTextarea;
                    } else {
                        target = document.querySelector('[data-markdown-editor]') || document.querySelector('textarea');
                    }
                }
                if (target && target.id === 'calloutBody') {
                    target = null;
                }
                calloutTarget = target || fallbackTextarea();
                if (calloutTitleInput.length) {
                    calloutTitleInput.val(calloutTitleInput.val() || 'Aviso');
                }
                if (calloutBodyInput.length) {
                    calloutBodyInput.val(calloutBodyInput.val() || '');
                }
                var showModal = function() {
                    if (calloutModal.length && typeof calloutModal.modal === 'function') {
                        calloutModal.modal('show');
                    } else if (calloutModal.length) {
                        calloutModal.addClass('show').css('display', 'block').attr('aria-hidden', 'false');
                    }
                };
                if (calloutModal.length) {
                    showModal();
                } else {
                    // Fallback a prompts si el modal no está disponible
                    var title = window.prompt('Título del aviso/caja', 'Aviso') || 'Aviso';
                    var bodyRaw = window.prompt('Contenido del aviso (texto o enlaces)', '') || '';
                    var lines = bodyRaw.split(/\n+/).map(function(line) { return line.trim(); }).filter(function(line) { return line !== ''; });
                    if (!lines.length) {
                        lines = ['Contenido del aviso.'];
                    }
                    var bodyHtml = lines.map(function(line) { return '  <p>' + line + '</p>'; }).join('\n');
                    var callout = '\n\n<div class="callout-box">\n  <h4>' + title + '</h4>\n' + bodyHtml + '\n</div>\n\n';
                    replaceSelection(calloutTarget, callout, callout.length, callout.length);
                    calloutTarget = null;
                    calloutTargetSelector = '';
                }
            }

            var nisabaTarget = null;
            function openNisabaModal(textarea) {
                var modal = document.getElementById('nisabaModal');
                if (!modal) {
                    return;
                }
                var target = textarea;
                if (!target || target.tagName !== 'TEXTAREA') {
                    if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
                        target = document.activeElement;
                    } else if (lastFocusedTextarea && lastFocusedTextarea.tagName === 'TEXTAREA') {
                        target = lastFocusedTextarea;
                    } else {
                        target = fallbackTextarea();
                    }
                }
                if (target && target.id === 'calloutBody') {
                    target = null;
                }
                nisabaTarget = target || fallbackTextarea();
                if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                    window.jQuery(modal).modal('show');
                } else {
                    modal.classList.add('show');
                    modal.style.display = 'block';
                    modal.removeAttribute('aria-hidden');
                }
            }

            function decodeBase64Utf8(value) {
                if (!value) {
                    return '';
                }
                try {
                    return decodeURIComponent(escape(window.atob(value)));
                } catch (err) {
                    try {
                        return window.atob(value);
                    } catch (fallbackErr) {
                        return '';
                    }
                }
            }

            function escapeHtml(value) {
                return (value || '').replace(/[&<>"]/g, function(char) {
                    switch (char) {
                        case '&': return '&amp;';
                        case '<': return '&lt;';
                        case '>': return '&gt;';
                        case '"': return '&quot;';
                        default: return char;
                    }
                });
            }

            var nisabaInsertButton = document.getElementById('nisabaInsert');
            if (nisabaInsertButton) {
                nisabaInsertButton.addEventListener('click', function() {
                    var modal = document.getElementById('nisabaModal');
                    var target = nisabaTarget || fallbackTextarea();
                    if (!modal || !target) {
                        return;
                    }
                    var selections = modal.querySelectorAll('[data-nisaba-item]');
                    var blocks = [];
                    selections.forEach(function(input) {
                        if (!input.checked) {
                            return;
                        }
                        var title = input.getAttribute('data-note-title') || 'Nota de Nisaba';
                        var link = input.getAttribute('data-note-link') || '';
                        var contentEncoded = input.getAttribute('data-note-content') || '';
                        var content = decodeBase64Utf8(contentEncoded);
                        var safeTitle = escapeHtml(title);
                        var sourceLine = '';
                        if (link) {
                            var safeLink = escapeHtml(link);
                            sourceLine = '\n<p><strong>Fuente:</strong> <a href="' + safeLink + '" target="_blank" rel="noopener">' + safeLink + '</a></p>';
                        }
                        blocks.push('\n\n<h2>' + safeTitle + '</h2>\n' + content + sourceLine + '\n');
                    });
                    if (!blocks.length) {
                        return;
                    }
                    var insertText = blocks.join('\n');
                    replaceSelection(target, insertText, insertText.length, insertText.length);
                    if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
                        window.jQuery(modal).modal('hide');
                    } else {
                        modal.classList.remove('show');
                        modal.style.display = 'none';
                        modal.setAttribute('aria-hidden', 'true');
                    }
                });
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
        window.nammuAssetApply = <?= json_encode($assetApply, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        $(document).ready(function() {

            var imageTargetMode = '';
            var imageTargetInput = '';
            var imageTargetPrefix = '';
            var imageTargetEditor = '';
            var imageTargetAccept = '';
            var lastImageTrigger = null;
            var imageTargetSelection = null;
            var imageTargetTextarea = null;
            var skipImageModalSelectionCapture = false;
            var modalSearchInput = $('#modal-image-search');
            var tagsInput = $('#image_tags');
            var tagsTargetInput = $('#image-tags-target');
            var tagsModal = $('#tagsModal');
            var tagsModalInput = $('#tagsModalInput');
            var tagsModalTarget = $('#tagsModalTarget');
            var tagsModalRedirect = $('#tagsModalRedirect');
            var insertActions = $('#image-insert-actions');
            var insertActionGroups = insertActions.find('[data-insert-group]');
            var pendingInsert = null;
            function getQueryParam(name) {
                var params = new URLSearchParams(window.location.search);
                return params.get(name);
            }

            var isResourcesPage = window.location.search.indexOf('page=resources') !== -1 || window.location.href.indexOf('admin.php') !== -1 && !window.location.search;
            var resourceScrollKey = 'nammuResourceScroll';
            var resourcesPageFromUrl = parseInt(getQueryParam('p'), 10);
            resourcesPageFromUrl = isNaN(resourcesPageFromUrl) ? null : resourcesPageFromUrl;
            var currentResourcesPage = resourcesPageFromUrl || (parseInt($('#resource-gallery').data('resources-page'), 10) || 1);
            var resourcesSearchFromUrl = getQueryParam('search') || '';
            var currentResourcesSearch = resourcesSearchFromUrl !== '' ? resourcesSearchFromUrl : (($('#resource-gallery').data('resources-search') || '').toString());
            var calloutModal = $('#calloutModal');
            var calloutTitleInput = $('#calloutTitle');
            var calloutBodyInput = $('#calloutBody');
            var calloutInsertBtn = $('#calloutInsert');
            var calloutTarget = null;
            var calloutTargetSelector = '';
            var lastFocusedTextarea = null;
            var calloutModalEl = document.getElementById('calloutModal');
            var autosavePayloadInput = document.getElementById('imageUploadAutosavePayload');
            var imageUploadForm = document.querySelector('#imageModal form');
            var uploadTargetTypeInput = document.getElementById('imageUploadTargetType');
            var uploadTargetInputInput = document.getElementById('imageUploadTargetInput');
            var uploadTargetEditorInput = document.getElementById('imageUploadTargetEditor');
            var uploadTargetPrefixInput = document.getElementById('imageUploadTargetPrefix');
            var uploadTargetSelectionStart = document.getElementById('imageUploadSelectionStart');
            var uploadTargetSelectionEnd = document.getElementById('imageUploadSelectionEnd');
            var uploadTargetSelectionScroll = document.getElementById('imageUploadSelectionScroll');
            var assetApply = window.nammuAssetApply || null;

            function collectEditorForm() {
                if (lastImageTrigger) {
                    var triggerForm = $(lastImageTrigger).closest('form');
                    if (triggerForm.length) {
                        return { context: detectContext(triggerForm), form: triggerForm };
                    }
                }
                var editForm = $('#content_edit').closest('form');
                if (editForm.length) {
                    return { context: 'edit', form: editForm };
                }
                var publishForm = $('#content_publish').closest('form');
                if (publishForm.length) {
                    return { context: 'publish', form: publishForm };
                }
                var itineraryForm = $('#itinerary-form form');
                if (itineraryForm.length) {
                    return { context: 'itinerary', form: itineraryForm };
                }
                var topicForm = $('#topic-form form');
                if (topicForm.length) {
                    return { context: 'topic', form: topicForm };
                }
                return null;
            }

            function detectContext($form) {
                if ($form.find('#content_edit').length) {
                    return 'edit';
                }
                if ($form.find('#content_publish').length) {
                    return 'publish';
                }
                if ($form.find('#itinerary_content').length) {
                    return 'itinerary';
                }
                if ($form.find('#topic_content').length) {
                    return 'topic';
                }
                return 'generic';
            }

            function buildAutosavePayload() {
                var ctx = collectEditorForm();
                if (!ctx) {
                    return '';
                }
                var form = ctx.form;
                var fields = {};
                if (ctx.context === 'itinerary') {
                    fields = {
                        itinerary_title: form.find('[name="itinerary_title"]').val() || '',
                        itinerary_description: form.find('[name="itinerary_description"]').val() || '',
                        itinerary_content: form.find('[name="itinerary_content"]').val() || '',
                        itinerary_image: form.find('[name="itinerary_image"]').val() || '',
                        itinerary_status: form.find('[name="itinerary_status"]').val() || '',
                        itinerary_slug: form.find('[name="itinerary_slug"]').val() || '',
                        itinerary_class: form.find('[name="itinerary_class"]').val() || '',
                        itinerary_class_custom: form.find('[name="itinerary_class_custom"]').val() || '',
                        itinerary_usage_logic: form.find('[name="itinerary_usage_logic"]:checked').val() || '',
                        itinerary_quiz_payload: form.find('[name="itinerary_quiz_payload"]').val() || ''
                    };
                } else if (ctx.context === 'topic') {
                    fields = {
                        topic_title: form.find('[name="topic_title"]').val() || '',
                        topic_description: form.find('[name="topic_description"]').val() || '',
                        topic_content: form.find('[name="topic_content"]').val() || '',
                        topic_image: form.find('[name="topic_image"]').val() || '',
                        topic_slug: form.find('[name="topic_slug"]').val() || '',
                        topic_number: form.find('[name="topic_number"]').val() || '',
                        topic_quiz_payload: form.find('[name="topic_quiz_payload"]').val() || '',
                        topic_itinerary_slug: form.find('[name="topic_itinerary_slug"]').val() || ''
                    };
                } else {
                    fields = {
                        title: form.find('[name="title"]').val() || '',
                        category: form.find('[name="category"]').val() || '',
                        date: form.find('[name="date"]').val() || '',
                        image: form.find('[name="image"]').val() || '',
                        description: form.find('[name="description"]').val() || '',
                        content: form.find('[name="content"]').val() || '',
                        type: form.find('[name="type"]').val() || '',
                        status: form.find('[name="status"]').val() || '',
                        filename: form.find('[name="filename"]').val() || '',
                        new_filename: form.find('[name="new_filename"]').val() || ''
                    };
                }
                var hasContent = Object.keys(fields).some(function(key) {
                    var value = fields[key];
                    return value && value.toString().trim() !== '';
                });
                if (!hasContent) {
                    return '';
                }
                return JSON.stringify({
                    context: ctx.context,
                    fields: fields
                });
            }

            if (imageUploadForm) {
                imageUploadForm.addEventListener('submit', function() {
                    if (!autosavePayloadInput) {
                        return;
                    }
                    autosavePayloadInput.value = buildAutosavePayload();
                    if (uploadTargetTypeInput) uploadTargetTypeInput.value = imageTargetMode || '';
                    if (uploadTargetInputInput) uploadTargetInputInput.value = imageTargetInput || '';
                    if (uploadTargetEditorInput) uploadTargetEditorInput.value = imageTargetEditor || '';
                    if (uploadTargetPrefixInput) uploadTargetPrefixInput.value = imageTargetPrefix || '';
                    if (uploadTargetSelectionStart) {
                        uploadTargetSelectionStart.value = imageTargetSelection ? String(imageTargetSelection.start || 0) : '';
                    }
                    if (uploadTargetSelectionEnd) {
                        uploadTargetSelectionEnd.value = imageTargetSelection ? String(imageTargetSelection.end || 0) : '';
                    }
                    if (uploadTargetSelectionScroll) {
                        uploadTargetSelectionScroll.value = imageTargetSelection ? String(imageTargetSelection.scrollTop || 0) : '';
                    }
                });
            }

            $(document).on('focusin', 'textarea', function() {
                lastFocusedTextarea = this;
            });

            function fallbackTextarea() {
                var active = document.activeElement;
                if (active && active.tagName === 'TEXTAREA' && active.id !== 'calloutBody') {
                    return active;
                }
                if (lastFocusedTextarea && lastFocusedTextarea.tagName === 'TEXTAREA' && lastFocusedTextarea.id !== 'calloutBody') {
                    return lastFocusedTextarea;
                }
                var editorTextarea = document.querySelector('[data-markdown-editor]');
                if (editorTextarea && editorTextarea.tagName === 'TEXTAREA') {
                    return editorTextarea;
                }
                var anyTextarea = document.querySelector('textarea');
                return anyTextarea || null;
            }

            function resolveImageTargetTextarea() {
                var target = null;
                if (imageTargetEditor) {
                    try {
                        target = document.querySelector(imageTargetEditor);
                    } catch (selectorError) {
                        target = null;
                    }
                }
                if (!target && document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
                    target = document.activeElement;
                }
                if (!target && lastFocusedTextarea && lastFocusedTextarea.tagName === 'TEXTAREA') {
                    target = lastFocusedTextarea;
                }
                if (!target) {
                    target = fallbackTextarea();
                }
                if (target && target.id === 'calloutBody') {
                    return null;
                }
                return target;
            }

            $(document).on('click', '[data-md-action="callout"]', function(evt) {
                // Garantiza que el modal se abra aunque falle el toolbar handler
                var toolbar = this.closest('[data-markdown-toolbar]');
                var target = null;
                if (toolbar) {
                    var selector = toolbar.getAttribute('data-target');
                    if (selector) {
                        try {
                            target = document.querySelector(selector);
                        } catch (e) {
                            target = null;
                        }
                    }
                    if (!target) {
                        var sib = toolbar.nextElementSibling;
                        if (sib && sib.tagName === 'TEXTAREA') {
                            target = sib;
                        }
                    }
                }
                openCalloutModal(target);
            });
            function ensureCalloutModal() {
                calloutModal = $('#calloutModal');
                calloutTitleInput = $('#calloutTitle');
                calloutBodyInput = $('#calloutBody');
                calloutInsertBtn = $('#calloutInsert');
                if (!calloutModal.length) {
                    var html = [
                        '<div class="modal fade" id="calloutModal" tabindex="-1" role="dialog" aria-labelledby="calloutModalLabel" aria-hidden="true">',
                        '  <div class="modal-dialog" role="document">',
                        '    <div class="modal-content">',
                        '      <div class="modal-header">',
                        '        <h5 class="modal-title" id="calloutModalLabel">Caja destacada</h5>',
                        '        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">',
                        '          <span aria-hidden="true">&times;</span>',
                        '        </button>',
                        '      </div>',
                        '      <div class="modal-body">',
                        '        <div class="form-group">',
                        '          <label for="calloutTitle">Título</label>',
                        '          <input type="text" id="calloutTitle" class="form-control" value="Aviso">',
                        '        </div>',
                        '        <div class="form-group">',
                        '          <label for="calloutBody">Contenido del aviso</label>',
                        '          <textarea id="calloutBody" class="form-control" rows="4" placeholder="Añade aquí bibliografía, enlaces o notas."></textarea>',
                        '        </div>',
                        '      </div>',
                        '      <div class="modal-footer">',
                        '        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>',
                        '        <button type="button" class="btn btn-primary" id="calloutInsert">Insertar</button>',
                        '      </div>',
                        '    </div>',
                        '  </div>',
                        '</div>'
                    ].join('');
                    $('body').append(html);
                    calloutModal = $('#calloutModal');
                    calloutTitleInput = $('#calloutTitle');
                    calloutBodyInput = $('#calloutBody');
                    calloutInsertBtn = $('#calloutInsert');
                }
            }

        

            $('#imageModal').on('show.bs.modal', function (event) {

                var button = $(event.relatedTarget);
                lastImageTrigger = button && button.length ? button[0] : null;

                if (button && button.length) {
                    imageTargetMode = button.data('target-type') || '';
                    imageTargetInput = button.data('target-input') || '';
                    imageTargetPrefix = button.data('target-prefix') || '';
                    imageTargetEditor = button.data('target-editor') || '';
                    imageTargetAccept = button.data('target-accept') || '';
                }
                if (!skipImageModalSelectionCapture) {
                    imageTargetTextarea = resolveImageTargetTextarea();
                    if (imageTargetTextarea && typeof imageTargetTextarea.selectionStart === 'number') {
                        imageTargetSelection = {
                            start: imageTargetTextarea.selectionStart,
                            end: typeof imageTargetTextarea.selectionEnd === 'number' ? imageTargetTextarea.selectionEnd : imageTargetTextarea.selectionStart,
                            scrollTop: imageTargetTextarea.scrollTop
                        };
                    } else {
                        imageTargetSelection = null;
                    }
                } else {
                    skipImageModalSelectionCapture = false;
                }
                var anchorInput = document.getElementById('imageUploadRedirectAnchor');
                if (anchorInput) {
                    var anchorVal = '';
                    if (button && button.length) {
                        anchorVal = button.data('redirect-anchor') || '';
                    }
                    anchorInput.value = anchorVal || '';
                }
                if (modalSearchInput.length) {
                    modalSearchInput.val('');
                    applyModalFilter('');
                }
                pendingInsert = null;
                if (insertActions.length) {
                    insertActions.addClass('d-none');
                }
                if (imageTargetMode === 'uploader') {
                    // Nada especial, solo aseguramos que la búsqueda queda limpia
                    return;
                }

            });

            $('#imageModal').on('hidden.bs.modal', function () {
                imageTargetSelection = null;
                imageTargetTextarea = null;
            });

        

            var currentPage = 1;

            var itemsPerPage = 8;

            var galleryItems = $('.image-gallery .gallery-item');

            var filteredGalleryItems = galleryItems;

            var totalItems = filteredGalleryItems.length;

            var totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));

        

            function showPage(page) {

                galleryItems.hide();

                if (!filteredGalleryItems.length) {
                    currentPage = 1;
                    return;
                }

                filteredGalleryItems.slice((page - 1) * itemsPerPage, page * itemsPerPage).show();

                currentPage = page;

            }

        

            function setupPagination() {

                var pagination = $('#image-pagination');

                pagination.empty();

                var groupSize = 16;

                if (!filteredGalleryItems.length) {
                    return;
                }

                for (var i = 1; i <= totalPages; i++) {

                    var li = $('<li class="page-item"><a class="page-link" href="#">' + i + '</a></li>');

                    if (i === currentPage) {

                        li.addClass('active');

                    }

                    li.on('click', function(e) {

                        e.preventDefault();

                        currentPage = parseInt($(this).text(), 10) || 1;

                        showPage(currentPage);

                        setupPagination();

                    });

                    pagination.append(li);

                    if (i % groupSize === 0 && i !== totalPages) {

                        pagination.append('<li class="page-break"></li>');

                    }

                }

            }

            function applyModalFilter(term) {

                var normalized = (term || '').toString().toLowerCase().trim();

                if (!normalized.length) {

                    filteredGalleryItems = galleryItems;

                } else {

                    filteredGalleryItems = galleryItems.filter(function() {

                        var haystack = ($(this).data('media-search') || '').toString().toLowerCase();

                        return haystack.indexOf(normalized) !== -1;

                    });

                }

                totalItems = filteredGalleryItems.length;

                totalPages = Math.max(1, Math.ceil(Math.max(totalItems, 1) / itemsPerPage));

                showPage(1);

                setupPagination();

            }

            if (modalSearchInput.length) {

                modalSearchInput.on('input', function() {

                    applyModalFilter($(this).val());

                });

            }

            applyModalFilter('');

            var resourceSearchInput = $('#resource-search-input');

            var resourceItems = $('[data-resource-search-value]');

            if (resourceSearchInput.length && resourceItems.length) {

                resourceSearchInput.on('input', function() {

                    var normalized = ($(this).val() || '').toString().toLowerCase().trim();

                    currentResourcesSearch = ($(this).val() || '').toString();

                    resourceItems.each(function() {

                        var haystack = ($(this).attr('data-resource-search-value') || '').toString().toLowerCase();

                        var matches = !normalized.length || haystack.indexOf(normalized) !== -1;

                        $(this).toggle(matches);

                    });

                });

            }

            $(document).on('click', '[data-tag-filter]', function(e) {
                e.preventDefault();
                var tag = ($(this).data('tag-filter') || '').toString();
                var scope = ($(this).data('tag-scope') || '').toString();
                if (!tag.length) {
                    return;
                }
                if (scope === 'modal') {
                    if (modalSearchInput.length) {
                        modalSearchInput.val(tag);
                        applyModalFilter(tag);
                    }
                } else {
                    var url = 'admin.php?page=resources&search=' + encodeURIComponent(tag);
                    window.location = url;
                }
            });

            function saveResourceScroll() {
                if (!isResourcesPage) {
                    return;
                }
                try {
                    var scroll = window.pageYOffset || document.documentElement.scrollTop || 0;
                    localStorage.setItem(resourceScrollKey, JSON.stringify({ scroll: scroll, page: currentResourcesPage, search: currentResourcesSearch }));
                } catch (err) {
                    // ignore
                }
            }

            if (isResourcesPage) {
                try {
                    var storedScroll = localStorage.getItem(resourceScrollKey);
                    if (storedScroll) {
                        var parsed = JSON.parse(storedScroll);
                        var value = parsed && typeof parsed.scroll === 'number' ? parsed.scroll : 0;
                        var storedPage = parsed && typeof parsed.page === 'number' ? parsed.page : null;
                        if ((storedPage && storedPage !== currentResourcesPage) || (parsed && parsed.search !== undefined && parsed.search !== currentResourcesSearch)) {
                            // Do not restore scroll if landing on a different page or search
                            value = 0;
                        }
                        setTimeout(function() {
                            window.scrollTo(0, value);
                        }, 50);
                    }
                    localStorage.removeItem(resourceScrollKey);
                } catch (err) {
                    // ignore
                }
            }

            function showInsertActions(mediaName, mediaType, mediaSrc, mediaMime, mediaTags) {
                if (!insertActions.length) {
                    return;
                }
                pendingInsert = {
                    name: mediaName,
                    type: mediaType,
                    src: mediaSrc,
                    mime: mediaMime,
                    tags: mediaTags || ''
                };
                if (insertActionGroups.length) {
                    var groupKey = 'image';
                    if (mediaType === 'pdf') {
                        groupKey = 'pdf';
                    } else if (mediaType === 'video') {
                        groupKey = 'video';
                    }
                    insertActionGroups.addClass('d-none');
                    insertActionGroups.filter('[data-insert-group="' + groupKey + '"]').removeClass('d-none');
                }
                insertActions.removeClass('d-none');
            }

            $('.edit-tags-btn').on('click', function() {
                var currentTags = $(this).data('tag-list') || '';
                var target = $(this).data('tag-target') || '';
                tagsModalInput.val(currentTags);
                tagsModalTarget.val(target);
                var isModalContext = $('#imageModal').hasClass('show');
                var searchParams = new URLSearchParams(window.location.search || '');
                var currentPageParam = searchParams.get('page') || '';
                var currentFileParam = searchParams.get('file') || '';
                var queryString = (window.location.search || '').replace(/^\?/, '');
                var anchorVal = '';
                if (lastImageTrigger && typeof lastImageTrigger.getAttribute === 'function') {
                    anchorVal = lastImageTrigger.getAttribute('data-redirect-anchor') || '';
                }
                $('#tagsModalRedirectPage').val(currentPageParam);
                $('#tagsModalRedirectUrl').val(queryString);
                $('#tagsModalRedirectFile').val(currentFileParam);
                $('#tagsModalRedirectAnchor').val(anchorVal);
                $('#tagsModalReturnToModal').val(isModalContext ? '1' : '');
                $('#tagsModalTargetType').val(imageTargetMode || '');
                $('#tagsModalTargetInput').val(imageTargetInput || '');
                $('#tagsModalTargetEditor').val(imageTargetEditor || '');
                $('#tagsModalTargetPrefix').val(imageTargetPrefix || '');
                if (imageTargetSelection) {
                    $('#tagsModalSelectionStart').val(imageTargetSelection.start || '');
                    $('#tagsModalSelectionEnd').val(imageTargetSelection.end || '');
                    $('#tagsModalSelectionScroll').val(imageTargetSelection.scrollTop || '');
                } else {
                    $('#tagsModalSelectionStart').val('');
                    $('#tagsModalSelectionEnd').val('');
                    $('#tagsModalSelectionScroll').val('');
                }
                tagsModal.modal('show');
            });

            $('#tagsModalSave').on('click', function() {
                if (!tagsModalTarget.val()) {
                    tagsModal.modal('hide');
                    return;
                }
                saveResourceScroll();
                $('#tagsModalForm').trigger('submit');
            });

            $('#tagsModalForm').on('submit', function() {
                saveResourceScroll();
                if (tagsModalRedirect.length) {
                    tagsModalRedirect.val(currentResourcesPage);
                }
                var redirectSearchInput = document.getElementById('tagsModalRedirectSearch');
                if (redirectSearchInput) {
                    redirectSearchInput.value = currentResourcesSearch;
                }
            });

        

            showPage(1);

            setupPagination();

        

            $('.image-gallery').on('click', '[data-media-name]', function() {

                var $media = $(this);
                var mediaName = $media.data('mediaName');
                var mediaType = $media.data('mediaType') || 'image';
                var mediaSrc = $media.data('mediaSrc') || '';
                var mediaMime = $media.data('mediaMime') || '';
                var mediaTags = $media.data('mediaTags') || '';

                if (!mediaName) {
                    return;
                }

                if (imageTargetMode === 'field') {
                    var acceptList = (imageTargetAccept || 'image').toString().split(',').map(function(value) {
                        return value.trim().toLowerCase();
                    }).filter(Boolean);
                    if (!acceptList.length) {
                        acceptList = ['image'];
                    }
                    if (acceptList.indexOf(mediaType.toLowerCase()) === -1) {
                        var acceptLabel = acceptList.join(', ');
                        alert('Solo puedes seleccionar archivos de tipo ' + acceptLabel + ' para este campo.');
                        return;
                    }
                    var targetId = imageTargetInput || 'image';
                    var $input = $('#' + targetId);
                    if ($input.length) {
                        var prefix = imageTargetPrefix || '';
                        $input.val(prefix + mediaName);
                        $input.trigger('change');
                    }

                } else if (imageTargetMode === 'editor') {

                    if (mediaType === 'image' || mediaType === 'pdf' || mediaType === 'video') {
                        showInsertActions(mediaName, mediaType, mediaSrc, mediaMime, mediaTags);
                        return;
                    }

                    insertMediaInContent(mediaType, mediaSrc, mediaMime, 'full');
                    $('#imageModal').modal('hide');

                }

                $('#imageModal').modal('hide');

            });

            function resolvePostTitle() {
                var titleValue = '';
                if (lastImageTrigger && typeof lastImageTrigger.closest === 'function') {
                    var form = lastImageTrigger.closest('form');
                    if (form) {
                        var titleInput = form.querySelector('[name="title"]');
                        if (titleInput && titleInput.value) {
                            titleValue = titleInput.value;
                        }
                    }
                }
                if (!titleValue) {
                    var fallback = document.querySelector('input[name="title"]');
                    if (fallback && fallback.value) {
                        titleValue = fallback.value;
                    }
                }
                return (titleValue || '').toString().trim();
            }

            function resolveImageText(tagsText) {
                var tags = (tagsText || '').toString().split(',').map(function(tag) {
                    return tag.trim();
                }).filter(Boolean);
                if (tags.length) {
                    return tags.join(', ');
                }
                return resolvePostTitle();
            }

            function escapeHtmlAttr(value) {
                return (value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            function escapeMarkdownAlt(value) {
                return (value || '').replace(/]/g, '\\]');
            }

            function escapeMarkdownTitle(value) {
                return (value || '').replace(/"/g, '\\"');
            }

            function insertMediaInContent(type, source, mime, mode, tagsText) {
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
                    if (mode === 'link') {
                        snippet = '[' + (source.split('/').pop() || 'Video') + '](' + source + ')';
                    } else {
                        var safeSource = source;
                        var sourceTag = mime ? '        <source src="' + safeSource + '" type="' + mime + '">' : '        <source src="' + safeSource + '">';
                        snippet = '\n\n<div class="embedded-video">\n    <video controls preload="metadata">\n' + sourceTag + '\n    </video>\n</div>\n\n';
                    }
                } else if (type === 'pdf') {
                    if (mode === 'link') {
                        snippet = '[' + (source.split('/').pop() || 'Documento') + '](' + source + ')';
                    } else {
                        var hasHash = source.indexOf('#') !== -1;
                        var pdfBase = source.split('#')[0];
                        var defaultParams = 'page=1&zoom=page-fit&spread=0&toolbar=0&navpanes=0&scrollbar=0&statusbar=0&pagemode=none';
                        var pdfSrc = hasHash ? source : pdfBase + '#' + defaultParams;
                        var pdfHref = pdfBase;
                        snippet = '\n\n<div class="embedded-pdf">\n    <iframe src="' + pdfSrc + '" title="Documento PDF" loading="lazy" allowfullscreen></iframe>\n    <div class="embedded-pdf__actions" aria-label="Acciones del PDF">\n        <a class="embedded-pdf__action" href="' + pdfHref + '" download>Descargar PDF</a>\n        <a class="embedded-pdf__action" href="' + pdfHref + '" target="_blank" rel="noopener">Ver a pantalla completa</a>\n    </div>\n</div>\n\n';
                    }
                } else if (type === 'document') {
                    snippet = '[' + (source.split('/').pop() || 'Documento') + '](' + source + ')';
                } else {
                    var imageText = resolveImageText(tagsText);
                    var altText = escapeHtmlAttr(imageText || '');
                    var titleText = escapeHtmlAttr(imageText || '');
                    if (mode === 'vignette') {
                        var altAttr = altText;
                        var titleAttr = titleText;
                        snippet = '\n\n<img src="' + source + '" alt="' + altAttr + '"' + (titleAttr ? ' title="' + titleAttr + '"' : '') + ' style="float:right; max-width:33%; margin:0 0 1rem 1rem;" />\n\n';
                    } else {
                        snippet = '\n\n<img src="' + source + '" alt="' + altText + '"' + (titleText ? ' title="' + titleText + '"' : '') + ' />\n\n';
                    }
                }
                if (imageTargetSelection && imageTargetTextarea === contentTextArea) {
                    insertTextAtRange(contentTextArea, snippet, imageTargetSelection);
                    imageTargetSelection = null;
                    imageTargetTextarea = null;
                } else {
                    insertTextAtCursor(contentTextArea, snippet);
                }
            }

            function applyUploadedMediaIfNeeded() {
                if (!assetApply) {
                    return;
                }
                if (assetApply.restore_payload) {
                    try {
                        var restoreData = JSON.parse(assetApply.restore_payload);
                        restoreFormFields(restoreData);
                    } catch (err) {
                        // ignore restore errors
                    }
                }
                if (assetApply.return_to_modal) {
                    imageTargetMode = assetApply.mode || '';
                    imageTargetInput = assetApply.input || '';
                    imageTargetEditor = assetApply.editor || '';
                    imageTargetPrefix = assetApply.prefix || '';
                    if (assetApply.editor) {
                        try {
                            imageTargetTextarea = document.querySelector(assetApply.editor);
                        } catch (selectorError) {
                            imageTargetTextarea = null;
                        }
                    }
                    if (assetApply.selection) {
                        imageTargetSelection = assetApply.selection;
                    }
                    if (modalSearchInput.length && assetApply.files && assetApply.files.length) {
                        var filterName = assetApply.files[0] && assetApply.files[0].name ? assetApply.files[0].name : '';
                        if (filterName) {
                            modalSearchInput.val(filterName);
                            applyModalFilter(filterName);
                        }
                    }
                    if (assetApply.anchor) {
                        window.location.hash = assetApply.anchor.replace('#', '');
                    }
                    skipImageModalSelectionCapture = true;
                    $('#imageModal').modal('show');
                    window.nammuAssetApply = null;
                    return;
                }
                if (!assetApply.files || !assetApply.files.length) {
                    return;
                }
                var firstFile = assetApply.files[0];
                var targetValue = (assetApply.prefix || '') + (firstFile.name || '');
                var targetSrc = firstFile.src || targetValue;
                if (assetApply.mode === 'field' && targetValue) {
                    if (assetApply.input) {
                        var $fieldInput = $('#' + assetApply.input);
                        if ($fieldInput.length) {
                            $fieldInput.val(targetValue);
                        }
                    }
                } else if (assetApply.mode === 'editor' && targetSrc) {
                    insertMediaInContent('image', targetSrc, firstFile.mime || '', 'full');
                }
                if (assetApply.anchor) {
                    window.location.hash = assetApply.anchor.replace('#', '');
                }
                window.nammuAssetApply = null;
            }

            function restoreFormFields(payload) {
                if (!payload || typeof payload !== 'object' || !payload.fields) {
                    return;
                }
                var fields = payload.fields;
                Object.keys(fields).forEach(function(key) {
                    var value = fields[key];
                    var $input = $('[name="' + key + '"]');
                    if (!$input.length) {
                        return;
                    }
                    if ($input.is(':radio')) {
                        $input.filter('[value="' + value + '"]').prop('checked', true);
                    } else {
                        $input.val(value);
                    }
                });
            }

            function insertTextAtRange(textarea, text, range) {
                if (!textarea || !range) {
                    insertTextAtCursor(textarea, text);
                    return;
                }
                var start = typeof range.start === 'number' ? range.start : textarea.value.length;
                var end = typeof range.end === 'number' ? range.end : start;
                var value = textarea.value;
                textarea.value = value.substring(0, start) + text + value.substring(end);
                var cursorPosition = start + text.length;
                if (typeof setSelection === 'function') {
                    setSelection(textarea, cursorPosition, cursorPosition, range.scrollTop);
                    return;
                }
                textarea.focus();
                if (typeof textarea.setSelectionRange === 'function') {
                    textarea.setSelectionRange(cursorPosition, cursorPosition);
                }
                if (typeof range.scrollTop === 'number') {
                    textarea.scrollTop = range.scrollTop;
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

            insertActions.on('click', '[data-insert-mode]', function() {
                if (!pendingInsert) {
                    return;
                }
                var mode = $(this).data('insert-mode') || 'full';
                insertMediaInContent(pendingInsert.type, pendingInsert.src, pendingInsert.mime, mode, pendingInsert.tags);
                $('#imageModal').modal('hide');
                pendingInsert = null;
                insertActions.addClass('d-none');
            });

            applyUploadedMediaIfNeeded();

            $('[data-delete-tag-form]').on('submit', function() {
                saveResourceScroll();
                $(this).find('[name="redirect_p"]').val(currentResourcesPage);
                $(this).find('[name="redirect_search"]').val(currentResourcesSearch);
            });

            function resolveCalloutTarget() {
                if (calloutTarget && calloutTarget.tagName === 'TEXTAREA' && calloutTarget.id !== 'calloutBody') {
                    return calloutTarget;
                }
                var fb = fallbackTextarea();
                if (fb && fb.id === 'calloutBody') {
                    return null;
                }
                return fb;
            }

            function handleCalloutInsert() {
                ensureCalloutModal();
                calloutTarget = resolveCalloutTarget();
                if (!calloutTarget || calloutTarget.id === 'calloutBody') {
                    calloutTarget = fallbackTextarea();
                }
                if (!calloutTarget || calloutTarget.id === 'calloutBody') {
                    var allTextareas = document.querySelectorAll('textarea[data-markdown-editor], textarea');
                    allTextareas.forEach(function(el) {
                        if (!calloutTarget && el.id !== 'calloutBody' && el.tagName === 'TEXTAREA') {
                            calloutTarget = el;
                        }
                    });
                }
                if (!calloutTarget) {
                    if (calloutModal.length && typeof calloutModal.modal === 'function') {
                        calloutModal.modal('hide');
                    } else if (calloutModal.length) {
                        calloutModal.removeClass('show').css('display', 'none').attr('aria-hidden', 'true');
                    }
                    return;
                }
                var title = (calloutTitleInput.val() || 'Aviso').toString().trim();
                if (title === '') {
                    title = 'Aviso';
                }
                var bodyRaw = (calloutBodyInput.val() || '').toString();
                var lines = bodyRaw.split(/\n+/).map(function(line) { return line.trim(); }).filter(function(line) { return line !== ''; });
                if (!lines.length) {
                    lines = ['Contenido del aviso.'];
                }
                var bodyHtml = lines.map(function(line) {
                    return '  <p>' + line + '</p>';
                }).join('\n');
                var callout = '\n\n<div class="callout-box">\n  <h4>' + title + '</h4>\n' + bodyHtml + '\n</div>\n\n';
                if (calloutTarget && typeof calloutTarget.value === 'string') {
                    try {
                        replaceSelection(calloutTarget, callout, callout.length, callout.length);
                    } catch (errInsert) {
                        try {
                            insertTextAtCursor(calloutTarget, callout);
                        } catch (errFallback) {
                            // ignore
                        }
                    }
                }
                if (typeof calloutTarget.focus === 'function') {
                    calloutTarget.focus();
                }
                calloutTarget = null;
                calloutTargetSelector = '';
                if (calloutModal.length && typeof calloutModal.modal === 'function') {
                    calloutModal.modal('hide');
                } else if (calloutModal.length) {
                    calloutModal.removeClass('show').css('display', 'none').attr('aria-hidden', 'true');
                }
            }

            $(document).on('click', '#calloutInsert', function(e) {
                e.preventDefault();
                handleCalloutInsert();
            });

        

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

        

                    var imageRelative = $(this).data('image-relative') || '';

        

                    var imageTags = $(this).data('image-tags') || '';

        

                    $('#new-image-name').val(imageName + '-edited.png');

        

                    tagsInput.val(imageTags);

        

                    tagsTargetInput.val(imageRelative);

        

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

        

                    form.append($('<input type="hidden" name="image_tags">').val(tagsInput.val()));

        

                    form.append($('<input type="hidden" name="redirect_p">').val(currentResourcesPage));

        

                    form.append($('<input type="hidden" name="redirect_search">').val(currentResourcesSearch));

        

                    

        

                    $('body').append(form);

        

                    saveResourceScroll();

        

                    form.submit();

        

                });

        

                $('#save-tags-only').on('click', function() {

        

                    var target = tagsTargetInput.val();

        

                    if (!target) {

        

                        alert('Selecciona una imagen para poder guardar sus etiquetas.');

        

                        return;

        

                    }

        

                    var form = $('<form action="admin.php?page=resources" method="post"></form>');

        

                    form.append('<input type="hidden" name="update_image_tags" value="1">');

        

                    form.append($('<input type="hidden" name="original_image">').val(target));

        

                    form.append($('<input type="hidden" name="image_tags">').val(tagsInput.val()));

        

                    form.append($('<input type="hidden" name="redirect_p">').val(currentResourcesPage));

        

                    form.append($('<input type="hidden" name="redirect_search">').val(currentResourcesSearch));

        

                    $('body').append(form);

        

                    saveResourceScroll();

        

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
        document.addEventListener('DOMContentLoaded', function() {
            var confirmButtons = document.querySelectorAll('[data-confirm-publish]');
            if (!confirmButtons.length) {
                return;
            }
            confirmButtons.forEach(function(button) {
                button.addEventListener('click', function(event) {
                    var label = button.getAttribute('data-confirm-label') || button.textContent || '';
                    label = label.replace(/\s+/g, ' ').trim();
                    var action = label !== '' ? (label.charAt(0).toLowerCase() + label.slice(1)) : 'continuar';
                    if (!window.confirm('¿Estás seguro de querer ' + action + '?')) {
                        event.preventDefault();
                        var form = button.closest('form');
                        if (form) {
                            var notice = form.querySelector('[data-publish-cancelled]');
                            if (notice) {
                                notice.classList.remove('d-none');
                            }
                        }
                    }
                });
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
                if (['single', 'page', 'draft', 'newsletter', 'podcast'].indexOf(type) === -1) {
                    type = 'single';
                }
                modal.find('#delete-post-template').val(type);
            });
        });
        </script>

        </body>

        </html>
