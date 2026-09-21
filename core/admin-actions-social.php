<?php
/**
 * Nammu — panel de administración. Acciones POST: Envíos manuales a redes sociales y guardado de la configuración de redes.
 *
 * Se incluye desde admin.php dentro del ámbito global, una vez validado el token CSRF,
 * cuando la petición trae alguna de las claves de formulario de este grupo.
 */

use Nammu\Core\ItineraryRepository;

if (isset($_POST['send_social_post'])) {
        $networkKey = $_POST['social_network'] ?? '';
        $filename = $_POST['social_filename'] ?? '';
        $templateTarget = $_POST['social_template'] ?? 'single';
        $templateTarget = in_array($templateTarget, ['single', 'page', 'draft', 'newsletter', 'podcast'], true) ? $templateTarget : 'single';
        $redirectTemplate = urlencode($templateTarget);
        $networkLabels = admin_social_network_labels(true);
        if (!isset($networkLabels[$networkKey])) {
            $_SESSION['social_feedback'] = ['type' => 'danger', 'message' => 'Red social no válida.'];
            header('Location: admin.php?page=edit&template=' . $redirectTemplate);
            exit;
        }
        $filename = nammu_normalize_filename($filename);
        $feedback = [
            'type' => 'danger',
            'message' => 'No se pudo encontrar la entrada solicitada.',
        ];
        if ($filename !== '' && is_file(CONTENT_DIR . '/' . $filename)) {
            $postData = get_post_content($filename);
            if ($postData) {
                $metadata = $postData['metadata'] ?? [];
                $template = strtolower(trim((string) ($metadata['Template'] ?? 'post')));
                if (in_array($template, ['single', 'post', 'page', 'podcast'], true)) {
                    $slug = pathinfo($filename, PATHINFO_FILENAME);
                    $slug = $slug !== '' ? $slug : $filename;
                    $title = (string) ($metadata['Title'] ?? $slug);
                    $description = (string) ($metadata['Description'] ?? '');
                    $image = $metadata['Image'] ?? '';
                    $imageUrl = admin_public_asset_url((string) $image);
                    $customUrl = '';
                    if ($networkKey === 'fediverse') {
                        if (!function_exists('nammu_fediverse_deliver_named_local_item') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
                            require_once NAMMU_ROOT . '/core/fediverso.php';
                        }
                        if (!function_exists('nammu_fediverse_deliver_named_local_item')) {
                            $feedback['message'] = 'La integración con Fediverso no está disponible en esta instalación.';
                            $_SESSION['social_feedback'] = $feedback;
                            header('Location: admin.php?page=edit&template=' . $redirectTemplate);
                            exit;
                        }
                        $fediverseResult = nammu_fediverse_deliver_named_local_item($slug, $template, load_config_file());
                        $feedback = [
                            'type' => !empty($fediverseResult['ok']) ? 'success' : 'danger',
                            'message' => (string) ($fediverseResult['message'] ?? 'No se pudo reenviar el contenido al Fediverso.'),
                        ];
                        $_SESSION['social_feedback'] = $feedback;
                        header('Location: admin.php?page=edit&template=' . $redirectTemplate);
                        exit;
                    }
                    if ($template === 'podcast') {
                        $customUrl = admin_public_podcast_url($slug);
                        $imagePath = (string) ($metadata['Image'] ?? '');
                        $imageUrl = admin_public_asset_url($imagePath);
                        if ($customUrl === '') {
                            $feedback['message'] = 'No se encontró la URL pública del episodio para compartir.';
                            $_SESSION['social_feedback'] = $feedback;
                            header('Location: admin.php?page=edit&template=' . $redirectTemplate);
                            exit;
                        }
                    }
                    $allSocialSettings = admin_cached_social_settings();
                    $networkSettings = $allSocialSettings[$networkKey] ?? [];
                    $sendResult = admin_send_content_to_social_network($networkKey, $slug, $title, $description, (string) $image, $networkSettings, $customUrl, $imageUrl, 'publicación');
                    $feedback = [
                        'type' => !empty($sendResult['ok']) ? 'success' : 'danger',
                        'message' => (string) ($sendResult['message'] ?? ''),
                    ];
                } else {
                    $feedback['message'] = 'Sólo las entradas, páginas y podcasts pueden enviarse a redes sociales.';
                }
            }
        }
        $_SESSION['social_feedback'] = $feedback;
        header('Location: admin.php?page=edit&template=' . $redirectTemplate);
        exit;
} elseif (isset($_POST['send_social_actuality'])) {
        $networkKey = trim((string) ($_POST['social_network'] ?? ''));
        $actualityType = trim((string) ($_POST['actuality_type'] ?? ''));
        $actualityId = preg_replace('/[^a-f0-9]/i', '', (string) ($_POST['actuality_id'] ?? '')) ?? '';
        $templateTarget = $actualityType === 'news' ? 'news' : 'notes';
        $networkLabels = admin_social_network_labels(true);
        $feedback = [
            'type' => 'danger',
            'message' => 'No se pudo encontrar el contenido solicitado.',
        ];
        if (!isset($networkLabels[$networkKey])) {
            $feedback['message'] = 'Red social no válida.';
        } elseif ($actualityId !== '') {
            if (!function_exists('nammu_actuality_get_manual_item') && is_file(NAMMU_ROOT . '/core/actualidad.php')) {
                require_once NAMMU_ROOT . '/core/actualidad.php';
            }
            $item = null;
            if ($actualityType === 'news' && function_exists('nammu_actuality_get_news_item')) {
                $item = nammu_actuality_get_news_item($actualityId);
            } elseif ($actualityType === 'notes' && function_exists('nammu_actuality_get_manual_item')) {
                $item = nammu_actuality_get_manual_item($actualityId);
            }
            if (is_array($item)) {
                if ($networkKey === 'fediverse') {
                    if (!function_exists('nammu_fediverse_deliver_actuality_item') && is_file(NAMMU_ROOT . '/core/fediverso.php')) {
                        require_once NAMMU_ROOT . '/core/fediverso.php';
                    }
                    if (!function_exists('nammu_fediverse_deliver_actuality_item')) {
                        $feedback['message'] = 'La integración con Fediverso no está disponible en esta instalación.';
                    } else {
                        $fediverseResult = nammu_fediverse_deliver_actuality_item($actualityId, load_config_file());
                        $feedback = [
                            'type' => !empty($fediverseResult['ok']) ? 'success' : 'danger',
                            'message' => (string) ($fediverseResult['message'] ?? 'No se pudo reenviar el contenido al Fediverso.'),
                        ];
                    }
                } else {
                    if (!function_exists('admin_social_broadcast_fediverse_url_for_actuality_item') && is_file(NAMMU_ROOT . '/core/admin-redes.php')) {
                        require_once NAMMU_ROOT . '/core/admin-redes.php';
                    }
                    $title = trim((string) ($item['title'] ?? ''));
                    $description = trim((string) (($item['raw_text'] ?? '') ?: ($item['description'] ?? '')));
                    if ($title === '' && $actualityType === 'notes') {
                        $title = 'Nota';
                    }
                    $fediverseUrl = function_exists('admin_social_broadcast_fediverse_url_for_actuality_item')
                        ? admin_social_broadcast_fediverse_url_for_actuality_item($item)
                        : '';
                    $images = array_values(array_filter(array_map('strval', is_array($item['images'] ?? null) ? $item['images'] : [])));
                    $image = trim((string) (($item['image'] ?? '') ?: ($images[0] ?? '')));
                    $allSocialSettings = admin_cached_social_settings();
                    $networkSettings = $allSocialSettings[$networkKey] ?? [];
                    $sendResult = admin_send_content_to_social_network($networkKey, $actualityId, $title, $description, $image, $networkSettings, $fediverseUrl, $image, $actualityType === 'news' ? 'noticia' : 'nota');
                    if (!empty($sendResult['ok']) && $actualityType === 'news' && function_exists('admin_social_rss_mark_broadcast_result')) {
                        admin_social_rss_mark_broadcast_result([
                            'source' => 'social-rss-news',
                            'source_id' => $actualityId,
                        ], 'sent', [
                            'sent_networks' => [$networkKey],
                        ]);
                    }
                    $feedback = [
                        'type' => !empty($sendResult['ok']) ? 'success' : 'danger',
                        'message' => (string) ($sendResult['message'] ?? ''),
                    ];
                }
            }
        }
        if ($templateTarget === 'news') {
            $_SESSION['news_feedback'] = $feedback;
        } else {
            $_SESSION['notes_feedback'] = $feedback;
        }
        header('Location: admin.php?page=edit&template=' . urlencode($templateTarget));
        exit;
} elseif (isset($_POST['send_social_itinerary'])) {
        $networkKey = $_POST['social_network'] ?? '';
        $itinerarySlug = ItineraryRepository::normalizeSlug($_POST['itinerary_slug'] ?? '');
        $networkLabels = admin_social_network_labels();
        $feedback = [
            'type' => 'danger',
            'message' => 'No se pudo encontrar el itinerario solicitado.',
        ];
        if (!isset($networkLabels[$networkKey])) {
            $feedback = ['type' => 'danger', 'message' => 'Red social no válida.'];
        } elseif ($itinerarySlug !== '') {
            $itinerary = admin_load_itinerary($itinerarySlug);
            if ($itinerary) {
                $title = (string) $itinerary->getTitle();
                $description = (string) $itinerary->getDescription();
                $image = $itinerary->getImage() ?? '';
                $customUrl = admin_public_itinerary_url($itinerary->getSlug());
                $imageUrl = admin_public_asset_url($image);
                $allSocialSettings = admin_cached_social_settings();
                $networkSettings = $allSocialSettings[$networkKey] ?? [];
                $sendResult = admin_send_content_to_social_network($networkKey, $itinerarySlug, $title, $description, (string) $image, $networkSettings, $customUrl, $imageUrl, 'itinerario');
                $feedback = [
                    'type' => !empty($sendResult['ok']) ? 'success' : 'danger',
                    'message' => (string) ($sendResult['message'] ?? ''),
                ];
            }
        }
        $_SESSION['itinerary_feedback'] = $feedback;
        header('Location: admin.php?page=itinerarios');
        exit;
} elseif (isset($_POST['save_social'])) {
        $podcastCategoryOptions = ['Arts', 'Business', 'Comedy', 'Education', 'Fiction', 'Government', 'History', 'Health & Fitness', 'Kids & Family', 'Leisure', 'Music', 'News', 'Religion & Spirituality', 'Science', 'Society & Culture', 'Sports', 'Technology', 'True Crime', 'TV & Film'];
        $social_default_description = trim($_POST['social_default_description'] ?? '');
        $social_home_image = trim($_POST['social_home_image'] ?? '');
        $social_podcast_image = trim($_POST['social_podcast_image'] ?? '');
        $social_podcast_category = trim($_POST['social_podcast_category'] ?? 'Technology');
        if (!in_array($social_podcast_category, $podcastCategoryOptions, true)) {
            $social_podcast_category = 'Technology';
        }
        $social_twitter = trim($_POST['social_twitter'] ?? '');
        if ($social_twitter !== '' && $social_twitter[0] === '@') {
            $social_twitter = substr($social_twitter, 1);
        }
        $social_linkedin = trim($_POST['social_linkedin'] ?? '');
        $social_facebook_app_id = trim($_POST['social_facebook_app_id'] ?? '');
        $telegram_token = trim($_POST['telegram_token'] ?? '');
        $telegram_channel = trim($_POST['telegram_channel'] ?? '');
        $telegram_auto = isset($_POST['telegram_auto']) ? 'on' : 'off';
        $facebook_token = trim($_POST['facebook_token'] ?? '');
        $facebook_app_secret = trim($_POST['facebook_app_secret'] ?? '');
        $facebook_channel = trim($_POST['facebook_channel'] ?? '');
        $facebook_auto = isset($_POST['facebook_auto']) ? 'on' : 'off';
        $twitter_api_key = trim($_POST['twitter_api_key'] ?? '');
        $twitter_api_secret = trim($_POST['twitter_api_secret'] ?? '');
        $twitter_access_token = trim($_POST['twitter_access_token'] ?? '');
        $twitter_access_secret = trim($_POST['twitter_access_secret'] ?? '');
        $twitter_auto = isset($_POST['twitter_auto']) ? 'on' : 'off';
        $linkedin_token = trim($_POST['linkedin_token'] ?? '');
        $linkedin_author = trim($_POST['linkedin_author'] ?? '');
        $linkedin_auto = isset($_POST['linkedin_auto']) ? 'on' : 'off';
        $bluesky_service = trim($_POST['bluesky_service'] ?? '');
        $bluesky_identifier = trim($_POST['bluesky_identifier'] ?? '');
        $bluesky_app_password = trim($_POST['bluesky_app_password'] ?? '');
        $bluesky_auto = isset($_POST['bluesky_auto']) ? 'on' : 'off';
        $instagram_token = trim($_POST['instagram_token'] ?? '');
        $instagram_channel = trim($_POST['instagram_channel'] ?? '');
        $instagram_profile = trim($_POST['instagram_profile'] ?? '');
        $instagram_auto = isset($_POST['instagram_auto']) ? 'on' : 'off';
        $podcast_spotify = trim($_POST['podcast_spotify'] ?? '');
        $podcast_ivoox = trim($_POST['podcast_ivoox'] ?? '');
        $podcast_apple = trim($_POST['podcast_apple'] ?? '');
        $podcast_youtube_music = trim($_POST['podcast_youtube_music'] ?? '');

        try {
            $config = load_config_file();
            $social = [
                'default_description' => $social_default_description,
                'home_image' => $social_home_image,
                'podcast_image' => $social_podcast_image,
                'podcast_category' => $social_podcast_category,
                'twitter' => $social_twitter,
                'linkedin' => $social_linkedin,
                'facebook_app_id' => $social_facebook_app_id,
            ];
            $hasSocial = array_filter($social, function ($value) {
                return $value !== '';
            });
            if (!empty($hasSocial)) {
                $config['social'] = $social;
            } else {
                unset($config['social']);
            }
            if ($telegram_token !== '' || $telegram_channel !== '' || $telegram_auto === 'on') {
                $config['telegram'] = [
                    'token' => $telegram_token,
                    'channel' => $telegram_channel,
                    'auto_post' => $telegram_auto,
                ];
            } else {
                unset($config['telegram']);
            }
            unset($config['whatsapp']);
            if ($facebook_token !== '' || $facebook_channel !== '' || $facebook_app_secret !== '' || $facebook_auto === 'on') {
                $config['facebook'] = [
                    'token' => $facebook_token,
                    'channel' => $facebook_channel,
                    'auto_post' => $facebook_auto,
                    'app_secret' => $facebook_app_secret,
                ];
            } else {
                unset($config['facebook']);
            }
            if ($twitter_api_key !== '' || $twitter_api_secret !== '' || $twitter_access_token !== '' || $twitter_access_secret !== '' || $twitter_auto === 'on') {
                $config['twitter'] = [
                    'api_key' => $twitter_api_key,
                    'api_secret' => $twitter_api_secret,
                    'access_token' => $twitter_access_token,
                    'access_secret' => $twitter_access_secret,
                    'auto_post' => $twitter_auto,
                ];
            } else {
                unset($config['twitter']);
            }
            if ($linkedin_token !== '' || $linkedin_author !== '' || $linkedin_auto === 'on') {
                $config['linkedin'] = [
                    'token' => $linkedin_token,
                    'author' => $linkedin_author,
                    'auto_post' => $linkedin_auto,
                ];
            } else {
                unset($config['linkedin']);
            }
            if ($bluesky_service !== '' || $bluesky_identifier !== '' || $bluesky_app_password !== '' || $bluesky_auto === 'on') {
                $service = $bluesky_service !== '' ? $bluesky_service : 'https://bsky.social';
                $config['bluesky'] = [
                    'service' => $service,
                    'identifier' => $bluesky_identifier,
                    'app_password' => $bluesky_app_password,
                    'auto_post' => $bluesky_auto,
                ];
            } else {
                unset($config['bluesky']);
            }
            if ($instagram_token !== '' || $instagram_channel !== '' || $instagram_profile !== '' || $instagram_auto === 'on') {
                $config['instagram'] = [
                    'token' => $instagram_token,
                    'channel' => $instagram_channel,
                    'profile' => $instagram_profile,
                    'auto_post' => $instagram_auto,
                ];
            } else {
                unset($config['instagram']);
            }
            $podcastServices = [
                'spotify' => $podcast_spotify,
                'ivoox' => $podcast_ivoox,
                'apple' => $podcast_apple,
                'youtube_music' => $podcast_youtube_music,
            ];
            $hasPodcastServices = array_filter($podcastServices, static function ($value) {
                return $value !== '';
            });
            if (!empty($hasPodcastServices)) {
                $config['podcast_services'] = $podcastServices;
            } else {
                unset($config['podcast_services']);
            }

            save_config_file($config);
            $_SESSION['social_feedback'] = [
                'type' => 'success',
                'message' => 'Redes sociales guardadas correctamente.',
            ];
        } catch (Throwable $e) {
            $_SESSION['social_feedback'] = [
                'type' => 'danger',
                'message' => 'Error guardando la configuración: ' . $e->getMessage(),
            ];
        }

        header('Location: admin.php?page=anuncios#facebook');
        exit;
}
