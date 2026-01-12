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
    $gscCacheTtl = 2 * 24 * 60 * 60;
    $gscCache = null;
    $gscUpdatedAtLabel = '';
    $gscForceRefresh = isset($_GET['gsc_refresh']) && $_GET['gsc_refresh'] === '1';
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
            return $gscCountryNames[$code] ?? '';
        }
        if (strlen($code) === 3) {
            return $gscCountryNames3[$code] ?? '';
        }
        return '';
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
    $gscCacheFresh = $gscCacheHasNew
        && (time() - (int) $gscCache['updated_at']) < $gscCacheTtl
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
            $endDate = $today->format('Y-m-d');
            $start28 = $today->modify('-27 days')->format('Y-m-d');
            $start7 = $today->modify('-6 days')->format('Y-m-d');
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

    $collectSourceUids = static function (string $category) use ($sourcesDaily, $startKey): array {
        $result = [];
        foreach ($sourcesDaily as $day => $payload) {
            if (!is_string($day) || $day < $startKey) {
                continue;
            }
            $bucket = is_array($payload) ? ($payload[$category] ?? []) : [];
            if (!is_array($bucket)) {
                continue;
            }
            $uids = $bucket['uids'] ?? [];
            if (is_array($uids)) {
                foreach ($uids as $uid => $flag) {
                    $result[$category][$uid] = true;
                }
            }
            $details = $bucket['detail'] ?? [];
            if (is_array($details)) {
                foreach ($details as $label => $detailPayload) {
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
    foreach (['direct', 'search', 'social', 'other'] as $bucket) {
        $sourceMain[$bucket] = [];
    }
    foreach ($sourcesDaily as $day => $payload) {
        if (!is_string($day) || $day < $startKey) {
            continue;
        }
        foreach (['direct', 'search', 'social', 'other'] as $bucket) {
            $bucketData = is_array($payload) ? ($payload[$bucket] ?? []) : [];
            $uids = is_array($bucketData) ? ($bucketData['uids'] ?? []) : [];
            foreach ($uids as $uid => $flag) {
                $sourceMain[$bucket][$uid] = true;
            }
        }
    }
    $sourceMainLabels = [
        'direct' => 'Entrada directa',
        'search' => 'Buscadores',
        'social' => 'Redes sociales',
        'other' => 'Otros',
    ];
    $sourceMainRows = $buildPercentTable($sourceMain, $sourceMainLabels);
    $searchDetailRows = $buildPercentTable($collectSourceUids('search'), []);
    $socialDetailRows = $buildPercentTable($collectSourceUids('social'), []);
    $otherDetailRows = $buildPercentTable($collectSourceUids('other'), []);

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
        $count = $monthlyTotals[$monthKey] ?? 0;
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
            'count' => $yearlyTotals[$year] ?? 0,
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
            .dashboard-toggle .btn.active {
                background: #1b8eed;
                color: #ffffff;
                border-color: #1b8eed;
            }
        </style>
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
            <div>
                <h2 class="mb-1">Escritorio Nammu</h2>
                <p class="text-muted mb-0">Resumen general de publicaciones y estadísticas del sitio.</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <h4 class="h6 text-uppercase text-muted mb-3">Usuarios únicos humanos (últimos 30 días)</h4>
                        <?php if ($last30Line['points'] === ''): ?>
                            <p class="text-muted mb-0">Sin datos todavía.</p>
                        <?php else: ?>
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
                                </g>
                            </svg>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4">
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
                <div class="card mb-4 dashboard-stat-block">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Búsquedas internas más frecuentes (últimos 30 días)</h4>
                            <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle" role="group" data-stat-toggle="internal-search" data-stat-toggle-type="mode">
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
            </div>

            <div class="col-lg-8">
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
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <h4 class="h6 text-uppercase text-muted mb-0 dashboard-card-title">Páginas más leídas</h4>
                                <div class="btn-group btn-group-sm btn-group-toggle dashboard-toggle" role="group" data-stat-toggle="pages-all" data-stat-toggle-type="mode">
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
                                                <td class="text-right"><?= (int) $item['percent'] ?>%</td>
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
                                                    <td class="text-right"><?= (int) $item['percent'] ?>%</td>
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
                                                    <td class="text-right"><?= (int) $item['percent'] ?>%</td>
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
                                                    <td><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td class="text-right"><?= (int) $item['percent'] ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($gscProperty !== '' && $gscClientId !== '' && $gscClientSecret !== '' && $gscRefreshToken !== ''): ?>
                    <div class="card mb-4">
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
                                <div class="btn-group btn-group-sm mb-3" role="group" data-stat-toggle data-stat-toggle-type="period" data-stat-scope="gsc-main">
                                    <button type="button" class="btn btn-outline-secondary active" data-stat-period="28">Últimos 28 días</button>
                                    <button type="button" class="btn btn-outline-secondary" data-stat-period="7">Últimos 7 días</button>
                                </div>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm mb-0" data-stat-list data-stat-period="28" data-stat-scope="gsc-main">
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
                                    <table class="table table-sm mb-0 d-none" data-stat-list data-stat-period="7" data-stat-scope="gsc-main">
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
                                    <div class="btn-group btn-group-sm mb-3" role="group" data-stat-toggle data-stat-toggle-type="period" data-stat-scope="gsc-terms">
                                        <button type="button" class="btn btn-outline-secondary active" data-stat-period="28">Últimos 28 días</button>
                                        <button type="button" class="btn btn-outline-secondary" data-stat-period="7">Últimos 7 días</button>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscQueries28)): ?>
                                    <div class="table-responsive" data-stat-list data-stat-period="28" data-stat-scope="gsc-terms">
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
                                    <div class="table-responsive d-none" data-stat-list data-stat-period="7" data-stat-scope="gsc-terms">
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
                                    <div class="btn-group btn-group-sm mb-3" role="group" data-stat-toggle data-stat-toggle-type="period" data-stat-scope="gsc-pages">
                                        <button type="button" class="btn btn-outline-secondary active" data-stat-period="28">Últimos 28 días</button>
                                        <button type="button" class="btn btn-outline-secondary" data-stat-period="7">Últimos 7 días</button>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscPages7)): ?>
                                    <div class="table-responsive mb-3 d-none" data-stat-list data-stat-period="7" data-stat-scope="gsc-pages">
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
                                    <div class="table-responsive mb-3" data-stat-list data-stat-period="28" data-stat-scope="gsc-pages">
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
                                    <div class="btn-group btn-group-sm mb-3" role="group" data-stat-toggle data-stat-toggle-type="period" data-stat-scope="gsc-countries">
                                        <button type="button" class="btn btn-outline-secondary active" data-stat-period="28">Últimos 28 días</button>
                                        <button type="button" class="btn btn-outline-secondary" data-stat-period="7">Últimos 7 días</button>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscCountries7)): ?>
                                    <div class="table-responsive mb-3 d-none" data-stat-list data-stat-period="7" data-stat-scope="gsc-countries">
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
                                                        <td><?= htmlspecialchars($row['country'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($gscCountries28)): ?>
                                    <div class="table-responsive" data-stat-list data-stat-period="28" data-stat-scope="gsc-countries">
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
                                                        <td><?= htmlspecialchars($row['country'], ENT_QUOTES, 'UTF-8') ?></td>
                                                        <td class="text-right"><?= (int) $row['clicks'] ?></td>
                                                        <td class="text-right"><?= (int) $row['impressions'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
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
                var periodByScope = {};
                var modeByScope = {};

                block.querySelectorAll('[data-stat-toggle]').forEach(function(group) {
                    var scope = group.getAttribute('data-stat-scope') || '';
                    var modeBtn = group.querySelector('[data-stat-mode].active');
                    var periodBtn = group.querySelector('[data-stat-period].active');
                    if (modeBtn) {
                        modeByScope[scope] = modeBtn.getAttribute('data-stat-mode');
                    }
                    if (periodBtn) {
                        periodByScope[scope] = periodBtn.getAttribute('data-stat-period');
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
            }

            document.querySelectorAll('.dashboard-stat-block').forEach(function(block) {
                updateBlock(block);
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
                var block = group.closest('.dashboard-stat-block');
                if (block) {
                    updateBlock(block);
                }
            });
        })();
    </script>
<?php endif; ?>
