<?php
/**
 * Nammu — panel de administración.
 * URLs públicas de entradas, podcasts, newsletters, itinerarios y recursos.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

function admin_public_post_url(string $slug): string {
    $base = admin_base_url();
    if ($base === '') {
        $settings = get_settings();
        $siteUrl = trim((string) ($settings['site_url'] ?? ''));
        if ($siteUrl !== '') {
            $base = rtrim($siteUrl, '/');
        }
    }
    $path = '/' . ltrim($slug, '/');
    if ($base === '') {
        return $path;
    }
    return $base . $path;
}

function admin_public_podcast_url(string $slug): string {
    $base = admin_base_url();
    if ($base === '') {
        $settings = get_settings();
        $siteUrl = trim((string) ($settings['site_url'] ?? ''));
        if ($siteUrl !== '') {
            $base = rtrim($siteUrl, '/');
        }
    }
    $path = '/podcast/' . rawurlencode(ltrim($slug, '/'));
    if ($base === '') {
        return $path;
    }
    return $base . $path;
}

function admin_public_newsletter_url(string $slug): string {
    $base = admin_base_url();
    if ($base === '') {
        $settings = get_settings();
        $siteUrl = trim((string) ($settings['site_url'] ?? ''));
        if ($siteUrl !== '') {
            $base = rtrim($siteUrl, '/');
        }
    }
    $path = '/newsletters/' . rawurlencode(ltrim($slug, '/'));
    if ($base === '') {
        return $path;
    }
    return $base . $path;
}

/**
 * @return string[]
 */
function admin_parse_related_slugs_input(string $raw): array {
    return nammu_parse_related_slugs_input($raw);
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
    if ($base === '') {
        $settings = get_settings();
        $siteUrl = trim((string) ($settings['site_url'] ?? ''));
        if ($siteUrl !== '') {
            $base = rtrim($siteUrl, '/');
        }
    }
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

function admin_local_asset_path(string $pathOrUrl): string {
    $value = trim($pathOrUrl);
    if ($value === '' || !defined('ASSETS_DIR')) {
        return '';
    }
    if (preg_match('#^https?://#i', $value)) {
        $urlPath = (string) (parse_url($value, PHP_URL_PATH) ?? '');
        if ($urlPath === '') {
            return '';
        }
        $normalized = ltrim($urlPath, '/');
        if (!str_starts_with($normalized, 'assets/')) {
            return '';
        }
        $relative = substr($normalized, 7);
    } else {
        $normalized = ltrim(str_replace('\\', '/', $value), '/');
        $relative = str_starts_with($normalized, 'assets/') ? substr($normalized, 7) : $normalized;
    }
    if ($relative === '') {
        return '';
    }
    $absolute = rtrim(ASSETS_DIR, '/') . '/' . $relative;
    return is_file($absolute) ? $absolute : '';
}

function admin_public_itinerary_url(string $slug): string {
    $base = admin_base_url();
    if ($base === '') {
        $settings = get_settings();
        $siteUrl = trim((string) ($settings['site_url'] ?? ''));
        if ($siteUrl !== '') {
            $base = rtrim($siteUrl, '/');
        }
    }
    $path = '/itinerarios/' . rawurlencode($slug);
    return $base === '' ? $path : $base . $path;
}
