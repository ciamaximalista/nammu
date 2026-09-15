<?php
/**
 * Nammu — panel de administración.
 * Envío a cada red (Facebook, X, LinkedIn, Bluesky, Instagram, Telegram) y utilidades HTTP.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

function admin_send_facebook_post(string $slug, string $title, string $description, array $settings, string $urlOverride = '', string $imageUrl = ''): bool {
    $token = $settings['token'] ?? '';
    $pageId = $settings['channel'] ?? '';
    if ($token === '' || $pageId === '') {
        error_log('Facebook post error (missing credentials): token=' . ($token !== '' ? 'set' : 'empty') . ' pageId=' . ($pageId !== '' ? $pageId : 'empty'));
        return false;
    }
    $targetUrl = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    $trackedUrl = admin_add_utm_params($targetUrl, [
        'utm_source' => 'facebook',
        'utm_medium' => 'social',
    ]);
    $fediverseUrl = admin_social_fediverse_thread_url($slug, admin_social_fediverse_template($slug, $urlOverride));
    $message = admin_build_sentence_limited_social_message($title, $description, $trackedUrl, 5000, 'admin_bold_unicode_text', admin_social_appendix_text($fediverseUrl));
    $imageUrl = trim($imageUrl);
    if ($imageUrl !== '' && preg_match('#^https?://#i', $imageUrl)) {
        $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($pageId) . '/photos';
        $params = [
            'url' => $imageUrl,
            'caption' => $message,
            'access_token' => $token,
        ];
        $body = http_build_query($params);
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($body),
        ];
        $httpCode = null;
        $responseBody = admin_http_post_body_response($endpoint, $body, $headers, $httpCode);
        $ok = $responseBody !== null && ($httpCode === null || ($httpCode >= 200 && $httpCode < 300));
        if ($ok) {
            return true;
        }
        if (!$ok) {
            error_log('Facebook post error (photo): http=' . ($httpCode ?? 'n/a') . ' response=' . (string) $responseBody);
        }
        // Fallback: publish without image when media upload fails.
    }
    $endpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($pageId) . '/feed';
    $params = [
        'message' => $message,
        'access_token' => $token,
    ];
    $body = http_build_query($params);
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($body),
    ];
    $httpCode = null;
    $responseBody = admin_http_post_body_response($endpoint, $body, $headers, $httpCode);
    $ok = $responseBody !== null && ($httpCode === null || ($httpCode >= 200 && $httpCode < 300));
    if (!$ok) {
        error_log('Facebook post error (feed): http=' . ($httpCode ?? 'n/a') . ' response=' . (string) $responseBody);
    }
    return $ok;
}

function admin_send_twitter_post(string $slug, string $title, string $description, array $settings, string $urlOverride = '', string $imageUrl = '', ?string &$error = null): bool {
    $targetUrl = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    $trackedUrl = admin_add_utm_params($targetUrl, [
        'utm_source' => 'twitter',
        'utm_medium' => 'social',
    ]);
    $endpoint = 'https://api.twitter.com/2/tweets';
    $fediverseUrl = admin_social_fediverse_thread_url($slug, admin_social_fediverse_template($slug, $urlOverride));
    $text = admin_build_sentence_limited_social_message($title, $description, $trackedUrl, 280, 'admin_bold_unicode_text', admin_social_appendix_text($fediverseUrl));
    $payload = ['text' => $text];
    return admin_send_twitter_api_request($endpoint, $payload, $settings, $error);
}

function admin_twitter_percent_encode(string $value): string {
    return str_replace('%7E', '~', rawurlencode($value));
}

function admin_twitter_build_oauth_header(string $method, string $url, array $settings, ?string &$error = null, array $extraParams = []): ?string {
    $consumerKey = trim((string) ($settings['api_key'] ?? ''));
    $consumerSecret = trim((string) ($settings['api_secret'] ?? ''));
    $accessToken = trim((string) ($settings['access_token'] ?? ''));
    $accessSecret = trim((string) ($settings['access_secret'] ?? ''));
    if ($consumerKey === '' || $consumerSecret === '' || $accessToken === '' || $accessSecret === '') {
        $error = 'Faltan credenciales de X: API Key, API Key Secret, Access Token y Access Token Secret.';
        return null;
    }

    $oauth = [
        'oauth_consumer_key' => $consumerKey,
        'oauth_nonce' => bin2hex(random_bytes(16)),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => (string) time(),
        'oauth_token' => $accessToken,
        'oauth_version' => '1.0',
    ];
    $urlParts = parse_url($url);
    $baseUrl = $url;
    $queryParams = [];
    if (is_array($urlParts)) {
        if (isset($urlParts['scheme'], $urlParts['host'])) {
            $baseUrl = $urlParts['scheme'] . '://' . $urlParts['host']
                . (isset($urlParts['port']) ? ':' . $urlParts['port'] : '')
                . ($urlParts['path'] ?? '');
        }
        if (!empty($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
        }
    }
    $signatureParams = array_merge($queryParams, $extraParams, $oauth);
    ksort($signatureParams);
    $parameterPairs = [];
    foreach ($signatureParams as $key => $value) {
        $parameterPairs[] = admin_twitter_percent_encode((string) $key) . '=' . admin_twitter_percent_encode((string) $value);
    }
    $baseString = strtoupper($method) . '&' . admin_twitter_percent_encode($baseUrl) . '&' . admin_twitter_percent_encode(implode('&', $parameterPairs));
    $signingKey = admin_twitter_percent_encode($consumerSecret) . '&' . admin_twitter_percent_encode($accessSecret);
    $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

    $headerParts = [];
    foreach ($oauth as $key => $value) {
        $headerParts[] = admin_twitter_percent_encode((string) $key) . '="' . admin_twitter_percent_encode((string) $value) . '"';
    }

    return 'OAuth ' . implode(', ', $headerParts);
}

function admin_send_twitter_api_request(string $endpoint, array $payload, array $settings, ?string &$error = null): bool {
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body) || $body === '') {
        $error = 'No se pudo codificar el mensaje para X.';
        return false;
    }

    $authorization = admin_twitter_build_oauth_header('POST', $endpoint, $settings, $error);
    if ($authorization === null) {
        return false;
    }

    $headers = [
        'Authorization: ' . $authorization,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($body),
    ];
    $httpCode = null;
    $responseBody = admin_http_post_body_response($endpoint, $body, $headers, $httpCode);
    if ($responseBody !== null && $httpCode !== null && $httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    $error = 'No se pudo enviar la publicación a X. Comprueba las credenciales.';
    error_log('X post error: http=' . ($httpCode ?? 'n/a') . ' response=' . (string) $responseBody);
    if ($responseBody !== null) {
        $decoded = json_decode($responseBody, true);
        if (is_array($decoded)) {
            $message = '';
            if (isset($decoded['detail']) && is_string($decoded['detail'])) {
                $message = $decoded['detail'];
            } elseif (isset($decoded['title']) && is_string($decoded['title'])) {
                $message = $decoded['title'];
            } elseif (isset($decoded['error']) && is_string($decoded['error'])) {
                $message = $decoded['error'];
            } elseif (!empty($decoded['errors']) && is_array($decoded['errors'])) {
                $firstError = $decoded['errors'][0] ?? null;
                if (is_array($firstError)) {
                    $message = (string) ($firstError['message'] ?? $firstError['detail'] ?? $firstError['title'] ?? '');
                } elseif (is_string($firstError)) {
                    $message = $firstError;
                }
            }
            if ($message !== '') {
                $error = 'X: ' . $message;
            }
        }
    }

    return false;
}

function admin_twitter_upload_media(string $imageRef, string $imageUrl, array $settings, ?string &$error = null): ?string {
    if (!function_exists('curl_init') || !function_exists('curl_file_create')) {
        $error = 'X: el servidor no puede adjuntar imágenes porque falta soporte CURLFile.';
        return null;
    }

    $localPath = admin_local_asset_path($imageRef);
    $tmpPath = '';
    $pathForCurl = '';
    $mime = 'application/octet-stream';
    $uploadName = 'imagen';

    if ($localPath !== '') {
        $pathForCurl = $localPath;
        $uploadName = basename($localPath);
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($localPath);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
    } else {
        $binary = admin_http_get_binary($imageUrl);
        if ($binary === '') {
            $error = 'X: no se pudo descargar la imagen pública para adjuntarla.';
            return null;
        }
        $path = (string) (parse_url($imageUrl, PHP_URL_PATH) ?? '');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($binary);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
        if ($extension === '') {
            $extension = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'bin',
            };
        }
        $tmpPath = tempnam(sys_get_temp_dir(), 'nammu_x_');
        if ($tmpPath === false || @file_put_contents($tmpPath, $binary, LOCK_EX) === false) {
            $error = 'X: no se pudo preparar temporalmente la imagen.';
            return null;
        }
        $finalTmpPath = $tmpPath . '.' . $extension;
        @rename($tmpPath, $finalTmpPath);
        $tmpPath = $finalTmpPath;
        $pathForCurl = $tmpPath;
        $uploadName = basename($path !== '' ? $path : ('imagen.' . $extension));
    }

    $endpoint = 'https://upload.twitter.com/1.1/media/upload.json';
    $authorization = admin_twitter_build_oauth_header('POST', $endpoint, $settings, $error);
    if ($authorization === null) {
        if ($tmpPath !== '') {
            @unlink($tmpPath);
        }
        return null;
    }

    try {
        $payload = [
            'media' => curl_file_create($pathForCurl, $mime, $uploadName),
        ];
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authorization,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $responseBody = curl_exec($ch);
        $httpCode = null;
        if ($responseBody !== false) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
    } finally {
        if ($tmpPath !== '') {
            @unlink($tmpPath);
        }
    }

    if (!is_string($responseBody) || $responseBody === '' || $httpCode === null || $httpCode < 200 || $httpCode >= 300) {
        $error = 'X: no se pudo subir la imagen.';
        $decoded = is_string($responseBody) ? json_decode($responseBody, true) : null;
        if (is_array($decoded)) {
            $message = '';
            if (!empty($decoded['errors']) && is_array($decoded['errors'])) {
                $first = $decoded['errors'][0] ?? null;
                if (is_array($first)) {
                    $message = (string) ($first['message'] ?? '');
                }
            } elseif (isset($decoded['error'])) {
                $message = (string) $decoded['error'];
            }
            if ($message !== '') {
                $error = 'X: ' . $message;
            }
        }
        error_log('X media upload error: http=' . ($httpCode ?? 'n/a') . ' response=' . (string) $responseBody);
        return null;
    }

    $decoded = json_decode($responseBody, true);
    $mediaId = is_array($decoded) ? (string) ($decoded['media_id_string'] ?? $decoded['media_id'] ?? '') : '';
    if ($mediaId === '') {
        $error = 'X: la API no devolvió media_id.';
        error_log('X media upload error: missing media_id response=' . (string) $responseBody);
        return null;
    }

    return $mediaId;
}

function admin_send_linkedin_post(string $slug, string $title, string $description, array $settings, string $urlOverride = '', string $imageUrl = '', ?string &$error = null): bool {
    $token = trim((string) ($settings['token'] ?? ''));
    $author = trim((string) ($settings['author'] ?? ''));
    if ($token === '' || $author === '') {
        return false;
    }
    $baseUrl = nammu_base_url();
    if ($urlOverride !== '') {
        $postUrl = $urlOverride;
    } else {
        $postUrl = rtrim($baseUrl, '/') . '/' . ltrim($slug, '/');
    }
    $trackedUrl = admin_add_utm_params($postUrl, [
        'utm_source' => 'linkedin',
        'utm_medium' => 'social',
    ]);
    $fediverseUrl = admin_social_fediverse_thread_url($slug, admin_social_fediverse_template($slug, $urlOverride));
    $messageTitle = trim($title) !== '' ? trim($title) : $slug;
    $normalizedAuthor = $author;
    if (preg_match('/^urn:li:member:(.+)$/', $normalizedAuthor, $match) === 1) {
        $normalizedAuthor = 'urn:li:person:' . $match[1];
    } elseif (preg_match('/^urn:li:company:(.+)$/', $normalizedAuthor, $match) === 1) {
        $normalizedAuthor = 'urn:li:organization:' . $match[1];
    }
    if (!preg_match('/^urn:li:(person|organization):[A-Za-z0-9_-]+$/', $normalizedAuthor)) {
        $error = 'LinkedIn: el Author URN debe ser urn:li:person:ID o urn:li:organization:ID.';
        return false;
    }
    $commentary = admin_build_sentence_limited_social_message($messageTitle, $description, $trackedUrl, 3000, 'admin_bold_unicode_text', admin_social_appendix_text($fediverseUrl));
    $legacyAuthor = $normalizedAuthor;
    if (preg_match('/^urn:li:person:(.+)$/', $legacyAuthor, $match) === 1) {
        $legacyAuthor = 'urn:li:member:' . $match[1];
    } elseif (preg_match('/^urn:li:organization:(.+)$/', $legacyAuthor, $match) === 1) {
        $legacyAuthor = 'urn:li:company:' . $match[1];
    }
    $payload = [
        'author' => $normalizedAuthor,
        'lifecycleState' => 'PUBLISHED',
        'visibility' => 'PUBLIC',
        'distribution' => [
            'feedDistribution' => 'MAIN_FEED',
            'targetEntities' => [],
            'thirdPartyDistributionChannels' => [],
        ],
        'content' => [
            'article' => [
                'source' => $trackedUrl,
                'title' => $messageTitle,
            ],
        ],
        'commentary' => $commentary,
        'isReshareDisabledByAuthor' => false,
    ];
    $baseHeaders = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
        'X-Restli-Protocol-Version: 2.0.0',
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body) || $body === '') {
        $error = 'LinkedIn: no se pudo codificar la publicación.';
        return false;
    }
    $versionCandidates = [];
    $cursor = new DateTimeImmutable('first day of this month');
    for ($i = 0; $i < 24; $i++) {
        $versionCandidates[] = $cursor->format('Ym');
        $cursor = $cursor->modify('-1 month');
    }
    $lastHttpCode = null;
    $lastResponseBody = null;
    foreach ($versionCandidates as $version) {
        $headers = $baseHeaders;
        $headers[] = 'LinkedIn-Version: ' . $version;
        $headers[] = 'Content-Length: ' . strlen($body);
        $httpCode = null;
        $responseBody = admin_http_post_body_response('https://api.linkedin.com/rest/posts', $body, $headers, $httpCode);
        $lastHttpCode = $httpCode;
        $lastResponseBody = $responseBody;
        if ($responseBody !== null && $httpCode !== null && $httpCode >= 200 && $httpCode < 300) {
            return true;
        }
        $decoded = is_string($responseBody) ? json_decode($responseBody, true) : null;
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? '') : '';
        if ($message !== '' && stripos($message, 'Requested version') === false) {
            $error = 'LinkedIn: ' . $message;
            error_log('LinkedIn post error: http=' . (string) $httpCode . ' response=' . (string) $responseBody);
            return false;
        }
    }
    $decoded = is_string($lastResponseBody) ? json_decode($lastResponseBody, true) : null;
    $lastMessage = is_array($decoded) ? (string) ($decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? '') : '';

    // Fallback al endpoint clásico, más tolerante en algunas apps.
    $legacyPayload = [
        'author' => $legacyAuthor,
        'lifecycleState' => 'PUBLISHED',
        'specificContent' => [
            'com.linkedin.ugc.ShareContent' => [
                'shareCommentary' => [
                    'text' => $commentary,
                ],
                'shareMediaCategory' => 'ARTICLE',
                'media' => [
                    [
                        'status' => 'READY',
                        'originalUrl' => $trackedUrl,
                    ],
                ],
            ],
        ],
        'visibility' => [
            'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
        ],
    ];
    $legacyBody = json_encode($legacyPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($legacyBody) && $legacyBody !== '') {
        $legacyHeaders = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'X-Restli-Protocol-Version: 2.0.0',
            'Content-Length: ' . strlen($legacyBody),
        ];
        $legacyHttpCode = null;
        $legacyResponse = admin_http_post_body_response('https://api.linkedin.com/v2/ugcPosts', $legacyBody, $legacyHeaders, $legacyHttpCode);
        if ($legacyResponse !== null && $legacyHttpCode !== null && $legacyHttpCode >= 200 && $legacyHttpCode < 300) {
            return true;
        }
        $legacyDecoded = is_string($legacyResponse) ? json_decode($legacyResponse, true) : null;
        $legacyMessage = is_array($legacyDecoded) ? (string) ($legacyDecoded['message'] ?? $legacyDecoded['error_description'] ?? $legacyDecoded['error'] ?? '') : '';
        if ($legacyMessage !== '') {
            $error = 'LinkedIn: ' . $legacyMessage;
        }
        error_log('LinkedIn legacy post error: http=' . (string) $legacyHttpCode . ' response=' . (string) $legacyResponse);
    }

    if (($error === null || $error === '') && $lastMessage !== '') {
        $error = 'LinkedIn: ' . $lastMessage;
    }
    if ($error === null || $error === '') {
        $error = 'LinkedIn: error al publicar.';
    }
    error_log('LinkedIn post error: http=' . (string) $lastHttpCode . ' response=' . (string) $lastResponseBody);
    return false;
}

function admin_send_bluesky_post(string $slug, string $title, string $description, array $settings, string $urlOverride = '', string $imageUrl = '', ?string &$error = null): bool {
    if (!function_exists('admin_bluesky_build_link_facets') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
        require_once NAMMU_ROOT . '/core/admin-redes.php';
    }
    $service = trim((string) ($settings['service'] ?? ''));
    if ($service === '') {
        $service = 'https://bsky.social';
    }
    $service = rtrim($service, '/');
    $identifier = trim((string) ($settings['identifier'] ?? ''));
    $identifier = ltrim($identifier, '@');
    $identifier = preg_replace('/[\\p{Cf}\\p{Z}\\s]+/u', '', $identifier);
    $appPassword = trim((string) ($settings['app_password'] ?? ''));
    $appPassword = preg_replace('/\\s+/', '', $appPassword);
    if ($identifier === '' || $appPassword === '') {
        $error = 'Faltan credenciales de Bluesky.';
        return false;
    }
    $sessionEndpoint = $service . '/xrpc/com.atproto.server.createSession';
    $sessionPayload = json_encode([
        'identifier' => $identifier,
        'password' => $appPassword,
    ]);
    $sessionHeaders = [
        'Content-Type: application/json',
        'Content-Length: ' . strlen((string) $sessionPayload),
    ];
    $sessionCode = null;
    $sessionResponse = admin_http_post_body_response($sessionEndpoint, (string) $sessionPayload, $sessionHeaders, $sessionCode);
    if ($sessionResponse === null || $sessionCode === null || $sessionCode < 200 || $sessionCode >= 300) {
        $error = 'Error creando sesión en Bluesky.';
        if ($sessionResponse !== null) {
            $payload = json_decode($sessionResponse, true);
            if (is_array($payload) && isset($payload['error'])) {
                $error = 'Bluesky: ' . $payload['error'];
            }
        }
        return false;
    }
    $session = json_decode($sessionResponse, true);
    if (!is_array($session) || empty($session['accessJwt']) || empty($session['did'])) {
        $error = 'Respuesta inválida de Bluesky.';
        return false;
    }
    $targetUrl = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    $trackedUrl = admin_add_utm_params($targetUrl, [
        'utm_source' => 'bluesky',
        'utm_medium' => 'social',
    ]);
    $fediverseUrl = admin_social_fediverse_thread_url($slug, admin_social_fediverse_template($slug, $urlOverride));
    $textParts = [];
    $titleTrim = trim($title);
    if ($titleTrim !== '') {
        $textParts[] = admin_bold_unicode_text($titleTrim);
    } else {
        $textParts[] = 'Nueva publicación disponible';
    }
    $appendix = admin_social_appendix_text($fediverseUrl);
    if ($appendix !== '') {
        $textParts[] = $appendix;
    }
    $text = implode("\n\n", array_filter($textParts, static fn(string $part): bool => trim($part) !== ''));
    if (function_exists('mb_strlen')) {
        if (mb_strlen($text, 'UTF-8') > 300) {
            if ($appendix !== '') {
                $reserved = mb_strlen("\n\n" . $appendix, 'UTF-8');
                $available = max(1, 300 - $reserved - 1);
                $titleOnly = $titleTrim !== '' ? admin_bold_unicode_text($titleTrim) : 'Nueva publicación disponible';
                if (mb_strlen($titleOnly, 'UTF-8') > $available) {
                    $titleOnly = rtrim(mb_substr($titleOnly, 0, $available, 'UTF-8')) . '…';
                }
                $text = $titleOnly . "\n\n" . $appendix;
            } else {
                $text = mb_substr($text, 0, 299, 'UTF-8') . '…';
            }
        }
    } elseif (strlen($text) > 300) {
        if ($appendix !== '') {
            $reserved = strlen("\n\n" . $appendix);
            $available = max(1, 300 - $reserved - 1);
            $titleOnly = $titleTrim !== '' ? admin_bold_unicode_text($titleTrim) : 'Nueva publicación disponible';
            if (strlen($titleOnly) > $available) {
                $titleOnly = rtrim(substr($titleOnly, 0, $available)) . '…';
            }
            $text = $titleOnly . "\n\n" . $appendix;
        } else {
            $text = substr($text, 0, 299) . '…';
        }
    }
    $record = [
        '$type' => 'app.bsky.feed.post',
        'text' => $text,
        'createdAt' => gmdate('Y-m-d\\TH:i:s\\Z'),
    ];
    if (function_exists('admin_bluesky_build_link_facets')) {
        $facets = admin_bluesky_build_link_facets($text);
        if (!empty($facets)) {
            $record['facets'] = $facets;
        }
    }
    if ($trackedUrl !== '') {
        $embed = [
            '$type' => 'app.bsky.embed.external',
            'external' => [
                'uri' => $trackedUrl,
                'title' => $title !== '' ? $title : $trackedUrl,
                'description' => '',
            ],
        ];
        $imageUrl = trim($imageUrl);
        if ($imageUrl !== '' && preg_match('#^https?://#i', $imageUrl)) {
            $blob = admin_bluesky_upload_blob($service, $session['accessJwt'], $imageUrl);
            if ($blob !== null) {
                $embed['external']['thumb'] = $blob;
            }
        }
        if ($description !== '') {
            $desc = trim((string) $description);
            $maxDesc = 200;
            if (function_exists('mb_strlen')) {
                if (mb_strlen($desc, 'UTF-8') > $maxDesc) {
                    $desc = rtrim(mb_substr($desc, 0, $maxDesc - 1, 'UTF-8')) . '…';
                }
            } elseif (strlen($desc) > $maxDesc) {
                $desc = rtrim(substr($desc, 0, $maxDesc - 1)) . '…';
            }
            $embed['external']['description'] = $desc;
        }
        $record['embed'] = [
            '$type' => 'app.bsky.embed.external',
            'external' => $embed['external'],
        ];
    }
    $sendRecord = static function (array $recordPayload) use ($service, $session): array {
        $payload = json_encode([
            'repo' => $session['did'],
            'collection' => 'app.bsky.feed.post',
            'record' => $recordPayload,
        ]);
        $headers = [
            'Authorization: Bearer ' . $session['accessJwt'],
            'Content-Type: application/json',
            'Content-Length: ' . strlen((string) $payload),
        ];
        $httpCode = null;
        $response = admin_http_post_body_response($service . '/xrpc/com.atproto.repo.createRecord', (string) $payload, $headers, $httpCode);
        return [$httpCode, $response];
    };
    [$httpCode, $postResponse] = $sendRecord($record);
    if (($httpCode === null || $httpCode < 200 || $httpCode >= 300) && is_array($record['embed']['external'] ?? null)) {
        $decodedFirst = $postResponse !== null ? json_decode($postResponse, true) : null;
        $firstError = is_array($decodedFirst) ? (string) ($decodedFirst['error'] ?? '') : '';
        if (strcasecmp($firstError, 'BlobTooLarge') === 0 && isset($record['embed']['external']['thumb'])) {
            $recordRetry = $record;
            unset($recordRetry['embed']['external']['thumb']);
            [$httpCode, $postResponse] = $sendRecord($recordRetry);
        }
    }
    if ($httpCode !== null && $httpCode >= 200 && $httpCode < 300) {
        return true;
    }
    $error = 'No se pudo crear el post en Bluesky.';
    if ($postResponse !== null) {
        $payload = json_decode($postResponse, true);
        if (is_array($payload) && isset($payload['error'])) {
            $error = 'Bluesky: ' . $payload['error'];
        }
    }
    return false;
}

function admin_bluesky_upload_blob(string $service, string $accessToken, string $imageUrl): ?array {
    $binary = admin_http_get_binary($imageUrl);
    if ($binary === '') {
        return null;
    }
    $binary = admin_bluesky_prepare_blob_binary($binary);
    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($binary);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: ' . $mime,
        'Content-Length: ' . strlen($binary),
    ];
    $httpCode = null;
    $response = admin_http_post_body_response($service . '/xrpc/com.atproto.repo.uploadBlob', $binary, $headers, $httpCode);
    if ($response === null || $httpCode === null || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }
    $payload = json_decode($response, true);
    if (!is_array($payload) || empty($payload['blob']['ref']['$link']) || empty($payload['blob']['mimeType'])) {
        return null;
    }
    return [
        '$type' => 'blob',
        'ref' => [
            '$link' => $payload['blob']['ref']['$link'],
        ],
        'mimeType' => $payload['blob']['mimeType'],
        'size' => (int) ($payload['blob']['size'] ?? 0),
    ];
}

function admin_bluesky_prepare_blob_binary(string $binary, int $maxBytes = 900000): string
{
    if ($binary === '' || strlen($binary) <= $maxBytes) {
        return $binary;
    }
    if (class_exists('Imagick')) {
        try {
            $image = new Imagick();
            $image->readImageBlob($binary);
            $image = $image->coalesceImages();
            $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
            $width = (int) $image->getImageWidth();
            $height = (int) $image->getImageHeight();
            if ($width > 1600 || $height > 1600) {
                $image->thumbnailImage(1600, 1600, true, true);
            }
            $image->setImageFormat('jpeg');
            $image->setImageCompression(Imagick::COMPRESSION_JPEG);
            $quality = 82;
            for ($i = 0; $i < 8; $i++) {
                $image->setImageCompressionQuality($quality);
                $candidate = (string) $image->getImageBlob();
                if ($candidate !== '' && strlen($candidate) <= $maxBytes) {
                    return $candidate;
                }
                $quality -= 8;
                if ($quality < 40) {
                    break;
                }
            }
            $fallback = (string) $image->getImageBlob();
            if ($fallback !== '') {
                return strlen($fallback) < strlen($binary) ? $fallback : $binary;
            }
        } catch (Throwable $e) {
            // fallback to GD below
        }
    }
    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $resource = @imagecreatefromstring($binary);
        if ($resource !== false) {
            $width = imagesx($resource);
            $height = imagesy($resource);
            if ($width > 1600 || $height > 1600) {
                $ratio = min(1600 / max(1, $width), 1600 / max(1, $height));
                $targetW = max(1, (int) floor($width * $ratio));
                $targetH = max(1, (int) floor($height * $ratio));
                $resized = imagecreatetruecolor($targetW, $targetH);
                imagecopyresampled($resized, $resource, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
                $resource = $resized;
            }
            $quality = 82;
            $best = '';
            for ($i = 0; $i < 8; $i++) {
                ob_start();
                imagejpeg($resource, null, $quality);
                $candidate = (string) ob_get_clean();
                if ($candidate !== '') {
                    $best = $candidate;
                    if (strlen($candidate) <= $maxBytes) {
                        return $candidate;
                    }
                }
                $quality -= 8;
                if ($quality < 40) {
                    break;
                }
            }
            if ($best !== '' && strlen($best) < strlen($binary)) {
                return $best;
            }
        }
    }
    return $binary;
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

function admin_build_instagram_caption(string $slug, string $title, string $description, string $urlOverride = ''): string {
    $parts = [];
    $titleTrim = trim($title);
    if ($titleTrim !== '') {
        $parts[] = admin_bold_unicode_text($titleTrim);
    }
    $descriptionTrim = trim($description);
    if ($descriptionTrim !== '') {
        $parts[] = $descriptionTrim;
    }
    $url = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    if ($url !== '') {
        $parts[] = 'Lee el artículo: ' . $url;
    }
    $fediverseUrl = admin_social_fediverse_thread_url($slug, admin_social_fediverse_template($slug, $urlOverride));
    $appendix = admin_social_appendix_text($fediverseUrl);
    if ($appendix !== '') {
        $parts[] = $appendix;
    }
    return implode("\n\n", $parts);
}

function admin_instagram_load_image_resource(string $absolutePath, ?string &$type = null) {
    if (!is_file($absolutePath)) {
        return null;
    }
    $info = @getimagesize($absolutePath);
    if (!is_array($info) || empty($info[2])) {
        return null;
    }
    $imageType = (int) $info[2];
    $resource = null;
    if ($imageType === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
        $resource = @imagecreatefromjpeg($absolutePath);
        $type = 'jpeg';
    } elseif ($imageType === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
        $resource = @imagecreatefrompng($absolutePath);
        $type = 'png';
    } elseif ($imageType === IMAGETYPE_GIF && function_exists('imagecreatefromgif')) {
        $resource = @imagecreatefromgif($absolutePath);
        $type = 'gif';
    } elseif ($imageType === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        $resource = @imagecreatefromwebp($absolutePath);
        $type = 'webp';
    }
    return $resource ?: null;
}

function admin_prepare_instagram_image_url(string $imageTrim, string $baseUrl, ?string &$error = null): string {
    $imageUrl = function_exists('nammu_resolve_asset') ? nammu_resolve_asset($imageTrim, $baseUrl) : '';
    if ($imageUrl === null || $imageUrl === '') {
        $error = 'No se pudo resolver la URL pública de la imagen destacada.';
        return '';
    }
    $minAllowedRatio = 0.8;
    $maxAllowedRatio = 1.91;
    $projectRoot = NAMMU_ROOT;
    $imageTrimNorm = str_replace('\\', '/', $imageTrim);
    $relativePath = ltrim($imageTrimNorm, '/');
    if (preg_match('#^https?://#i', $imageTrimNorm)) {
        $imageUrlParts = @parse_url($imageTrimNorm);
        $baseUrlParts = @parse_url($baseUrl);
        $imageHost = strtolower((string) ($imageUrlParts['host'] ?? ''));
        $baseHost = strtolower((string) ($baseUrlParts['host'] ?? ''));
        $imagePath = (string) ($imageUrlParts['path'] ?? '');
        if ($imagePath !== '' && ($baseHost === '' || $imageHost === $baseHost)) {
            $relativePath = ltrim($imagePath, '/');
        }
    }
    if ($relativePath === '') {
        $urlPath = (string) (@parse_url($imageUrl, PHP_URL_PATH) ?: '');
        if ($urlPath !== '') {
            $relativePath = ltrim($urlPath, '/');
        }
    }
    $absolutePath = $relativePath !== '' ? ($projectRoot . '/' . $relativePath) : '';

    $src = null;
    $sourceMtime = '';
    if ($absolutePath !== '' && is_file($absolutePath)) {
        $srcType = null;
        $src = admin_instagram_load_image_resource($absolutePath, $srcType);
        $sourceMtime = (string) @filemtime($absolutePath);
    }
    if (!$src && function_exists('imagecreatefromstring')) {
        $rawImage = @file_get_contents($imageUrl);
        if (($rawImage === false || $rawImage === '') && function_exists('curl_init')) {
            $ch = curl_init($imageUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $rawImage = curl_exec($ch);
        }
        if (is_string($rawImage) && $rawImage !== '') {
            $src = @imagecreatefromstring($rawImage);
            $sourceMtime = (string) strlen($rawImage);
        }
    }
    if (!$src) {
        $error = 'No se pudo cargar la imagen para Instagram.';
        return '';
    }
    $width = (int) imagesx($src);
    $height = (int) imagesy($src);
    if ($width < 10 || $height < 10) {
        $error = 'La imagen destacada no tiene dimensiones válidas para Instagram.';
        return '';
    }
    $originalRatio = $height > 0 ? ($width / $height) : 0.0;
    $originalRatioAllowed = $originalRatio >= $minAllowedRatio && $originalRatio <= $maxAllowedRatio;
    if (!function_exists('imagecreatetruecolor')) {
        if (!$originalRatioAllowed) {
            $error = 'La imagen destacada tiene una proporcion no admitida por Instagram y el servidor no puede generar una variante valida (GD no disponible).';
            return '';
        }
        return $imageUrl;
    }

    // Build a square, centered variant (1:1) to avoid intermittent Instagram ratio rejections.
    $squareSide = (int) min($width, $height);
    $srcX = (int) floor(($width - $squareSide) / 2);
    $srcY = (int) floor(($height - $squareSide) / 2);
    // Instagram accepts 1:1, and a stable 1080x1080 output avoids size/ratio edge cases.
    $targetSide = 1080;
    $jpegQuality = 88;
    if (isset($GLOBALS['nammu_instagram_target_side']) && is_int($GLOBALS['nammu_instagram_target_side'])) {
        $targetSide = max(640, min(1080, (int) $GLOBALS['nammu_instagram_target_side']));
    }
    if (isset($GLOBALS['nammu_instagram_jpeg_quality']) && is_int($GLOBALS['nammu_instagram_jpeg_quality'])) {
        $jpegQuality = max(60, min(92, (int) $GLOBALS['nammu_instagram_jpeg_quality']));
    }

    $dst = imagecreatetruecolor($targetSide, $targetSide);
    if (!$dst) {
        return $imageUrl;
    }
    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $targetSide, $targetSide, $squareSide, $squareSide);

    $socialDir = $projectRoot . '/assets/social';
    if (!is_dir($socialDir)) {
        @mkdir($socialDir, 0755, true);
    }
    $hashBase = ($relativePath !== '' ? $relativePath : $imageUrl) . '|' . $sourceMtime . '|' . $width . 'x' . $height . '|sq';
    $variantName = 'instagram_' . substr(sha1($hashBase . '|q:' . $jpegQuality . '|t:' . $targetSide), 0, 20) . '.jpg';
    $variantAbsolute = $socialDir . '/' . $variantName;
    $saved = @imagejpeg($dst, $variantAbsolute, $jpegQuality);
    if (!$saved) {
        if (!$originalRatioAllowed) {
            $error = 'No se pudo generar la imagen ajustada para Instagram.';
            return '';
        }
        return $imageUrl;
    }
    $variantSize = @getimagesize($variantAbsolute);
    if (!is_array($variantSize) || (int) ($variantSize[0] ?? 0) !== 1080 || (int) ($variantSize[1] ?? 0) !== 1080) {
        @unlink($variantAbsolute);
        $error = 'No se pudo generar una imagen valida 1080x1080 para Instagram.';
        return '';
    }
    $variantUrl = rtrim($baseUrl, '/') . '/assets/social/' . rawurlencode($variantName);
    return $variantUrl;
}

function admin_prepare_podcast_artwork_image(string $imageValue, string $siteUrl = '', ?string &$error = null): string
{
    $imageValue = trim($imageValue);
    if ($imageValue === '') {
        return '';
    }
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled')) {
        $error = 'No se pudo normalizar la imagen de podcast (GD no disponible).';
        return $imageValue;
    }

    $projectRoot = NAMMU_ROOT;
    $siteHost = '';
    if ($siteUrl !== '') {
        $siteHost = strtolower((string) (@parse_url($siteUrl, PHP_URL_HOST) ?: ''));
    }

    $relativePath = '';
    if (preg_match('#^https?://#i', $imageValue)) {
        $imageHost = strtolower((string) (@parse_url($imageValue, PHP_URL_HOST) ?: ''));
        $imagePath = (string) (@parse_url($imageValue, PHP_URL_PATH) ?: '');
        if ($imagePath !== '' && ($siteHost === '' || $imageHost === $siteHost)) {
            $relativePath = ltrim($imagePath, '/');
        }
    } else {
        $relativePath = ltrim(str_replace('\\', '/', $imageValue), '/');
    }

    $src = null;
    $sourceKey = $imageValue;
    if ($relativePath !== '') {
        $absolutePath = $projectRoot . '/' . $relativePath;
        $srcType = null;
        $src = admin_instagram_load_image_resource($absolutePath, $srcType);
        if ($src) {
            $sourceKey = $relativePath . '|' . (string) @filemtime($absolutePath);
        }
    }

    if (!$src && function_exists('imagecreatefromstring') && preg_match('#^https?://#i', $imageValue)) {
        $rawImage = @file_get_contents($imageValue);
        if (($rawImage === false || $rawImage === '') && function_exists('curl_init')) {
            $ch = curl_init($imageValue);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $rawImage = curl_exec($ch);
        }
        if (is_string($rawImage) && $rawImage !== '') {
            $src = @imagecreatefromstring($rawImage);
            $sourceKey = $imageValue . '|len:' . strlen($rawImage);
        }
    }

    if (!$src) {
        $error = 'No se pudo cargar la imagen de podcast para normalizarla.';
        return $imageValue;
    }

    $width = (int) imagesx($src);
    $height = (int) imagesy($src);
    if ($width < 10 || $height < 10) {
        $error = 'La imagen de podcast no tiene dimensiones válidas.';
        return $imageValue;
    }

    if ($width === 3000 && $height === 3000) {
        return $imageValue;
    }

    // Escala para cubrir 3000x3000 y recorta el centro.
    $target = 3000;
    $scale = max($target / $width, $target / $height);
    $scaledW = (int) ceil($width * $scale);
    $scaledH = (int) ceil($height * $scale);

    $tmp = imagecreatetruecolor($scaledW, $scaledH);
    if (!$tmp) {
        return $imageValue;
    }
    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $scaledW, $scaledH, $width, $height);

    $dst = imagecreatetruecolor($target, $target);
    if (!$dst) {
        return $imageValue;
    }
    $srcX = (int) floor(($scaledW - $target) / 2);
    $srcY = (int) floor(($scaledH - $target) / 2);
    imagecopyresampled($dst, $tmp, 0, 0, $srcX, $srcY, $target, $target, $target, $target);

    $podcastDir = $projectRoot . '/assets/podcast';
    if (!is_dir($podcastDir)) {
        @mkdir($podcastDir, 0755, true);
    }
    $variantName = 'artwork_' . substr(sha1($sourceKey . '|' . $width . 'x' . $height . '|3000sq'), 0, 20) . '.jpg';
    $variantAbsolute = $podcastDir . '/' . $variantName;
    $saved = @imagejpeg($dst, $variantAbsolute, 90);


    if (!$saved) {
        $error = 'No se pudo guardar la imagen normalizada de podcast.';
        return $imageValue;
    }

    $check = @getimagesize($variantAbsolute);
    if (!is_array($check) || (int) ($check[0] ?? 0) !== 3000 || (int) ($check[1] ?? 0) !== 3000) {
        @unlink($variantAbsolute);
        $error = 'No se pudo generar una imagen 3000x3000 válida para podcast.';
        return $imageValue;
    }

    return 'assets/podcast/' . $variantName;
}

function admin_probe_public_url_headers(string $url): array {
    $headers = @get_headers($url, true);
    if (!is_array($headers) || empty($headers[0])) {
        return ['ok' => false, 'status' => '', 'content_type' => ''];
    }
    $statusLine = is_array($headers[0]) ? (string) end($headers[0]) : (string) $headers[0];
    $contentTypeRaw = $headers['Content-Type'] ?? '';
    if (is_array($contentTypeRaw)) {
        $contentTypeRaw = (string) end($contentTypeRaw);
    } else {
        $contentTypeRaw = (string) $contentTypeRaw;
    }
    $contentType = strtolower(trim(explode(';', $contentTypeRaw)[0]));
    $isOkStatus = stripos($statusLine, ' 200 ') !== false;
    $isImage = strncmp($contentType, 'image/', 6) === 0;
    return [
        'ok' => $isOkStatus && $isImage,
        'status' => $statusLine,
        'content_type' => $contentType,
    ];
}

function admin_wait_instagram_media_ready(string $creationId, string $token, int $maxAttempts = 12, int $delaySeconds = 2, ?array &$lastPayload = null): bool {
    $lastPayload = null;
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $statusEndpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($creationId)
            . '?fields=status_code,status,error_message&access_token=' . rawurlencode($token);
        $payload = admin_http_get_json($statusEndpoint);
        if (is_array($payload)) {
            $lastPayload = $payload;
            if (isset($payload['error']['message'])) {
                return false;
            }
            $statusCode = strtoupper((string) ($payload['status_code'] ?? ''));
            if ($statusCode === 'FINISHED') {
                return true;
            }
            if ($statusCode === 'ERROR' || !empty($payload['error_message'])) {
                return false;
            }
        }
        if ($attempt < $maxAttempts - 1) {
            sleep($delaySeconds);
        }
    }
    return false;
}

function admin_send_instagram_post(string $slug, string $title, string $image, array $settings, string $description = '', string $urlOverride = '', ?string &$error = null): bool {
    $token = trim((string) ($settings['token'] ?? ''));
    $accountId = trim((string) ($settings['channel'] ?? ''));
    if ($token === '' || $accountId === '') {
        $error = 'Falta token o ID de cuenta de Instagram.';
        return false;
    }
    $imageTrim = trim($image);
    if ($imageTrim === '') {
        $error = 'Instagram requiere una imagen destacada.';
        return false;
    }
    $baseUrl = admin_base_url();
    $GLOBALS['nammu_instagram_target_side'] = 1080;
    $GLOBALS['nammu_instagram_jpeg_quality'] = 88;
    $imageUrl = admin_prepare_instagram_image_url($imageTrim, $baseUrl, $error);
    unset($GLOBALS['nammu_instagram_target_side'], $GLOBALS['nammu_instagram_jpeg_quality']);
    if ($imageUrl === '') {
        return false;
    }
    $probe = admin_probe_public_url_headers($imageUrl);
    if (!($probe['ok'] ?? false)) {
        $status = (string) ($probe['status'] ?? '');
        $contentType = (string) ($probe['content_type'] ?? '');
        $error = 'Instagram: la URL de imagen no es accesible como imagen pública (HTTP=' . $status . ', Content-Type=' . $contentType . ').';
        error_log('Instagram post error (image_url_probe): slug=' . $slug . ' imageUrl=' . $imageUrl . ' status=' . $status . ' contentType=' . $contentType);
        return false;
    }
    $targetUrl = $urlOverride !== '' ? $urlOverride : admin_public_post_url($slug);
    $trackedUrl = admin_add_utm_params($targetUrl, [
        'utm_source' => 'instagram',
        'utm_medium' => 'social',
    ]);
    $caption = admin_build_instagram_caption($slug, $title, $description, $trackedUrl);
    $createEndpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($accountId) . '/media';
    $createPayload = [
        'image_url' => $imageUrl,
        'caption' => $caption,
        'access_token' => $token,
    ];
    $createResponse = admin_http_post_form_json($createEndpoint, $createPayload);
    $createErrorMessage = is_array($createResponse) ? (string) ($createResponse['error']['message'] ?? '') : '';
    if ((!is_array($createResponse) || empty($createResponse['id'])) && $createErrorMessage !== '') {
        $mightBeMediaIssue = stripos($createErrorMessage, 'aspect ratio') !== false
            || stripos($createErrorMessage, 'only photo or video') !== false
            || stripos($createErrorMessage, 'image') !== false
            || stripos($createErrorMessage, 'media') !== false;
        if ($mightBeMediaIssue) {
            $retryError = null;
            $GLOBALS['nammu_instagram_target_side'] = 960;
            $GLOBALS['nammu_instagram_jpeg_quality'] = 76;
            $retryImageUrl = admin_prepare_instagram_image_url($imageTrim, $baseUrl, $retryError);
            unset($GLOBALS['nammu_instagram_target_side'], $GLOBALS['nammu_instagram_jpeg_quality']);
            if ($retryImageUrl !== '' && $retryImageUrl !== $imageUrl) {
                $retryProbe = admin_probe_public_url_headers($retryImageUrl);
                if (!empty($retryProbe['ok'])) {
                    $createPayload['image_url'] = $retryImageUrl;
                    $createResponse = admin_http_post_form_json($createEndpoint, $createPayload);
                }
            }
        }
    }
    if (!is_array($createResponse) || empty($createResponse['id'])) {
        if (is_array($createResponse) && isset($createResponse['error']['message'])) {
            $error = 'Instagram: ' . (string) $createResponse['error']['message'];
        }
        error_log('Instagram post error (create_media): slug=' . $slug . ' accountId=' . $accountId . ' imageUrl=' . $imageUrl . ' response=' . json_encode($createResponse, JSON_UNESCAPED_UNICODE));
        return false;
    }
    $creationId = (string) $createResponse['id'];
    $mediaStatus = null;
    $mediaReady = admin_wait_instagram_media_ready($creationId, $token, 12, 2, $mediaStatus);
    if (!$mediaReady) {
        $statusCode = is_array($mediaStatus) ? (string) ($mediaStatus['status_code'] ?? '') : '';
        $statusMessage = '';
        if (is_array($mediaStatus)) {
            $statusMessage = (string) ($mediaStatus['error']['message'] ?? ($mediaStatus['error_message'] ?? ($mediaStatus['status'] ?? '')));
        }
        error_log('Instagram post warning (media_not_ready_yet): slug=' . $slug . ' accountId=' . $accountId . ' creationId=' . $creationId . ' statusCode=' . $statusCode . ' payload=' . json_encode($mediaStatus, JSON_UNESCAPED_UNICODE));
        // Do not abort here. Some containers become publishable even when status polling is inconclusive.
    }
    $publishEndpoint = 'https://graph.facebook.com/v17.0/' . rawurlencode($accountId) . '/media_publish';
    $publishResponse = null;
    $publishAttempts = 8;
    for ($attempt = 0; $attempt < $publishAttempts; $attempt++) {
        $publishResponse = admin_http_post_form_json($publishEndpoint, [
            'creation_id' => $creationId,
            'access_token' => $token,
        ]);
        if (is_array($publishResponse) && !empty($publishResponse['id'])) {
            return true;
        }
        $message = is_array($publishResponse) ? (string) ($publishResponse['error']['message'] ?? '') : '';
        $isMediaNotReady = stripos($message, 'Media ID is not available') !== false;
        $isStillProcessing = stripos($message, 'not finished processing') !== false || stripos($message, 'is still being processed') !== false;
        if (($isMediaNotReady || $isStillProcessing) && $attempt < $publishAttempts - 1) {
            sleep(3);
            continue;
        }
        break;
    }
    if (is_array($publishResponse) && isset($publishResponse['error']['message'])) {
        $error = 'Instagram: ' . (string) $publishResponse['error']['message'];
    }
    error_log('Instagram post error (publish_media): slug=' . $slug . ' accountId=' . $accountId . ' response=' . json_encode($publishResponse, JSON_UNESCAPED_UNICODE));
    return false;
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

function admin_http_post_multipart_json(string $url, array $params, array $headers = [], ?int &$httpCode = null): ?array {
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $responseBody = curl_exec($ch);
    if ($responseBody !== false) {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }
    if (!is_string($responseBody) || $responseBody === '') {
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

function admin_send_telegram_message(string $token, string $chatId, string $text, ?string $parseMode = null, ?string &$error = null): bool {
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
        $error = 'Telegram: no se recibió respuesta de la API.';
        return false;
    }
    $decoded = json_decode($responseBody, true);
    if (is_array($decoded) && isset($decoded['ok'])) {
        if ((bool) $decoded['ok']) {
            return true;
        }
        $error = 'Telegram: ' . (string) ($decoded['description'] ?? 'error desconocido');
        return false;
    }
    if ($httpCode !== null) {
        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }
        $error = 'Telegram: HTTP ' . $httpCode . '.';
        return false;
    }
    $error = 'Telegram: no se pudo enviar el mensaje.';
    return false;
}

function admin_send_telegram_photo(string $token, string $chatId, string $photoUrl, string $caption, ?string &$error = null): bool {
    $endpoint = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendPhoto';
    $tempPath = null;
    $upload = function_exists('admin_telegram_prepare_upload') ? admin_telegram_prepare_upload($photoUrl, $tempPath) : null;
    if (is_array($upload) && function_exists('curl_init') && function_exists('curl_file_create')) {
        $payload = [
            'chat_id' => $chatId,
            'photo' => curl_file_create((string) $upload['path'], (string) $upload['mime'], (string) $upload['name']),
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false,
        ];
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $responseBody = curl_exec($ch);
        $httpCode = null;
        if ($responseBody !== false) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }
        if ($tempPath !== null && $tempPath !== '') {
            @unlink($tempPath);
        }
        if ($responseBody === false || $responseBody === null) {
            $error = 'Telegram: no se recibió respuesta de la API al subir la imagen.';
            return false;
        }
        $decoded = json_decode($responseBody, true);
        if (is_array($decoded) && isset($decoded['ok'])) {
            if ((bool) $decoded['ok']) {
                return true;
            }
            $error = 'Telegram: ' . (string) ($decoded['description'] ?? 'error desconocido');
            return false;
        }
        if ($httpCode !== null && $httpCode >= 200 && $httpCode < 300) {
            return true;
        }
        $error = 'Telegram: no se pudo enviar la imagen.';
        return false;
    }
    $payload = [
        'chat_id' => $chatId,
        'photo' => $photoUrl,
        'caption' => $caption,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => false,
    ];
    $response = admin_http_post_form_json($endpoint, $payload);
    if (is_array($response) && isset($response['ok'])) {
        if ((bool) $response['ok']) {
            return true;
        }
        $error = 'Telegram: ' . (string) ($response['description'] ?? 'error desconocido');
        return false;
    }
    $error = 'Telegram: no se pudo enviar la imagen.';
    return false;
}
