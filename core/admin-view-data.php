<?php
/**
 * Nammu — panel de administración. Datos comunes a todas las pestañas: ajustes del sitio, metadatos sociales, Nisaba, Telex,
 * Ideas y logotipo del panel. Se incluye desde admin.php (ámbito global).
 */
$settings = get_settings();
$socialDefaults = [
    'default_description' => '',
    'home_image' => '',
    'podcast_image' => '',
    'podcast_category' => 'Technology',
    'twitter' => '',
    'linkedin' => '',
    'facebook_app_id' => '',
];
$socialSettings = array_merge($socialDefaults, $settings['social'] ?? []);
$socialDefaultDescription = $socialSettings['default_description'] ?? '';
$socialHomeImage = $socialSettings['home_image'] ?? '';
$socialPodcastImage = $socialSettings['podcast_image'] ?? '';
$socialPodcastCategory = $socialSettings['podcast_category'] ?? 'Technology';
$socialPodcastCategoryOptions = ['Arts', 'Business', 'Comedy', 'Education', 'Fiction', 'Government', 'History', 'Health & Fitness', 'Kids & Family', 'Leisure', 'Music', 'News', 'Religion & Spirituality', 'Science', 'Society & Culture', 'Sports', 'Technology', 'True Crime', 'TV & Film'];
if (!in_array($socialPodcastCategory, $socialPodcastCategoryOptions, true)) {
    $socialPodcastCategory = 'Technology';
}
$socialTwitter = $socialSettings['twitter'] ?? '';
$socialLinkedin = $socialSettings['linkedin'] ?? '';
$socialFacebookAppId = $socialSettings['facebook_app_id'] ?? '';
$nisabaConfig = $settings['nisaba'] ?? [];
$nisabaUrls = is_array($nisabaConfig['urls'] ?? null) ? $nisabaConfig['urls'] : [];
$nisabaUrl = trim((string) ($nisabaConfig['url'] ?? ''));
if ($nisabaUrl !== '' && !in_array($nisabaUrl, $nisabaUrls, true)) {
    array_unshift($nisabaUrls, $nisabaUrl);
}
$nisabaUrlsValue = implode("\n", array_values(array_filter(array_map('strval', $nisabaUrls))));
$nisabaPrimaryUrl = $nisabaUrls[0] ?? '';
$nisabaEnabled = $nisabaPrimaryUrl !== '' && function_exists('admin_nisaba_fetch_notes');
$nisabaFeedUrl = $nisabaEnabled ? admin_nisaba_feed_url($nisabaPrimaryUrl) : '';
$nisabaPages = ['publish', 'edit', 'edit-post', 'itinerario', 'itinerario-tema'];
$nisabaModalEnabled = $nisabaEnabled && in_array($page, $nisabaPages, true);
$nisabaNotes = $nisabaModalEnabled ? admin_nisaba_fetch_notes($nisabaPrimaryUrl, 14) : [];
$telexConfig = $settings['telex'] ?? [];
$telexUrls = is_array($telexConfig['urls'] ?? null) ? $telexConfig['urls'] : [];
$telexEnabled = !empty($telexUrls) && function_exists('admin_telex_fetch_notes');
$telexPages = ['publish', 'edit', 'edit-post', 'itinerario', 'itinerario-tema'];
$telexModalEnabled = $telexEnabled && in_array($page, $telexPages, true);
$telexNotes = $telexModalEnabled ? admin_telex_fetch_notes($telexUrls, 14) : [];
$ideasPages = ['publish', 'edit', 'edit-post', 'itinerario', 'itinerario-tema'];
$ideasModalEnabled = in_array($page, $ideasPages, true) && function_exists('admin_ideas_build');
$ideasEnabled = $ideasModalEnabled;
$ideasSuggestions = $ideasModalEnabled ? admin_ideas_build(CONTENT_DIR, 30) : [];
$templateImages = is_array($settings['template']['images'] ?? null) ? $settings['template']['images'] : [];
$adminLogoPath = trim((string) ($templateImages['logo'] ?? ''));
$adminLogoUrl = '';
if ($adminLogoPath !== '') {
    if (preg_match('#^https?://#i', $adminLogoPath)) {
        $adminLogoUrl = $adminLogoPath;
    } else {
        $normalizedLogo = ltrim($adminLogoPath, '/');
        $normalizedLogo = str_replace(['../', '..\\', './', '.\\'], '', $normalizedLogo);
        if (!str_starts_with($normalizedLogo, 'assets/')) {
            $normalizedLogo = 'assets/' . $normalizedLogo;
        }
        $adminLogoUrl = $normalizedLogo;
    }
}
$adminLogoLink = trim((string) ($settings['site_url'] ?? ''));
$adminLogoLink = $adminLogoLink !== '' ? $adminLogoLink : 'index.php';
