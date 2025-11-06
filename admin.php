<?php
session_start();

// Load dependencies (optional)
$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
use Symfony\Component\Yaml\Yaml;

// --- User Configuration ---
define('USER_FILE', __DIR__ . '/config/user.php');
define('CONTENT_DIR', __DIR__ . '/content');
define('ASSETS_DIR', __DIR__ . '/assets');

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

function get_posts($page = 1, $per_page = 16, $templateFilter = 'single') {
    $settings = get_settings();
    $sort_order = $settings['sort_order'] ?? 'date';

    $posts = [];
    $files = glob(CONTENT_DIR . '/*.md');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $metadata = parse_yaml_front_matter($content);
        $template = strtolower($metadata['Template'] ?? '');
        $isEntry = in_array($template, ['single', 'post'], true);
        if ($templateFilter === 'single' && !$isEntry) {
            continue;
        }
        if ($templateFilter === 'page' && $template !== 'page') {
            continue;
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
            'timestamp' => $timestamp
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
    $filepath = CONTENT_DIR . '/' . $filename;
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

function get_images($page = 1, $per_page = 8) {
    $images = glob(ASSETS_DIR . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    usort($images, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    $total = count($images);
    $pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;
    return [
        'images' => array_slice($images, $offset, $per_page),
        'total' => $total,
        'pages' => $pages,
        'current_page' => $page
    ];
}

function get_assets($page = 1, $per_page = 40) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ASSETS_DIR));
    foreach ($iterator as $file) {
        if ($file->isDir()) continue;
        $files[] = $file->getPathname();
    }
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    $total = count($files);
    $pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;
    return [
        'assets' => array_slice($files, $offset, $per_page),
        'total' => $total,
        'pages' => $pages,
        'current_page' => $page
    ];
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

    $socialDefaults = [
        'default_description' => '',
        'home_image' => '',
        'twitter' => '',
        'facebook_app_id' => '',
    ];
    $social = array_merge($socialDefaults, $config['social'] ?? []);

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
            'global' => $global,
            'home' => $home,
        ],
        'social' => $social,
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

function register_user($username, $password) {
    $dir = dirname(USER_FILE);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio de configuración');
        }
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $content = "<?php return ['username' => '" . addslashes($username) . "', 'password' => '" . $hashed_password . "'];";
    if (file_put_contents(USER_FILE, $content) === false) {
        throw new RuntimeException('No se pudo escribir el archivo de usuario');
    }
}

