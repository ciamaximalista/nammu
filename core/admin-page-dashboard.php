<?php if ($page === 'dashboard'): ?>
    <?php
    $postsMetadata = get_all_posts_metadata();
    $postCount = 0;
    $pageCount = 0;
    $postCountsByYear = [];
    $newsletterCount = 0;
    $podcastCount = 0;
    foreach ($postsMetadata as $item) {
        $status = strtolower((string) ($item['metadata']['Status'] ?? 'published'));
        if ($status === 'draft') {
            continue;
        }
        $template = strtolower($item['metadata']['Template'] ?? 'post');
        if ($template === 'page') {
            $pageCount++;
        } elseif ($template === 'newsletter') {
            $newsletterCount++;
        } elseif ($template === 'podcast') {
            $podcastCount++;
        } elseif (in_array($template, ['post', 'single'], true)) {
            $postCount++;
            $dateValue = $item['metadata']['Date'] ?? ($item['metadata']['Updated'] ?? '');
            $timestamp = $dateValue !== '' ? strtotime($dateValue) : false;
            if ($timestamp === false) {
                $filename = $item['filename'] ?? '';
                $filePath = $filename !== '' ? CONTENT_DIR . '/' . $filename : '';
                $timestamp = ($filePath !== '' && is_file($filePath)) ? @filemtime($filePath) : false;
            }
            if ($timestamp !== false) {
                $year = (int) date('Y', $timestamp);
                if ($year > 0) {
                    $postCountsByYear[$year] = ($postCountsByYear[$year] ?? 0) + 1;
                }
            }
        }
    }
    if (!empty($postCountsByYear)) {
        krsort($postCountsByYear);
    }
    $itineraryCount = 0;
    $itineraryTitleMap = [];
    try {
        $itineraries = admin_itinerary_repository()->all();
        $itineraryCount = count($itineraries);
        foreach ($itineraries as $itinerary) {
            if (method_exists($itinerary, 'getSlug') && method_exists($itinerary, 'getTitle')) {
                $itineraryTitleMap[$itinerary->getSlug()] = $itinerary->getTitle();
            }
        }
    } catch (Throwable $e) {
        $itineraryCount = 0;
        $itineraries = [];
    }

    $analytics = function_exists('nammu_load_analytics') ? nammu_load_analytics() : [];
    $visitorsDaily = $analytics['visitors']['daily'] ?? [];
    $postsStats = $analytics['content']['posts'] ?? [];
    $pagesStats = $analytics['content']['pages'] ?? [];
    $platformDaily = $analytics['platform']['daily'] ?? [];
    $sourcesDaily = $analytics['sources']['daily'] ?? [];
    $searchesDaily = $analytics['searches']['daily'] ?? [];
    $botsDaily = $analytics['bots']['daily'] ?? [];
    $indexnowLog = function_exists('admin_indexnow_load_log') ? admin_indexnow_load_log() : [];
    $indexnowErrors = [];
    if (is_array($indexnowLog['errors'] ?? null)) {
        $indexnowErrors = array_values(array_filter($indexnowLog['errors'], static function ($item) {
            return is_array($item) && !empty($item['endpoint']);
        }));
    }
    $indexnowUrls = is_array($indexnowLog['urls'] ?? null) ? $indexnowLog['urls'] : [];
    $indexnowResponses = is_array($indexnowLog['responses'] ?? null) ? $indexnowLog['responses'] : [];
    $indexnowTimestamp = isset($indexnowLog['timestamp']) ? (int) $indexnowLog['timestamp'] : 0;
    $indexnowHasErrors = !empty($indexnowErrors);
    $indexnowHasLog = $indexnowTimestamp > 0;
    $gscSettings = $settings['search_console'] ?? [];
    $gscProperty = trim((string) ($gscSettings['property'] ?? ''));
    $gscClientId = trim((string) ($gscSettings['client_id'] ?? ''));
    $gscClientSecret = trim((string) ($gscSettings['client_secret'] ?? ''));
    $gscRefreshToken = trim((string) ($gscSettings['refresh_token'] ?? ''));
    $gscTotals28 = null;
    $gscTotals7 = null;
    $gscQueries28 = [];
    $gscQueries7 = [];
    $gscPages28 = [];
    $gscPages7 = [];
    $gscCountries28 = [];
    $gscCountries7 = [];
    $gscSitemapInfo = [
        'last_crawl' => '',
    ];
    $gscError = '';
    $gscCachePath = dirname(__DIR__) . '/config/gsc-cache.json';
    $gscCacheTtl = 24 * 60 * 60;
    $gscCache = null;
    $gscUpdatedAtLabel = '';
    $gscForceRefresh = isset($_GET['gsc_refresh']) && $_GET['gsc_refresh'] === '1';
    $bingSettings = $settings['bing_webmaster'] ?? [];
    $bingSiteUrl = trim((string) ($bingSettings['site_url'] ?? ''));
    $bingSiteUrlNormalized = $bingSiteUrl !== '' ? rtrim($bingSiteUrl, '/') . '/' : '';
    $bingApiKey = trim((string) ($bingSettings['api_key'] ?? ''));
    $bingHasOauth = !empty($bingSettings['refresh_token']) || !empty($bingSettings['access_token']);
    $bingTotals28 = null;
    $bingTotals7 = null;
    $bingQueries28 = [];
    $bingQueries7 = [];
    $bingPages28 = [];
    $bingPages7 = [];
    $bingError = '';
    $bingCachePath = dirname(__DIR__) . '/config/bing-cache.json';
    $bingCacheTtl = 24 * 60 * 60;
    $bingCache = null;
    $bingUpdatedAtLabel = '';
    $bingForceRefresh = isset($_GET['bing_refresh']) && $_GET['bing_refresh'] === '1';
    $bingDebug = isset($_GET['bing_debug']) && $_GET['bing_debug'] === '1';
    if ($bingDebug) {
        $GLOBALS['bing_debug_log'] = [];
    }
    $gscCountryNames = [
        'AD' => 'Andorra',
        'AE' => 'Emiratos Árabes Unidos',
        'AF' => 'Afganistán',
        'AG' => 'Antigua y Barbuda',
        'AI' => 'Anguila',
        'AL' => 'Albania',
        'AM' => 'Armenia',
        'AO' => 'Angola',
        'AR' => 'Argentina',
        'AS' => 'Samoa Americana',
        'AT' => 'Austria',
        'AU' => 'Australia',
        'AW' => 'Aruba',
        'AZ' => 'Azerbaiyán',
        'BA' => 'Bosnia y Herzegovina',
        'BB' => 'Barbados',
        'BD' => 'Bangladés',
        'BE' => 'Bélgica',
        'BF' => 'Burkina Faso',
        'BG' => 'Bulgaria',
        'BH' => 'Barein',
        'BI' => 'Burundi',
        'BJ' => 'Benín',
        'BM' => 'Bermudas',
        'BN' => 'Brunei',
        'BO' => 'Bolivia',
        'BR' => 'Brasil',
        'BS' => 'Bahamas',
        'BT' => 'Bután',
        'BW' => 'Botsuana',
        'BY' => 'Bielorrusia',
        'BZ' => 'Belice',
        'CA' => 'Canadá',
        'CD' => 'República Democrática del Congo',
        'CF' => 'República Centroafricana',
        'CG' => 'República del Congo',
        'CH' => 'Suiza',
        'CI' => 'Costa de Marfil',
        'CL' => 'Chile',
        'CM' => 'Camerún',
        'CN' => 'China',
        'CO' => 'Colombia',
        'CR' => 'Costa Rica',
        'CU' => 'Cuba',
        'CV' => 'Cabo Verde',
        'CY' => 'Chipre',
        'CZ' => 'República Checa',
        'DE' => 'Alemania',
        'DJ' => 'Yibuti',
        'DK' => 'Dinamarca',
        'DM' => 'Dominica',
        'DO' => 'República Dominicana',
        'DZ' => 'Argelia',
        'EC' => 'Ecuador',
        'EE' => 'Estonia',
        'EG' => 'Egipto',
        'ER' => 'Eritrea',
        'ES' => 'España',
        'ET' => 'Etiopía',
        'FI' => 'Finlandia',
        'FJ' => 'Fiyi',
        'FM' => 'Micronesia',
        'FR' => 'Francia',
        'GA' => 'Gabón',
        'GB' => 'Reino Unido',
        'GD' => 'Granada',
        'GE' => 'Georgia',
        'GF' => 'Guayana Francesa',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GL' => 'Groenlandia',
        'GM' => 'Gambia',
        'GN' => 'Guinea',
        'GP' => 'Guadalupe',
        'GQ' => 'Guinea Ecuatorial',
        'GR' => 'Grecia',
        'GT' => 'Guatemala',
        'GU' => 'Guam',
        'GY' => 'Guyana',
        'HK' => 'Hong Kong',
        'HN' => 'Honduras',
        'HR' => 'Croacia',
        'HT' => 'Haití',
        'HU' => 'Hungría',
        'ID' => 'Indonesia',
        'IE' => 'Irlanda',
        'IL' => 'Israel',
        'IN' => 'India',
        'IQ' => 'Irak',
        'IR' => 'Irán',
        'IS' => 'Islandia',
        'IT' => 'Italia',
        'JM' => 'Jamaica',
        'JO' => 'Jordania',
        'JP' => 'Japón',
        'KE' => 'Kenia',
        'KG' => 'Kirguistán',
        'KH' => 'Camboya',
        'KI' => 'Kiribati',
        'KM' => 'Comoras',
        'KN' => 'San Cristobal y Nieves',
        'KP' => 'Corea del Norte',
        'KR' => 'Corea del Sur',
        'KW' => 'Kuwait',
        'KZ' => 'Kazajistán',
        'LA' => 'Laos',
        'LB' => 'Líbano',
        'LC' => 'Santa Lucia',
        'LI' => 'Liechtenstein',
        'LK' => 'Sri Lanka',
        'LR' => 'Liberia',
        'LS' => 'Lesoto',
        'LT' => 'Lituania',
        'LU' => 'Luxemburgo',
        'LV' => 'Letonia',
        'LY' => 'Libia',
        'MA' => 'Marruecos',
        'MC' => 'Mónaco',
        'MD' => 'Moldavia',
        'ME' => 'Montenegro',
        'MG' => 'Madagascar',
        'MH' => 'Islas Marshall',
        'MK' => 'Macedonia del Norte',
        'ML' => 'Mali',
        'MM' => 'Birmania',
        'MN' => 'Mongolia',
        'MO' => 'Macao',
        'MQ' => 'Martinica',
        'MR' => 'Mauritania',
        'MT' => 'Malta',
        'MU' => 'Mauricio',
        'MV' => 'Maldivas',
        'MW' => 'Malaui',
        'MX' => 'México',
        'MY' => 'Malasia',
        'MZ' => 'Mozambique',
        'NA' => 'Namibia',
        'NC' => 'Nueva Caledonia',
        'NE' => 'Níger',
        'NG' => 'Nigeria',
        'NI' => 'Nicaragua',
        'NL' => 'Países Bajos',
        'NO' => 'Noruega',
        'NP' => 'Nepal',
        'NR' => 'Nauru',
        'NZ' => 'Nueva Zelanda',
        'OM' => 'Omán',
        'PA' => 'Panamá',
        'PE' => 'Perú',
        'PF' => 'Polinesia Francesa',
        'PG' => 'Papúa Nueva Guinea',
        'PH' => 'Filipinas',
        'PK' => 'Pakistán',
        'PL' => 'Polonia',
        'PR' => 'Puerto Rico',
        'PT' => 'Portugal',
        'PY' => 'Paraguay',
        'QA' => 'Catar',
        'RE' => 'Reunión',
        'RO' => 'Rumanía',
        'RS' => 'Serbia',
        'RU' => 'Rusia',
        'RW' => 'Ruanda',
        'SA' => 'Arabia Saudí',
        'SB' => 'Islas Salomon',
        'SC' => 'Seychelles',
        'SD' => 'Sudán',
        'SE' => 'Suecia',
        'SG' => 'Singapur',
        'SI' => 'Eslovenia',
        'SK' => 'Eslovaquia',
        'SL' => 'Sierra Leona',
        'SM' => 'San Marino',
        'SN' => 'Senegal',
        'SO' => 'Somalia',
        'SR' => 'Surinam',
        'ST' => 'Santo Tome y Principe',
        'SV' => 'El Salvador',
        'SY' => 'Siria',
        'SZ' => 'Suazilandia',
        'TD' => 'Chad',
        'TG' => 'Togo',
        'TH' => 'Tailandia',
        'TJ' => 'Tayikistán',
        'TL' => 'Timor Oriental',
        'TM' => 'Turkmenistán',
        'TN' => 'Túnez',
        'TO' => 'Tonga',
        'TR' => 'Turquía',
        'TT' => 'Trinidad y Tobago',
        'TW' => 'Taiwan',
        'TZ' => 'Tanzania',
        'UA' => 'Ucrania',
        'UG' => 'Uganda',
        'US' => 'Estados Unidos',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistán',
        'VA' => 'Vaticano',
        'VE' => 'Venezuela',
        'VG' => 'Islas Virgenes Britanicas',
        'VI' => 'Islas Virgenes de Estados Unidos',
        'VN' => 'Vietnam',
        'WS' => 'Samoa',
        'YE' => 'Yemen',
        'ZA' => 'Sudáfrica',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabue',
    ];
    $gscCountryNames3 = [
        'ARE' => 'Emiratos Árabes Unidos',
        'ARG' => 'Argentina',
        'AUS' => 'Australia',
        'AUT' => 'Austria',
        'BEL' => 'Bélgica',
        'BOL' => 'Bolivia',
        'BRA' => 'Brasil',
        'CAN' => 'Canadá',
        'CHE' => 'Suiza',
        'CHL' => 'Chile',
        'CHN' => 'China',
        'COL' => 'Colombia',
        'CUB' => 'Cuba',
        'DEU' => 'Alemania',
        'DOM' => 'República Dominicana',
        'ECU' => 'Ecuador',
        'ESP' => 'España',
        'FRA' => 'Francia',
        'GBR' => 'Reino Unido',
        'GTM' => 'Guatemala',
        'HND' => 'Honduras',
        'IRL' => 'Irlanda',
        'ITA' => 'Italia',
        'MEX' => 'México',
        'NIC' => 'Nicaragua',
        'NLD' => 'Países Bajos',
        'NOR' => 'Noruega',
        'PAN' => 'Panamá',
        'PER' => 'Perú',
        'PRT' => 'Portugal',
        'PRY' => 'Paraguay',
        'ROU' => 'Uruguay',
        'RUS' => 'Rusia',
        'SLV' => 'El Salvador',
        'SWE' => 'Suecia',
        'USA' => 'Estados Unidos',
        'VEN' => 'Venezuela',
    ];
    $gscResolveCountry = static function (string $value) use ($gscCountryNames, $gscCountryNames3): string {
        $code = strtoupper(trim($value));
        if ($code === '') {
            return '';
        }
        if (strlen($code) === 2) {
            if (isset($gscCountryNames[$code])) {
                return $gscCountryNames[$code];
            }
            if (function_exists('locale_get_display_region')) {
                $localized = locale_get_display_region('es_' . $code);
                if (is_string($localized) && $localized !== '' && $localized !== $code) {
                    return $localized;
                }
            }
            return 'País desconocido';
        }
        if (strlen($code) === 3) {
            if (isset($gscCountryNames3[$code])) {
                return $gscCountryNames3[$code];
            }
            if (function_exists('locale_get_display_region')) {
                $localized = locale_get_display_region('es_' . $code);
                if (is_string($localized) && $localized !== '' && $localized !== $code) {
                    return $localized;
                }
            }
            return 'País desconocido';
        }
        return $value !== '' ? $value : 'País desconocido';
    };
    if (is_file($gscCachePath)) {
        $rawCache = @file_get_contents($gscCachePath);
        $decodedCache = is_string($rawCache) && $rawCache !== '' ? json_decode($rawCache, true) : null;
        if (is_array($decodedCache)) {
            $gscCache = $decodedCache;
        }
    }

    $today = new DateTimeImmutable('today');
    $last30Start = $today->modify('-29 days');
    $last7Start = $today->modify('-6 days');
    $gscCacheValid = is_array($gscCache)
        && ($gscCache['property'] ?? '') === $gscProperty
        && isset($gscCache['updated_at']);
    $gscCacheHasNew = $gscCacheValid
        && isset($gscCache['totals7'], $gscCache['totals28'], $gscCache['queries7'], $gscCache['queries28']);
    $now = new DateTimeImmutable('now');
    $refreshAnchor = (new DateTimeImmutable('today'))->setTime(6, 0);
    if ($now < $refreshAnchor) {
        $refreshAnchor = $refreshAnchor->modify('-1 day');
    }
    $cacheUpdatedAt = $gscCacheValid ? (int) $gscCache['updated_at'] : 0;
    $cacheIsDailyFresh = $cacheUpdatedAt >= $refreshAnchor->getTimestamp();
    $gscCacheFresh = $gscCacheHasNew
        && $cacheIsDailyFresh
        && (time() - $cacheUpdatedAt) < $gscCacheTtl
        && !$gscForceRefresh;
    if ($gscCacheFresh) {
        $gscTotals28 = $gscCache['totals28'] ?? null;
        $gscTotals7 = $gscCache['totals7'] ?? null;
        $gscQueries28 = $gscCache['queries28'] ?? [];
        $gscQueries7 = $gscCache['queries7'] ?? [];
        $gscPages28 = $gscCache['pages28'] ?? [];
        $gscPages7 = $gscCache['pages7'] ?? [];
        $gscCountries28 = $gscCache['countries28'] ?? [];
        $gscCountries7 = $gscCache['countries7'] ?? [];
        $gscSitemapInfo = $gscCache['sitemap'] ?? ['last_crawl' => ''];
        $gscUpdatedAtLabel = !empty($gscCache['updated_at'])
            ? (new DateTimeImmutable('@' . (int) $gscCache['updated_at']))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d/m/y')
            : '';
    } elseif ($gscProperty !== '' && $gscClientId !== '' && $gscClientSecret !== '' && $gscRefreshToken !== '' && function_exists('admin_google_refresh_access_token')) {
        try {
            $tokenData = admin_google_refresh_access_token($gscClientId, $gscClientSecret, $gscRefreshToken);
            $accessToken = $tokenData['access_token'] ?? '';
            if ($accessToken === '') {
                throw new RuntimeException('No se pudo obtener el access token.');
            }
            $bingEnd = $today->modify('-1 day');
            $endDate = $bingEnd->format('Y-m-d');
            $start28 = $bingEnd->modify('-27 days')->format('Y-m-d');
            $start7 = $bingEnd->modify('-6 days')->format('Y-m-d');
            $normalizeTotals = static function (array $response): array {
                $row = [];
                if (isset($response['rows'][0]) && is_array($response['rows'][0])) {
                    $row = $response['rows'][0];
                }
                return [
                    'clicks' => (int) round((float) ($row['clicks'] ?? 0)),
                    'impressions' => (int) round((float) ($row['impressions'] ?? 0)),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                    'position' => (float) ($row['position'] ?? 0),
                ];
            };
            $totals28Resp = admin_gsc_query($accessToken, $gscProperty, $start28, $endDate, [], 1);
            $totals7Resp = admin_gsc_query($accessToken, $gscProperty, $start7, $endDate, [], 1);
            $gscTotals28 = $normalizeTotals($totals28Resp);
            $gscTotals7 = $normalizeTotals($totals7Resp);
            $normalizeQueries = static function (array $response): array {
                $rows = $response['rows'] ?? [];
                if (!is_array($rows)) {
                    return [];
                }
                $output = [];
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $keys = $row['keys'] ?? [];
                    $term = is_array($keys) ? (string) ($keys[0] ?? '') : '';
                    $term = trim($term);
                    if ($term === '') {
                        continue;
                    }
                    $output[] = [
                        'term' => $term,
                        'clicks' => (int) round((float) ($row['clicks'] ?? 0)),
                        'impressions' => (int) round((float) ($row['impressions'] ?? 0)),
                    ];
                }
                usort($output, static function (array $a, array $b): int {
                    if ($a['clicks'] === $b['clicks']) {
                        return $b['impressions'] <=> $a['impressions'];
                    }
                    return $b['clicks'] <=> $a['clicks'];
                });
                return array_slice($output, 0, 10);
            };
            $queries28Resp = admin_gsc_query($accessToken, $gscProperty, $start28, $endDate, ['query'], 50);
            $queries7Resp = admin_gsc_query($accessToken, $gscProperty, $start7, $endDate, ['query'], 50);
            $gscQueries28 = $normalizeQueries($queries28Resp);
            $gscQueries7 = $normalizeQueries($queries7Resp);
            $normalizeDimensions = static function (array $response, string $labelKey): array {
                $rows = $response['rows'] ?? [];
                if (!is_array($rows)) {
                    return [];
                }
                $output = [];
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $keys = $row['keys'] ?? [];
                    $label = is_array($keys) ? (string) ($keys[0] ?? '') : '';
                    $label = trim($label);
                    if ($label === '') {
                        continue;
                    }
                    $output[] = [
                        $labelKey => $label,
                        'clicks' => (int) round((float) ($row['clicks'] ?? 0)),
                        'impressions' => (int) round((float) ($row['impressions'] ?? 0)),
                    ];
                }
                usort($output, static function (array $a, array $b): int {
                    if ($a['clicks'] === $b['clicks']) {
                        return $b['impressions'] <=> $a['impressions'];
                    }
                    return $b['clicks'] <=> $a['clicks'];
                });
                return array_slice($output, 0, 10);
            };
            $pages7Resp = admin_gsc_query($accessToken, $gscProperty, $start7, $endDate, ['page'], 50);
            $pages28Resp = admin_gsc_query($accessToken, $gscProperty, $start28, $endDate, ['page'], 50);
            $countries7Resp = admin_gsc_query($accessToken, $gscProperty, $start7, $endDate, ['country'], 50);
            $countries28Resp = admin_gsc_query($accessToken, $gscProperty, $start28, $endDate, ['country'], 50);
            $gscPages7 = $normalizeDimensions($pages7Resp, 'page');
            $gscPages28 = $normalizeDimensions($pages28Resp, 'page');
            $gscCountries7 = $normalizeDimensions($countries7Resp, 'country');
            $gscCountries28 = $normalizeDimensions($countries28Resp, 'country');
            $sitemapUrl = '';
            $baseUrlValue = $settings['site_url'] ?? '';
            if (!is_string($baseUrlValue)) {
                $baseUrlValue = '';
            }
            $baseUrlValue = rtrim(trim($baseUrlValue), '/');
            if ($baseUrlValue === '' && function_exists('nammu_base_url')) {
                $baseUrlValue = rtrim(nammu_base_url(), '/');
            }
            if ($baseUrlValue !== '') {
                $sitemapUrl = $baseUrlValue . '/sitemap.xml';
            }
            if ($sitemapUrl !== '' && function_exists('admin_gsc_get')) {
                $siteParam = rawurlencode($gscProperty);
                $sitemapParam = rawurlencode($sitemapUrl);
                $sitemapResp = admin_gsc_get($accessToken, 'https://www.googleapis.com/webmasters/v3/sites/' . $siteParam . '/sitemaps/' . $sitemapParam);
                $lastDownloaded = $sitemapResp['lastDownloaded'] ?? '';
                if ($lastDownloaded !== '') {
                    try {
                        $lastDt = new DateTimeImmutable($lastDownloaded);
                        $gscSitemapInfo['last_crawl'] = $lastDt->format('d/m/y');
                    } catch (Throwable $e) {
                        $gscSitemapInfo['last_crawl'] = '';
                    }
                }
            }
            $cachePayload = [
                'property' => $gscProperty,
                'updated_at' => time(),
                'totals28' => $gscTotals28,
                'totals7' => $gscTotals7,
                'queries28' => $gscQueries28,
                'queries7' => $gscQueries7,
                'pages28' => $gscPages28,
                'pages7' => $gscPages7,
                'countries28' => $gscCountries28,
                'countries7' => $gscCountries7,
                'sitemap' => $gscSitemapInfo,
            ];
            $cacheJson = json_encode($cachePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($cacheJson !== false) {
                @file_put_contents($gscCachePath, $cacheJson, LOCK_EX);
            }
            $gscUpdatedAtLabel = (new DateTimeImmutable('now'))->format('d/m/y');
        } catch (Throwable $e) {
            if ($gscCacheHasNew) {
                $gscTotals28 = $gscCache['totals28'] ?? null;
                $gscTotals7 = $gscCache['totals7'] ?? null;
                $gscQueries28 = $gscCache['queries28'] ?? [];
                $gscQueries7 = $gscCache['queries7'] ?? [];
                $gscPages28 = $gscCache['pages28'] ?? [];
                $gscPages7 = $gscCache['pages7'] ?? [];
                $gscCountries28 = $gscCache['countries28'] ?? [];
                $gscCountries7 = $gscCache['countries7'] ?? [];
                $gscSitemapInfo = $gscCache['sitemap'] ?? ['last_crawl' => ''];
                $gscUpdatedAtLabel = !empty($gscCache['updated_at'])
                    ? (new DateTimeImmutable('@' . (int) $gscCache['updated_at']))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d/m/y')
                    : '';
            } else {
                $gscError = $e->getMessage();
            }
        }
    }

    if (!empty($gscCountries7)) {
        $normalizedCountries7 = [];
        foreach ($gscCountries7 as $row) {
            $name = $gscResolveCountry((string) ($row['country'] ?? ''));
            if ($name === '') {
                continue;
            }
            $row['country'] = $name;
            $normalizedCountries7[] = $row;
        }
        $gscCountries7 = $normalizedCountries7;
    }
    if (!empty($gscCountries28)) {
        $normalizedCountries28 = [];
        foreach ($gscCountries28 as $row) {
            $name = $gscResolveCountry((string) ($row['country'] ?? ''));
            if ($name === '') {
                continue;
            }
            $row['country'] = $name;
            $normalizedCountries28[] = $row;
        }
        $gscCountries28 = $normalizedCountries28;
    }

    if (is_file($bingCachePath)) {
        $rawCache = @file_get_contents($bingCachePath);
        $decodedCache = is_string($rawCache) && $rawCache !== '' ? json_decode($rawCache, true) : null;
        if (is_array($decodedCache)) {
            $bingCache = $decodedCache;
        }
    }

    $bingCacheValid = is_array($bingCache)
        && ($bingCache['site_url'] ?? '') === $bingSiteUrl
        && isset($bingCache['updated_at']);
    $bingCacheHasNew = $bingCacheValid
        && isset($bingCache['totals7'], $bingCache['totals28'], $bingCache['queries7'], $bingCache['queries28']);
    $bingCacheUpdatedAt = $bingCacheValid ? (int) $bingCache['updated_at'] : 0;
    $bingCacheIsDailyFresh = $bingCacheUpdatedAt >= $refreshAnchor->getTimestamp();
    $bingCacheFresh = $bingCacheHasNew
        && $bingCacheIsDailyFresh
        && (time() - $bingCacheUpdatedAt) < $bingCacheTtl
        && !$bingForceRefresh;
    if ($bingCacheFresh) {
        $bingTotals28 = $bingCache['totals28'] ?? null;
        $bingTotals7 = $bingCache['totals7'] ?? null;
        $bingQueries28 = $bingCache['queries28'] ?? [];
        $bingQueries7 = $bingCache['queries7'] ?? [];
        $bingPages28 = $bingCache['pages28'] ?? [];
        $bingPages7 = $bingCache['pages7'] ?? [];
        $bingUpdatedAtLabel = !empty($bingCache['updated_at'])
            ? (new DateTimeImmutable('@' . (int) $bingCache['updated_at']))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d/m/y')
            : '';
    } elseif ($bingSiteUrl !== '' && ($bingApiKey !== '' || $bingHasOauth) && function_exists('admin_bing_request_with_dates')) {
        $bingExtractRows = static function ($payload, array $keys): array {
            if (!is_array($payload)) {
                return [];
            }
            $data = $payload;
            if (isset($data['d']) && is_array($data['d'])) {
                $data = $data['d'];
            }
            foreach ($keys as $key) {
                if (isset($data[$key]) && is_array($data[$key])) {
                    return array_values($data[$key]);
                }
            }
            if (array_values($data) === $data) {
                return $data;
            }
            return [];
        };
        $bingValue = static function (array $row, array $keys, float $default = 0.0): float {
            foreach ($keys as $key) {
                if (isset($row[$key]) && $row[$key] !== '') {
                    return (float) $row[$key];
                }
                $lower = strtolower($key);
                foreach ($row as $rowKey => $value) {
                    if (strtolower((string) $rowKey) === $lower && $value !== '') {
                        return (float) $value;
                    }
                }
            }
            return $default;
        };
        $bingParseDate = static function ($value): ?int {
            if (is_numeric($value)) {
                return (int) floor(((float) $value) / 1000);
            }
            if (!is_string($value) || $value === '') {
                return null;
            }
            if (preg_match('/Date\\((\\d{10,})/', $value, $match)) {
                return (int) floor(((float) $match[1]) / 1000);
            }
            $timestamp = strtotime($value);
            return $timestamp !== false ? $timestamp : null;
        };
        $bingFilterRowsByDate = static function (array $rows, string $startDate, string $endDate) use ($bingParseDate): array {
            $startTs = strtotime($startDate . ' 00:00:00');
            $endTs = strtotime($endDate . ' 23:59:59');
            if ($startTs === false || $endTs === false) {
                return $rows;
            }
            $filtered = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $dateValue = $row['Date'] ?? $row['date'] ?? null;
                $rowTs = $dateValue !== null ? $bingParseDate($dateValue) : null;
                if ($rowTs === null) {
                    $filtered[] = $row;
                    continue;
                }
                if ($rowTs >= $startTs && $rowTs <= $endTs) {
                    $filtered[] = $row;
                }
            }
            return $filtered;
        };
        
        $bingNormalizeTotals = static function (array $rows) use ($bingValue): array {
            $totalClicks = 0;
            $totalImpressions = 0;
            $weightedPosition = 0.0;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $clicks = $bingValue($row, ['Clicks', 'clicks']);
                $impressions = $bingValue($row, ['Impressions', 'impressions']);
                $position = $bingValue($row, ['AvgPosition', 'AveragePosition', 'position', 'avgPosition']);
                $totalClicks += (int) round($clicks);
                $totalImpressions += (int) round($impressions);
                if ($impressions > 0) {
                    $weightedPosition += $position * $impressions;
                }
            }
            $ctr = $totalImpressions > 0 ? $totalClicks / $totalImpressions : 0.0;
            $avgPosition = $totalImpressions > 0 ? $weightedPosition / $totalImpressions : 0.0;
            return [
                'clicks' => $totalClicks,
                'impressions' => $totalImpressions,
                'ctr' => $ctr,
                'position' => $avgPosition,
            ];
        };
        $bingNormalizeDimension = static function (array $rows, array $labelKeys, string $labelKey) use ($bingValue): array {
            $bucket = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $label = '';
                foreach ($labelKeys as $key) {
                    if (!isset($row[$key])) {
                        continue;
                    }
                    $raw = $row[$key];
                    if (is_array($raw)) {
                        foreach ($raw as $rawValue) {
                            if (is_scalar($rawValue) && trim((string) $rawValue) !== '') {
                                $label = trim((string) $rawValue);
                                break 2;
                            }
                        }
                    } elseif (is_scalar($raw) && trim((string) $raw) !== '') {
                        $label = trim((string) $raw);
                        break;
                    }
                }
                if ($label === '') {
                    foreach ($row as $rawValue) {
                        if (!is_scalar($rawValue)) {
                            continue;
                        }
                        $candidate = trim((string) $rawValue);
                        if ($candidate === '') {
                            continue;
                        }
                        if (strpos($candidate, '://') !== false || str_starts_with($candidate, '/') || strpos($candidate, '.blog') !== false || strpos($candidate, '.com') !== false) {
                            $label = $candidate;
                            break;
                        }
                    }
                }
                if ($label === '') {
                    continue;
                }
                $key = $label;
                if (!isset($bucket[$key])) {
                    $bucket[$key] = [
                        $labelKey => $label,
                        'clicks' => 0,
                        'impressions' => 0,
                    ];
                }
                $bucket[$key]['clicks'] += (int) round($bingValue($row, ['Clicks', 'clicks']));
                $bucket[$key]['impressions'] += (int) round($bingValue($row, ['Impressions', 'impressions']));
            }
            $output = array_values($bucket);
            usort($output, static function (array $a, array $b): int {
                if ($a['clicks'] === $b['clicks']) {
                    return $b['impressions'] <=> $a['impressions'];
                }
                return $b['clicks'] <=> $a['clicks'];
            });
            return array_slice($output, 0, 10);
        };
        try {
            if (!$bingHasOauth && $bingApiKey === '') {
                throw new RuntimeException('Conecta Bing Webmaster Tools desde Configuración para ver los datos.');
            }
            $endDate = $today->format('Y-m-d');
            $start30 = $today->modify('-29 days')->format('Y-m-d');
            $start7 = $today->modify('-6 days')->format('Y-m-d');
            $baseParams = [
                'siteUrl' => $bingSiteUrlNormalized !== '' ? $bingSiteUrlNormalized : $bingSiteUrl,
            ];
            if ($bingApiKey !== '') {
                $baseParams['apikey'] = $bingApiKey;
            }
            $totals28Resp = admin_bing_request_with_dates_multi(['GetRankAndTrafficStats'], $baseParams, $start30, $endDate);
            $totals7Resp = admin_bing_request_with_dates_multi(['GetRankAndTrafficStats'], $baseParams, $start7, $endDate);
            $totals28Rows = $bingFilterRowsByDate($bingExtractRows($totals28Resp, ['RankAndTrafficStats', 'rankAndTrafficStats', 'SiteStats', 'siteStats']), $start30, $endDate);
            $totals7Rows = $bingFilterRowsByDate($bingExtractRows($totals7Resp, ['RankAndTrafficStats', 'rankAndTrafficStats', 'SiteStats', 'siteStats']), $start7, $endDate);
            $bingTotals28 = $bingNormalizeTotals($totals28Rows);
            $bingTotals7 = $bingNormalizeTotals($totals7Rows);

            $queries28Resp = admin_bing_request_with_dates_multi(['GetQueryStats', 'GetPageQueryStats'], $baseParams, $start30, $endDate);
            $queries7Resp = admin_bing_request_with_dates_multi(['GetQueryStats', 'GetPageQueryStats'], $baseParams, $start7, $endDate);
            $queries28Rows = $bingFilterRowsByDate($bingExtractRows($queries28Resp, ['QueryStats', 'queryStats', 'PageQueryStats', 'pageQueryStats']), $start30, $endDate);
            $queries7Rows = $bingFilterRowsByDate($bingExtractRows($queries7Resp, ['QueryStats', 'queryStats', 'PageQueryStats', 'pageQueryStats']), $start7, $endDate);
            $bingQueries28 = $bingNormalizeDimension($queries28Rows, ['Query', 'query'], 'term');
            $bingQueries7 = $bingNormalizeDimension($queries7Rows, ['Query', 'query'], 'term');

            $pages28Resp = admin_bing_request_with_dates_multi(['GetPageStats', 'GetPageQueryStats'], $baseParams, $start30, $endDate);
            $pages7Resp = admin_bing_request_with_dates_multi(['GetPageStats', 'GetPageQueryStats'], $baseParams, $start7, $endDate);
            $pages28Rows = $bingFilterRowsByDate($bingExtractRows($pages28Resp, ['PageStats', 'pageStats', 'PageQueryStats', 'pageQueryStats']), $start30, $endDate);
            $pages7Rows = $bingFilterRowsByDate($bingExtractRows($pages7Resp, ['PageStats', 'pageStats', 'PageQueryStats', 'pageQueryStats']), $start7, $endDate);
            $bingPages28 = $bingNormalizeDimension($pages28Rows, ['Page', 'page', 'Url', 'url', 'PageUrl', 'pageUrl', 'Uri', 'uri', 'Query', 'query'], 'page');
            $bingPages7 = $bingNormalizeDimension($pages7Rows, ['Page', 'page', 'Url', 'url', 'PageUrl', 'pageUrl', 'Uri', 'uri', 'Query', 'query'], 'page');

            if (($bingTotals28['clicks'] ?? 0) === 0 && ($bingTotals28['impressions'] ?? 0) === 0) {
                $alternateSiteUrl = $bingSiteUrl;
                if ($alternateSiteUrl !== '' && $alternateSiteUrl !== ($baseParams['siteUrl'] ?? '')) {
                    $fallbackParams = [
                        'siteUrl' => $alternateSiteUrl,
                    ];
                    if ($bingApiKey !== '') {
                        $fallbackParams['apikey'] = $bingApiKey;
                    }
                    $fallbackTotals28 = admin_bing_request_with_dates_multi(['GetRankAndTrafficStats'], $fallbackParams, $start30, $endDate);
                    $fallbackTotals7 = admin_bing_request_with_dates_multi(['GetRankAndTrafficStats'], $fallbackParams, $start7, $endDate);
                    $fallbackQueries28 = admin_bing_request_with_dates_multi(['GetQueryStats', 'GetPageQueryStats'], $fallbackParams, $start30, $endDate);
                    $fallbackQueries7 = admin_bing_request_with_dates_multi(['GetQueryStats', 'GetPageQueryStats'], $fallbackParams, $start7, $endDate);
                    $fallbackPages28 = admin_bing_request_with_dates_multi(['GetPageStats', 'GetPageQueryStats'], $fallbackParams, $start30, $endDate);
                    $fallbackPages7 = admin_bing_request_with_dates_multi(['GetPageStats', 'GetPageQueryStats'], $fallbackParams, $start7, $endDate);

                    $fallbackTotals28Rows = $bingFilterRowsByDate($bingExtractRows($fallbackTotals28, ['RankAndTrafficStats', 'rankAndTrafficStats', 'SiteStats', 'siteStats']), $start30, $endDate);
                    $fallbackTotals7Rows = $bingFilterRowsByDate($bingExtractRows($fallbackTotals7, ['RankAndTrafficStats', 'rankAndTrafficStats', 'SiteStats', 'siteStats']), $start7, $endDate);
                    $fallbackQueries28Rows = $bingFilterRowsByDate($bingExtractRows($fallbackQueries28, ['QueryStats', 'queryStats', 'PageQueryStats', 'pageQueryStats']), $start30, $endDate);
                    $fallbackQueries7Rows = $bingFilterRowsByDate($bingExtractRows($fallbackQueries7, ['QueryStats', 'queryStats', 'PageQueryStats', 'pageQueryStats']), $start7, $endDate);
                    $fallbackPages28Rows = $bingFilterRowsByDate($bingExtractRows($fallbackPages28, ['PageStats', 'pageStats', 'PageQueryStats', 'pageQueryStats']), $start30, $endDate);
                    $fallbackPages7Rows = $bingFilterRowsByDate($bingExtractRows($fallbackPages7, ['PageStats', 'pageStats', 'PageQueryStats', 'pageQueryStats']), $start7, $endDate);

                    $bingTotals28 = $bingNormalizeTotals($fallbackTotals28Rows);
                    $bingTotals7 = $bingNormalizeTotals($fallbackTotals7Rows);
                    $bingQueries28 = $bingNormalizeDimension($fallbackQueries28Rows, ['Query', 'query'], 'term');
                    $bingQueries7 = $bingNormalizeDimension($fallbackQueries7Rows, ['Query', 'query'], 'term');
                    $bingPages28 = $bingNormalizeDimension($fallbackPages28Rows, ['Page', 'page', 'Url', 'url', 'PageUrl', 'pageUrl', 'Uri', 'uri', 'Query', 'query'], 'page');
                    $bingPages7 = $bingNormalizeDimension($fallbackPages7Rows, ['Page', 'page', 'Url', 'url', 'PageUrl', 'pageUrl', 'Uri', 'uri', 'Query', 'query'], 'page');
                }
            }

            $cachePayload = [
                'site_url' => $bingSiteUrl,
                'updated_at' => time(),
                'totals28' => $bingTotals28,
                'totals7' => $bingTotals7,
                'queries28' => $bingQueries28,
                'queries7' => $bingQueries7,
                'pages28' => $bingPages28,
                'pages7' => $bingPages7,
            ];
            $cacheJson = json_encode($cachePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($cacheJson !== false) {
                @file_put_contents($bingCachePath, $cacheJson, LOCK_EX);
            }
            $bingUpdatedAtLabel = (new DateTimeImmutable('now'))->format('d/m/y');
        } catch (Throwable $e) {
            if ($bingCacheHasNew) {
                $bingTotals28 = $bingCache['totals28'] ?? null;
                $bingTotals7 = $bingCache['totals7'] ?? null;
                $bingQueries28 = $bingCache['queries28'] ?? [];
                $bingQueries7 = $bingCache['queries7'] ?? [];
                $bingPages28 = $bingCache['pages28'] ?? [];
                $bingPages7 = $bingCache['pages7'] ?? [];
                $bingUpdatedAtLabel = !empty($bingCache['updated_at'])
                    ? (new DateTimeImmutable('@' . (int) $bingCache['updated_at']))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d/m/y')
                    : '';
            } else {
                $bingError = $e->getMessage();
            }
        }
        if ($bingDebug && isset($GLOBALS['bing_debug_log']) && is_array($GLOBALS['bing_debug_log'])) {
            $debugPayload = [
                'timestamp' => time(),
                'site_url' => $bingSiteUrl,
                'entries' => $GLOBALS['bing_debug_log'],
            ];
            $debugPath = dirname(__DIR__) . '/config/bing-debug.json';
            $debugJson = json_encode($debugPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (is_string($debugJson)) {
                @file_put_contents($debugPath, $debugJson, LOCK_EX);
            }
        }
    }

    $sumRange = static function (array $daily, DateTimeImmutable $start, DateTimeImmutable $end): int {
        $total = 0;
        $startKey = $start->format('Y-m-d');
        $endKey = $end->format('Y-m-d');
        foreach ($daily as $day => $count) {
            if (!is_string($day) || $day < $startKey || $day > $endKey) {
                continue;
            }
            if (is_array($count)) {
                $total += (int) ($count['views'] ?? 0);
            } else {
                $total += (int) $count;
            }
        }
        return $total;
    };
    $sumAllViews = static function (array $daily): int {
        $total = 0;
        foreach ($daily as $count) {
            if (is_array($count)) {
                $total += (int) ($count['views'] ?? 0);
            } else {
                $total += (int) $count;
            }
        }
        return $total;
    };

    $uniqueRange = static function (array $daily, DateTimeImmutable $start, DateTimeImmutable $end): int {
        $uids = [];
        $startKey = $start->format('Y-m-d');
        $endKey = $end->format('Y-m-d');
        foreach ($daily as $day => $payload) {
            if (!is_string($day) || $day < $startKey || $day > $endKey) {
                continue;
            }
            if (!is_array($payload)) {
                continue;
            }
            $dayUids = $payload['uids'] ?? [];
            if (!is_array($dayUids)) {
                continue;
            }
            foreach ($dayUids as $uid => $flag) {
                $uids[$uid] = true;
            }
        }
        return count($uids);
    };

    $uniqueAll = static function (array $daily): int {
        $uids = [];
        foreach ($daily as $payload) {
            if (!is_array($payload)) {
                continue;
            }
            $dayUids = $payload['uids'] ?? [];
            if (!is_array($dayUids)) {
                continue;
            }
            foreach ($dayUids as $uid => $flag) {
                $uids[$uid] = true;
            }
        }
        return count($uids);
    };

    $unique30 = [];
    $startKey = $last30Start->format('Y-m-d');
    foreach ($visitorsDaily as $day => $payload) {
        if (!is_string($day) || $day < $startKey) {
            continue;
        }
        $uids = is_array($payload) ? ($payload['uids'] ?? []) : [];
        foreach ($uids as $uid => $flag) {
            $unique30[$uid] = true;
        }
    }
    $unique30Count = count($unique30);

    $collectPlatformUids = static function (string $category) use ($platformDaily, $startKey): array {
        $result = [];
        foreach ($platformDaily as $day => $payload) {
            if (!is_string($day) || $day < $startKey) {
                continue;
            }
            $bucket = is_array($payload) ? ($payload[$category] ?? []) : [];
            foreach ($bucket as $label => $data) {
                $uids = is_array($data) ? ($data['uids'] ?? []) : [];
                foreach ($uids as $uid => $flag) {
                    $result[$label][$uid] = true;
                }
            }
        }
        return $result;
    };

    $platformDevices = $collectPlatformUids('device');
    $platformBrowsers = $collectPlatformUids('browser');
    $platformSystems = $collectPlatformUids('os');
    $platformLanguages = $collectPlatformUids('language');

    $emailDetailLabels = ['Suscriptores', 'Newsletter', 'Reenvios', 'Lista de correo'];
    $emailBreakdownLabels = ['Suscriptores', 'Reenvios', 'Lista de correo'];
    $collectSourceUids = static function (string $category) use ($sourcesDaily, $startKey, $emailDetailLabels): array {
        $result = [];
        foreach ($sourcesDaily as $day => $payload) {
            if (!is_string($day) || $day < $startKey) {
                continue;
            }
            $bucket = is_array($payload) ? ($payload[$category] ?? []) : [];
            if (!is_array($bucket)) {
                continue;
            }
            $details = $bucket['detail'] ?? [];
            if (is_array($details)) {
                foreach ($details as $label => $detailPayload) {
                    if ($category === 'other' && in_array((string) $label, $emailDetailLabels, true)) {
                        continue;
                    }
                    $detailUids = is_array($detailPayload) ? ($detailPayload['uids'] ?? []) : [];
                    foreach ($detailUids as $uid => $flag) {
                        $result[$label][$uid] = true;
                    }
                }
            }
        }
        return $result;
    };

    $ensureDeviceBuckets = static function (array $map, array $days): array {
        if (empty($days)) {
            return $map;
        }
        foreach (['desktop', 'mobile', 'tablet'] as $label) {
            if (!isset($map[$label])) {
                $map[$label] = [];
            }
        }
        return $map;
    };
    $platformDevices = $ensureDeviceBuckets($platformDevices, $platformDaily);

    if (!isset($platformBrowsers['otros'])) {
        $platformBrowsers['otros'] = [];
    }
    if (!isset($platformLanguages['otros'])) {
        $platformLanguages['otros'] = [];
    }
    $desktopUids = $platformDevices['desktop'] ?? [];
    $desktopCount = count($desktopUids);

    $botCounts = [];
    $botTotal = 0;
    foreach ($botsDaily as $day => $payload) {
        if (!is_string($day) || $day < $startKey) {
            continue;
        }
        if (!is_array($payload)) {
            continue;
        }
        foreach ($payload as $botLabel => $botData) {
            $count = is_array($botData) ? (int) ($botData['count'] ?? 0) : (int) $botData;
            if ($count <= 0) {
                continue;
            }
            $botCounts[$botLabel] = ($botCounts[$botLabel] ?? 0) + $count;
            $botTotal += $count;
        }
    }
    if (!empty($botCounts)) {
        arsort($botCounts);
    }

    $buildPercentTable = static function (array $map, array $labelMap): array {
        $counts = [];
        foreach ($map as $label => $uids) {
            $count = is_array($uids) ? count($uids) : 0;
            if ($count > 0) {
                $counts[$label] = $count;
            }
        }
        $total = array_sum($counts);
        if ($total <= 0) {
            return [];
        }
        $items = [];
        foreach ($counts as $label => $count) {
            $raw = ($count / $total) * 100;
            $percent = (int) round($raw);
            if ($percent === 0 && $count > 0) {
                $percent = 1;
            }
            $items[] = [
                'label' => $label,
                'count' => $count,
                'percent' => $percent,
                'remainder' => $raw - floor($raw),
            ];
        }
        $sum = array_sum(array_column($items, 'percent'));
        $diff = 100 - $sum;
        if ($diff !== 0) {
            usort($items, static function (array $a, array $b): int {
                return $b['remainder'] <=> $a['remainder'];
            });
            if ($diff > 0) {
                $i = 0;
                while ($diff > 0) {
                    $items[$i % count($items)]['percent']++;
                    $diff--;
                    $i++;
                }
            } else {
                $diff = abs($diff);
                $i = 0;
                while ($diff > 0) {
                    $index = $i % count($items);
                    if ($items[$index]['percent'] > 1) {
                        $items[$index]['percent']--;
                        $diff--;
                    }
                    $i++;
                }
            }
        }
        $rows = [];
        foreach ($items as $item) {
            if ($item['percent'] <= 0) {
                continue;
            }
        $rows[] = [
            'label' => $labelMap[$item['label']] ?? ucfirst((string) $item['label']),
            'percent' => $item['percent'],
            'count' => $item['count'],
        ];
        }
        usort($rows, static function (array $a, array $b): int {
            return $b['percent'] <=> $a['percent'];
        });
        return $rows;
    };

    $languageLabel = static function (string $code): string {
        $map = [
            'es' => 'Espanol',
            'en' => 'Ingles',
            'fr' => 'Frances',
            'de' => 'Aleman',
            'it' => 'Italiano',
            'pt' => 'Portugues',
            'ca' => 'Catalan',
            'eu' => 'Euskera',
            'gl' => 'Gallego',
        ];
        if (isset($map[$code])) {
            return $map[$code];
        }
        return strtoupper($code);
    };

    $deviceList = $buildPercentTable($platformDevices, [
        'desktop' => 'Escritorio',
        'mobile' => 'Movil',
        'tablet' => 'Tablet',
    ]);
    $browserList = $buildPercentTable($platformBrowsers, [
        'chrome' => 'Chrome',
        'firefox' => 'Firefox',
        'edge' => 'Edge',
        'safari' => 'Safari',
        'opera' => 'Opera',
        'otros' => 'Otros',
    ]);
    $systemList = $buildPercentTable($platformSystems, [
        'windows' => 'Windows',
        'macos' => 'macOS',
        'linux' => 'Linux',
        'chromeos' => 'ChromeOS',
        'otros' => 'Otros',
    ]);
    $languageLabelMap = [];
    foreach ($platformLanguages as $code => $uids) {
        $languageLabelMap[$code] = $languageLabel((string) $code);
    }
    $languageList = $buildPercentTable($platformLanguages, $languageLabelMap);

    $sourceMain = [];
    foreach (['direct', 'search', 'social', 'email', 'push', 'other'] as $bucket) {
        $sourceMain[$bucket] = [];
    }
    foreach ($sourcesDaily as $day => $payload) {
        if (!is_string($day) || $day < $startKey) {
            continue;
        }
        foreach (['direct', 'search', 'social', 'email', 'push', 'other'] as $bucket) {
            $bucketData = is_array($payload) ? ($payload[$bucket] ?? []) : [];
            $uids = is_array($bucketData) ? ($bucketData['uids'] ?? []) : [];
            foreach ($uids as $uid => $flag) {
                $sourceMain[$bucket][$uid] = true;
            }
            if ($bucket === 'other') {
                $details = is_array($bucketData) ? ($bucketData['detail'] ?? []) : [];
                if (is_array($details)) {
                    foreach ($details as $label => $detailPayload) {
                        if (!in_array((string) $label, $emailDetailLabels, true)) {
                            continue;
                        }
                        $detailUids = is_array($detailPayload) ? ($detailPayload['uids'] ?? []) : [];
                        foreach ($detailUids as $uid => $flag) {
                            $sourceMain['email'][$uid] = true;
                        }
                    }
                }
            }
        }
    }
    $sourceMainLabels = [
        'direct' => 'Entrada directa',
        'search' => 'Buscadores',
        'social' => 'Redes sociales',
        'email' => 'Lista de correo',
        'push' => 'Notificaciones push',
        'other' => 'Sitios web',
    ];
    $sourceMainRows = $buildPercentTable($sourceMain, $sourceMainLabels);
    $searchDetailRows = $buildPercentTable($collectSourceUids('search'), []);
    $socialDetailRows = $buildPercentTable($collectSourceUids('social'), []);
    $pushDetailRows = $buildPercentTable($collectSourceUids('push'), []);
    $collectEmailDetails = static function () use ($sourcesDaily, $startKey, $emailBreakdownLabels): array {
        $details = [];
        $mailNeedles = [
            'mail.google.com',
            'gmail.com',
            'outlook.live.com',
            'outlook.com',
            'hotmail.com',
            'live.com',
            'protection.outlook.com',
            'mail.yahoo.com',
            'yahoo.com',
            'mail.aol.com',
            'aol.com',
            'icloud.com',
            'mail.icloud.com',
            'proton.me',
            'protonmail.com',
            'tutanota.com',
            'mail.com',
            'gmx.',
            'zoho.',
        ];
        foreach ($sourcesDaily as $day => $payload) {
            if (!is_string($day) || $day < $startKey) {
                continue;
            }
            $bucketData = is_array($payload) ? ($payload['email'] ?? []) : [];
            $detailData = is_array($bucketData) ? ($bucketData['detail'] ?? []) : [];
            if (!is_array($detailData)) {
                continue;
            }
            foreach ($detailData as $label => $detailPayload) {
                if (!in_array((string) $label, $emailBreakdownLabels, true)) {
                    continue;
                }
                if ((string) $label === 'Lista de correo') {
                    $label = 'Suscriptores';
                }
                if (!isset($details[$label])) {
                    $details[$label] = ['uids' => []];
                }
                $detailUids = is_array($detailPayload) ? ($detailPayload['uids'] ?? []) : [];
                if (is_array($detailUids)) {
                    foreach ($detailUids as $uid => $flag) {
                        $details[$label]['uids'][$uid] = true;
                    }
                }
            }
            $otherBucket = is_array($payload) ? ($payload['other'] ?? []) : [];
            $otherDetail = is_array($otherBucket) ? ($otherBucket['detail'] ?? []) : [];
            if (is_array($otherDetail)) {
                foreach ($otherDetail as $label => $detailPayload) {
                    $key = strtolower(trim((string) $label));
                    $isMail = false;
                    foreach ($mailNeedles as $needle) {
                        if ($key === $needle || str_contains($key, $needle)) {
                            $isMail = true;
                            break;
                        }
                    }
                    if (!$isMail) {
                        continue;
                    }
                    if (!isset($details['Reenvios'])) {
                        $details['Reenvios'] = ['uids' => []];
                    }
                    $detailUids = is_array($detailPayload) ? ($detailPayload['uids'] ?? []) : [];
                    if (is_array($detailUids)) {
                        foreach ($detailUids as $uid => $flag) {
                            $details['Reenvios']['uids'][$uid] = true;
                        }
                    }
                }
            }
        }
        return $details;
    };
    $emailDetails = $collectEmailDetails();
    $emailUids = [];
    foreach ($emailDetails as $label => $detailPayload) {
        $emailUids[$label] = $detailPayload['uids'] ?? [];
    }
    $emailDetailRows = $buildPercentTable($emailUids, []);
    $emailTotalUids = [];
    foreach ($emailUids as $uids) {
        if (!is_array($uids)) {
            continue;
        }
        foreach ($uids as $uid => $flag) {
            $emailTotalUids[$uid] = true;
        }
    }
    if (!empty($emailTotalUids)) {
        $sourceMain['email'] = $emailTotalUids;
        $sourceMainRows = $buildPercentTable($sourceMain, $sourceMainLabels);
    }
    $collectOtherDetails = static function () use ($sourcesDaily, $startKey, $emailDetailLabels): array {
        $details = [];
        foreach ($sourcesDaily as $day => $payload) {
            if (!is_string($day) || $day < $startKey) {
                continue;
            }
            $bucketData = is_array($payload) ? ($payload['other'] ?? []) : [];
            $detailData = is_array($bucketData) ? ($bucketData['detail'] ?? []) : [];
            if (!is_array($detailData)) {
                continue;
            }
            foreach ($detailData as $label => $detailPayload) {
                if (is_array($emailDetailLabels) && in_array((string) $label, $emailDetailLabels, true)) {
                    continue;
                }
                if (!isset($details[$label])) {
                    $details[$label] = ['uids' => [], 'url' => ''];
                }
                $detailUids = is_array($detailPayload) ? ($detailPayload['uids'] ?? []) : [];
                if (is_array($detailUids)) {
                    foreach ($detailUids as $uid => $flag) {
                        $details[$label]['uids'][$uid] = true;
                    }
                }
                $detailUrl = is_array($detailPayload) ? (string) ($detailPayload['url'] ?? '') : '';
                if ($detailUrl !== '') {
                    $details[$label]['url'] = $detailUrl;
                }
            }
        }
        return $details;
    };
    $otherDetails = $collectOtherDetails();
    $otherUids = [];
    $otherUrls = [];
    foreach ($otherDetails as $label => $detailPayload) {
        $otherUids[$label] = $detailPayload['uids'] ?? [];
        if (!empty($detailPayload['url'])) {
            $otherUrls[$label] = $detailPayload['url'];
        }
    }
    $otherDetailRows = $buildPercentTable($otherUids, []);
    foreach ($otherDetailRows as &$row) {
        $label = $row['label'] ?? '';
        if ($label !== '' && isset($otherUrls[$label])) {
            $row['url'] = $otherUrls[$label];
        }
    }
    unset($row);

    $searchTermCounts = [];
    $searchTermUids = [];
    foreach ($searchesDaily as $day => $payload) {
        if (!is_string($day) || $day < $startKey) {
            continue;
        }
        if (!is_array($payload)) {
            continue;
        }
        foreach ($payload as $term => $termData) {
            $termKey = trim((string) $term);
            if ($termKey === '') {
                continue;
            }
            $count = 0;
            $uids = [];
            if (is_array($termData)) {
                $count = (int) ($termData['count'] ?? 0);
                $uids = is_array($termData['uids'] ?? null) ? $termData['uids'] : [];
            } else {
                $count = (int) $termData;
            }
            if ($count > 0) {
                $searchTermCounts[$termKey] = ($searchTermCounts[$termKey] ?? 0) + $count;
            }
            if (!empty($uids)) {
                if (!isset($searchTermUids[$termKey])) {
                    $searchTermUids[$termKey] = [];
                }
                foreach ($uids as $uid => $flag) {
                    $searchTermUids[$termKey][$uid] = true;
                }
            }
        }
    }
    $searchCountsList = [];
    foreach ($searchTermCounts as $term => $count) {
        $searchCountsList[] = ['term' => $term, 'count' => (int) $count];
    }
    usort($searchCountsList, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $searchCountsList = array_slice($searchCountsList, 0, 10);

    $searchUsersList = [];
    foreach ($searchTermUids as $term => $uids) {
        $uniqueCount = is_array($uids) ? count($uids) : 0;
        if ($uniqueCount > 0) {
            $searchUsersList[] = ['term' => $term, 'count' => $uniqueCount];
        }
    }
    usort($searchUsersList, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $searchUsersList = array_slice($searchUsersList, 0, 10);

    $monthlyUids = [];
    $monthlyTotals = [];
    foreach ($visitorsDaily as $day => $payload) {
        if (!is_string($day) || strlen($day) < 7) {
            continue;
        }
        $month = substr($day, 0, 7);
        if (!isset($monthlyUids[$month])) {
            $monthlyUids[$month] = [];
        }
        $uids = is_array($payload) ? ($payload['uids'] ?? []) : [];
        foreach ($uids as $uid => $flag) {
            $monthlyUids[$month][$uid] = true;
        }
        $monthlyTotals[$month] = ($monthlyTotals[$month] ?? 0) + count($uids);
    }
    $currentMonthKey = $today->format('Y-m');
    if (!isset($monthlyUids[$currentMonthKey])) {
        $monthlyUids[$currentMonthKey] = [];
    }
    if (!isset($monthlyTotals[$currentMonthKey])) {
        $monthlyTotals[$currentMonthKey] = 0;
    }
    ksort($monthlyUids);

    $last30Daily = [];
    for ($i = 29; $i >= 0; $i--) {
        $dayKey = $today->modify('-' . $i . ' days')->format('Y-m-d');
        $payload = $visitorsDaily[$dayKey] ?? [];
        $uids = is_array($payload) ? ($payload['uids'] ?? []) : [];
        $last30Daily[$dayKey] = count($uids);
    }
    $last30DailyMax = max(1, max($last30Daily));
    $last30Keys = array_keys($last30Daily);
    $last30LabelStart = $last30Keys[0] ?? '';
    $last30LabelMid = $last30Keys[(int) floor(count($last30Keys) / 2)] ?? '';
    $last30LabelEnd = $last30Keys[count($last30Keys) - 1] ?? '';

    $last12Months = [];
    $monthCursor = $today->modify('first day of this month');
    for ($i = 11; $i >= 0; $i--) {
        $monthKey = $monthCursor->modify('-' . $i . ' months')->format('Y-m');
        $last12Months[$monthKey] = isset($monthlyUids[$monthKey]) ? count($monthlyUids[$monthKey]) : 0;
    }
    $last12MonthsMax = max(1, max($last12Months));
    $last12Keys = array_keys($last12Months);
    $last12LabelStart = $last12Keys[0] ?? '';
    $last12LabelMid = $last12Keys[(int) floor(count($last12Keys) / 2)] ?? '';
    $last12LabelEnd = $last12Keys[count($last12Keys) - 1] ?? '';

    $formatDateEs = static function (string $date): string {
        try {
            $dt = new DateTimeImmutable($date);
            return $dt->format('d/m/y');
        } catch (Throwable $e) {
            return $date;
        }
    };
    $formatDayMonthEs = static function (string $date): string {
        try {
            $dt = new DateTimeImmutable($date);
            return $dt->format('d/m');
        } catch (Throwable $e) {
            return $date;
        }
    };
    $formatMonthEs = static function (string $date): string {
        try {
            $dt = new DateTimeImmutable($date);
            return $dt->format('m/y');
        } catch (Throwable $e) {
            return $date;
        }
    };

    $chartTop = 30;
    $chartBottom = 150;
    $buildLinePoints = static function (array $series, int $max, int $top, int $bottom): array {
        $count = count($series);
        if ($count === 0) {
            return ['points' => '', 'coords' => [], 'max' => 0, 'maxIndex' => null];
        }
        $width = 270;
        $height = $bottom - $top;
        $step = $count > 1 ? ($width / ($count - 1)) : 0;
        $points = [];
        $coords = [];
        $maxValue = 0;
        $maxIndex = null;
        $index = 0;
        foreach ($series as $value) {
            $x = $step * $index;
            $ratio = $max > 0 ? ($value / $max) : 0;
            $y = $bottom - ($ratio * $height);
            $points[] = sprintf('%.2f,%.2f', $x, $y);
            $coords[] = ['x' => $x, 'y' => $y, 'value' => (int) $value];
            if ($value >= $maxValue) {
                $maxValue = (int) $value;
                $maxIndex = $index;
            }
            $index++;
        }
        return [
            'points' => implode(' ', $points),
            'coords' => $coords,
            'max' => $maxValue,
            'maxIndex' => $maxIndex,
        ];
    };

    $last30Line = $buildLinePoints($last30Daily, $last30DailyMax, $chartTop, $chartBottom);
    $last12Line = $buildLinePoints($last12Months, $last12MonthsMax, $chartTop, $chartBottom);

    $yearlyUids = [];
    $yearlyTotals = [];
    foreach ($visitorsDaily as $day => $payload) {
        if (!is_string($day) || strlen($day) < 4) {
            continue;
        }
        $yearKey = substr($day, 0, 4);
        if (!isset($yearlyUids[$yearKey])) {
            $yearlyUids[$yearKey] = [];
        }
        $uids = is_array($payload) ? ($payload['uids'] ?? []) : [];
        foreach ($uids as $uid => $flag) {
            $yearlyUids[$yearKey][$uid] = true;
        }
        $yearlyTotals[$yearKey] = ($yearlyTotals[$yearKey] ?? 0) + count($uids);
    }
    $currentYearKey = $today->format('Y');
    if (!isset($yearlyUids[$currentYearKey])) {
        $yearlyUids[$currentYearKey] = [];
    }
    if (!isset($yearlyTotals[$currentYearKey])) {
        $yearlyTotals[$currentYearKey] = 0;
    }
    ksort($yearlyUids);

    $todayKey = $today->format('Y-m-d');
    $todayPayload = $visitorsDaily[$todayKey] ?? [];
    $todayUids = is_array($todayPayload) ? ($todayPayload['uids'] ?? []) : [];
    $todayCount = count($todayUids);

    $dayNames = [
        'sun' => 'Domingo',
        'mon' => 'Lunes',
        'tue' => 'Martes',
        'wed' => 'Miercoles',
        'thu' => 'Jueves',
        'fri' => 'Viernes',
        'sat' => 'Sabado',
    ];
    $monthNames = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];
    $last7DailyList = [];
    $dailyKeys = array_keys($visitorsDaily);
    sort($dailyKeys);
    $firstDayKey = $dailyKeys[0] ?? '';
    for ($i = 0; $i < 7; $i++) {
        $day = $today->modify('-' . $i . ' days');
        $dayKey = $day->format('Y-m-d');
        if ($firstDayKey !== '' && $dayKey < $firstDayKey) {
            break;
        }
        $payload = $visitorsDaily[$dayKey] ?? [];
        $uids = is_array($payload) ? ($payload['uids'] ?? []) : [];
        $dayLabelKey = strtolower($day->format('D'));
        $last7DailyList[] = [
            'label' => $dayNames[$dayLabelKey] ?? $dayKey,
            'count' => count($uids),
        ];
    }

    $last12MonthsList = [];
    $monthlyKeys = array_keys($monthlyUids);
    sort($monthlyKeys);
    $firstMonthKey = $monthlyKeys[0] ?? '';
    $monthCursor = $today->modify('first day of this month');
    for ($i = 0; $i < 12; $i++) {
        $month = $monthCursor->modify('-' . $i . ' months');
        $monthKey = $month->format('Y-m');
        if ($firstMonthKey !== '' && $monthKey < $firstMonthKey) {
            break;
        }
        $count = isset($monthlyUids[$monthKey]) ? count($monthlyUids[$monthKey]) : 0;
        $monthNum = (int) $month->format('n');
        $last12MonthsList[] = [
            'label' => ($monthNames[$monthNum] ?? $month->format('m')) . ' ' . $month->format('Y'),
            'count' => $count,
        ];
    }

    $yearList = [];
    $yearKeys = array_keys($yearlyUids);
    rsort($yearKeys);
    foreach ($yearKeys as $year) {
        if ($year === '') {
            continue;
        }
        $yearList[] = [
            'label' => $year,
            'count' => isset($yearlyUids[$year]) ? count($yearlyUids[$year]) : 0,
        ];
    }

    $allPosts = [];
    foreach ($postsStats as $slug => $item) {
        $daily = $item['daily'] ?? [];
        $total = (int) ($item['total'] ?? 0);
        $totalFromDaily = $sumAllViews(is_array($daily) ? $daily : []);
        if ($totalFromDaily > $total) {
            $total = $totalFromDaily;
        }
        if ($total <= 0) {
            continue;
        }
        $allPosts[] = [
            'slug' => $slug,
            'title' => $item['title'] ?? $slug,
            'count' => $total,
            'unique' => $uniqueAll(is_array($daily) ? $daily : []),
        ];
    }
    $topPosts = $allPosts;
    usort($topPosts, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $topPosts = array_slice($topPosts, 0, 10);

    $templateBySlug = [];
    foreach ($postsMetadata as $item) {
        $filename = (string) ($item['filename'] ?? '');
        if ($filename === '' || !str_ends_with($filename, '.md')) {
            continue;
        }
        $slug = basename($filename, '.md');
        $status = strtolower((string) ($item['metadata']['Status'] ?? 'published'));
        if ($status === 'draft') {
            continue;
        }
        $templateBySlug[$slug] = strtolower((string) ($item['metadata']['Template'] ?? 'post'));
    }

    $image30ViewsPosts = 0;
    $image30ViewsNewsletter = 0;
    $image30ViewsPodcast = 0;
    foreach ($postsStats as $slug => $item) {
        if (!is_array($item)) {
            continue;
        }
        $daily = is_array($item['daily'] ?? null) ? $item['daily'] : [];
        $views30 = $sumRange($daily, $last30Start, $today);
        if ($views30 <= 0) {
            continue;
        }
        $template = $templateBySlug[(string) $slug] ?? 'post';
        if ($template === 'newsletter') {
            $image30ViewsNewsletter += $views30;
        } elseif ($template === 'podcast') {
            $image30ViewsPodcast += $views30;
        } else {
            $image30ViewsPosts += $views30;
        }
    }

    $image30ViewsPages = 0;
    $image30ViewsItineraries = 0;
    foreach ($pagesStats as $slug => $item) {
        if (!is_array($item)) {
            continue;
        }
        $daily = is_array($item['daily'] ?? null) ? $item['daily'] : [];
        $views30 = $sumRange($daily, $last30Start, $today);
        if ($views30 <= 0) {
            continue;
        }
        if (is_string($slug) && str_starts_with($slug, 'itinerarios/')) {
            $image30ViewsItineraries += $views30;
        } else {
            $image30ViewsPages += $views30;
        }
    }

    $image30TotalViews = $image30ViewsPosts + $image30ViewsPages + $image30ViewsItineraries + $image30ViewsNewsletter + $image30ViewsPodcast;
    $image30PagesPerUser = $unique30Count > 0 ? ($image30TotalViews / $unique30Count) : 0.0;

    $image30UserDays = [];
    foreach ($last30Daily as $dayKey => $_count) {
        $payload = $visitorsDaily[$dayKey] ?? [];
        $uids = is_array($payload) ? ($payload['uids'] ?? []) : [];
        if (!is_array($uids)) {
            continue;
        }
        foreach ($uids as $uid => $flag) {
            $image30UserDays[$uid] = (int) ($image30UserDays[$uid] ?? 0) + 1;
        }
    }
    $image30RecurringUsers = 0;
    foreach ($image30UserDays as $daysSeen) {
        if ((int) $daysSeen >= 2) {
            $image30RecurringUsers++;
        }
    }
    $image30RecurringRate = $unique30Count > 0 ? (($image30RecurringUsers / $unique30Count) * 100) : 0.0;

    $image30DailyValues = array_values($last30Daily);
    $image30DailyAverage = !empty($image30DailyValues) ? (array_sum($image30DailyValues) / count($image30DailyValues)) : 0.0;
    if (!empty($image30DailyValues)) {
        sort($image30DailyValues);
        $mid = (int) floor(count($image30DailyValues) / 2);
        if (count($image30DailyValues) % 2 === 0) {
            $image30DailyMedian = ($image30DailyValues[$mid - 1] + $image30DailyValues[$mid]) / 2;
        } else {
            $image30DailyMedian = $image30DailyValues[$mid];
        }
        $image30DailyPeak = max($image30DailyValues);
    } else {
        $image30DailyMedian = 0;
        $image30DailyPeak = 0;
    }
    $topPostsByUnique = array_values(array_filter($allPosts, static function (array $item): bool {
        return (int) ($item['unique'] ?? 0) > 0;
    }));
    usort($topPostsByUnique, static function (array $a, array $b): int {
        return $b['unique'] <=> $a['unique'];
    });
    $topPostsByUnique = array_slice($topPostsByUnique, 0, 10);

    $topPostsWeek = [];
    $topPostsMonth = [];
    foreach ($postsStats as $slug => $item) {
        $daily = $item['daily'] ?? [];
        $countWeek = $sumRange($daily, $last7Start, $today);
        $countMonth = $sumRange($daily, $last30Start, $today);
        if ($countWeek > 0) {
            $topPostsWeek[] = [
                'slug' => $slug,
                'title' => $item['title'] ?? $slug,
                'count' => $countWeek,
                'unique' => $uniqueRange($daily, $last7Start, $today),
            ];
        }
        if ($countMonth > 0) {
            $topPostsMonth[] = [
                'slug' => $slug,
                'title' => $item['title'] ?? $slug,
                'count' => $countMonth,
                'unique' => $uniqueRange($daily, $last30Start, $today),
            ];
        }
    }
    $topPostsWeekByUnique = $topPostsWeek;
    usort($topPostsWeekByUnique, static function (array $a, array $b): int {
        return $b['unique'] <=> $a['unique'];
    });
    $topPostsWeekByUnique = array_values(array_filter($topPostsWeekByUnique, static function (array $item): bool {
        return (int) ($item['unique'] ?? 0) > 0;
    }));
    $topPostsWeekByUnique = array_slice($topPostsWeekByUnique, 0, 10);
    $topPostsMonthByUnique = $topPostsMonth;
    usort($topPostsMonthByUnique, static function (array $a, array $b): int {
        return $b['unique'] <=> $a['unique'];
    });
    $topPostsMonthByUnique = array_values(array_filter($topPostsMonthByUnique, static function (array $item): bool {
        return (int) ($item['unique'] ?? 0) > 0;
    }));
    $topPostsMonthByUnique = array_slice($topPostsMonthByUnique, 0, 10);
    usort($topPostsWeek, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    usort($topPostsMonth, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $topPostsWeek = array_slice($topPostsWeek, 0, 10);
    $topPostsMonth = array_slice($topPostsMonth, 0, 10);

    $isSystemPageSlug = static function (string $slug): bool {
        if ($slug === 'index' || $slug === 'podcast' || $slug === 'categorias' || $slug === 'letras' || $slug === 'itinerarios' || $slug === 'buscar' || $slug === 'avisos' || $slug === 'correos') {
            return true;
        }
        if (preg_match('#^pagina/([1-9][0-9]*)$#', $slug)) {
            return true;
        }
        return str_starts_with($slug, 'categoria/')
            || str_starts_with($slug, 'letra/');
    };
    $allPages = [];
    $allSystemPages = [];
    foreach ($pagesStats as $slug => $item) {
        if (str_starts_with($slug, 'itinerarios/')) {
            continue;
        }
        $daily = $item['daily'] ?? [];
        $total = (int) ($item['total'] ?? 0);
        $totalFromDaily = $sumAllViews(is_array($daily) ? $daily : []);
        if ($totalFromDaily > $total) {
            $total = $totalFromDaily;
        }
        if ($total <= 0) {
            continue;
        }
        $title = $item['title'] ?? $slug;
        if ($slug === 'index') {
            $title = 'Portada';
        } elseif (preg_match('#^pagina/([1-9][0-9]*)$#', $slug, $pageMatch)) {
            $title = 'Página ' . $pageMatch[1];
        }
        $entry = [
            'slug' => $slug,
            'title' => $title,
            'count' => $total,
            'unique' => $uniqueAll(is_array($daily) ? $daily : []),
            'daily' => is_array($daily) ? $daily : [],
        ];
        if ($isSystemPageSlug($slug)) {
            $allSystemPages[] = $entry;
        } else {
            $allPages[] = $entry;
        }
    }
    $topPages = $allPages;
    usort($topPages, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $topPages = array_slice($topPages, 0, 10);
    $topPagesByUnique = array_values(array_filter($allPages, static function (array $item): bool {
        return (int) ($item['unique'] ?? 0) > 0;
    }));
    usort($topPagesByUnique, static function (array $a, array $b): int {
        return $b['unique'] <=> $a['unique'];
    });
    $topPagesByUnique = array_slice($topPagesByUnique, 0, 10);

    $topSystemPages = $allSystemPages;
    usort($topSystemPages, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $topSystemPages = array_slice($topSystemPages, 0, 10);
    $topSystemPagesByUnique = array_values(array_filter($allSystemPages, static function (array $item): bool {
        return (int) ($item['unique'] ?? 0) > 0;
    }));
    usort($topSystemPagesByUnique, static function (array $a, array $b): int {
        return $b['unique'] <=> $a['unique'];
    });
    $topSystemPagesByUnique = array_slice($topSystemPagesByUnique, 0, 10);
    $topSystemPagesWeek = [];
    $topSystemPagesMonth = [];
    foreach ($allSystemPages as $item) {
        $daily = $item['daily'] ?? [];
        $countWeek = $sumRange($daily, $last7Start, $today);
        $countMonth = $sumRange($daily, $last30Start, $today);
        if ($countWeek > 0) {
            $topSystemPagesWeek[] = [
                'slug' => $item['slug'],
                'title' => $item['title'],
                'count' => $countWeek,
                'unique' => $uniqueRange($daily, $last7Start, $today),
            ];
        }
        if ($countMonth > 0) {
            $topSystemPagesMonth[] = [
                'slug' => $item['slug'],
                'title' => $item['title'],
                'count' => $countMonth,
                'unique' => $uniqueRange($daily, $last30Start, $today),
            ];
        }
    }
    $topSystemPagesWeekByUnique = $topSystemPagesWeek;
    usort($topSystemPagesWeekByUnique, static function (array $a, array $b): int {
        return $b['unique'] <=> $a['unique'];
    });
    $topSystemPagesWeekByUnique = array_values(array_filter($topSystemPagesWeekByUnique, static function (array $item): bool {
        return (int) ($item['unique'] ?? 0) > 0;
    }));
    $topSystemPagesWeekByUnique = array_slice($topSystemPagesWeekByUnique, 0, 10);
    $topSystemPagesMonthByUnique = $topSystemPagesMonth;
    usort($topSystemPagesMonthByUnique, static function (array $a, array $b): int {
        return $b['unique'] <=> $a['unique'];
    });
    $topSystemPagesMonthByUnique = array_values(array_filter($topSystemPagesMonthByUnique, static function (array $item): bool {
        return (int) ($item['unique'] ?? 0) > 0;
    }));
    $topSystemPagesMonthByUnique = array_slice($topSystemPagesMonthByUnique, 0, 10);
    usort($topSystemPagesWeek, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    usort($topSystemPagesMonth, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $topSystemPagesWeek = array_slice($topSystemPagesWeek, 0, 10);
    $topSystemPagesMonth = array_slice($topSystemPagesMonth, 0, 10);

    $buildSystemPageUrl = static function (string $slug): string {
        if ($slug === 'index') {
            return '/';
        }
        if ($slug === 'buscar') {
            return '/buscar.php';
        }
        if ($slug === 'avisos') {
            return '/avisos.php';
        }
        if ($slug === 'correos') {
            return '/correos.php';
        }
        if ($slug === 'podcast') {
            return '/podcast';
        }
        if ($slug === 'categorias') {
            return '/categorias';
        }
        if ($slug === 'letras') {
            return '/letras';
        }
        if ($slug === 'itinerarios') {
            return '/itinerarios';
        }
        if (preg_match('#^pagina/([1-9][0-9]*)$#', $slug, $pageMatch)) {
            return '/pagina/' . $pageMatch[1];
        }
        if (str_starts_with($slug, 'categoria/')) {
            return '/' . $slug;
        }
        if (str_starts_with($slug, 'letra/')) {
            return '/' . $slug;
        }
        return '/' . $slug;
    };

    $itineraryPageUniques = [];
    foreach ($pagesStats as $slug => $item) {
        if (!str_starts_with($slug, 'itinerarios/')) {
            continue;
        }
        $parts = explode('/', $slug);
        if (count($parts) !== 2 || $parts[1] === '') {
            continue;
        }
        $daily = is_array($item['daily'] ?? null) ? $item['daily'] : [];
        $unique = $uniqueAll($daily);
        if ($unique > 0) {
            $itineraryPageUniques[$parts[1]] = max($itineraryPageUniques[$parts[1]] ?? 0, $unique);
        }
    }

    $topItineraryViews = [];
    $topItineraryStarts = [];
    $topItineraryCompletes = [];
    foreach ($itineraries ?? [] as $itineraryItem) {
        $slug = $itineraryItem->getSlug();
        $viewCount = $itineraryPageUniques[$slug] ?? 0;
        if ($viewCount > 0) {
            $topItineraryViews[] = [
                'slug' => $slug,
                'title' => $itineraryTitleMap[$slug] ?? $slug,
                'count' => $viewCount,
            ];
        }
        $rawStats = admin_get_itinerary_stats($itineraryItem);
        $topicStatsList = array_values($rawStats['topics'] ?? []);
        usort($topicStatsList, static function (array $a, array $b): int {
            return (int) ($a['number'] ?? 0) <=> (int) ($b['number'] ?? 0);
        });
        $presentationReaders = (int) ($rawStats['started'] ?? 0);
        $topicOneStarters = 0;
        foreach ($topicStatsList as $topicStat) {
            $topicNumber = (int) ($topicStat['number'] ?? 0);
            if ($topicNumber === 1) {
                $topicOneStarters = (int) ($topicStat['count'] ?? 0);
                break;
            }
        }
        if ($topicOneStarters === 0 && !empty($topicStatsList)) {
            $topicOneStarters = (int) ($topicStatsList[0]['count'] ?? 0);
        }
        $lastTopicCount = 0;
        if (!empty($topicStatsList)) {
            $lastTopic = $topicStatsList[count($topicStatsList) - 1];
            $lastTopicCount = (int) ($lastTopic['count'] ?? 0);
        }
        if ($topicOneStarters > 0 || $presentationReaders > 0) {
            $topItineraryStarts[] = [
                'slug' => $slug,
                'title' => $itineraryTitleMap[$slug] ?? $slug,
                'count' => $topicOneStarters > 0 ? $topicOneStarters : $presentationReaders,
            ];
        }
        if ($lastTopicCount > 0) {
            $topItineraryCompletes[] = [
                'slug' => $slug,
                'title' => $itineraryTitleMap[$slug] ?? $slug,
                'count' => $lastTopicCount,
            ];
        }
    }
    usort($topItineraryStarts, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    usort($topItineraryCompletes, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    usort($topItineraryViews, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $topItineraryViews = array_slice($topItineraryViews, 0, 10);
    $topItineraryStarts = array_slice($topItineraryStarts, 0, 10);
    $topItineraryCompletes = array_slice($topItineraryCompletes, 0, 10);

    $resourceCounts = [
        'images' => 0,
        'videos' => 0,
        'audios' => 0,
        'pdfs' => 0,
        'epubs' => 0,
        'docs' => 0,
        'others' => 0,
    ];
    if (function_exists('get_media_items')) {
        $mediaItems = get_media_items(1, 0);
        foreach ($mediaItems['items'] ?? [] as $item) {
            $ext = strtolower((string) ($item['extension'] ?? ''));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
                $resourceCounts['images']++;
            } elseif (in_array($ext, ['mp4', 'webm', 'mov', 'm4v', 'ogv', 'ogg'], true)) {
                $resourceCounts['videos']++;
            } elseif (in_array($ext, ['mp3', 'wav', 'flac', 'm4a', 'aac', 'oga'], true)) {
                $resourceCounts['audios']++;
            } elseif ($ext === 'pdf') {
                $resourceCounts['pdfs']++;
            } elseif ($ext === 'epub') {
                $resourceCounts['epubs']++;
            } elseif (in_array($ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'md', 'txt', 'rtf'], true)) {
                $resourceCounts['docs']++;
            } else {
                $resourceCounts['others']++;
            }
        }
    }

    $avisosPostsCount = 0;
    $avisosItinerariesCount = 0;
    $avisosPodcastCount = 0;
    $newsletterSubscriberCount = 0;
    if (function_exists('admin_load_mailing_subscriber_entries')) {
        try {
            $entries = admin_load_mailing_subscriber_entries();
            foreach ($entries as $entry) {
                $prefs = $entry['prefs'] ?? admin_mailing_default_prefs();
                if (!empty($prefs['posts'])) {
                    $avisosPostsCount++;
                }
                if (!empty($prefs['itineraries'])) {
                    $avisosItinerariesCount++;
                }
                if (!empty($prefs['podcast'])) {
                    $avisosPodcastCount++;
                }
                if (!empty($prefs['newsletter'])) {
                    $newsletterSubscriberCount++;
                }
            }
        } catch (Throwable $e) {
            $avisosPostsCount = 0;
            $avisosItinerariesCount = 0;
            $avisosPodcastCount = 0;
            $newsletterSubscriberCount = 0;
        }
    }
    $postalSubscriberCount = 0;
    if (function_exists('postal_load_entries')) {
        try {
            $postalSubscriberCount = count(postal_load_entries());
        } catch (Throwable $e) {
            $postalSubscriberCount = 0;
        }
    }
    $pushSubscriberCount = 0;
    $pushEnabled = false;
    $socialCounts = [];
    if (function_exists('get_settings')) {
        $settings = get_settings();
        $pushEnabled = (($settings['ads']['push_enabled'] ?? 'off') === 'on');
        if ($pushEnabled && function_exists('nammu_push_subscriber_count')) {
            $pushSubscriberCount = nammu_push_subscriber_count();
        }
        $telegramCount = admin_get_telegram_follower_count($settings['telegram'] ?? []);
        if ($telegramCount !== null) {
            $socialCounts['Telegram'] = $telegramCount;
        }
        $facebookCount = admin_get_facebook_follower_count($settings['facebook'] ?? []);
        if ($facebookCount !== null) {
            $socialCounts['Facebook'] = $facebookCount;
        }
        $twitterCount = admin_get_twitter_follower_count($settings['twitter'] ?? []);
        if ($twitterCount !== null) {
            $socialCounts['Twitter/X'] = $twitterCount;
        }
    }
    ?>

    <div class="tab-pane active">
        <style>
            .dashboard-card-title {
                color: #1b8eed !important;
            }
            .dashboard-section-title {
                color: #ea2f28 !important;
            }
            .dashboard-links a {
                color: #7fa7d9 !important;
            }
            .dashboard-links a:hover {
                color: #7fa7d9 !important;
            }
            .dashboard-links li:first-child {
                background: #1b8eed;
                border-radius: 8px;
                padding: 0.2rem 0.4rem;
            }
            .dashboard-links li:first-child a,
            .dashboard-links li:first-child span {
                color: #ffffff !important;
            }
            .dashboard-toggle .btn {
                color: #1b8eed;
                border-color: #1b8eed;
            }
            .dashboard-toggle .btn.active,
            .gsc-toggle .btn.active {
                background: #1b8eed;
                color: #ffffff;
                border-color: #1b8eed;
            }
            .gsc-period-input {
                position: absolute;
                opacity: 0;
                pointer-events: none;
            }
            #gsc-period-28:checked ~ .gsc-buttons label[for="gsc-period-28"],
            #gsc-period-7:checked ~ .gsc-buttons label[for="gsc-period-7"] {
                background: #1b8eed;
                color: #ffffff;
                border-color: #1b8eed;
            }
            .gsc-period-7 {
                display: none;
            }
            #gsc-period-7:checked ~ .gsc-content .gsc-period-7 {
                display: block;
            }
            #gsc-period-7:checked ~ .gsc-content .gsc-period-28 {
                display: none;
            }
            #gsc-period-28:checked ~ .gsc-content .gsc-period-28 {
                display: block;
            }
            #gsc-period-28:checked ~ .gsc-content .gsc-period-7 {
                display: none;
            }
        </style>
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
            <div>
                <h2 class="mb-1">Escritorio Nammu</h2>
                <p class="text-muted mb-0">Resumen general de publicaciones y estadísticas del sitio.</p>
            </div>
        </div>
        <?php if ($indexnowHasErrors): ?>
            <div class="mb-4" style="border:1px solid #ea2f28;background:#fff5f5;border-radius:12px;padding:16px;">
                <h3 class="h6 text-uppercase mb-2" style="color:#ea2f28;">Errores al enviar IndexNow</h3>
                <?php if ($indexnowTimestamp > 0): ?>
                    <p class="text-muted mb-2">Último intento: <?= htmlspecialchars(date('d/m/y H:i', $indexnowTimestamp), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <ul class="mb-0 pl-3">
                    <?php foreach ($indexnowErrors as $error): ?>
                        <?php
                        $endpoint = (string) ($error['endpoint'] ?? '');
                        $status = (int) ($error['status'] ?? 0);
                        $message = trim((string) ($error['message'] ?? ''));
                        ?>
                        <li>
                            <strong><?= htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($status > 0): ?>
                                (HTTP <?= $status ?>)
                            <?php endif; ?>
                            <?php if ($message !== ''): ?>
                                — <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($indexnowHasLog): ?>
            <div class="mb-4" style="border:1px solid #cce2ff;background:#f5f9ff;border-radius:12px;padding:16px;">
                <h3 class="h6 text-uppercase mb-2" style="color:#1b8eed;">IndexNow enviado correctamente</h3>
                <p class="text-muted mb-2">Último envío: <?= htmlspecialchars(date('d/m/y H:i', $indexnowTimestamp), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($indexnowUrls)): ?>
                    <p class="mb-0 text-muted">URLs enviadas: <?= htmlspecialchars(implode(', ', array_slice($indexnowUrls, 0, 3)), ENT_QUOTES, 'UTF-8') ?><?= count($indexnowUrls) > 3 ? '…' : '' ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3">Usuarios únicos humanos (últimos 30 días)</h4>
                        <?php if ($last30Line['points'] === ''): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <?php
                            $last30CoordCount = count($last30Line['coords']);
                            $last30TodayPoint = $last30CoordCount > 0 ? $last30Line['coords'][$last30CoordCount - 1] : null;
                            ?>
                            <svg width="100%" height="170" viewBox="0 0 320 170" preserveAspectRatio="none" aria-hidden="true">
                                <line x1="30" y1="<?= (int) $chartTop ?>" x2="30" y2="<?= (int) $chartBottom ?>" stroke="#ccd6e0" stroke-width="1"></line>
                                <line x1="30" y1="<?= (int) $chartBottom ?>" x2="300" y2="<?= (int) $chartBottom ?>" stroke="#ccd6e0" stroke-width="1"></line>
                                <text x="4" y="<?= (int) $chartTop ?>" font-size="10" fill="#6c757d"><?= (int) $last30DailyMax ?></text>
                                <text x="12" y="<?= (int) $chartBottom ?>" font-size="10" fill="#6c757d">0</text>
                                <text x="30" y="166" font-size="10" text-anchor="start" fill="#6c757d"><?= htmlspecialchars($formatDayMonthEs($last30LabelStart), ENT_QUOTES, 'UTF-8') ?></text>
                                <text x="165" y="166" font-size="10" text-anchor="middle" fill="#6c757d"><?= htmlspecialchars($formatDayMonthEs($last30LabelMid), ENT_QUOTES, 'UTF-8') ?></text>
                                <text x="300" y="166" font-size="10" text-anchor="end" fill="#6c757d"><?= htmlspecialchars($formatDayMonthEs($last30LabelEnd), ENT_QUOTES, 'UTF-8') ?></text>
                                <g transform="translate(30,0)">
                                    <polyline fill="none" stroke="#1b8eed" stroke-width="2" points="<?= htmlspecialchars($last30Line['points'], ENT_QUOTES, 'UTF-8') ?>"></polyline>
                                    <?php foreach ($last30Line['coords'] as $point): ?>
                                        <circle cx="<?= htmlspecialchars((string) $point['x'], ENT_QUOTES, 'UTF-8') ?>" cy="<?= htmlspecialchars((string) $point['y'], ENT_QUOTES, 'UTF-8') ?>" r="2.5" fill="#1b8eed"></circle>
                                    <?php endforeach; ?>
                                    <?php if ($last30TodayPoint): ?>
                                        <text x="<?= htmlspecialchars((string) $last30TodayPoint['x'], ENT_QUOTES, 'UTF-8') ?>" y="<?= (int) max(12, $last30TodayPoint['y'] - 6) ?>" font-size="10" text-anchor="middle" fill="#1b8eed">
                                            <?= (int) $last30TodayPoint['value'] ?>
                                        </text>
                                    <?php endif; ?>
                                </g>
                            </svg>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3">Usuarios únicos humanos (último año)</h4>
                        <?php if ($last12Line['points'] === ''): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <?php
                            $last12CoordCount = count($last12Line['coords']);
                            $last12CurrentPoint = $last12CoordCount > 0 ? $last12Line['coords'][$last12CoordCount - 1] : null;
                            ?>
                            <svg width="100%" height="170" viewBox="0 0 320 170" preserveAspectRatio="none" aria-hidden="true">
                                <line x1="30" y1="<?= (int) $chartTop ?>" x2="30" y2="<?= (int) $chartBottom ?>" stroke="#ccd6e0" stroke-width="1"></line>
                                <line x1="30" y1="<?= (int) $chartBottom ?>" x2="300" y2="<?= (int) $chartBottom ?>" stroke="#ccd6e0" stroke-width="1"></line>
                                <text x="4" y="<?= (int) $chartTop ?>" font-size="10" fill="#6c757d"><?= (int) $last12MonthsMax ?></text>
                                <text x="12" y="<?= (int) $chartBottom ?>" font-size="10" fill="#6c757d">0</text>
                                <text x="30" y="166" font-size="10" text-anchor="start" fill="#6c757d"><?= htmlspecialchars($formatMonthEs($last12LabelStart . '-01'), ENT_QUOTES, 'UTF-8') ?></text>
                                <text x="165" y="166" font-size="10" text-anchor="middle" fill="#6c757d"><?= htmlspecialchars($formatMonthEs($last12LabelMid . '-01'), ENT_QUOTES, 'UTF-8') ?></text>
                                <text x="300" y="166" font-size="10" text-anchor="end" fill="#6c757d"><?= htmlspecialchars($formatMonthEs($last12LabelEnd . '-01'), ENT_QUOTES, 'UTF-8') ?></text>
                                <g transform="translate(30,0)">
                                    <polyline fill="none" stroke="#0a4c8a" stroke-width="2" points="<?= htmlspecialchars($last12Line['points'], ENT_QUOTES, 'UTF-8') ?>"></polyline>
                                    <?php foreach ($last12Line['coords'] as $point): ?>
                                        <circle cx="<?= htmlspecialchars((string) $point['x'], ENT_QUOTES, 'UTF-8') ?>" cy="<?= htmlspecialchars((string) $point['y'], ENT_QUOTES, 'UTF-8') ?>" r="2.5" fill="#0a4c8a"></circle>
                                    <?php endforeach; ?>
                                    <?php if ($last12CurrentPoint): ?>
                                        <text x="<?= htmlspecialchars((string) $last12CurrentPoint['x'], ENT_QUOTES, 'UTF-8') ?>" y="<?= (int) max(12, $last12CurrentPoint['y'] - 6) ?>" font-size="10" text-anchor="middle" fill="#0a4c8a">
                                            <?= (int) $last12CurrentPoint['value'] ?>
                                        </text>
                                    <?php endif; ?>
                                </g>
                            </svg>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 order-lg-2">
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Imagen 30 días</h4>
                        <p class="mb-2"><strong>Usuarios únicos humanos:</strong> <?= (int) $unique30Count ?></p>
                        <p class="mb-2"><strong>Recurrentes (2+ días):</strong> <?= (int) $image30RecurringUsers ?> (<?= number_format($image30RecurringRate, 2, ',', '.') ?>%)</p>
                        <p class="mb-2"><strong>Vistas totales (posts + páginas + itinerarios + newsletter + podcast):</strong> <?= (int) $image30TotalViews ?></p>
                        <p class="mb-2"><strong>Páginas por usuario:</strong> <?= number_format($image30PagesPerUser, 2, ',', '.') ?></p>
                        <p class="mb-0"><strong>Promedio diario:</strong> <?= number_format($image30DailyAverage, 2, ',', '.') ?> · <strong>Mediana:</strong> <?= number_format($image30DailyMedian, 2, ',', '.') ?> · <strong>Pico:</strong> <?= (int) $image30DailyPeak ?></p>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Suscriptores</h4>
                        <?php if ($avisosPostsCount > 0): ?>
                            <p class="mb-2"><strong>Avisos entradas:</strong> <?= (int) $avisosPostsCount ?></p>
                        <?php endif; ?>
                        <?php if ($avisosItinerariesCount > 0): ?>
                            <p class="mb-2"><strong>Avisos itinerarios:</strong> <?= (int) $avisosItinerariesCount ?></p>
                        <?php endif; ?>
                        <?php if ($avisosPodcastCount > 0): ?>
                            <p class="mb-2"><strong>Avisos podcast:</strong> <?= (int) $avisosPodcastCount ?></p>
                        <?php endif; ?>
                        <?php if ($newsletterSubscriberCount > 0): ?>
                            <p class="mb-2"><strong>Newsletter:</strong> <?= (int) $newsletterSubscriberCount ?></p>
                        <?php endif; ?>
                        <?php if ($postalSubscriberCount > 0): ?>
                            <p class="mb-2"><strong>Correo postal:</strong> <?= (int) $postalSubscriberCount ?></p>
                        <?php endif; ?>
                        <?php if ($pushEnabled && $pushSubscriberCount > 0): ?>
                            <p class="mb-2"><strong>Notificaciones Push:</strong> <?= (int) $pushSubscriberCount ?></p>
                        <?php endif; ?>
                        <?php if (!empty($socialCounts)): ?>
                            <?php foreach ($socialCounts as $label => $count): ?>
                                <p class="mb-2"><strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>:</strong> <?= (int) $count ?></p>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Publicaciones</h4>
                        <p class="mb-2"><strong>Entradas:</strong> <?= (int) $postCount ?></p>
                        <?php if (!empty($postCountsByYear)): ?>
                            <div class="table-responsive mb-2">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <?php foreach ($postCountsByYear as $year => $count): ?>
                                            <tr>
                                                <td><?= (int) $year ?></td>
                                                <td class="text-right"><?= (int) $count ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        <p class="mb-2"><strong>Páginas:</strong> <?= (int) $pageCount ?></p>
                        <p class="mb-2"><strong>Newsletters:</strong> <?= (int) $newsletterCount ?></p>
                        <p class="mb-2"><strong>Podcast:</strong> <?= (int) $podcastCount ?></p>
                        <p class="mb-0"><strong>Itinerarios:</strong> <?= (int) $itineraryCount ?></p>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Recursos</h4>
                        <?php if ($resourceCounts['images'] > 0): ?>
                            <p class="mb-2"><strong>Imagenes:</strong> <?= (int) $resourceCounts['images'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['videos'] > 0): ?>
                            <p class="mb-2"><strong>Videos:</strong> <?= (int) $resourceCounts['videos'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['audios'] > 0): ?>
                            <p class="mb-2"><strong>Audios:</strong> <?= (int) $resourceCounts['audios'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['pdfs'] > 0): ?>
                            <p class="mb-2"><strong>PDFs:</strong> <?= (int) $resourceCounts['pdfs'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['epubs'] > 0): ?>
                            <p class="mb-2"><strong>EPUBs:</strong> <?= (int) $resourceCounts['epubs'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['docs'] > 0): ?>
                            <p class="mb-2"><strong>Documentos:</strong> <?= (int) $resourceCounts['docs'] ?></p>
                        <?php endif; ?>
                        <?php if ($resourceCounts['others'] > 0): ?>
                            <p class="mb-0"><strong>Otros:</strong> <?= (int) $resourceCounts['others'] ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Bots y crawlers</h4>
                        <?php if ($botTotal === 0): ?>
                            <p class="text-muted mb-0">Sin visitas de bots registradas.</p>
                        <?php else: ?>
                            <p class="mb-2"><strong>Total últimos 30 días:</strong> <?= (int) $botTotal ?></p>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Bot</th>
                                            <th class="text-right">Visitas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($botCounts as $botLabel => $count): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string) $botLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-right"><?= (int) $count ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Plataforma (últimos 30 días)</h4>
                        <?php if (empty($deviceList) && empty($browserList) && empty($systemList) && empty($languageList)): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <?php if (!empty($deviceList)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Dispositivo</p>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($deviceList as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% (<?= (int) $item['count'] ?>)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($browserList)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Navegador</p>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($browserList as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% (<?= (int) $item['count'] ?>)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($systemList)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Sistema (escritorio)</p>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($systemList as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% (<?= (int) $item['count'] ?>)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($languageList)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Lengua</p>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($languageList as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% (<?= (int) $item['count'] ?>)</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 order-lg-1">
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Usuarios únicos humanos</h4>
                        <p class="mb-3"><strong>Hoy:</strong> <?= (int) $todayCount ?></p>

                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Últimos 7 días</p>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php foreach ($last7DailyList as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-right"><?= (int) $item['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Últimos 12 meses</p>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php foreach ($last12MonthsList as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-right"><?= (int) $item['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Años</p>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php if (empty($yearList)): ?>
                                        <tr>
                                            <td colspan="2" class="text-muted">Sin datos todavía.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($yearList as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-right"><?= (int) $item['count'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php
                $hasPostStats = !empty($topPosts) || !empty($topPostsByUnique)
                    || !empty($topPostsWeek) || !empty($topPostsWeekByUnique)
                    || !empty($topPostsMonth) || !empty($topPostsMonthByUnique);
                ?>
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Entradas más leídas</h4>
                            <div class="d-flex flex-column align-items-start">
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle my-2" role="group" data-stat-toggle="posts" data-stat-toggle-type="mode">
                                    <button type="button" class="btn btn-outline-primary active" data-stat-mode="views">Vistas</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="users">Usuarios</button>
                                </div>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle my-2" role="group" data-stat-toggle="posts" data-stat-toggle-type="period">
                                    <button type="button" class="btn btn-outline-primary" data-stat-period="week">Últimos 7 días</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-period="month">Últimos 30 días</button>
                                    <button type="button" class="btn btn-outline-primary active" data-stat-period="all">Desde el comienzo del blog</button>
                                </div>
                            </div>
                        </div>
                        <?php if (!$hasPostStats): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <ol class="mb-0 dashboard-links" data-stat-list="posts" data-stat-mode="views" data-stat-period="all">
                                <?php foreach ($topPosts as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="users" data-stat-period="all">
                                <?php foreach ($topPostsByUnique as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="views" data-stat-period="week">
                                <?php foreach ($topPostsWeek as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="users" data-stat-period="week">
                                <?php foreach ($topPostsWeekByUnique as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="views" data-stat-period="month">
                                <?php foreach ($topPostsMonth as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="posts" data-stat-mode="users" data-stat-period="month">
                                <?php foreach ($topPostsMonthByUnique as $item): ?>
                                    <li>
                                        <?php $url = admin_public_post_url($item['slug']); ?>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>

                <?php
                $hasSystemPageStats = !empty($topSystemPages) || !empty($topSystemPagesByUnique)
                    || !empty($topSystemPagesWeek) || !empty($topSystemPagesWeekByUnique)
                    || !empty($topSystemPagesMonth) || !empty($topSystemPagesMonthByUnique);
                ?>
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Páginas sistémicas más leídas</h4>
                            <div class="d-flex flex-column align-items-start">
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle my-2" role="group" data-stat-toggle="system-pages" data-stat-toggle-type="mode">
                                    <button type="button" class="btn btn-outline-primary active" data-stat-mode="views">Vistas</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="users">Usuarios</button>
                                </div>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle my-2" role="group" data-stat-toggle="system-pages" data-stat-toggle-type="period">
                                    <button type="button" class="btn btn-outline-primary" data-stat-period="week">Últimos 7 días</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-period="month">Últimos 30 días</button>
                                    <button type="button" class="btn btn-outline-primary active" data-stat-period="all">Desde el comienzo del blog</button>
                                </div>
                            </div>
                        </div>
                        <?php if (!$hasSystemPageStats): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <ol class="mb-0 dashboard-links" data-stat-list="system-pages" data-stat-mode="views" data-stat-period="all">
                                <?php foreach ($topSystemPages as $item): ?>
                                    <?php $url = $buildSystemPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="system-pages" data-stat-mode="users" data-stat-period="all">
                                <?php foreach ($topSystemPagesByUnique as $item): ?>
                                    <?php $url = $buildSystemPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="system-pages" data-stat-mode="views" data-stat-period="week">
                                <?php foreach ($topSystemPagesWeek as $item): ?>
                                    <?php $url = $buildSystemPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="system-pages" data-stat-mode="users" data-stat-period="week">
                                <?php foreach ($topSystemPagesWeekByUnique as $item): ?>
                                    <?php $url = $buildSystemPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="system-pages" data-stat-mode="views" data-stat-period="month">
                                <?php foreach ($topSystemPagesMonth as $item): ?>
                                    <?php $url = $buildSystemPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="system-pages" data-stat-mode="users" data-stat-period="month">
                                <?php foreach ($topSystemPagesMonthByUnique as $item): ?>
                                    <?php $url = $buildSystemPageUrl($item['slug']); ?>
                                    <li>
                                        <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                        <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($itineraryCount > 0): ?>
                    <div class="card mb-4 dashboard-stat-block">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Itinerarios (usuarios únicos)</h4>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle" role="group" data-stat-toggle="itineraries" data-stat-toggle-type="mode">
                                    <button type="button" class="btn btn-outline-primary active" data-stat-mode="views">Vieron</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="starts">Comenzaron</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="completes">Completaron</button>
                                </div>
                            </div>
                            <?php if (empty($topItineraryViews) && empty($topItineraryStarts) && empty($topItineraryCompletes)): ?>
                                <p class="text-muted mb-0">Sin datos todavía.</p>
                            <?php else: ?>
                                <ol class="mb-0 dashboard-links" data-stat-list="itineraries" data-stat-mode="views">
                                    <?php foreach ($topItineraryViews as $item): ?>
                                        <li>
                                            <?php $url = admin_public_itinerary_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <ol class="mb-0 dashboard-links d-none" data-stat-list="itineraries" data-stat-mode="starts">
                                    <?php foreach ($topItineraryStarts as $item): ?>
                                        <li>
                                            <?php $url = admin_public_itinerary_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <ol class="mb-0 dashboard-links d-none" data-stat-list="itineraries" data-stat-mode="completes">
                                    <?php foreach ($topItineraryCompletes as $item): ?>
                                        <li>
                                            <?php $url = admin_public_itinerary_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($pageCount > 0): ?>
                    <div class="card mb-4 dashboard-stat-block">
                        <div class="card-body">
                            <div class="d-flex flex-column gap-2 mb-3">
                                <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Páginas más leídas</h4>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle align-self-start" role="group" data-stat-toggle="pages-all" data-stat-toggle-type="mode">
                                    <button type="button" class="btn btn-outline-primary active" data-stat-mode="views">Vistas</button>
                                    <button type="button" class="btn btn-outline-primary" data-stat-mode="users">Usuarios</button>
                                </div>
                            </div>
                            <?php if (empty($topPages) && empty($topPagesByUnique)): ?>
                                <p class="text-muted mb-0">Sin datos todavía.</p>
                            <?php else: ?>
                                <ol class="mb-0 dashboard-links" data-stat-list="pages-all" data-stat-mode="views">
                                    <?php foreach ($topPages as $item): ?>
                                        <li>
                                            <?php $url = admin_public_post_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <ol class="mb-0 dashboard-links d-none" data-stat-list="pages-all" data-stat-mode="users">
                                    <?php foreach ($topPagesByUnique as $item): ?>
                                        <li>
                                            <?php $url = admin_public_post_url($item['slug']); ?>
                                            <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                            <span class="text-muted">(<?= (int) $item['unique'] ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <div class="d-flex flex-column gap-2 mb-3">
                            <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Búsquedas internas más frecuentes (últimos 30 días)</h4>
                            <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle align-self-start" role="group" data-stat-toggle="internal-search" data-stat-toggle-type="mode">
                                <button type="button" class="btn btn-outline-primary active" data-stat-mode="searches">Búsquedas</button>
                                <button type="button" class="btn btn-outline-primary" data-stat-mode="users">Usuarios</button>
                            </div>
                        </div>
                        <?php if (empty($searchCountsList) && empty($searchUsersList)): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
                            <ol class="mb-0 dashboard-links" data-stat-list="internal-search" data-stat-mode="searches">
                                <?php foreach ($searchCountsList as $item): ?>
                                    <li>
                                        <span><?= htmlspecialchars($item['term'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <ol class="mb-0 dashboard-links d-none" data-stat-list="internal-search" data-stat-mode="users">
                                <?php foreach ($searchUsersList as $item): ?>
                                    <li>
                                        <span><?= htmlspecialchars($item['term'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-muted">(<?= (int) $item['count'] ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($sourceMainRows)): ?>
                    <div class="card mb-4">
                        <div class="card-body dashboard-stat-block">
                            <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Origen de los usuarios únicos (últimos 30 días)</h4>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <?php foreach ($sourceMainRows as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-right"><?= (int) $item['percent'] ?>% <span class="text-muted">(<?= (int) $item['count'] ?>)</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (!empty($searchDetailRows)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Buscadores</p>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($searchDetailRows as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% <span class="text-muted">(<?= (int) $item['count'] ?>)</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($socialDetailRows)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Redes sociales</p>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($socialDetailRows as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% <span class="text-muted">(<?= (int) $item['count'] ?>)</span></td>
                                                </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($emailDetailRows)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Lista de correo</p>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($emailDetailRows as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% <span class="text-muted">(<?= (int) $item['count'] ?>)</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($pushDetailRows)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Notificaciones push</p>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($pushDetailRows as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% <span class="text-muted">(<?= (int) $item['count'] ?>)</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($otherDetailRows)): ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Sitios web</p>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <?php foreach ($otherDetailRows as $item): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($item['url'])): ?>
                                                            <a href="<?= htmlspecialchars((string) $item['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>% <span class="text-muted">(<?= (int) $item['count'] ?>)</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <?php if (
                    ($gscProperty !== '' && $gscClientId !== '' && $gscClientSecret !== '' && $gscRefreshToken !== '')
                    || ($bingSiteUrl !== '' || $bingApiKey !== '' || $bingHasOauth)
                ): ?>
                    <h3 class="h6 text-uppercase text-muted mb-3">Integración con buscadores</h3>
                <?php endif; ?>
                <?php if ($gscProperty !== '' && $gscClientId !== '' && $gscClientSecret !== '' && $gscRefreshToken !== ''): ?>
                    <div class="card mb-4" id="gsc-dashboard">
                        <div class="card-body">
                            <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Google Search Console</h4>
                            <?php if ($gscError !== ''): ?>
                                <p class="text-muted mb-0"><?= htmlspecialchars($gscError, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php elseif ($gscTotals7 === null || $gscTotals28 === null): ?>
                                <p class="text-muted mb-0">Sin datos disponibles.</p>
                            <?php else: ?>
                                <?php if ($gscUpdatedAtLabel !== ''): ?>
                                    <p class="text-muted mb-2">Datos servidos por Google Search Console API el <?= htmlspecialchars($gscUpdatedAtLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <form method="get" class="mb-2">
                                    <input type="hidden" name="page" value="dashboard">
                                    <input type="hidden" name="gsc_refresh" value="1">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Actualizar datos ahora</button>
                                </form>
                                <input type="radio" name="gsc-period" id="gsc-period-28" class="gsc-period-input" checked>
                                <input type="radio" name="gsc-period" id="gsc-period-7" class="gsc-period-input">
                                <div class="btn-group btn-group-sm mb-3 dashboard-toggle gsc-toggle gsc-buttons" role="group">
                                    <label class="btn btn-outline-secondary gsc-period-label" for="gsc-period-28">Últimos 28 días</label>
                                    <label class="btn btn-outline-secondary gsc-period-label" for="gsc-period-7">Últimos 7 días</label>
                                </div>
                                <div class="gsc-content">
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0 gsc-period-28" data-gsc-period="28" data-stat-list data-stat-period="28" data-stat-scope="gsc-main" data-stat-kind="table">
                                        <tbody>
                                            <tr>
                                                <td>Clicks totales</td>
                                                <td class="text-right"><?= (int) $gscTotals28['clicks'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>Impresiones totales</td>
                                                <td class="text-right"><?= (int) $gscTotals28['impressions'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>CTR medio</td>
                                                <td class="text-right"><?= number_format($gscTotals28['ctr'] * 100, 2, ',', '.') ?>%</td>
                                            </tr>
                                            <tr>
                                                <td>Posición media</td>
                                                <td class="text-right"><?= number_format($gscTotals28['position'], 1, ',', '.') ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table class="table table-sm mb-0 gsc-period-7" data-gsc-period="7" data-stat-list data-stat-period="7" data-stat-scope="gsc-main" data-stat-kind="table">
                                        <tbody>
                                            <tr>
                                                <td>Clicks totales</td>
                                                <td class="text-right"><?= (int) $gscTotals7['clicks'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>Impresiones totales</td>
                                                <td class="text-right"><?= (int) $gscTotals7['impressions'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>CTR medio</td>
                                                <td class="text-right"><?= number_format($gscTotals7['ctr'] * 100, 2, ',', '.') ?>%</td>
                                            </tr>
                                            <tr>
                                                <td>Posición media</td>
                                                <td class="text-right"><?= number_format($gscTotals7['position'], 1, ',', '.') ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td>Última consulta al sitemap</td>
                                                <td class="text-right"><?= $gscSitemapInfo['last_crawl'] !== '' ? htmlspecialchars($gscSitemapInfo['last_crawl'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($gscQueries28) || !empty($gscQueries7)): ?>
                                    <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Términos más clicados</p>
                                <?php endif; ?>
                                <?php if (!empty($gscQueries28)): ?>
                                    <div class="table-responsive gsc-period-28" data-gsc-period="28" data-stat-list data-stat-period="28" data-stat-scope="gsc-terms" data-stat-kind="block">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Término</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gscQueries28 as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['term'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscQueries7)): ?>
                                    <div class="table-responsive gsc-period-7" data-gsc-period="7" data-stat-list data-stat-period="7" data-stat-scope="gsc-terms" data-stat-kind="block">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Término</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gscQueries7 as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['term'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscPages7) || !empty($gscPages28)): ?>
                                    <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Páginas más clicadas</p>
                                <?php endif; ?>
                                <?php if (!empty($gscPages7)): ?>
                                    <div class="table-responsive mb-3 gsc-period-7" data-gsc-period="7" data-stat-list data-stat-period="7" data-stat-scope="gsc-pages" data-stat-kind="block">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Página</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gscPages7 as $row): ?>
                                                    <tr>
                                                        <?php
                                                        $pageUrl = (string) $row['page'];
                                                        $slugValue = $pageUrl;
                                                        if ($pageUrl !== '') {
                                                            $parsed = parse_url($pageUrl);
                                                            if (is_array($parsed) && isset($parsed['path'])) {
                                                                $slugValue = trim((string) $parsed['path'], '/');
                                                            }
                                                        }
                                                        ?>
                                                        <td class="text-truncate">
                                                            <?php if ($pageUrl !== ''): ?>
                                                                <a href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                    <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscPages28)): ?>
                                    <div class="table-responsive mb-3 gsc-period-28" data-gsc-period="28" data-stat-list data-stat-period="28" data-stat-scope="gsc-pages" data-stat-kind="block">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Página</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gscPages28 as $row): ?>
                                                    <tr>
                                                        <?php
                                                        $pageUrl = (string) $row['page'];
                                                        $slugValue = $pageUrl;
                                                        if ($pageUrl !== '') {
                                                            $parsed = parse_url($pageUrl);
                                                            if (is_array($parsed) && isset($parsed['path'])) {
                                                                $slugValue = trim((string) $parsed['path'], '/');
                                                            }
                                                        }
                                                        ?>
                                                        <td class="text-truncate">
                                                            <?php if ($pageUrl !== ''): ?>
                                                                <a href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                    <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscCountries7) || !empty($gscCountries28)): ?>
                                    <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Principales países</p>
                                <?php endif; ?>
                                <?php if (!empty($gscCountries7)): ?>
                                    <div class="table-responsive mb-3 gsc-period-7" data-gsc-period="7" data-stat-list data-stat-period="7" data-stat-scope="gsc-countries" data-stat-kind="block">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>País</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gscCountries7 as $row): ?>
                                                    <tr>
                                                        <?php
                                                        $rawCountry = trim((string) ($row['country'] ?? ''));
                                                        $countryLabel = $gscResolveCountry($rawCountry);
                                                        if ($countryLabel === '') {
                                                            $countryLabel = $rawCountry;
                                                        }
                                                        if ($countryLabel === '') {
                                                            continue;
                                                        }
                                                        ?>
                                                        <td><?= htmlspecialchars($countryLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscCountries28)): ?>
                                    <div class="table-responsive gsc-period-28" data-gsc-period="28" data-stat-list data-stat-period="28" data-stat-scope="gsc-countries" data-stat-kind="block">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>País</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($gscCountries28 as $row): ?>
                                                    <tr>
                                                        <?php
                                                        $rawCountry = trim((string) ($row['country'] ?? ''));
                                                        $countryLabel = $gscResolveCountry($rawCountry);
                                                        if ($countryLabel === '') {
                                                            $countryLabel = $rawCountry;
                                                        }
                                                        if ($countryLabel === '') {
                                                            continue;
                                                        }
                                                        ?>
                                                        <td><?= htmlspecialchars($countryLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($bingSiteUrl !== '' || $bingApiKey !== '' || $bingHasOauth): ?>
                    <div class="card mb-4 dashboard-stat-block" id="bing-dashboard">
                        <div class="card-body">
                            <h4 class="h6 text-uppercase text-muted mb-3 dashboard-card-title">Microsoft Bing Webmaster Tools</h4>
                            <?php if ($bingSiteUrl === ''): ?>
                                <p class="text-muted mb-0">Define la URL del sitio en Configuración para mostrar los datos de Bing.</p>
                            <?php elseif ($bingError !== ''): ?>
                                <p class="text-muted mb-0"><?= htmlspecialchars($bingError, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php elseif ($bingTotals7 === null || $bingTotals28 === null): ?>
                                <p class="text-muted mb-0">Sin datos disponibles.</p>
                            <?php else: ?>
                                <?php if ($bingUpdatedAtLabel !== ''): ?>
                                    <p class="text-muted mb-2">Datos servidos por Bing Webmaster Tools API el <?= htmlspecialchars($bingUpdatedAtLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <form method="get" class="mb-2">
                                    <input type="hidden" name="page" value="dashboard">
                                    <input type="hidden" name="bing_refresh" value="1">
                                    <?php if ($bingDebug): ?>
                                        <input type="hidden" name="bing_debug" value="1">
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Actualizar datos ahora</button>
                                </form>
                                <?php if ($bingDebug && !empty($GLOBALS['bing_debug_log'])): ?>
                                    <div class="alert alert-warning mb-3">
                                        <div class="small text-muted mb-1">Depuración Bing</div>
                                        <pre class="mb-0 small"><?= htmlspecialchars(json_encode($GLOBALS['bing_debug_log'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                                    </div>
                                <?php endif; ?>
                                <div class="btn-group btn-group-sm mb-3 dashboard-toggle bing-toggle bing-buttons" role="group" data-stat-toggle="bing-period" data-stat-scope="bing-period" data-stat-toggle-type="period">
                                    <button type="button" class="btn btn-outline-secondary bing-period-label active" data-stat-period="28">Últimos 30 días</button>
                                    <button type="button" class="btn btn-outline-secondary bing-period-label" data-stat-period="7">Últimos 7 días</button>
                                </div>
                                <div class="bing-content">
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0 bing-period-28" data-stat-list data-stat-scope="bing-period" data-stat-period="28">
                                        <tbody>
                                            <tr>
                                                <td>Clicks totales</td>
                                                <td class="text-right"><?= (int) $bingTotals28['clicks'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>Impresiones totales</td>
                                                <td class="text-right"><?= (int) $bingTotals28['impressions'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>CTR medio</td>
                                                <td class="text-right"><?= number_format($bingTotals28['ctr'] * 100, 2, ',', '.') ?>%</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <table class="table table-sm mb-0 bing-period-7 d-none" data-stat-list data-stat-scope="bing-period" data-stat-period="7">
                                        <tbody>
                                            <tr>
                                                <td>Clicks totales</td>
                                                <td class="text-right"><?= (int) $bingTotals7['clicks'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>Impresiones totales</td>
                                                <td class="text-right"><?= (int) $bingTotals7['impressions'] ?></td>
                                            </tr>
                                            <tr>
                                                <td>CTR medio</td>
                                                <td class="text-right"><?= number_format($bingTotals7['ctr'] * 100, 2, ',', '.') ?>%</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (!empty($bingQueries28) || !empty($bingQueries7)): ?>
                                    <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Términos más clicados</p>
                                <?php endif; ?>
                                <?php if (!empty($bingQueries28)): ?>
                                    <div class="table-responsive bing-period-28" data-stat-list data-stat-scope="bing-period" data-stat-period="28">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Término</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bingQueries28 as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['term'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($bingQueries7)): ?>
                                    <div class="table-responsive bing-period-7 d-none" data-stat-list data-stat-scope="bing-period" data-stat-period="7">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Término</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bingQueries7 as $row): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($row['term'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (empty($bingQueries7) && !empty($bingQueries28)): ?>
                                    <p class="text-muted mb-0 bing-period-7 d-none" data-stat-list data-stat-scope="bing-period" data-stat-period="7">Sin términos en los últimos 7 días.</p>
                                <?php endif; ?>
                                <?php if (empty($bingQueries28) && !empty($bingQueries7)): ?>
                                    <p class="text-muted mb-0 bing-period-28" data-stat-list data-stat-scope="bing-period" data-stat-period="28">Sin términos en los últimos 30 días.</p>
                                <?php endif; ?>
                                <p class="text-muted mb-2 text-uppercase small dashboard-section-title">Páginas más clicadas</p>
                                <?php if (!empty($bingPages7)): ?>
                                    <div class="table-responsive mb-3 bing-period-7 d-none" data-stat-list data-stat-scope="bing-period" data-stat-period="7">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Página</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bingPages7 as $row): ?>
                                                    <tr>
                                                        <?php
                                                        $pageUrl = (string) $row['page'];
                                                        $slugValue = $pageUrl;
                                                        if ($pageUrl !== '') {
                                                            $parsed = parse_url($pageUrl);
                                                            if (is_array($parsed) && isset($parsed['path'])) {
                                                                $slugValue = trim((string) $parsed['path'], '/');
                                                            }
                                                        }
                                                        ?>
                                                        <td class="text-truncate">
                                                            <?php if ($pageUrl !== ''): ?>
                                                                <a href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                    <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($bingPages28)): ?>
                                    <div class="table-responsive mb-3 bing-period-28" data-stat-list data-stat-scope="bing-period" data-stat-period="28">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Página</th>
                                                    <th class="text-right">Clicks</th>
                                                    <th class="text-right">Impresiones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bingPages28 as $row): ?>
                                                    <tr>
                                                        <?php
                                                        $pageUrl = (string) $row['page'];
                                                        $slugValue = $pageUrl;
                                                        if ($pageUrl !== '') {
                                                            $parsed = parse_url($pageUrl);
                                                            if (is_array($parsed) && isset($parsed['path'])) {
                                                                $slugValue = trim((string) $parsed['path'], '/');
                                                            }
                                                        }
                                                        ?>
                                                        <td class="text-truncate">
                                                            <?php if ($pageUrl !== ''): ?>
                                                                <a href="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                                                    <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($slugValue, ENT_QUOTES, 'UTF-8') ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (empty($bingPages7) && empty($bingPages28)): ?>
                                    <p class="text-muted mb-0">Sin datos de páginas todavía.</p>
                                <?php elseif (empty($bingPages7)): ?>
                                    <p class="text-muted mb-0 bing-period-7 d-none" data-stat-list data-stat-scope="bing-period" data-stat-period="7">Sin páginas clicadas en los últimos 7 días.</p>
                                <?php elseif (empty($bingPages28)): ?>
                                    <p class="text-muted mb-0 bing-period-28" data-stat-list data-stat-scope="bing-period" data-stat-period="28">Sin páginas clicadas en los últimos 30 días.</p>
                                <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        (function() {
            function updateBlock(block) {
                function applyScopePeriod(scope, period) {
                    if (!scope || !period) {
                        return;
                    }
                    block.querySelectorAll('[data-stat-list][data-stat-scope="' + scope + '"][data-stat-period]').forEach(function(list) {
                        list.classList.toggle('d-none', list.getAttribute('data-stat-period') !== period);
                    });
                }

                var periodByScope = {};
                var modeByScope = {};

                block.querySelectorAll('[data-stat-toggle]').forEach(function(group) {
                    var scope = group.getAttribute('data-stat-scope') || '';
                    var modeOverride = group.getAttribute('data-stat-mode-current');
                    var periodOverride = group.getAttribute('data-stat-period-current');
                    var modeBtn = group.querySelector('[data-stat-mode].active');
                    var periodBtn = group.querySelector('[data-stat-period].active');
                    if (modeBtn) {
                        modeByScope[scope] = modeOverride || modeBtn.getAttribute('data-stat-mode');
                    }
                    if (periodBtn) {
                        periodByScope[scope] = periodOverride || periodBtn.getAttribute('data-stat-period');
                    }
                });

                block.querySelectorAll('[data-stat-list]').forEach(function(list) {
                    var scope = list.getAttribute('data-stat-scope') || '';
                    var mode = modeByScope[scope] || null;
                    var period = periodByScope[scope] || null;
                    var match = true;
                    if (mode && list.hasAttribute('data-stat-mode') && list.getAttribute('data-stat-mode') !== mode) {
                        match = false;
                    }
                    if (period && list.hasAttribute('data-stat-period') && list.getAttribute('data-stat-period') !== period) {
                        match = false;
                    }
                    list.classList.toggle('d-none', !match);
                });

                Object.keys(periodByScope).forEach(function(scope) {
                    applyScopePeriod(scope, periodByScope[scope]);
                });

                block.querySelectorAll('table[data-stat-list]').forEach(function(table) {
                    var wrapper = table.closest('.table-responsive');
                    if (!wrapper) {
                        return;
                    }
                    var hasVisibleTable = false;
                    wrapper.querySelectorAll('table[data-stat-list]').forEach(function(item) {
                        if (!item.classList.contains('d-none')) {
                            hasVisibleTable = true;
                        }
                    });
                    wrapper.classList.toggle('d-none', !hasVisibleTable);
                });
            }

            function applyScopePeriod(block, scope, period) {
                if (!scope || !period) {
                    return;
                }
                block.querySelectorAll('[data-stat-list][data-stat-scope="' + scope + '"][data-stat-period]').forEach(function(list) {
                    list.classList.toggle('d-none', list.getAttribute('data-stat-period') !== period);
                });
            }

            document.querySelectorAll('.dashboard-stat-block').forEach(function(block) {
                updateBlock(block);
                block.querySelectorAll('[data-stat-toggle][data-stat-scope]').forEach(function(group) {
                    var scope = group.getAttribute('data-stat-scope') || '';
                    var periodBtn = group.querySelector('[data-stat-period].active');
                    if (periodBtn) {
                        applyScopePeriod(block, scope, periodBtn.getAttribute('data-stat-period'));
                    }
                });
            });

            document.addEventListener('click', function(event) {
                var btn = event.target.closest('[data-stat-mode], [data-stat-period]');
                if (!btn) {
                    return;
                }
                var group = btn.closest('[data-stat-toggle]');
                if (!group) {
                    return;
                }
                event.preventDefault();
                group.querySelectorAll('[data-stat-mode], [data-stat-period]').forEach(function(item) {
                    item.classList.toggle('active', item === btn);
                });
                if (btn.hasAttribute('data-stat-period')) {
                    group.setAttribute('data-stat-period-current', btn.getAttribute('data-stat-period'));
                }
                if (btn.hasAttribute('data-stat-mode')) {
                    group.setAttribute('data-stat-mode-current', btn.getAttribute('data-stat-mode'));
                }
                var block = group.closest('.dashboard-stat-block');
                if (block) {
                    updateBlock(block);
                    var scope = group.getAttribute('data-stat-scope') || '';
                    if (scope && btn.hasAttribute('data-stat-period')) {
                        applyScopePeriod(block, scope, btn.getAttribute('data-stat-period'));
                    }
                }
            });
            // GSC toggle uses CSS radios.
        })();
    </script>
<?php endif; ?>
