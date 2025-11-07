<?php

declare(strict_types=1);

use Nammu\Core\Post;
function nammu_base_url(): string
{
    $explicit = getenv('NAMMU_BASE_URL');
    if ($explicit !== false && $explicit !== '') {
        return rtrim($explicit, '/');
    }

    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $host = trim($host);
    if ($host === '') {
        return '';
    }

    $scheme = 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwarded = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
        $schemeCandidate = strtolower(trim($forwarded[0]));
        if (in_array($schemeCandidate, ['http', 'https'], true)) {
            $scheme = $schemeCandidate;
        }
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
        $schemeCandidate = strtolower((string) $_SERVER['REQUEST_SCHEME']);
        if (in_array($schemeCandidate, ['http', 'https'], true)) {
            $scheme = $schemeCandidate;
        }
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName !== '' ? $scriptName : '/'));
    if ($dir === '/' || $dir === '.') {
        $dir = '';
    } else {
        $dir = rtrim($dir, '/');
    }

    $portSuffix = '';
    if (isset($_SERVER['SERVER_PORT']) && !str_contains($host, ':')) {
        $port = (int) $_SERVER['SERVER_PORT'];
        if (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443)) {
            $portSuffix = ':' . $port;
        }
    }

    return rtrim($scheme . '://' . $host . $portSuffix . $dir, '/');
}

function nammu_route_path(): string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseDir = str_replace('\\', '/', dirname($scriptName));
    $baseDir = $baseDir === '/' ? '' : rtrim($baseDir, '/');

    if ($baseDir !== '' && str_starts_with($path, $baseDir)) {
        $path = substr($path, strlen($baseDir));
        if ($path === false || $path === '') {
            $path = '/';
        }
    }

    return $path === '' ? '/' : $path;
}

