<?php

declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/admin-redes.php';
require_once __DIR__ . '/core/actualidad.php';

use Nammu\Core\ContentRepository;
use Nammu\Core\MarkdownConverter;
use Nammu\Core\TemplateRenderer;

if (function_exists('nammu_publish_scheduled_posts')) {
    nammu_publish_scheduled_posts(__DIR__ . '/content');
}
if (function_exists('nammu_process_scheduled_notifications_queue')) {
    nammu_process_scheduled_notifications_queue();
}

$contentDir = __DIR__ . '/content';
$itinerariesDir = __DIR__ . '/itinerarios';
$contentRepository = new ContentRepository($contentDir);
$markdown = new MarkdownConverter();

$siteDocument = $contentRepository->getDocument('index');
$siteTitle = $siteDocument['metadata']['Title'] ?? 'Nammu Blog';
$siteDescription = $siteDocument['metadata']['Description'] ?? '';

$baseUrl = nammu_base_url();
$publicBaseUrl = $baseUrl;
$homeUrl = $publicBaseUrl !== '' ? $publicBaseUrl : '/';
$rssUrl = ($publicBaseUrl !== '' ? $publicBaseUrl : '') . '/rss.xml';

$theme = nammu_template_settings();
$footerRaw = $theme['footer'] ?? '';
$theme['footer_html'] = '';
if ($footerRaw !== '') {
    if (str_contains($footerRaw, '<')) {
        $theme['footer_html'] = preg_replace('/>\s+</u', '><', trim($footerRaw));
    } else {
        $theme['footer_html'] = $markdown->toHtml($footerRaw);
    }
}
$theme['logo_url'] = nammu_resolve_asset($theme['images']['logo'] ?? '', $publicBaseUrl);
$theme['favicon_url'] = null;
if (!empty($theme['logo_url'])) {
    $logoPath = parse_url($theme['logo_url'], PHP_URL_PATH) ?? '';
    if ($logoPath && preg_match('/\.(png|ico)$/i', $logoPath)) {
        $theme['favicon_url'] = $theme['logo_url'];
    }
}

$socialConfig = nammu_social_settings();
$siteNameForMeta = $theme['blog'] !== '' ? $theme['blog'] : $siteTitle;
$homeDescription = $socialConfig['default_description'] !== '' ? $socialConfig['default_description'] : $siteDescription;
$homeImage = nammu_resolve_asset($socialConfig['home_image'] ?? '', $publicBaseUrl);

$displaySiteTitle = $theme['blog'] !== '' ? $theme['blog'] : $siteTitle;
$configData = nammu_load_config();
$siteLang = $configData['site_lang'] ?? 'es';
if (!is_string($siteLang) || $siteLang === '') {
    $siteLang = 'es';
}
$postalUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/correos.php';
$postalLogoSvg = nammu_postal_icon_svg();
$hasItineraries = !empty(is_dir(__DIR__ . '/itinerarios') ? glob(__DIR__ . '/itinerarios/*') : []);
$hasPodcast = !empty(nammu_collect_podcast_items(__DIR__ . '/content', $publicBaseUrl));
$footerLinks = nammu_build_footer_links($configData, $theme, $homeUrl, $postalUrl, $hasItineraries, $hasPodcast);
$categoryMapAll = nammu_collect_categories_from_posts($contentRepository->all());
$uncategorizedSlug = nammu_slugify_label('Sin Categoría');
$hasCategories = false;
foreach ($categoryMapAll as $slugKey => $data) {
    if ($slugKey !== $uncategorizedSlug) {
        $hasCategories = true;
        break;
    }
}
$sortOrderValue = strtolower((string) ($configData['pages_order_by'] ?? 'date'));
$sortOrder = in_array($sortOrderValue, ['date', 'alpha'], true) ? $sortOrderValue : 'date';
$isAlphabeticalOrder = ($sortOrder === 'alpha');
$lettersIndexUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/letras';
$newsletterItems = function_exists('nammu_newsletter_collect_items')
    ? nammu_newsletter_collect_items(__DIR__ . '/content', $publicBaseUrl)
    : [];
$hasNewsletters = !empty($newsletterItems);
$newslettersIndexUrl = ($publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '') . '/newsletters';

