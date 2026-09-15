<?php
/**
 * Nammu — panel de administración.
 * Lectura y escritura de contenidos en content/: front matter, listados, slugs y autoguardado.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

function get_all_posts_metadata() {
    $posts = [];
    $files = glob(CONTENT_DIR . '/*.md');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $metadata = parse_yaml_front_matter($content);
        $visibilityRaw = strtolower(trim((string) ($metadata['Visibility'] ?? $metadata['visibility'] ?? 'public')));
        $posts[] = [
            'filename' => basename($file),
            'metadata' => $metadata,
        ];
    }
    return $posts;
}

function parse_yaml_front_matter($content) {
    $metadata = [];
    if (!is_string($content)) {
        return $metadata;
    }
    if (preg_match('/^---\s*\R(.*?)\R---\s*\R?/s', $content, $matches) === 1) {
        $yaml = (string) ($matches[1] ?? '');
        $lines = preg_split('/\R/', $yaml) ?: [];
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
        $visibilityRaw = strtolower(trim((string) ($metadata['Visibility'] ?? $metadata['visibility'] ?? 'public')));
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
            'status' => ($template === 'newsletter' && $status === 'newsletter') ? 'newsletter' : ($isDraft ? 'draft' : 'published'),
            'publish_at' => $metadata['PublishAt'] ?? '',
            'visibility' => in_array($visibilityRaw, ['private', 'privada', '1', 'true', 'yes', 'on'], true) ? 'private' : 'public',
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
    if (!is_string($content)) {
        return null;
    }
    $metadata = parse_yaml_front_matter($content);
    $body = $content;
    if (preg_match('/^---\s*\R(.*?)\R---\s*\R?(.*)$/s', $content, $matches) === 1) {
        $body = (string) ($matches[2] ?? '');
    }

    return [
        'metadata' => $metadata,
        'content' => $body,
    ];
}

function admin_chmod_content_file(string $path): void
{
    $contentDir = realpath(CONTENT_DIR);
    $realPath = realpath($path);
    if ($contentDir === false || $realPath === false || !is_file($realPath)) {
        return;
    }
    $contentPrefix = rtrim($contentDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if ($realPath !== $contentDir && strncmp($realPath, $contentPrefix, strlen($contentPrefix)) !== 0) {
        return;
    }
    if (function_exists('nammu_apply_shared_permissions')) {
        nammu_apply_shared_permissions($realPath, 0664, dirname($realPath));
    } else {
        @chmod($realPath, 0664);
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
    $typeRaw = trim((string) ($fields['type'] ?? 'Entrada'));
    if ($typeRaw === 'Página') {
        $type = 'Página';
        $template = 'page';
    } elseif ($typeRaw === 'Podcast') {
        $type = 'Podcast';
        $template = 'podcast';
    } elseif ($typeRaw === 'Newsletter') {
        $type = 'Newsletter';
        $template = 'newsletter';
    } else {
        $type = 'Entrada';
        $template = 'post';
    }
    $pageVisibilityInput = strtolower(trim((string) ($fields['page_visibility'] ?? 'public')));
    $pageVisibility = ($type === 'Página' && $pageVisibilityInput === 'private') ? 'private' : 'public';
    $statusInput = strtolower(trim((string) ($fields['status'] ?? '')));
    $status = in_array($statusInput, ['draft', 'published'], true) ? $statusInput : 'draft';
    $lang = trim((string) ($fields['lang'] ?? ''));
    $relatedInput = trim((string) ($fields['related_slugs'] ?? ''));
    $hasRelatedField = array_key_exists('related_slugs', $fields);
    $relatedSlugs = [];

    $existingFilename = nammu_normalize_filename((string) ($fields['filename'] ?? ''));
    $isExistingFile = $existingFilename !== '' && is_file(CONTENT_DIR . '/' . $existingFilename);
    $targetFilename = $existingFilename;
    $ordo = 0;
    $existingMeta = [];

    if ($isExistingFile) {
        $existing = get_post_content($existingFilename);
        $existingMeta = is_array($existing['metadata'] ?? null) ? $existing['metadata'] : [];
        $ordo = (int) ($existingMeta['Ordo'] ?? 0);
        if ($lang === '') {
            $lang = trim((string) ($existingMeta['Lang'] ?? ''));
        }
        if (!in_array($status, ['draft', 'published'], true)) {
            $status = strtolower($existingMeta['Status'] ?? 'draft');
            if ($status !== 'draft' && $status !== 'published') {
                $status = 'draft';
            }
        }
        if ($type === 'Entrada' && isset($existingMeta['Template'])) {
            $existingTemplate = strtolower(trim((string) $existingMeta['Template']));
            if (in_array($existingTemplate, ['post', 'single'], true)) {
                $template = $existingTemplate;
            }
        }
        if ($template !== 'post' && $template !== 'podcast') {
            $relatedInput = '';
        } elseif ($hasRelatedField && $relatedInput === '') {
            $existingRelatedRaw = trim((string) ($existingMeta['Related'] ?? $existingMeta['related'] ?? ''));
            if ($existingRelatedRaw !== '') {
                $relatedSlugs = admin_parse_related_slugs_input($existingRelatedRaw);
            }
        }
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

    if (($template === 'post' || $template === 'podcast') && empty($relatedSlugs)) {
        $relatedSlugs = admin_parse_related_slugs_input($relatedInput);
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
    if ($lang !== '') {
        $file_content .= "Lang: " . $lang . "\n";
    }
    if ($template === 'page') {
        $file_content .= "Visibility: " . $pageVisibility . "\n";
    }
    if (!empty($relatedSlugs) && in_array($template, ['post', 'podcast'], true)) {
        $file_content .= "Related: " . implode(', ', $relatedSlugs) . "\n";
    }
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
    if (file_put_contents($tempPath, $file_content, LOCK_EX) === false) {
        @unlink($tempPath);
        $result['message'] = 'No se pudieron guardar los cambios antes de recargar la página.';
        return $result;
    }
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