function nammu_template_settings(): array
{
    $defaults = nammu_default_template_settings();
    $config = nammu_load_config();

    $template = $config['template'] ?? [];
    $fonts = array_merge($defaults['fonts'], $template['fonts'] ?? []);
    $colors = array_merge($defaults['colors'], $template['colors'] ?? []);
    $images = array_merge($defaults['images'], $template['images'] ?? []);
    $footer = $template['footer'] ?? ($defaults['footer'] ?? '');
    $footerLogo = $template['footer_logo'] ?? ($defaults['footer_logo'] ?? 'none');
    if (!in_array($footerLogo, ['none', 'top', 'bottom'], true)) {
        $footerLogo = $defaults['footer_logo'] ?? 'none';
    }
    $globalConfig = $template['global'] ?? [];
    $global = array_merge($defaults['global'], $globalConfig);
    $cornerStyle = $global['corners'] ?? $defaults['global']['corners'];
    if (!in_array($cornerStyle, ['rounded', 'square'], true)) {
        $cornerStyle = $defaults['global']['corners'];
    }
    $global['corners'] = $cornerStyle;

    $homeConfig = $template['home'] ?? [];
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
    $homeHeaderDefaults = $defaults['home']['header'];
    $homeHeader = array_merge($homeHeaderDefaults, $homeConfig['header'] ?? []);
    $headerTypes = ['none', 'graphic', 'text', 'mixed'];
    if (!in_array($homeHeader['type'], $headerTypes, true)) {
        $homeHeader['type'] = $homeHeaderDefaults['type'];
    }
    $headerModes = ['contain', 'cover'];
    if (!in_array($homeHeader['mode'], $headerModes, true)) {
        $homeHeader['mode'] = $homeHeaderDefaults['mode'];
    }
    $textHeaderStyles = ['boxed', 'plain'];
    if (!in_array($homeHeader['text_style'], $textHeaderStyles, true)) {
        $homeHeader['text_style'] = $homeHeaderDefaults['text_style'];
    }
    if (in_array($homeHeader['type'], ['graphic', 'mixed'], true) && trim((string) $homeHeader['image']) === '') {
        $homeHeader['type'] = $homeHeader['type'] === 'mixed' ? 'text' : 'none';
    }
    if ($homeHeader['type'] !== 'graphic' && $homeHeader['type'] !== 'mixed') {
        $homeHeader['image'] = '';
        $homeHeader['mode'] = $homeHeaderDefaults['mode'];
    }
    if ($homeHeader['type'] !== 'text' && $homeHeader['type'] !== 'mixed') {
        $homeHeader['text_style'] = $homeHeaderDefaults['text_style'];
    }
    $home['header'] = $homeHeader;
    $author = $config['site_author'] ?? '';
    $blog = $config['site_name'] ?? '';

    $fontRequests = [];
    $titleFont = $fonts['title'] ?? '';
    $bodyFont = $fonts['body'] ?? '';
    $codeFont = $fonts['code'] ?? '';
    $quoteFont = $fonts['quote'] ?? '';

    if ($titleFont !== '') {
        $fontRequests[$titleFont] = 'wght@400;700';
    }
    if ($bodyFont !== '') {
        $fontRequests[$bodyFont] = 'wght@400;700';
    }
    if ($quoteFont !== '') {
        if (!isset($fontRequests[$quoteFont])) {
            $fontRequests[$quoteFont] = 'wght@400;700';
        }
    }
    if ($codeFont !== '') {
        if (!isset($fontRequests[$codeFont])) {
            $fontRequests[$codeFont] = 'wght@400';
        }
    }

    $families = [];
    foreach ($fontRequests as $fontName => $variant) {
        if ($fontName === '' || $fontName === null) {
            continue;
        }
        $family = str_replace(' ', '+', $fontName);
        if ($variant !== '') {
            $family .= ':' . $variant;
        }
        $families[] = $family;
    }

    $fontUrl = null;
    if (!empty($families)) {
        $fontUrl = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $families) . '&display=swap';
    }

    $searchDefaults = $defaults['search'] ?? [
        'mode' => 'none',
        'position' => 'title',
        'floating' => 'off',
    ];
    $searchConfig = array_merge($searchDefaults, $template['search'] ?? []);
    if (!in_array($searchConfig['mode'], ['none', 'home', 'single', 'both'], true)) {
        $searchConfig['mode'] = $searchDefaults['mode'];
    }
    if (!in_array($searchConfig['position'], ['title', 'footer'], true)) {
        $searchConfig['position'] = $searchDefaults['position'];
    }
    if (!in_array($searchConfig['floating'], ['off', 'on'], true)) {
        $searchConfig['floating'] = $searchDefaults['floating'];
    }

    return [
        'fonts' => $fonts,
        'colors' => $colors,
        'images' => $images,
        'fontUrl' => $fontUrl,
        'footer' => $footer,
        'footer_logo' => $footerLogo,
        'global' => $global,
        'corners' => $cornerStyle,
        'home' => $home,
        'author' => $author,
        'blog' => $blog,
        'search' => $searchConfig,
    ];
}

function nammu_default_template_settings(): array
{
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
            'mode' => 'single',
            'position' => 'footer',
            'floating' => 'off',
        ],
    ];
}

function nammu_load_config(): array
{
    $configFile = __DIR__ . '/../config/config.yml';
    if (!is_file($configFile)) {
        return [];
    }

    if (function_exists('yaml_parse_file')) {
        $parsed = @yaml_parse_file($configFile);
        return is_array($parsed) ? $parsed : [];
    }

    $contents = file_get_contents($configFile);
    if ($contents === false) {
        return [];
    }

    return nammu_simple_yaml_parse($contents);
}

function nammu_simple_yaml_parse(string $yaml): array
{
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
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $stack[] = &$current[$key];
            $indentStack[] = $indent + 2;
            continue;
        }

        $current[$key] = nammu_simple_yaml_unescape($value);
    }

    return $result;
}