$renderer = new TemplateRenderer(__DIR__ . '/template', [
    'siteTitle' => $displaySiteTitle,
    'siteDescription' => $siteDescription,
    'rssUrl' => $rssUrl !== '' ? $rssUrl : '/rss.xml',
    'baseUrl' => $homeUrl,
    'theme' => $theme,
    'postalEnabled' => ($configData['postal']['enabled'] ?? 'off') === 'on',
    'postalUrl' => $postalUrl,
    'postalLogoSvg' => $postalLogoSvg,
    'footerLinks' => $footerLinks,
]);
$renderer->setGlobal('hasCategories', $hasCategories);
$renderer->setGlobal('pageLang', $siteLang);
$renderer->setGlobal('lettersIndexUrl', $isAlphabeticalOrder ? $lettersIndexUrl : null);
$renderer->setGlobal('showLetterIndexButton', $isAlphabeticalOrder);
$renderer->setGlobal('hasNewsletters', $hasNewsletters);
$renderer->setGlobal('newslettersIndexUrl', $newslettersIndexUrl);
$renderer->setGlobal('hasPodcast', $hasPodcast);
$renderer->setGlobal('podcastIndexUrl', $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') . '/podcast' : '/podcast');
$renderer->setGlobal('hasItineraries', $hasItineraries);
$renderer->setGlobal('itinerariesIndexUrl', $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') . '/itinerarios' : '/itinerarios');
$renderer->setGlobal('socialConfig', $socialConfig);
$renderer->setGlobal('baseUrl', $publicBaseUrl !== '' ? $publicBaseUrl : '/');

$rssSettings = admin_social_rss_settings(['social_rss' => $configData['social_rss'] ?? []]);
$feeds = admin_social_rss_feed_list($rssSettings['feeds']);
$snapshot = nammu_actuality_load_items_snapshot();
$items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];
if (empty($items) && function_exists('nammu_actuality_has_content') && nammu_actuality_has_content($configData)) {
    $rebuilt = nammu_actuality_rebuild_snapshot($publicBaseUrl, $configData, $siteTitle, $siteDescription, $siteLang);
    $items = is_array($rebuilt['items'] ?? null) ? $rebuilt['items'] : [];
}

$actualityHeroBackground = '';
$actualityCache = nammu_actuality_load_cache();
$cacheItems = array_values(is_array($actualityCache['items'] ?? null) ? $actualityCache['items'] : []);
if (!empty($cacheItems)) {
    usort($cacheItems, static function (array $a, array $b): int {
        return ((int) ($a['last_used'] ?? 0)) <=> ((int) ($b['last_used'] ?? 0));
    });
    $heroIndex = max(0, count($cacheItems) - 3);
    $candidate = is_array($cacheItems[$heroIndex] ?? null) ? $cacheItems[$heroIndex] : [];
    $actualityHeroBackground = trim((string) ($candidate['public_url'] ?? ''));
}
$renderer->setGlobal('actualityHeroBackground', $actualityHeroBackground);

$content = $renderer->render('actuality', [
    'items' => $items,
    'feedsCount' => count($feeds),
    'hasActuality' => function_exists('nammu_actuality_has_content') ? nammu_actuality_has_content($configData) : !empty($feeds),
]);

$pageTitle = 'Actualidad';
$pageDescription = (function_exists('nammu_actuality_has_content') && nammu_actuality_has_content($configData))
    ? 'Actualidad agregada desde las fuentes RSS configuradas y notas manuales del sitio.'
    : 'No hay contenido de actualidad configurado todavía.';
$canonical = ($publicBaseUrl !== '' ? $publicBaseUrl : '') . '/actualidad.php';

$socialMeta = nammu_build_social_meta([
    'type' => 'website',
    'title' => $pageTitle . ' — ' . $siteNameForMeta,
    'description' => $pageDescription,
    'url' => $canonical,
    'image' => $homeImage,
    'site_name' => $siteNameForMeta,
], $socialConfig);

if (function_exists('nammu_record_pageview')) {
    nammu_record_pageview('pages', 'actualidad', 'Actualidad');
}

echo $renderer->render('layout', [
    'pageTitle' => $pageTitle,
    'metaDescription' => $pageDescription,
    'content' => $content,
    'socialMeta' => $socialMeta,
    'jsonLd' => [[
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitle,
        'url' => $canonical,
        'description' => $pageDescription,
        'inLanguage' => $siteLang,
    ]],
    'pageLang' => $siteLang,
    'showLogo' => true,
]);
