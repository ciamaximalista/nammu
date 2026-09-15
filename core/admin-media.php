<?php
/**
 * Nammu — panel de administración.
 * Biblioteca de recursos (assets/): extensiones, subidas, variantes WebP, etiquetas y tipos MIME.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

function nammu_allowed_media_extensions(): array {
    return [
        'jpg','jpeg','png','gif','webp','svg',
        'mp4','webm','mov','m4v','ogv','ogg',
        'mp3','wav','flac','m4a','aac','oga',
        'pdf','epub','doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp','md','txt','rtf'
    ];
}

function nammu_upload_limits_label(): string {
    $uploadMax = ini_get('upload_max_filesize') ?: 'desconocido';
    $postMax = ini_get('post_max_size') ?: 'desconocido';
    return 'upload_max_filesize=' . $uploadMax . ', post_max_size=' . $postMax;
}

function nammu_admin_wants_json_response(): bool {
    $ajaxFlag = $_POST['nammu_ajax'] ?? $_GET['nammu_ajax'] ?? '';
    if ((string) $ajaxFlag === '1') {
        return true;
    }
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return is_string($requestedWith) && strtolower($requestedWith) === 'xmlhttprequest';
}

function nammu_admin_send_json_response(array $payload): void {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function nammu_admin_media_item_payload(string $relative): array {
    $relative = str_replace('\\', '/', ltrim($relative, '/'));
    $name = basename($relative);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $documentExts = ['pdf','doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp','md','txt','rtf'];
    $type = 'image';
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
    $tags = load_media_tags()[$relative] ?? [];
    return [
        'name' => $name,
        'relative' => $relative,
        'src' => 'assets/' . $relative,
        'type' => $type,
        'extension' => $ext,
        'mime' => $mime,
        'tags' => is_array($tags) ? array_values(array_map('strval', $tags)) : [],
    ];
}

function nammu_is_generated_webp_variant(string $pathOrName): bool {
    $name = strtolower(basename($pathOrName));
    return (bool) preg_match('/\.(?:jpe?g|png|gif)\.webp$/i', $name);
}

function nammu_generate_webp_variant_for_asset(string $absolutePath): ?string {
    if (!is_file($absolutePath) || !function_exists('imagewebp') || !function_exists('getimagesize')) {
        return null;
    }
    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    if ($extension === 'webp' || nammu_is_generated_webp_variant($absolutePath)) {
        return null;
    }
    $imageInfo = @getimagesize($absolutePath);
    if (!is_array($imageInfo)) {
        return null;
    }
    $type = (int) ($imageInfo[2] ?? 0);
    $src = null;
    if ($type === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
        $src = @imagecreatefromjpeg($absolutePath);
    } elseif ($type === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
        $src = @imagecreatefrompng($absolutePath);
    } elseif ($type === IMAGETYPE_GIF && function_exists('imagecreatefromgif')) {
        $src = @imagecreatefromgif($absolutePath);
    }
    if (!$src) {
        return null;
    }
    if (function_exists('imagepalettetotruecolor')) {
        @imagepalettetotruecolor($src);
    }
    @imagealphablending($src, true);
    @imagesavealpha($src, true);

    $target = $absolutePath . '.webp';
    $tmp = $target . '.tmp-' . getmypid();
    $ok = @imagewebp($src, $tmp, 82);
    if (!$ok || !is_file($tmp) || filesize($tmp) <= 0) {
        @unlink($tmp);
        return null;
    }
    if (!@rename($tmp, $target)) {
        @unlink($tmp);
        return null;
    }
    if (function_exists('nammu_apply_shared_permissions')) {
        nammu_apply_shared_permissions($target, 0664, dirname($target));
    } else {
        @chmod($target, 0664);
    }
    return $target;
}

function get_media_items($page = 1, $per_page = 24) {
    $extensions = nammu_allowed_media_extensions();
    $patterns = array_merge($extensions, array_map('strtoupper', $extensions));
    $pattern = ASSETS_DIR . '/*.{'.implode(',', $patterns).'}';
    $files = glob($pattern, GLOB_BRACE) ?: [];
    $items = [];
    foreach ($files as $file) {
        if (nammu_is_generated_webp_variant($file)) {
            continue;
        }
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
    if (function_exists('nammu_atomic_write_file')) {
        nammu_atomic_write_file(MEDIA_TAGS_FILE, $json);
    } else {
        @file_put_contents(MEDIA_TAGS_FILE, $json, LOCK_EX);
        @chmod(MEDIA_TAGS_FILE, 0664);
    }
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