function nammu_simple_yaml_unescape(string $value): string
{
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

function nammu_social_settings(): array
{
    $config = nammu_load_config();
    $defaults = [
        'default_description' => '',
        'home_image' => '',
        'twitter' => '',
        'facebook_app_id' => '',
    ];

    $social = array_merge($defaults, $config['social'] ?? []);

    if ($social['home_image'] !== '') {
        $social['home_image'] = nammu_resolve_asset($social['home_image'], '');
    }

    return $social;
}

function nammu_build_social_meta(array $data, array $socialConfig): array
{
    $title = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    if ($description === '') {
        $description = $socialConfig['default_description'] ?? '';
    }
    $url = $data['url'] ?? '';
    $image = $data['image'] ?? '';
    $siteName = trim($data['site_name'] ?? '');

    $properties = [
        'og:type' => $data['type'] ?? 'website',
        'og:title' => $title,
        'og:description' => $description,
        'og:url' => $url,
    ];

    if ($image !== '') {
        $properties['og:image'] = $image;
    }
    if ($siteName !== '') {
        $properties['og:site_name'] = $siteName;
    }
    if (!empty($socialConfig['facebook_app_id'])) {
        $properties['fb:app_id'] = $socialConfig['facebook_app_id'];
    }

    $names = [
        'twitter:card' => 'summary_large_image',
        'twitter:title' => $title,
        'twitter:description' => $description,
    ];

    if ($url !== '') {
        $names['twitter:url'] = $url;
    }
    if ($image !== '') {
        $names['twitter:image'] = $image;
    }
    if (!empty($socialConfig['twitter'])) {
        $handle = $socialConfig['twitter'];
        if ($handle !== '' && $handle[0] !== '@') {
            $handle = '@' . $handle;
        }
        $names['twitter:site'] = $handle;
    }

    return [
        'canonical' => $url,
        'properties' => $properties,
        'names' => $names,
    ];
}

function nammu_excerpt_text(string $html, int $length = 200): string
{
    $text = trim(strip_tags($html));
    $text = preg_replace('/\s+/u', ' ', $text);
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}

function nammu_format_date_spanish(?DateTimeImmutable $date, ?string $fallback = ''): string
{
    if ($date instanceof DateTimeImmutable) {
        $months = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];
        $monthIndex = (int) $date->format('n');
        $monthName = $months[$monthIndex] ?? $date->format('m');
        return ltrim($date->format('j')) . ' de ' . $monthName . ' de ' . $date->format('Y');
    }

    $fallback = $fallback !== null ? trim($fallback) : '';
    if ($fallback === '') {
        return '';
    }

    $knownFormats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
    foreach ($knownFormats as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $fallback);
        if ($parsed instanceof DateTimeImmutable) {
            return nammu_format_date_spanish($parsed, '');
        }
    }

    $timestamp = strtotime($fallback);
    if ($timestamp !== false) {
        $parsed = (new DateTimeImmutable())->setTimestamp($timestamp);
        return nammu_format_date_spanish($parsed, '');
    }

    return $fallback;
}

