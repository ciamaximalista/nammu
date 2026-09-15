<?php
/**
 * Nammu — panel de administración. Acciones POST: Configuración general, plantilla, backups, buscadores, Nisaba/Telex, push, IndexNow y anuncios.
 *
 * Se incluye desde admin.php dentro del ámbito global, una vez validado el token CSRF,
 * cuando la petición trae alguna de las claves de formulario de este grupo.
 */

if (isset($_POST['test_gsc'])) {
        $gsc_property = trim($_POST['gsc_property'] ?? '');
        $gsc_client_id = trim($_POST['gsc_client_id'] ?? '');
        $gsc_client_secret = trim($_POST['gsc_client_secret'] ?? '');
        $gsc_refresh_token = trim($_POST['gsc_refresh_token'] ?? '');
        try {
            $config = load_config_file();
            if ($gsc_property !== '' || $gsc_client_id !== '' || $gsc_client_secret !== '' || $gsc_refresh_token !== '') {
                $config['search_console'] = [
                    'property' => $gsc_property,
                    'client_id' => $gsc_client_id,
                    'client_secret' => $gsc_client_secret,
                    'refresh_token' => $gsc_refresh_token,
                ];
            } else {
                unset($config['search_console']);
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $_SESSION['search_console_feedback'] = [
                'type' => 'danger',
                'message' => 'Error guardando Search Console: ' . $e->getMessage(),
            ];
            header('Location: admin.php?page=configuracion');
            exit;
        }
        $feedback = [
            'type' => 'danger',
            'message' => 'Faltan datos para conectar con Search Console.',
        ];
        if ($gsc_property !== '' && $gsc_client_id !== '' && $gsc_client_secret !== '' && $gsc_refresh_token !== '') {
            try {
                $tokenData = admin_google_refresh_access_token($gsc_client_id, $gsc_client_secret, $gsc_refresh_token);
                $accessToken = $tokenData['access_token'] ?? '';
                if ($accessToken === '') {
                    throw new RuntimeException('No se pudo obtener un access token válido.');
                }
                $opts = [
                    'http' => [
                        'method' => 'GET',
                        'header' => "Authorization: Bearer {$accessToken}\r\n",
                        'timeout' => 12,
                        'ignore_errors' => true,
                    ],
                ];
                $resp = @file_get_contents('https://www.googleapis.com/webmasters/v3/sites', false, stream_context_create($opts));
                $statusLine = $http_response_header[0] ?? '';
                $statusOk = is_string($statusLine) && preg_match('/\s200\s/', $statusLine);
                $decoded = json_decode((string) $resp, true);
                $propertyFound = false;
                if (is_array($decoded) && !empty($decoded['siteEntry'])) {
                    $normalizedProperty = rtrim($gsc_property, '/') . '/';
                    foreach ($decoded['siteEntry'] as $entry) {
                        $siteUrl = $entry['siteUrl'] ?? '';
                        if ($siteUrl === $gsc_property || $siteUrl === $normalizedProperty) {
                            $propertyFound = true;
                            break;
                        }
                    }
                }
                if ($statusOk && $propertyFound) {
                    $feedback = [
                        'type' => 'success',
                        'message' => 'Conexión correcta con Search Console.',
                    ];
                } elseif ($statusOk) {
                    $feedback = [
                        'type' => 'danger',
                        'message' => 'La cuenta no tiene acceso a la propiedad indicada en Search Console.',
                    ];
                } else {
                    $errorMsg = is_array($decoded) ? ($decoded['error']['message'] ?? '') : '';
                    $feedback = [
                        'type' => 'danger',
                        'message' => $errorMsg !== '' ? $errorMsg : 'No se pudo conectar con Search Console.',
                    ];
                }
            } catch (Throwable $e) {
                $feedback = [
                    'type' => 'danger',
                    'message' => 'Error al conectar con Search Console: ' . $e->getMessage(),
                ];
            }
        }
        $_SESSION['search_console_feedback'] = $feedback;
        header('Location: admin.php?page=configuracion');
        exit;
} elseif (isset($_POST['test_bing'])) {
        $bing_site_url = trim($_POST['bing_site_url'] ?? '');
        $bing_client_id = trim($_POST['bing_client_id'] ?? '');
        $bing_client_secret = trim($_POST['bing_client_secret'] ?? '');
        $bing_api_key = trim($_POST['bing_api_key'] ?? '');
        try {
            $config = load_config_file();
            if ($bing_site_url !== '' || $bing_client_id !== '' || $bing_client_secret !== '') {
                $currentBing = $config['bing_webmaster'] ?? [];
                $clearTokens = false;
                if (($currentBing['client_id'] ?? '') !== $bing_client_id || ($currentBing['client_secret'] ?? '') !== $bing_client_secret) {
                    $clearTokens = true;
                }
                $config['bing_webmaster'] = array_merge($currentBing, [
                    'site_url' => $bing_site_url,
                    'client_id' => $bing_client_id,
                    'client_secret' => $bing_client_secret,
                    'api_key' => $bing_api_key,
                ]);
                if ($clearTokens) {
                    $config['bing_webmaster']['refresh_token'] = '';
                    $config['bing_webmaster']['access_token'] = '';
                    $config['bing_webmaster']['access_expires_at'] = 0;
                }
            } else {
                unset($config['bing_webmaster']);
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $_SESSION['bing_webmaster_feedback'] = [
                'type' => 'danger',
                'message' => 'Error guardando Bing Webmaster Tools: ' . $e->getMessage(),
            ];
            header('Location: admin.php?page=configuracion');
            exit;
        }
        $feedback = [
            'type' => 'danger',
            'message' => 'Faltan datos para conectar con Bing Webmaster Tools.',
        ];
        if ($bing_site_url !== '' && ($bing_client_id !== '' && $bing_client_secret !== '')) {
            try {
                $token = admin_bing_get_access_token(true);
                if ($token === null) {
                    $feedback = [
                        'type' => 'success',
                        'message' => 'Credenciales guardadas. Pulsa "Conectar con Bing" para autorizar la cuenta.',
                    ];
                } else {
                    $feedback = [
                        'type' => 'success',
                        'message' => 'Conexión OAuth correcta con Bing Webmaster Tools.',
                    ];
                }
            } catch (Throwable $e) {
                $feedback = [
                    'type' => 'danger',
                    'message' => 'Error al conectar con Bing Webmaster Tools: ' . $e->getMessage(),
                ];
            }
        }
        $_SESSION['bing_webmaster_feedback'] = $feedback;
        header('Location: admin.php?page=configuracion');
        exit;
} elseif (isset($_POST['save_backup_settings'])) {
        $backupDirectory = trim((string) ($_POST['backup_directory'] ?? ''));
        $backupError = null;
        if (admin_update_backup_directory($backupDirectory, $backupError)) {
            $_SESSION['backup_feedback'] = [
                'type' => 'success',
                'message' => 'Directorio de backups actualizado correctamente.',
            ];
        } else {
            $_SESSION['backup_feedback'] = [
                'type' => 'danger',
                'message' => $backupError ?: 'No se pudo actualizar el directorio de backups.',
            ];
        }
        header('Location: admin.php?page=configuracion');
        exit;
} elseif (isset($_POST['restore_stats_backup'])) {
        $selectedBackup = trim((string) ($_POST['stats_backup_file'] ?? ''));
        $restoreError = null;
        if ($selectedBackup === '') {
            $_SESSION['stats_backup_feedback'] = [
                'type' => 'danger',
                'message' => 'Selecciona un backup para restaurar.',
            ];
        } elseif (admin_restore_stats_backup($selectedBackup, $restoreError)) {
            $_SESSION['stats_backup_feedback'] = [
                'type' => 'success',
                'message' => 'Estadísticas restauradas correctamente desde ' . $selectedBackup . '.',
            ];
        } else {
            $_SESSION['stats_backup_feedback'] = [
                'type' => 'danger',
                'message' => $restoreError ?: 'No se pudieron restaurar las estadísticas.',
            ];
        }
        header('Location: admin.php?page=configuracion');
        exit;
} elseif (isset($_POST['save_rejected_origins'])) {
        $rawDomains = trim((string) ($_POST['rejected_origin_domains'] ?? ''));
        $domains = [];
        foreach (preg_split('/[\r\n,]+/', $rawDomains) ?: [] as $entry) {
            $entry = strtolower(trim((string) $entry));
            if ($entry === '') {
                continue;
            }
            if (preg_match('#^https?://#i', $entry) !== 1) {
                $entry = 'https://' . $entry;
            }
            $host = strtolower(trim((string) (parse_url($entry, PHP_URL_HOST) ?? '')));
            $host = preg_replace('/^www\./i', '', $host) ?? $host;
            $host = trim($host, ". \t\n\r\0\x0B");
            if ($host === '' || preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host) !== 1) {
                continue;
            }
            $domains[$host] = true;
        }
        try {
            $config = load_config_file();
            if (!empty($domains)) {
                $config['rejected_origins'] = [
                    'domains' => array_keys($domains),
                ];
                sort($config['rejected_origins']['domains'], SORT_NATURAL | SORT_FLAG_CASE);
                $_SESSION['rejected_origins_feedback'] = [
                    'type' => 'success',
                    'message' => 'Orígenes rechazados guardados correctamente.',
                ];
            } else {
                unset($config['rejected_origins']);
                $_SESSION['rejected_origins_feedback'] = [
                    'type' => 'success',
                    'message' => 'Lista de orígenes rechazados vaciada.',
                ];
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $_SESSION['rejected_origins_feedback'] = [
                'type' => 'danger',
                'message' => 'Error guardando orígenes rechazados: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=configuracion#rejected-origins');
        exit;
} elseif (isset($_POST['save_settings'])) {
        $sort_order = $_POST['sort_order'] ?? 'date';
        $sort_order = $sort_order === 'alpha' ? 'alpha' : 'date';
        $site_author = trim($_POST['site_author'] ?? '');
        $site_name = trim($_POST['site_name'] ?? '');
        $site_url = trim($_POST['site_url'] ?? '');
        $site_lang = trim($_POST['site_lang'] ?? 'es');
        $eupl_notice = isset($_POST['eupl_notice']) ? 'on' : 'off';
        $multi_instance_enabled = isset($_POST['multi_instance_enabled']) ? 'on' : 'off';
        $multi_instance_cluster = trim((string) ($_POST['multi_instance_cluster'] ?? ''));
        $multi_instance_shared_cache_dir = trim((string) ($_POST['multi_instance_shared_cache_dir'] ?? ''));
        $multi_instance_shared_queue_dir = trim((string) ($_POST['multi_instance_shared_queue_dir'] ?? ''));
        $multi_instance_instances_root_dir = trim((string) ($_POST['multi_instance_instances_root_dir'] ?? ''));
        $multi_instance_scheduler_mode = trim((string) ($_POST['multi_instance_scheduler_mode'] ?? 'standalone'));
        $multi_instance_scheduler_strategy = trim((string) ($_POST['multi_instance_scheduler_strategy'] ?? 'fixed'));
        if (!in_array($multi_instance_scheduler_mode, ['standalone', 'central'], true)) {
            $multi_instance_scheduler_mode = 'standalone';
        }
        if (!in_array($multi_instance_scheduler_strategy, ['fixed', 'activity'], true)) {
            $multi_instance_scheduler_strategy = 'fixed';
        }
        $social_default_description = trim($_POST['social_default_description'] ?? '');

        try {
            $config = load_config_file();

            $config['pages_order_by'] = $sort_order;
            $config['pages_order'] = $sort_order === 'date' ? 'desc' : 'asc';

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
            if ($site_url !== '') {
                $config['site_url'] = $site_url;
            } else {
                unset($config['site_url']);
            }
            if ($site_lang !== '') {
                $config['site_lang'] = $site_lang;
            } else {
                unset($config['site_lang']);
            }
            $config['eupl_notice'] = $eupl_notice;
            if (
                $multi_instance_enabled === 'on'
                || $multi_instance_cluster !== ''
                || $multi_instance_shared_cache_dir !== ''
                || $multi_instance_shared_queue_dir !== ''
                || $multi_instance_instances_root_dir !== ''
                || $multi_instance_scheduler_mode !== 'standalone'
                || $multi_instance_scheduler_strategy !== 'fixed'
            ) {
                $config['multi_instance'] = [
                    'enabled' => $multi_instance_enabled,
                    'cluster' => $multi_instance_cluster,
                    'shared_cache_dir' => $multi_instance_shared_cache_dir,
                    'shared_queue_dir' => $multi_instance_shared_queue_dir,
                    'instances_root_dir' => $multi_instance_instances_root_dir,
                    'scheduler_mode' => $multi_instance_scheduler_mode,
                    'scheduler_strategy' => $multi_instance_scheduler_strategy,
                ];
            } else {
                unset($config['multi_instance']);
            }

            $social = $config['social'] ?? [];
            if ($social_default_description !== '') {
                $social['default_description'] = $social_default_description;
            } else {
                unset($social['default_description']);
            }
            if (!empty($social)) {
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
} elseif (isset($_POST['save_machine_files'])) {
        $llmsContent = trim((string) ($_POST['llms_content'] ?? ''));
        $identityContent = trim((string) ($_POST['identity_content'] ?? ''));

        try {
            $config = load_config_file();

            if ($llmsContent !== '') {
                $config['llms'] = ['content' => $llmsContent];
            } else {
                unset($config['llms']);
            }

            if ($identityContent !== '') {
                $config['identity'] = ['content' => $identityContent];
            } else {
                unset($config['identity']);
            }

            save_config_file($config);
        } catch (Throwable $e) {
            $error = "Error guardando llms.txt e identity.txt: " . $e->getMessage();
        }

        header('Location: admin.php?page=configuracion');
        exit;
} elseif (isset($_POST['save_contact'])) {
        $contact_telegram = trim($_POST['contact_telegram'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $contact_footer = isset($_POST['contact_footer']) ? 'on' : 'off';
        $contact_signature = isset($_POST['contact_signature']) ? 'on' : 'off';
        $contact_signature_fields = $_POST['contact_signature_fields'] ?? [];
        if (!is_array($contact_signature_fields)) {
            $contact_signature_fields = [];
        }
        $contact_signature_fields = array_values(array_intersect(['telegram', 'email', 'phone'], $contact_signature_fields));
        try {
            $config = load_config_file();
            if ($contact_telegram !== '' || $contact_email !== '' || $contact_phone !== '' || $contact_footer === 'on' || $contact_signature === 'on') {
                $config['contact'] = [
                    'telegram' => $contact_telegram,
                    'email' => $contact_email,
                    'phone' => $contact_phone,
                    'footer' => $contact_footer,
                    'signature' => $contact_signature,
                    'signature_fields' => $contact_signature_fields,
                ];
            } else {
                unset($config['contact']);
            }
            save_config_file($config);
            $_SESSION['contact_feedback'] = [
                'type' => 'success',
                'message' => 'Formas de contacto guardadas correctamente.',
            ];
        } catch (Throwable $e) {
            $_SESSION['contact_feedback'] = [
                'type' => 'danger',
                'message' => "Error guardando las formas de contacto: " . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=configuracion');
        exit;
} elseif (isset($_POST['save_google_fonts'])) {
        $google_fonts_api = trim($_POST['google_fonts_api'] ?? '');
        try {
            $config = load_config_file();
            if ($google_fonts_api !== '') {
                $config['google_fonts_api'] = $google_fonts_api;
            } else {
                unset($config['google_fonts_api']);
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $error = "Error guardando Google Fonts: " . $e->getMessage();
        }
        header('Location: admin.php?page=configuracion');
        exit;
} elseif (isset($_POST['save_nisaba'])) {
        $nisaba_raw = trim((string) ($_POST['nisaba_urls'] ?? ''));
        $nisaba_urls = array_values(array_filter(array_map('trim', preg_split('/\\r?\\n/', $nisaba_raw))));
        $nisaba_urls = array_values(array_filter($nisaba_urls, static function (string $url): bool {
            return $url !== '' && preg_match('#^https?://#i', $url);
        }));
        try {
            $config = load_config_file();
            if (!empty($nisaba_urls)) {
                $config['nisaba'] = [
                    'urls' => $nisaba_urls,
                ];
                $_SESSION['nisaba_feedback'] = [
                    'type' => 'success',
                    'message' => 'Configuración de Nisaba guardada correctamente.',
                ];
            } else {
                unset($config['nisaba']);
                $_SESSION['nisaba_feedback'] = [
                    'type' => 'success',
                    'message' => 'Integración con Nisaba desactivada.',
                ];
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $_SESSION['nisaba_feedback'] = [
                'type' => 'danger',
                'message' => 'Error guardando Nisaba: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=configuracion');
        exit;
} elseif (isset($_POST['save_telex'])) {
        $telex_raw = trim($_POST['telex_urls'] ?? '');
        $telex_urls = array_values(array_filter(array_map('trim', preg_split('/\\r?\\n/', $telex_raw))));
        $telex_urls = array_values(array_filter($telex_urls, static function (string $url): bool {
            return $url !== '' && preg_match('~\\.xml(\\?|#|$)~i', $url);
        }));
        try {
            $config = load_config_file();
            if (!empty($telex_urls)) {
                $config['telex'] = [
                    'urls' => $telex_urls,
                ];
                $_SESSION['telex_feedback'] = [
                    'type' => 'success',
                    'message' => 'Configuración de Telex guardada correctamente.',
                ];
            } else {
                unset($config['telex']);
                $_SESSION['telex_feedback'] = [
                    'type' => 'success',
                    'message' => 'Integración con Telex desactivada.',
                ];
            }
            save_config_file($config);
        } catch (Throwable $e) {
            $_SESSION['telex_feedback'] = [
                'type' => 'danger',
                'message' => 'Error guardando Telex: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=configuracion');
        exit;
} elseif (isset($_POST['save_ads_settings'])) {
        $enabled = isset($_POST['ads_enabled']) ? 'on' : 'off';
        $scope = $_POST['ads_scope'] ?? 'home';
        if (!in_array($scope, ['home', 'all'], true)) {
            $scope = 'home';
        }
        $text = trim((string) ($_POST['ads_text'] ?? ''));
        $image = trim((string) ($_POST['ads_image'] ?? ''));
        $link = trim((string) ($_POST['ads_link'] ?? ''));
        $linkLabel = trim((string) ($_POST['ads_link_label'] ?? ''));
        try {
            $config = load_config_file();
            if (!isset($config['ads'])) {
                $config['ads'] = [];
            }
            $config['ads']['enabled'] = $enabled;
            $config['ads']['scope'] = $scope;
            $config['ads']['text'] = $text;
            $config['ads']['image'] = $image;
            $config['ads']['link'] = $link;
            $config['ads']['link_label'] = $linkLabel;
            save_config_file($config);
            $_SESSION['ads_feedback'] = [
                'type' => 'success',
                'message' => 'Preferencias de anuncios guardadas.',
            ];
        } catch (Throwable $e) {
            $_SESSION['ads_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudieron guardar las preferencias: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=anuncios');
        exit;
} elseif (isset($_POST['save_push_settings'])) {
        $pushEnabled = isset($_POST['push_enabled']) ? 'on' : 'off';
        $pushPosts = isset($_POST['push_posts']) ? 'on' : 'off';
        $pushItineraries = isset($_POST['push_itineraries']) ? 'on' : 'off';
        try {
            $config = load_config_file();
            if (!isset($config['ads'])) {
                $config['ads'] = [];
            }
            $config['ads']['push_enabled'] = $pushEnabled;
            $config['ads']['push_posts'] = $pushPosts;
            $config['ads']['push_itineraries'] = $pushItineraries;
            save_config_file($config);
            $_SESSION['ads_feedback'] = [
                'type' => 'success',
                'message' => 'Preferencias de notificaciones push guardadas.',
            ];
        } catch (Throwable $e) {
            $_SESSION['ads_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudieron guardar las preferencias: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=anuncios');
        exit;
} elseif (isset($_POST['save_indexnow_settings'])) {
        $indexnowEnabled = isset($_POST['indexnow_enabled']) ? 'on' : 'off';
        try {
            $config = load_config_file();
            if (!isset($config['indexnow'])) {
                $config['indexnow'] = [];
            }
            $config['indexnow']['enabled'] = $indexnowEnabled;
            if ($indexnowEnabled === 'on') {
                admin_indexnow_prepare_config($config);
            }
            save_config_file($config);
            $_SESSION['ads_feedback'] = [
                'type' => 'success',
                'message' => 'Preferencias de IndexNow guardadas.',
            ];
        } catch (Throwable $e) {
            $_SESSION['ads_feedback'] = [
                'type' => 'danger',
                'message' => 'No se pudieron guardar las preferencias de IndexNow: ' . $e->getMessage(),
            ];
        }
        header('Location: admin.php?page=anuncios');
        exit;
} elseif (isset($_POST['save_template'])) {
        $config = load_config_file();
        $defaults = get_default_template_settings();

        $fonts = [
            'title' => trim($_POST['title_font'] ?? ''),
            'body' => trim($_POST['body_font'] ?? ''),
            'note' => trim($_POST['note_font'] ?? ''),
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
        $footerNammuPosted = isset($_POST['footer_nammu']) ? 'on' : 'off';
        $footerLogoPosted = $_POST['footer_logo_position'] ?? $defaults['footer_logo'];
        if (!in_array($footerLogoPosted, ['none', 'top', 'bottom'], true)) {
            $footerLogoPosted = $defaults['footer_logo'];
        }
        $logoImage = trim($_POST['logo_image'] ?? '');
        $images = ['logo' => $logoImage];
        $homeContentPosted = $_POST['home_content'] ?? ($defaults['home']['content'] ?? 'blog');
        if (!in_array($homeContentPosted, ['blog', 'podcast', 'fediverse', 'itineraries'], true)) {
            $homeContentPosted = $defaults['home']['content'] ?? 'blog';
        }
        $homeColumnsPosted = isset($_POST['home_columns']) ? (int) $_POST['home_columns'] : $defaults['home']['columns'];
        if (!in_array($homeColumnsPosted, [1, 2, 3], true)) {
            $homeColumnsPosted = $defaults['home']['columns'];
        }
        $homeFirstRowEnabled = isset($_POST['home_first_row_enabled']) && $_POST['home_first_row_enabled'] === '1';
        $homeFirstRowColumns = isset($_POST['home_first_row_columns']) ? (int) $_POST['home_first_row_columns'] : ($defaults['home']['first_row_columns'] ?? $homeColumnsPosted);
        if (!in_array($homeFirstRowColumns, [1, 2, 3], true)) {
            $homeFirstRowColumns = $homeColumnsPosted;
        }
        $homeFirstRowFill = $_POST['home_first_row_fill'] ?? ($defaults['home']['first_row_fill'] ?? 'off');
        $homeFirstRowFill = $homeFirstRowFill === 'on' ? 'on' : 'off';
        $homeFirstRowAlign = $_POST['home_first_row_align'] ?? ($defaults['home']['first_row_align'] ?? 'left');
        if (!in_array($homeFirstRowAlign, ['left', 'center'], true)) {
            $homeFirstRowAlign = 'left';
        }
        $homeFirstRowStyle = $_POST['home_first_row_style'] ?? ($defaults['home']['first_row_style'] ?? 'inherit');
        if (!in_array($homeFirstRowStyle, ['inherit', 'boxed', 'flat'], true)) {
            $homeFirstRowStyle = 'inherit';
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
        $homeCardStylePosted = in_array($homeCardStylePosted, ['full', 'square-right', 'square-tall-right', 'circle-right'], true)
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
        $homeHeaderButtonsPosted = $_POST['home_header_buttons'] ?? ($defaults['home']['header_buttons'] ?? 'none');
        if (!in_array($homeHeaderButtonsPosted, ['home', 'both', 'none'], true)) {
            $homeHeaderButtonsPosted = $defaults['home']['header_buttons'] ?? 'none';
        }
        $homeDictionaryIntroPosted = (string) ($_POST['home_dictionary_intro'] ?? '');
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
        $searchDefaults = $defaults['search'] ?? ['mode' => 'single', 'position' => 'footer', 'floating' => 'off', 'fediverse_floating_cta' => 'on'];
        $searchModePosted = $_POST['search_mode'] ?? $searchDefaults['mode'];
        if (!in_array($searchModePosted, ['none', 'home', 'single', 'both'], true)) {
            $searchModePosted = $searchDefaults['mode'];
        }
        $searchPositionPosted = $_POST['search_position'] ?? $searchDefaults['position'];
        if (!in_array($searchPositionPosted, ['title', 'footer'], true)) {
            $searchPositionPosted = $searchDefaults['position'];
        }
        if ($searchModePosted === 'none') {
            $searchPositionPosted = $searchDefaults['position'];
        }
        $searchFloatingPosted = $_POST['search_floating'] ?? ($searchDefaults['floating'] ?? 'off');
        if (!in_array($searchFloatingPosted, ['off', 'on'], true)) {
            $searchFloatingPosted = $searchDefaults['floating'] ?? 'off';
        }
        $searchFediverseFloatingCtaPosted = $_POST['search_fediverse_floating_cta'] ?? ($searchDefaults['fediverse_floating_cta'] ?? 'on');
        if (!in_array($searchFediverseFloatingCtaPosted, ['off', 'on'], true)) {
            $searchFediverseFloatingCtaPosted = $searchDefaults['fediverse_floating_cta'] ?? 'on';
        }
        $subscriptionDefaults = $defaults['subscription'] ?? ['mode' => 'none', 'position' => 'footer', 'floating' => 'off'];
        $subscriptionModePosted = $_POST['subscription_mode'] ?? $subscriptionDefaults['mode'];
        if (!in_array($subscriptionModePosted, ['none', 'home', 'single', 'both'], true)) {
            $subscriptionModePosted = $subscriptionDefaults['mode'];
        }
        $subscriptionPositionPosted = $_POST['subscription_position'] ?? $subscriptionDefaults['position'];
        if (!in_array($subscriptionPositionPosted, ['title', 'footer'], true)) {
            $subscriptionPositionPosted = $subscriptionDefaults['position'];
        }
        if ($subscriptionModePosted === 'none') {
            $subscriptionPositionPosted = $subscriptionDefaults['position'];
        }
        $subscriptionFloatingPosted = $_POST['subscription_floating'] ?? ($subscriptionDefaults['floating'] ?? 'off');
        if (!in_array($subscriptionFloatingPosted, ['off', 'on'], true)) {
            $subscriptionFloatingPosted = $subscriptionDefaults['floating'] ?? 'off';
        }
        $entryTocDefaults = $defaults['entry']['toc'] ?? ['auto' => 'off', 'min_headings' => 3];
        $entryAutoPosted = $_POST['entry_toc_auto'] ?? ($entryTocDefaults['auto'] ?? 'off');
        if (!in_array($entryAutoPosted, ['on', 'off'], true)) {
            $entryAutoPosted = $entryTocDefaults['auto'] ?? 'off';
        }
        $entryMinPosted = (int) ($_POST['entry_toc_min'] ?? ($entryTocDefaults['min_headings'] ?? 3));
        if (!in_array($entryMinPosted, [2, 3, 4], true)) {
            $entryMinPosted = $entryTocDefaults['min_headings'] ?? 3;
        }

        $config['template'] = [
            'fonts' => $fonts,
            'colors' => $colors,
            'images' => $images,
            'footer' => $footerMd,
            'footer_logo' => $footerLogoPosted,
            'footer_nammu' => $footerNammuPosted,
            'global' => [
                'corners' => $cornerStylePosted,
            ],
            'home' => [
                'content' => $homeContentPosted,
                'columns' => $homeColumnsPosted,
                'first_row_enabled' => $homeFirstRowEnabled ? 'on' : 'off',
                'first_row_columns' => $homeFirstRowColumns,
                'first_row_fill' => $homeFirstRowFill,
                'first_row_align' => $homeFirstRowAlign,
                'first_row_style' => $homeFirstRowStyle,
                'per_page' => $homePerPageValue,
                'card_style' => $homeCardStylePosted,
                'full_image_mode' => $homeFullImageModePosted,
                'blocks' => $homeBlocksModePosted,
                'dictionary_intro' => $homeDictionaryIntroPosted,
                'header_buttons' => $homeHeaderButtonsPosted,
                'header' => [
                    'type' => $homeHeaderTypePosted,
                    'image' => $homeHeaderImagePosted,
                    'mode' => $homeHeaderModePosted,
                    'text_style' => $homeHeaderTextStylePosted,
                    'order' => $homeHeaderOrderPosted,
                ],
            ],
            'search' => [
                'mode' => $searchModePosted,
                'position' => $searchPositionPosted,
                'floating' => $searchFloatingPosted,
                'fediverse_floating_cta' => $searchFediverseFloatingCtaPosted,
            ],
            'subscription' => [
                'mode' => $subscriptionModePosted,
                'position' => $subscriptionPositionPosted,
                'floating' => $subscriptionFloatingPosted,
            ],
            'entry' => [
                'toc' => [
                    'auto' => $entryAutoPosted,
                    'min_headings' => $entryMinPosted,
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
