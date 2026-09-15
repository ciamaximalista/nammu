<?php
/**
 * Nammu — panel de administración.
 * Integración con Google Search Console y Bing Webmaster Tools.
 *
 * Extraído de admin.php; se carga desde admin.php (y desde cualquier script que necesite el panel).
 */

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