function nammu_resolve_asset(?string $path, string $baseUrl): ?string
{
    if ($path === null) {
        return null;
    }

    $path = trim($path);
    if ($path === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $normalized = ltrim($path, '/');
    if (!str_starts_with($normalized, 'assets/')) {
        $normalized = 'assets/' . $normalized;
    }

    if ($baseUrl !== '') {
        return rtrim($baseUrl, '/') . '/' . $normalized;
    }

    return '/' . $normalized;
}

function nammu_slugify_label(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    $text = mb_strtolower($text, 'UTF-8');
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text;
}

/**
 * @param Post[] $posts
 * @return array<string, array{name:string, posts:Post[], count:int}>
 */
function nammu_collect_categories_from_posts(array $posts): array
{
    $categories = [];
    foreach ($posts as $post) {
        if (!$post instanceof Post) {
            continue;
        }
        $category = trim($post->getCategory());
        if ($category === '') {
            continue;
        }
        $slug = nammu_slugify_label($category);
        if ($slug === '') {
            continue;
        }
        if (!isset($categories[$slug])) {
            $categories[$slug] = [
                'name' => $category,
                'posts' => [],
                'count' => 0,
                'latest_timestamp' => null,
                'latest_image' => null,
            ];
        }
        $categories[$slug]['posts'][] = $post;
        $categories[$slug]['count']++;

        $date = $post->getDate();
        $timestamp = $date ? $date->getTimestamp() : null;
        if ($timestamp === null) {
            $raw = $post->getRawDate();
            if ($raw) {
                $timestamp = strtotime($raw) ?: null;
            }
        }
        $timestamp ??= 0;
        if ($categories[$slug]['latest_timestamp'] === null || $timestamp >= $categories[$slug]['latest_timestamp']) {
            $categories[$slug]['latest_timestamp'] = $timestamp;
            $categories[$slug]['latest_image'] = $post->getImage();
        }
    }

    foreach ($categories as $slug => &$data) {
        unset($data['latest_timestamp']);
    }
    unset($data);

    return $categories;
}

function nammu_letter_key_from_title(string $title): string
{
    $title = trim($title);
    if ($title === '') {
        return '#';
    }
    $firstChar = mb_substr($title, 0, 1, 'UTF-8');
    $upper = mb_strtoupper($firstChar, 'UTF-8');
    $mapping = [
        'Á' => 'A',
        'À' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'Å' => 'A',
        'É' => 'E',
        'È' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Í' => 'I',
        'Ì' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ó' => 'O',
        'Ò' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        'Ú' => 'U',
        'Ù' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ý' => 'Y',
    ];
    $upper = strtr($upper, $mapping);
    if ($upper === 'Ñ') {
        return 'Ñ';
    }
    if (preg_match('/^[A-Z]$/u', $upper)) {
        return $upper;
    }
    return '#';
}

function nammu_letter_slug(string $letter): string
{
    return $letter === '#' ? 'otros' : strtolower($letter);
}

function nammu_letter_from_slug(string $slug): string
{
    $slug = strtolower($slug);
    if ($slug === 'otros') {
        return '#';
    }
    $char = mb_substr($slug, 0, 1, 'UTF-8');
    $upper = mb_strtoupper($char, 'UTF-8');
    return $upper !== '' ? $upper : '#';
}

function nammu_letter_display_name(string $letter): string
{
    return $letter === '#' ? 'Otros' : $letter;
}

/**
 * @template T
 * @param array<int, T> $items
 * @param callable|null $formatter
 * @return array<string, array<mixed>>
 */
function nammu_group_items_by_letter(array $items, ?callable $formatter = null): array
{
    $groups = [];
    foreach ($items as $item) {
        $title = '';
        if ($item instanceof Post) {
            $title = $item->getTitle();
        } elseif (is_array($item) && isset($item['title'])) {
            $title = (string) $item['title'];
        } elseif (is_object($item) && method_exists($item, 'getTitle')) {
            $title = (string) $item->getTitle();
        }
        $letter = nammu_letter_key_from_title($title);
        if (!isset($groups[$letter])) {
            $groups[$letter] = [];
        }
        $groups[$letter][] = $formatter ? $formatter($item) : $item;
    }

    return nammu_sort_letter_groups($groups);
}

/**
 * @param array<string, array<mixed>> $groups
 * @return array<string, array<mixed>>
 */
function nammu_sort_letter_groups(array $groups): array
{
    $keys = array_keys($groups);
    usort($keys, static function (string $a, string $b): int {
        return nammu_letter_sort_weight($a) <=> nammu_letter_sort_weight($b);
    });
    $sorted = [];
    foreach ($keys as $key) {
        $sorted[$key] = $groups[$key];
    }
    return $sorted;
}

function nammu_letter_sort_weight(string $letter): int
{
    if ($letter === '#') {
        return 1000;
    }
    if ($letter === 'Ñ') {
        return ord('N') + 1;
    }
    $char = $letter[0] ?? 'Z';
    $ord = ord($char);
    if ($ord < ord('A') || $ord > ord('Z')) {
        return 900;
    }
    return $ord;
}
