<?php
/**
 * Nammu — panel de administración.
 * Protección CSRF del panel: token de sesión e inyección automática en formularios POST.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

function admin_csrf_token(): string
{
    if (empty($_SESSION['nammu_csrf_token']) || !is_string($_SESSION['nammu_csrf_token'])) {
        $_SESSION['nammu_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['nammu_csrf_token'];
}

function admin_csrf_input(): string
{
    return '<input type="hidden" name="_nammu_csrf" value="' . htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function admin_csrf_is_valid($token): bool
{
    $token = is_string($token) ? $token : '';
    return $token !== '' && hash_equals(admin_csrf_token(), $token);
}

function admin_start_csrf_form_injection(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    admin_csrf_token();
    ob_start(static function (string $html): string {
        if ($html === '' || stripos($html, '<form') === false) {
            return $html;
        }
        $input = admin_csrf_input();
        $html = preg_replace_callback('/<form\b[^>]*>/i', static function (array $matches) use ($input): string {
            $tag = $matches[0];
            if (!preg_match('/\bmethod\s*=\s*([\'"]?)post\1/i', $tag)) {
                return $tag;
            }
            if (stripos($tag, '_nammu_csrf') !== false) {
                return $tag;
            }
            return $tag . "\n" . $input;
        }, $html) ?? $html;
        $tokenJson = json_encode(admin_csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        if (is_string($tokenJson) && stripos($html, '</body>') !== false && stripos($html, 'window.NAMMU_CSRF_TOKEN') === false) {
            $script = '<script>window.NAMMU_CSRF_TOKEN=' . $tokenJson . ';document.addEventListener("submit",function(e){var f=e.target;if(!f||String(f.method||"").toLowerCase()!=="post"||f.querySelector("input[name=\'_nammu_csrf\']"))return;var i=document.createElement("input");i.type="hidden";i.name="_nammu_csrf";i.value=window.NAMMU_CSRF_TOKEN;f.appendChild(i);},true);</script>';
            $html = str_ireplace('</body>', $script . '</body>', $html);
        }
        return $html;
    });
}
