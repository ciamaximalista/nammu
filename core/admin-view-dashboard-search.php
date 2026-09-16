<?php
/**
 * Nammu — panel de administración. Datos del Escritorio: Integración con buscadores: cachés y datos de Google Search Console y Bing Webmaster Tools (incluye la tabla de
 * nombres de país y el resumen multi-instancia).
 * Lo incluye core/admin-view-dashboard.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 */
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
$multiInstanceDashboard = [
    'enabled' => false,
    'cluster' => '',
    'sites' => 0,
    'shared_cache_dir' => '',
    'shared_queue_dir' => '',
    'scheduler_mode' => 'standalone',
    'scheduler_strategy' => 'fixed',
    'tracked_hosts' => 0,
    'hosts_in_backoff' => 0,
    'recent_light_runs' => 0,
    'recent_maintenance_runs' => 0,
    'recent_heavy_runs' => 0,
    'fresh_sites' => 0,
    'warm_sites' => 0,
    'idle_sites' => 0,
    'runner_label' => '',
    'runner_url' => '',
    'runner_at_label' => '',
];
if (function_exists('admin_multi_instance_settings')) {
    $multiSettings = admin_multi_instance_settings($settings);
    if (($multiSettings['enabled'] ?? 'off') === 'on' && ($multiSettings['scheduler_mode'] ?? 'standalone') === 'central') {
        $multiInstanceDashboard['enabled'] = true;
        $multiInstanceDashboard['cluster'] = trim((string) ($multiSettings['cluster'] ?? ''));
        $multiInstanceDashboard['shared_cache_dir'] = trim((string) ($multiSettings['shared_cache_dir'] ?? ''));
        $multiInstanceDashboard['shared_queue_dir'] = trim((string) ($multiSettings['shared_queue_dir'] ?? ''));
        $multiInstanceDashboard['scheduler_mode'] = 'central';
        $multiInstanceDashboard['scheduler_strategy'] = trim((string) ($multiSettings['scheduler_strategy'] ?? 'fixed'));
        if (function_exists('admin_multi_instance_discover_cluster_sites')) {
            $clusterSites = admin_multi_instance_discover_cluster_sites($settings);
            $multiInstanceDashboard['sites'] = count($clusterSites);
            if (function_exists('admin_multi_instance_site_activity_profile')) {
                $clusterNow = time();
                foreach ($clusterSites as $clusterSite) {
                    $profile = admin_multi_instance_site_activity_profile(is_array($clusterSite) ? $clusterSite : [], $clusterNow);
                    $profileName = trim((string) ($profile['name'] ?? 'idle'));
                    if ($profileName === 'fresh') {
                        $multiInstanceDashboard['fresh_sites']++;
                    } elseif ($profileName === 'warm') {
                        $multiInstanceDashboard['warm_sites']++;
                    } else {
                        $multiInstanceDashboard['idle_sites']++;
                    }
                }
            }
        }
        if (function_exists('admin_multi_instance_scheduler_state_load')) {
            $clusterState = admin_multi_instance_scheduler_state_load($settings);
            $runs = is_array($clusterState['runs'] ?? null) ? $clusterState['runs'] : [];
            $currentHour = date('Y-m-d-H');
            $currentSlotPrefix = date('Y-m-d-H:');
            $runner = is_array($clusterState['last_runner'] ?? null) ? $clusterState['last_runner'] : [];
            $multiInstanceDashboard['runner_label'] = trim((string) ($runner['site_name'] ?? '')) ?: trim((string) ($runner['slug'] ?? ''));
            $multiInstanceDashboard['runner_url'] = trim((string) ($runner['site_url'] ?? ''));
            $runnerTimestamp = (int) ($runner['timestamp'] ?? 0);
            if ($runnerTimestamp > 0) {
                $multiInstanceDashboard['runner_at_label'] = date('d/m/Y H:i', $runnerTimestamp);
            }
            foreach ($runs as $run) {
                if (!is_array($run)) {
                    continue;
                }
                if (str_starts_with((string) ($run['light_slot'] ?? ''), $currentSlotPrefix)) {
                    $multiInstanceDashboard['recent_light_runs']++;
                }
                if (str_starts_with((string) ($run['maintenance_slot'] ?? ''), $currentSlotPrefix)) {
                    $multiInstanceDashboard['recent_maintenance_runs']++;
                }
                if ((string) ($run['heavy_hour'] ?? '') === $currentHour) {
                    $multiInstanceDashboard['recent_heavy_runs']++;
                }
            }
        }
        if (function_exists('nammu_multi_instance_remote_host_state_file')) {
            $hostStateFile = nammu_multi_instance_remote_host_state_file($settings);
            if ($hostStateFile !== '' && is_file($hostStateFile)) {
                $hostState = json_decode((string) @file_get_contents($hostStateFile), true);
                $hosts = is_array($hostState['hosts'] ?? null) ? $hostState['hosts'] : [];
                $multiInstanceDashboard['tracked_hosts'] = count($hosts);
                $nowMicro = microtime(true);
                foreach ($hosts as $hostPayload) {
                    if (!is_array($hostPayload)) {
                        continue;
                    }
                    if ((float) ($hostPayload['backoff_until'] ?? 0) > $nowMicro) {
                        $multiInstanceDashboard['hosts_in_backoff']++;
                    }
                }
            }
        }
    }
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
