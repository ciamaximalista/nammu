<?php
/**
 * Nammu — panel de administración. Datos del Escritorio: Recuentos de recursos y suscriptores (newsletter, correo postal, push, RRSS) para las tarjetas del Escritorio.
 * Lo incluye core/admin-view-dashboard.php en el ámbito global de admin.php; comparte variables con las demás piezas.
 */
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
if (!function_exists('nammu_fediverse_followers_store') && is_file(dirname(__DIR__) . '/core/fediverso.php')) {
    require_once dirname(__DIR__) . '/core/fediverso.php';
}
if (function_exists('get_settings')) {
    $settings = get_settings();
    $pushEnabled = (($settings['ads']['push_enabled'] ?? 'off') === 'on');
    if ($pushEnabled && function_exists('nammu_push_subscriber_count')) {
        $pushSubscriberCount = nammu_push_subscriber_count();
    }
    if (function_exists('nammu_fediverse_followers_store')) {
        try {
            $fediverseFollowerCount = count(nammu_fediverse_followers_store()['followers'] ?? []);
            if ($fediverseFollowerCount > 0) {
                $socialCounts['Fediverso'] = $fediverseFollowerCount;
            }
        } catch (Throwable $e) {
        }
    }
    $telegramCount = admin_get_telegram_follower_count($settings['telegram'] ?? []);
    if ($telegramCount !== null) {
        $socialCounts['Telegram'] = $telegramCount;
    }
    $facebookCount = admin_get_facebook_follower_count($settings['facebook'] ?? []);
    if ($facebookCount !== null) {
        $socialCounts['Facebook'] = $facebookCount;
    }
    $twitterSettings = is_array($settings['twitter'] ?? null) ? $settings['twitter'] : [];
    if (trim((string) ($twitterSettings['channel'] ?? '')) === '') {
        $twitterSettings['channel'] = trim((string) ($settings['social']['twitter'] ?? ''));
    }
    $twitterCount = admin_get_twitter_follower_count($twitterSettings);
    if ($twitterCount !== null) {
        $socialCounts['Twitter/X'] = $twitterCount;
    }
    $blueskyCount = admin_get_bluesky_follower_count($settings['bluesky'] ?? []);
    if ($blueskyCount !== null) {
        $socialCounts['Bluesky'] = $blueskyCount;
    }
    $instagramCount = admin_get_instagram_follower_count($settings['instagram'] ?? []);
    if ($instagramCount !== null) {
        $socialCounts['Instagram'] = $instagramCount;
    }
}
$subscriberCounts = [];
if ($avisosPostsCount > 0) {
    $subscriberCounts[] = ['label' => 'Avisos entradas', 'count' => $avisosPostsCount];
}
if ($avisosItinerariesCount > 0) {
    $subscriberCounts[] = ['label' => 'Avisos itinerarios', 'count' => $avisosItinerariesCount];
}
if ($avisosPodcastCount > 0) {
    $subscriberCounts[] = ['label' => 'Avisos podcast', 'count' => $avisosPodcastCount];
}
if ($newsletterSubscriberCount > 0) {
    $subscriberCounts[] = ['label' => 'Newsletter', 'count' => $newsletterSubscriberCount];
}
if ($postalSubscriberCount > 0) {
    $subscriberCounts[] = ['label' => 'Correo postal', 'count' => $postalSubscriberCount];
}
if ($pushEnabled && $pushSubscriberCount > 0) {
    $subscriberCounts[] = ['label' => 'Notificaciones Push', 'count' => $pushSubscriberCount];
}
foreach ($socialCounts as $label => $count) {
    if ((int) $count > 0) {
        $subscriberCounts[] = ['label' => (string) $label, 'count' => (int) $count];
    }
}
usort($subscriberCounts, static function (array $a, array $b): int {
    $byCount = ((int) $b['count']) <=> ((int) $a['count']);
    if ($byCount !== 0) {
        return $byCount;
    }
    return strcasecmp((string) $a['label'], (string) $b['label']);
});
