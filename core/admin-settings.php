<?php
/**
 * Nammu — panel de administración.
 * Configuración del sitio (config/config.yml), valores por defecto de plantilla, usuario del panel y URL base.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

use Symfony\Component\Yaml\Yaml;

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
    $homeContent = $home['content'] ?? $defaults['home']['content'];
    if (!in_array($homeContent, ['blog', 'podcast', 'fediverse', 'itineraries'], true)) {
        $homeContent = $defaults['home']['content'];
    }
    $home['content'] = $homeContent;
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
    $searchDefaults = $defaults['search'] ?? ['mode' => 'single', 'position' => 'footer', 'floating' => 'off', 'fediverse_floating_cta' => 'on'];
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
    $searchFediverseFloatingCta = $searchConfig['fediverse_floating_cta'] ?? ($searchDefaults['fediverse_floating_cta'] ?? 'on');
    if (!in_array($searchFediverseFloatingCta, ['off', 'on'], true)) {
        $searchFediverseFloatingCta = $searchDefaults['fediverse_floating_cta'] ?? 'on';
    }
    $searchConfig['mode'] = $searchMode;
    $searchConfig['position'] = $searchPosition;
    $searchConfig['floating'] = $searchFloating;
    $searchConfig['fediverse_floating_cta'] = $searchFediverseFloatingCta;
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
        'podcast_image' => '',
        'podcast_category' => 'Technology',
        'twitter' => '',
        'linkedin' => '',
        'facebook_app_id' => '',
    ];
    $social = array_merge($socialDefaults, $config['social'] ?? []);
    $userData = get_user_data();
    $account = [
        'username' => $userData['username'] ?? '',
    ];
    $telegram = admin_extract_telegram_settings($config);
    $facebook = admin_extract_social_settings('facebook', [
        'token' => '',
        'channel' => '',
        'recipient' => '',
        'auto_post' => 'off',
        'app_secret' => '',
        'token_refreshed_at' => 0,
        'token_refresh_attempted_at' => 0,
    ], $config);
    $twitter = admin_extract_social_settings('twitter', [
        'token' => '',
        'channel' => '',
        'recipient' => '',
        'auto_post' => 'off',
        'api_key' => '',
        'api_secret' => '',
        'access_token' => '',
        'access_secret' => '',
    ], $config);
    $bluesky = admin_extract_social_settings('bluesky', [
        'service' => 'https://bsky.social',
        'identifier' => '',
        'app_password' => '',
        'auto_post' => 'off',
    ], $config);
    $instagram = admin_extract_social_settings('instagram', [
        'token' => '',
        'channel' => '',
        'profile' => '',
        'recipient' => '',
        'auto_post' => 'off',
    ], $config);
    $linkedin = admin_extract_social_settings('linkedin', [
        'token' => '',
        'author' => '',
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
    $podcastServicesDefaults = [
        'spotify' => '',
        'ivoox' => '',
        'apple' => '',
        'youtube_music' => '',
    ];
    $podcastServices = array_merge($podcastServicesDefaults, $config['podcast_services'] ?? []);
    $nisaba = $config['nisaba'] ?? [];
    $telex = $config['telex'] ?? [];
    $contact = $config['contact'] ?? [];
    $socialRssDefaults = [
        'feeds' => '',
        'networks' => [],
    ];
    $socialRss = array_merge($socialRssDefaults, $config['social_rss'] ?? []);
    if (!is_array($socialRss['networks'] ?? null)) {
        $socialRss['networks'] = [];
    }
    $multiInstanceDefaults = [
        'enabled' => 'off',
        'cluster' => '',
        'shared_cache_dir' => '',
        'shared_queue_dir' => '',
        'instances_root_dir' => '',
        'scheduler_mode' => 'standalone',
        'scheduler_strategy' => 'fixed',
    ];
    $multiInstance = array_merge($multiInstanceDefaults, $config['multi_instance'] ?? []);
    if (!in_array($multiInstance['scheduler_mode'], ['standalone', 'central'], true)) {
        $multiInstance['scheduler_mode'] = 'standalone';
    }
    if (!in_array($multiInstance['scheduler_strategy'], ['fixed', 'activity'], true)) {
        $multiInstance['scheduler_strategy'] = 'fixed';
    }
    $rejectedOrigins = is_array($config['rejected_origins'] ?? null) ? $config['rejected_origins'] : [];
    if (!is_array($rejectedOrigins['domains'] ?? null)) {
        $rejectedOrigins['domains'] = [];
    }

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
        'facebook' => $facebook,
        'twitter' => $twitter,
        'bluesky' => $bluesky,
        'instagram' => $instagram,
        'linkedin' => $linkedin,
        'mailing' => $mailing,
        'postal' => $postal,
        'ads' => $ads,
        'indexnow' => $indexnow,
        'podcast_services' => $podcastServices,
        'nisaba' => $nisaba,
        'telex' => $telex,
        'contact' => $contact,
        'social_rss' => $socialRss,
        'multi_instance' => $multiInstance,
        'rejected_origins' => $rejectedOrigins,
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
    if (!nammu_ensure_directory($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de configuración');
    }

    $content = "<?php return ['username' => '" . addslashes($username) . "', 'password' => '" . $passwordHash . "'];";
    $saved = function_exists('nammu_atomic_write_file')
        ? nammu_atomic_write_file(USER_FILE, $content)
        : file_put_contents(USER_FILE, $content, LOCK_EX) !== false;
    if (!$saved) {
        throw new RuntimeException('No se pudo escribir el archivo de usuario');
    }
    nammu_apply_shared_permissions(USER_FILE, 0664, $dir);
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

function get_default_template_settings(): array {
    return [
        'fonts' => [
            'title' => 'Gabarito',
            'body' => 'Roboto',
            'note' => 'Roboto',
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
            'content' => 'blog',
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
            'header_buttons' => 'none',
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
            'fediverse_floating_cta' => 'on',
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
    $configFile = NAMMU_ROOT . '/config/config.yml';
    if (!is_file($configFile)) {
        return [];
    }
    $raw = file_get_contents($configFile);
    if ($raw === false) {
        return [];
    }
    if (class_exists(Yaml::class)) {
        try {
            $parsed = Yaml::parse($raw);
            return is_array($parsed) ? $parsed : [];
        } catch (Exception $e) {
            // Fallback to simple parser if Symfony YAML fails.
        }
    }
    $parsed = simple_yaml_parse($raw);
    return is_array($parsed) ? $parsed : [];
}

function save_config_file(array $config): void {
    $configFile = NAMMU_ROOT . '/config/config.yml';
    $dir = dirname($configFile);
    if (!nammu_ensure_directory($dir)) {
        throw new RuntimeException('No se pudo crear el directorio de configuración');
    }

    if (class_exists(Yaml::class)) {
        $yaml = Yaml::dump($config, 4, 2);
    } else {
        $yaml = simple_yaml_dump($config);
        if ($yaml !== '') {
            $yaml .= "\n";
        }
    }

    $saved = function_exists('nammu_atomic_write_file')
        ? nammu_atomic_write_file($configFile, $yaml)
        : file_put_contents($configFile, $yaml, LOCK_EX) !== false;
    if (!$saved) {
        throw new RuntimeException('No se pudo escribir el archivo de configuración');
    }
    nammu_apply_shared_permissions($configFile, 0664, $dir);
}