function verify_user($username, $password) {
    $user_data = get_user_data();
    if ($user_data && $user_data['username'] === $username && password_verify($password, $user_data['password'])) {
        return true;
    }
    return false;
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

// --- Routing and Logic ---

$page = $_GET['page'] ?? 'login';
$error = null;
$user_exists = file_exists(USER_FILE);

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
    } elseif (isset($_POST['publish'])) {
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

        if ($filenameInput === '' && $title !== '') {
            $filenameInput = $title;
        }

        $filename = nammu_unique_filename($filenameInput);

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
            $filepath = CONTENT_DIR . '/' . $filename . '.md';

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
            $file_content .= "Ordo: " . $ordo . "
";
            $file_content .= "---

";
            $file_content .= $content;

            if (file_put_contents($filepath, $file_content) === false) {
                $error = 'No se pudo guardar el contenido. Revisa los permisos de la carpeta content/.';
            } else {
                header('Location: admin.php?page=edit&created=' . urlencode($filename . '.md'));
                exit;
            }
        }
    } elseif (isset($_POST['update'])) {
        $filename = $_POST['filename'] ?? '';
        $title = $_POST['title'] ?? '';
        $template = 'post';
        $category = $_POST['category'] ?? '';
        $date = $_POST['date'] ? date('Y-m-d', strtotime($_POST['date'])) : date('Y-m-d');
        $image = $_POST['image'] ?? '';
        $description = $_POST['description'] ?? '';
        $type = $_POST['type'] ?? null;

        // Preserve existing Ordo value on update
        if (!empty($filename)) {
            $existing_post_data = get_post_content($filename);
            $ordo = $existing_post_data['metadata']['Ordo'] ?? '';
            if ($type === null) {
                $currentTemplate = strtolower($existing_post_data['metadata']['Template'] ?? 'post');
                $type = $currentTemplate === 'page' ? 'Página' : 'Entrada';
            }
        } else {
            $ordo = '';
        }

        $type = $type === 'Página' ? 'Página' : 'Entrada';
                $template = $type === 'Página' ? 'page' : 'post';

        $content = $_POST['content'] ?? '';

        if (!empty($filename)) {
            $filepath = CONTENT_DIR . '/' . $filename;

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
            $file_content .= "Ordo: " . $ordo . "
";
            $file_content .= "---

";
            $file_content .= $content;

            file_put_contents($filepath, $file_content);
            header('Location: admin.php?page=edit');
            exit;
        }
    } elseif (isset($_POST['upload_asset'])) {
        if (isset($_FILES['asset_file']) && $_FILES['asset_file']['error'] == 0) {
            $target_dir = ASSETS_DIR . '/';
            $target_file = $target_dir . basename($_FILES["asset_file"]["name"]);
            if (move_uploaded_file($_FILES["asset_file"]["tmp_name"], $target_file)) {
                // file uploaded successfully
            }
        }
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

            save_config_file($config);

        } catch (Throwable $e) {
            $error = "Error guardando la configuración: " . $e->getMessage();
        }

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
        $homeCardStylePosted = in_array($homeCardStylePosted, ['full', 'square-right', 'circle-right'], true)
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

        $config['template'] = [
            'fonts' => $fonts,
            'colors' => $colors,
            'images' => $images,
            'footer' => $footerMd,
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

                        <div class="collapse navbar-collapse">

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

                                <li class="nav-item <?= $page === 'configuracion' ? 'active' : '' ?>">

                                    <a class="nav-link" href="?page=configuracion"><h1>Configuración</h1></a>

                                </li>

                            </ul>

                            <form method="post" class="form-inline my-2 my-lg-0">

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

                                        <label for="content">Contenido (Markdown)</label>

                                        <textarea name="content" id="content" class="form-control" rows="15"></textarea>

                                        <button type="button" class="btn btn-secondary mt-2" data-toggle="modal" data-target="#imageModal" data-target-type="editor">Insertar imagen</button>

                                    </div>

                                    <div class="form-group">

                                        <label for="filename">Nombre de archivo (sin .md)</label>

                                        <input type="text" name="filename" id="filename" class="form-control" required>

                                    </div>

                                    <button type="submit" name="publish" class="btn btn-primary">Publicar</button>

                                </form>

                            </div>

                        <?php elseif ($page === 'edit'): ?>

                            <div class="tab-pane active">

                                <?php
                                $templateFilter = $_GET['template'] ?? 'single';
                                $templateFilter = $templateFilter === 'page' ? 'page' : 'single';
                                $currentTypeLabel = $templateFilter === 'page' ? 'Páginas' : 'Entradas';
                                ?>

                                <h2>Editar Entradas / Páginas</h2>

                                <div class="btn-group mb-3" role="group" aria-label="Filtrar por tipo">
                                    <a href="?page=edit&template=single" class="btn btn-sm btn-outline-primary <?= $templateFilter === 'single' ? 'active' : '' ?>">Entradas</a>
                                    <a href="?page=edit&template=page" class="btn btn-sm btn-outline-primary <?= $templateFilter === 'page' ? 'active' : '' ?>">Páginas</a>
                                </div>

                                <p class="text-muted">Mostrando <?= strtolower($currentTypeLabel) ?>.</p>

                                <table class="table table-striped">

                                    <thead>

                                        <tr>

                                            <th>Título</th>

                                            <th>Descripción</th>

                                            <th>Fecha</th>

                                            <th>Nombre de archivo</th>

                                            <th></th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php

                                        $current_page = $_GET['p'] ?? 1;

                                        $posts_data = get_posts($current_page, 16, $templateFilter);

                                        if (empty($posts_data['posts'])):
                                        ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No hay <?= strtolower($currentTypeLabel) ?> disponibles.</td>
                                            </tr>
                                        <?php
                                        else:
                                            foreach ($posts_data['posts'] as $post):
                                        ?>

                                            <tr>

                                                <td><?= htmlspecialchars($post['title']) ?></td>

                                                <td><?= htmlspecialchars($post['description']) ?></td>

                                                <td><?= htmlspecialchars($post['date']) ?></td>

                                                <td><?= htmlspecialchars($post['filename']) ?></td>

                                                <td><a href="?page=edit-post&file=<?= urlencode($post['filename']) ?>" class="btn btn-sm btn-primary">Editar</a></td>

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

                                                <a class="page-link" href="?page=edit&template=<?= urlencode($templateFilter) ?>&p=<?= $i ?>"><?= $i ?></a>

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

                                $filename = $_GET['file'] ?? '';

                                $post_data = get_post_content($filename);

                                if ($post_data):
                                    $currentTemplateValue = strtolower($post_data['metadata']['Template'] ?? 'post');
                                    $currentTypeValue = $currentTemplateValue === 'page' ? 'Página' : 'Entrada';
                                    $editHeading = $currentTypeValue === 'Página' ? 'Editar Página' : 'Editar Entrada';
                                ?>

                                <h2><?= $editHeading ?></h2>

                                <form method="post">

                                    <input type="hidden" name="filename" value="<?= htmlspecialchars($filename) ?>">

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

                                        <input type="date" name="date" id="date" class="form-control" value="<?= $post_data['metadata']['Date'] ?? date('Y-m-d') ?>" required>

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

                                        <label for="content">Contenido (Markdown)</label>

                                        <textarea name="content" id="content" class="form-control" rows="15"><?= htmlspecialchars($post_data['content']) ?></textarea>

                                        <button type="button" class="btn btn-secondary mt-2" data-toggle="modal" data-target="#imageModal" data-target-type="editor">Insertar imagen</button>

                                    </div>

                                    <button type="submit" name="update" class="btn btn-primary">Actualizar</button>

                                </form>

                                <?php else: ?>

                                    <div class="alert alert-danger">Contenido no encontrado.</div>

                                <?php endif; ?>

                            </div>

                        <?php elseif ($page === 'resources'): ?>

                            <div class="tab-pane active">

                                <h2>Recursos</h2>

                                

                                <h4>Subir nuevo archivo</h4>

                                <form method="post" enctype="multipart/form-data">

                                    <div class="form-group">

                                        <input type="file" name="asset_file" id="asset_file" class="form-control-file">

                                    </div>

                                    <button type="submit" name="upload_asset" class="btn btn-primary">Subir</button>

                                </form>

        

                                <hr>

        

                                <h4>Archivos existentes</h4>

                                <div class="row">

                                    <?php

                                    $current_page = $_GET['p'] ?? 1;

                                    $assets_data = get_assets($current_page, 40);

                                    foreach ($assets_data['assets'] as $asset):

                                        $relative_path = str_replace(ASSETS_DIR . '/', '', $asset);

                                        $is_image = in_array(strtolower(pathinfo($asset, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif']);

                                    ?>

                                        <div class="col-md-3 mb-3">

                                            <div class="card">

                                                <?php if ($is_image): ?>

                                                    <img src="assets/<?= htmlspecialchars($relative_path) ?>" class="card-img-top" style="height: 150px; object-fit: cover;">

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
                                        for ($i = 1; $i <= $assets_data['pages']; $i++): ?>

                                            <li class="page-item <?= $i == $assets_data['current_page'] ? 'active' : '' ?>">

                                                <a class="page-link" href="?page=resources&p=<?= $i ?>"><?= $i ?></a>

                                            </li>

                                            <?php if ($i % $pageGroupSize === 0 && $i < $assets_data['pages']): ?>
                                                <li class="page-break"></li>
                                            <?php endif; ?>

                                        <?php endfor; ?>

                                    </ul>

                                </nav>

                            </div>

                        <?php elseif ($page === 'template'): ?>

                            <?php
                            $templateSettings = $settings['template'] ?? get_default_template_settings();
                            $fontTitle = $templateSettings['fonts']['title'] ?? '';
                            $fontBody = $templateSettings['fonts']['body'] ?? '';
                            $fontCode = $templateSettings['fonts']['code'] ?? '';
                            $fontQuote = $templateSettings['fonts']['quote'] ?? '';
                            $templateColors = $templateSettings['colors'] ?? [];
                            $templateImages = $templateSettings['images'] ?? [];
                            $logoImage = $templateImages['logo'] ?? '';
                            $footerMd = $templateSettings['footer'] ?? '';
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
                                'brand' => 'Color de Cabecera (.post-brand)',
                                'code_background' => 'Color de fondo de código',
                                'code_text' => 'Color del código',
                            ];
                            $defaults = get_default_template_settings();
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
                            $cardStylesAllowed = ['full', 'square-right', 'circle-right'];
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

                                    <h4 class="mt-4">Footer</h4>
                                    <p class="text-muted">Este contenido se mostrará al final de cada página. Introduce HTML directamente (por ejemplo, &lt;strong&gt;...&lt;/strong&gt; o enlaces con &lt;a&gt; ).</p>
                                    <div class="form-group">
                                        <label for="footer_md">Contenido del footer (HTML)</label>
                                        <textarea name="footer_md" id="footer_md" rows="6" class="form-control" placeholder="Bloque de contacto, enlaces legales..."><?= htmlspecialchars($footerMd ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>

                                    <button type="submit" name="save_template" class="btn btn-primary">Guardar plantilla</button>
                                </form>

                            </div>

                        <?php elseif ($page === 'configuracion'): ?>

                            <div class="tab-pane active">

                                <h2>Configuración</h2>

                                <form method="post">

                                    <div class="form-group">
                                        <label for="site_author">Nombre del autor u organización</label>
                                        <input type="text" name="site_author" id="site_author" class="form-control" value="<?= htmlspecialchars($settings['site_author'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Fundación Repoblación">
                                    </div>

                                    <div class="form-group">
                                        <label for="site_name">Nombre del blog</label>
                                        <input type="text" name="site_name" id="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Memoria">
                                    </div>

                                    <div class="form-group">

                                        <label for="sort_order">Ordenar posts por:</label>

                                        <select name="sort_order" id="sort_order" class="form-control">

                                            <option value="date" <?= $settings['sort_order'] == 'date' ? 'selected' : '' ?>>Fecha (más recientes primero)</option>

                                            <option value="alpha" <?= $settings['sort_order'] == 'alpha' ? 'selected' : '' ?>>Orden alfabético (A-Z)</option>

                                        </select>

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

                                    <button type="submit" name="save_settings" class="btn btn-primary">Guardar</button>

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

                            $image_data = get_images(1, 1000); // Load all images for now

                            foreach ($image_data['images'] as $image):

                                $image_name = basename($image);

                            ?>

                                <div class="col-md-3 mb-3 gallery-item">

                                    <img src="assets/<?= $image_name ?>" class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover; cursor: pointer;" data-image-name="<?= $image_name ?>">

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

        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>

        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>

        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

        <script>

        $(document).ready(function() {

            var imageTargetMode = '';
            var imageTargetInput = '';
            var imageTargetPrefix = '';

        

            $('#imageModal').on('show.bs.modal', function (event) {

                var button = $(event.relatedTarget);

                imageTargetMode = button.data('target-type') || '';
                imageTargetInput = button.data('target-input') || '';
                imageTargetPrefix = button.data('target-prefix') || '';

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

        

            $('.gallery-item img').on('click', function() {

                var imageName = $(this).data('image-name');

                if (imageTargetMode === 'field') {

                    var targetId = imageTargetInput || 'image';
                    var $input = $('#' + targetId);
                    if ($input.length) {
                        var prefix = imageTargetPrefix || '';
                        $input.val(prefix + imageName);
                    }

                } else if (imageTargetMode === 'editor') {

                    insertImageInContent(imageName);

                }

                $('#imageModal').modal('hide');

            });

        

            function insertImageInContent(imageName) {

                var contentTextArea = document.getElementById('content');

                var cursorPosition = contentTextArea.selectionStart;

                var content = contentTextArea.value;

                var textToInsert = '![](assets/' + imageName + ')';

                contentTextArea.value = content.substring(0, cursorPosition) + textToInsert + content.substring(cursorPosition);

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

        </body>

        </html>
