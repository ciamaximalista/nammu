<?php

declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/helpers.php';

use Nammu\Core\ContentRepository;
use Nammu\Core\Itinerary;
use Nammu\Core\ItineraryRepository;
use Nammu\Core\MarkdownConverter;
use Nammu\Core\TemplateRenderer;
use Throwable;

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
$siteBio = $siteDocument['content'] ?? '';

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
$footerLinks = nammu_build_footer_links($configData, $theme, $homeUrl, $postalUrl);
$logoForJsonLd = $theme['logo_url'] ?? '';
$orgJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => $siteNameForMeta,
    'url' => $publicBaseUrl !== '' ? $publicBaseUrl : $homeUrl,
];
if (!empty($logoForJsonLd)) {
    $orgJsonLd['logo'] = $logoForJsonLd;
}
$siteJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => $siteNameForMeta,
    'url' => $publicBaseUrl !== '' ? $publicBaseUrl : $homeUrl,
    'description' => $homeDescription,
    'inLanguage' => $siteLang,
];
if ($publicBaseUrl !== '') {
    $siteJsonLd['potentialAction'] = [
        '@type' => 'SearchAction',
        'target' => rtrim($publicBaseUrl, '/') . '/buscar.php?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ];
}
$categoryMapAll = nammu_collect_categories_from_posts($contentRepository->all());
$uncategorizedSlug = nammu_slugify_label('Sin Categoría');
$hasCategories = false;
foreach ($categoryMapAll as $slugKey => $data) {
    if ($slugKey !== $uncategorizedSlug) {
        $hasCategories = true;
        break;
    }
}

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

$renderer->setGlobal('resolveImage', function (?string $image) use ($publicBaseUrl): ?string {
    if ($image === null || $image === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }

    $normalized = ltrim($image, '/');
    if (str_starts_with($normalized, 'assets/')) {
        $normalized = substr($normalized, 7);
    }
    $path = 'assets/' . ltrim($normalized, '/');
    return $publicBaseUrl !== '' ? $publicBaseUrl . '/' . $path : '/' . $path;
});

$renderer->setGlobal('postUrl', function (Post|string $post) use ($publicBaseUrl): string {
    $slug = $post instanceof Post ? $post->getSlug() : (string) $post;
    $path = '/' . rawurlencode($slug);
    return $publicBaseUrl !== '' ? $publicBaseUrl . $path : $path;
});
$renderer->setGlobal('paginationUrl', function (int $page) use ($publicBaseUrl): string {
    $target = $page <= 1 ? '/' : '/pagina/' . $page;
    return $publicBaseUrl !== '' ? $publicBaseUrl . $target : $target;
});

$renderer->setGlobal('markdownToHtml', function (string $markdownText) use ($markdown): string {
    return $markdown->toHtml($markdownText);
});
$renderer->setGlobal('baseUrl', $publicBaseUrl !== '' ? $publicBaseUrl : '/');
$renderer->setGlobal('socialConfig', $socialConfig);
$renderer->setGlobal('itinerariesIndexUrl', $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') . '/itinerarios' : '/itinerarios');

$loadedItineraries = [];
try {
    $itineraryRepository = new ItineraryRepository($itinerariesDir);
    $loadedItineraries = $itineraryRepository->all();
} catch (Throwable $e) {
    $itineraryRepository = null;
    $loadedItineraries = [];
}
$renderer->setGlobal('hasItineraries', !empty($loadedItineraries));

$queryRaw = trim($_GET['q'] ?? '');
$typeFilterRaw = trim($_GET['tipo'] ?? 'todo');
$typeFilter = nammu_normalize_search_type($typeFilterRaw);
$performSearch = mb_strlen($queryRaw) >= 2;

$searchSummary = [
    'query' => $queryRaw,
    'queryTokens' => [],
    'typeFilter' => $typeFilter,
    'total' => 0,
    'took' => 0.0,
    'didSearch' => false,
];

$results = [];
$documents = [];
$conditions = ['conditions' => [], 'tokens' => []];

if ($performSearch) {
    $searchSummary['didSearch'] = true;
    $documents = nammu_collect_documents($contentDir, $markdown);
    if (!empty($loadedItineraries)) {
        $documents = array_merge($documents, nammu_collect_itinerary_documents($loadedItineraries, $markdown));
    }
    $conditions = nammu_parse_search_query($queryRaw);
    if (!empty($conditions['type']) && $conditions['type'] !== 'todo') {
        $typeFilter = $conditions['type'];
        $searchSummary['typeFilter'] = $typeFilter;
    }
    $searchSummary['queryTokens'] = $conditions['tokens'];
    $start = microtime(true);
    $results = nammu_execute_search($documents, $conditions['conditions'], $typeFilter);
    $searchSummary['took'] = max(0, round((microtime(true) - $start) * 1000, 1));
    $searchSummary['total'] = count($results);
} elseif ($queryRaw !== '') {
    // query too short, show warning
    $searchSummary['didSearch'] = true;
}

$highlightTerms = array_column(array_filter($conditions['conditions'] ?? [], static fn ($c) => empty($c['negate'])), 'value');
$resultsForView = [];
foreach ($results as $item) {
    $relativeUrl = $item['relative_url'] ?? '/' . rawurlencode($item['slug']);
    $baseForUrl = $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') : '';
    $resultsForView[] = [
        'slug' => $item['slug'],
        'url' => $baseForUrl . $relativeUrl,
        'title' => nammu_highlight_terms($item['title'], $item['highlight_terms']),
        'description' => $item['description'] !== '' ? nammu_highlight_terms($item['description'], $item['highlight_terms']) : '',
        'snippet' => $item['snippet'],
        'type_label' => $item['type_label'],
        'category' => $item['category'],
        'date' => $item['date_display'],
        'score' => $item['score'],
        'audio_url' => $item['audio_url'] ?? '',
    ];
}

$content = $renderer->render('search', [
    'query' => $queryRaw,
    'results' => $resultsForView,
    'didSearch' => $searchSummary['didSearch'],
    'summary' => $searchSummary,
    'typeFilter' => $typeFilter,
    'filters' => [
        'todo' => 'Todo el sitio',
        'post' => 'Sólo entradas',
        'page' => 'Sólo páginas',
        'newsletter' => 'Sólo newsletters',
        'podcast' => 'Sólo podcasts',
        'itinerary' => 'Sólo itinerarios',
    ],
    'tips' => [
        'Usa comillas para buscar frases exactas, por ejemplo "bosque mediterráneo".',
        'Filtra por campos: title:"Plantación", category:educación, content:escuela.',
        'Excluye términos con el prefijo -, por ejemplo bosque -urbano.',
        'Limita por tipo con tipo:entrada, tipo:página, tipo:podcast, tipo:newsletter o tipo:itinerario.',
    ],
    'hasItineraries' => !empty($loadedItineraries),
    'itinerariesIndexUrl' => $publicBaseUrl !== '' ? rtrim($publicBaseUrl, '/') . '/itinerarios' : '/itinerarios',
]);

$queryForTitle = $queryRaw !== '' ? '“' . $queryRaw . '”' : '';
$pageTitle = $queryForTitle === '' ? 'Buscador' : 'Resultados para ' . $queryForTitle;

$canonical = ($publicBaseUrl !== '' ? $publicBaseUrl : '') . '/buscar.php';
if ($queryRaw !== '') {
    $canonical .= '?q=' . urlencode($queryRaw) . ($typeFilter !== 'todo' ? '&tipo=' . urlencode($typeFilter) : '');
}

$searchDescription = $queryRaw === ''
    ? 'Busca en todo el archivo del sitio'
    : 'Resultados para "' . $queryRaw . '"';

$socialMeta = nammu_build_social_meta([
    'type' => 'website',
    'title' => $pageTitle . ' — ' . $siteNameForMeta,
    'description' => $searchDescription,
    'url' => $canonical,
    'image' => $homeImage,
    'site_name' => $siteNameForMeta,
], $socialConfig);

echo $renderer->render('layout', [
    'pageTitle' => $pageTitle,
    'metaDescription' => $searchDescription,
    'content' => $content,
    'socialMeta' => $socialMeta,
    'jsonLd' => [$siteJsonLd, $orgJsonLd],
    'pageLang' => $siteLang,
    'metaRobots' => 'noindex, follow',
    'showLogo' => true,
]);

/**
 * @return array<int, array<string, mixed>>
 */
function nammu_collect_documents(string $contentDir, MarkdownConverter $markdown): array
{
    $files = glob(rtrim($contentDir, '/') . '/*.md') ?: [];
    $documents = [];

    foreach ($files as $file) {
        $raw = file_get_contents($file);
        if ($raw === false || trim($raw) === '') {
            continue;
        }

        [$metadata, $body] = nammu_extract_document($raw);
        $slug = basename($file, '.md');
        $templateRaw = $metadata['Template'] ?? $metadata['template'] ?? '';
        $template = strtolower(nammu_meta_value_to_string($templateRaw));
        $statusRaw = strtolower(nammu_meta_value_to_string($metadata['Status'] ?? 'published'));
        if ($statusRaw === 'draft') {
            continue;
        }
        $type = match ($template) {
            'page' => 'page',
            'single', 'post' => 'post',
            'newsletter' => 'newsletter',
            'podcast' => 'podcast',
            default => 'other',
        };

        $title = nammu_meta_value_to_string($metadata['Title'] ?? $slug);
        if ($title === '') {
            $title = $slug;
        }
        $description = nammu_meta_value_to_string($metadata['Description'] ?? '');
        $category = nammu_meta_value_to_string($metadata['Category'] ?? '');
        $dateRaw = nammu_meta_value_to_string($metadata['Date'] ?? '');
        $dateIso = $dateRaw;
        $dateDisplay = $dateRaw;

        if ($dateRaw !== '') {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
            if (!$parsed) {
                $parsed = DateTimeImmutable::createFromFormat('d/m/Y', $dateRaw);
            }
            if (!$parsed) {
                $timestamp = strtotime($dateRaw);
                if ($timestamp !== false) {
                    $parsed = (new DateTimeImmutable())->setTimestamp($timestamp);
                }
            }
            if ($parsed instanceof DateTimeImmutable) {
                $dateIso = $parsed->format('Y-m-d');
                $dateDisplay = nammu_format_date_spanish($parsed, $dateRaw);
            }
        }

        $bodyHtml = $markdown->toHtml($body);
        $bodyText = trim(preg_replace('/\s+/u', ' ', strip_tags($bodyHtml)));

        $documents[] = [
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'template' => $template,
            'type' => $type,
            'date_raw' => $dateRaw,
            'date_iso' => $dateIso,
            'date_display' => $dateDisplay,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'relative_url' => '/' . rawurlencode($slug),
            'fields' => [
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'content' => $bodyText,
                'slug' => $slug,
            ],
        ];
        if ($template === 'podcast') {
            $audioPath = nammu_meta_value_to_string($metadata['Audio'] ?? '');
            $documents[count($documents) - 1]['audio_url'] = nammu_build_audio_url($audioPath);
            $documents[count($documents) - 1]['relative_url'] = $documents[count($documents) - 1]['audio_url'] ?? $documents[count($documents) - 1]['relative_url'];
            $documents[count($documents) - 1]['type_label_override'] = 'Podcast';
        } elseif ($template === 'newsletter') {
            $documents[count($documents) - 1]['type_label_override'] = 'Newsletter';
        }
    }

    return $documents;
}

/**
 * @return array{array<string, mixed>, string}
 */
function nammu_extract_document(string $raw): array
{
    if (!preg_match('/^---\s*\R(.*?)\R---\s*\R?(.*)$/s', $raw, $matches)) {
        return [[], trim($raw)];
    }

    $meta = nammu_simple_yaml_parse($matches[1]);
    if (!is_array($meta)) {
        $meta = [];
    }

    $body = ltrim($matches[2] ?? '');

    return [$meta, $body];
}

function nammu_build_audio_url(string $path): string
{
    $publicBaseUrl = '';
    if (isset($GLOBALS['publicBaseUrl']) && is_string($GLOBALS['publicBaseUrl'])) {
        $publicBaseUrl = rtrim($GLOBALS['publicBaseUrl'], '/');
    }
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $normalized = ltrim($path, '/');
    if (str_starts_with($normalized, 'assets/')) {
        $normalized = substr($normalized, 7);
    }
    $relative = '/assets/' . $normalized;
    return $publicBaseUrl !== '' ? $publicBaseUrl . $relative : $relative;
}

function nammu_meta_value_to_string(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $parts[] = (string) $item;
            } elseif (is_object($item) && method_exists($item, '__toString')) {
                $parts[] = (string) $item;
            }
        }
        $value = implode(', ', $parts);
    } elseif (is_object($value)) {
        $value = method_exists($value, '__toString') ? (string) $value : '';
    }

    return trim((string) $value);
}

/**
 * @return array{conditions: array<int, array<string, mixed>>, tokens: array<int, string>}
 */
function nammu_parse_search_query(string $query): array
{
    $tokens = nammu_tokenize_query($query);
    $conditions = [];
    $recordedTokens = [];
    $typeFilter = 'todo';

    foreach ($tokens as $token) {
        $recordedTokens[] = $token;
    }

    foreach ($tokens as $token) {
        $negate = false;
        if ($token !== '' && $token[0] === '-') {
            $negate = true;
            $token = substr($token, 1);
        }

        $field = null;
        $value = $token;

        if (preg_match('/^([A-Za-zÀ-ÿ]+):(.*)$/u', $token, $matches)) {
            $field = strtolower($matches[1]);
            $value = $matches[2];
        }

        $phrase = false;
        if ($value !== '' && $value[0] === '"' && substr($value, -1) === '"') {
            $phrase = true;
            $value = substr($value, 1, -1);
        }

        $value = trim($value);
        if ($value === '') {
            continue;
        }

        if ($field !== null && in_array($field, ['type', 'tipo', 'template'], true)) {
            $typeCandidate = nammu_normalize_search_type($value);
            if ($typeCandidate !== 'todo') {
                $typeFilter = $typeCandidate;
            }
            continue;
        }

        $fields = null;
        if ($field !== null) {
            $fields = match ($field) {
                'title', 'titulo', 'título' => ['title'],
                'description', 'descripcion', 'descripción' => ['description'],
                'category', 'categoria', 'categoría' => ['category'],
                'slug' => ['slug'],
                'content', 'texto', 'body' => ['content'],
                default => null,
            };
        }

        $conditions[] = [
            'value' => $value,
            'negate' => $negate,
            'phrase' => $phrase,
            'fields' => $fields,
        ];
    }

    return [
        'conditions' => $conditions,
        'tokens' => $recordedTokens,
        'type' => $typeFilter,
    ];
}

function nammu_tokenize_query(string $query): array
{
    $tokens = [];
    $length = mb_strlen($query);
    $buffer = '';
    $inQuotes = false;

    for ($i = 0; $i < $length; $i++) {
        $char = mb_substr($query, $i, 1);
        if ($char === '"') {
            $buffer .= $char;
            $inQuotes = !$inQuotes;
            continue;
        }

        if (preg_match('/\s/u', $char) && !$inQuotes) {
            if ($buffer !== '') {
                $tokens[] = $buffer;
                $buffer = '';
            }
            continue;
        }

        $buffer .= $char;
    }

    if ($buffer !== '') {
        $tokens[] = $buffer;
    }

    return $tokens;
}

function nammu_normalize_search_type(string $type): string
{
    $type = strtolower($type);
    return match ($type) {
        'post', 'posts', 'entrada', 'entradas', 'blog' => 'post',
        'page', 'pages', 'pagina', 'página', 'paginas', 'páginas' => 'page',
        'newsletter', 'newsletters', 'boletin', 'boletín' => 'newsletter',
        'podcast', 'podcasts', 'episodio', 'episodios' => 'podcast',
        'itinerario', 'itinerarios', 'curso', 'cursos', 'libro', 'libros' => 'itinerary',
        default => 'todo',
    };
}

/**
 * @param array<int, array<string, mixed>> $documents
 * @param array<int, array<string, mixed>> $conditions
 * @return array<int, array<string, mixed>>
 */
function nammu_execute_search(array $documents, array $conditions, string $typeFilter): array
{
    $results = [];
    $defaultFields = ['title', 'description', 'category', 'content'];
    $fieldWeights = [
        'title' => 5,
        'description' => 3,
        'category' => 2,
        'content' => 1,
        'slug' => 2,
    ];

    foreach ($documents as $doc) {
        if ($typeFilter === 'post' && $doc['type'] !== 'post') {
            continue;
        }
        if ($typeFilter === 'page' && $doc['type'] !== 'page') {
            continue;
        }
        if ($typeFilter === 'newsletter' && $doc['type'] !== 'newsletter') {
            continue;
        }
        if ($typeFilter === 'podcast' && $doc['type'] !== 'podcast') {
            continue;
        }

        $score = 0;
        $match = true;
        $highlightTerms = [];

        foreach ($conditions as $condition) {
            $value = $condition['value'];
            $fields = $condition['fields'] ?? $defaultFields;
            $found = false;

            foreach ($fields as $field) {
                $haystack = $doc['fields'][$field] ?? '';
                if ($haystack === '') {
                    continue;
                }
                if (mb_stripos($haystack, $value) !== false) {
                    $found = true;
                    $score += ($fieldWeights[$field] ?? 1) * ($condition['phrase'] ? 2 : 1);
                }
            }

            if (!empty($condition['negate'])) {
                if ($found) {
                    $match = false;
                    break;
                }
            } else {
                if (!$found) {
                    $match = false;
                    break;
                }
                $highlightTerms[] = $value;
            }
        }

        if (!$match) {
            continue;
        }

        $snippet = nammu_build_snippet($doc['body_text'], $highlightTerms);

        $results[] = [
            'slug' => $doc['slug'],
            'title' => $doc['title'],
            'description' => $doc['description'],
            'category' => $doc['category'],
            'type_label' => $doc['type_label_override'] ?? match ($doc['type']) {
                'page' => 'Página',
                'post' => 'Entrada',
                'newsletter' => 'Newsletter',
                'podcast' => 'Podcast',
                default => 'Documento',
            },
            'date_display' => $doc['date_display'],
            'score' => $score,
            'snippet' => $snippet,
            'relative_url' => $doc['relative_url'] ?? '/' . rawurlencode($doc['slug']),
            'audio_url' => $doc['audio_url'] ?? '',
            'highlight_terms' => $highlightTerms,
        ];
    }

    usort($results, static function ($a, $b) {
        if ($a['score'] === $b['score']) {
            return strcmp($a['slug'], $b['slug']);
        }
        return $a['score'] < $b['score'] ? 1 : -1;
    });

    return $results;
}

function nammu_build_snippet(string $text, array $needles): string
{
    if ($text === '') {
        return '';
    }
    if (empty($needles)) {
        return mb_substr($text, 0, 180) . (mb_strlen($text) > 180 ? '…' : '');
    }

    $position = null;
    $needleUsed = '';
    foreach ($needles as $needle) {
        $pos = mb_stripos($text, $needle);
        if ($pos !== false) {
            $position = $pos;
            $needleUsed = $needle;
            break;
        }
    }

    if ($position === null) {
        return mb_substr($text, 0, 180) . (mb_strlen($text) > 180 ? '…' : '');
    }

    $radius = 90;
    $start = max(0, $position - $radius);
    $snippet = mb_substr($text, $start, 2 * $radius);
    if ($start > 0) {
        $snippet = '…' . $snippet;
    }
    if ($start + 2 * $radius < mb_strlen($text)) {
        $snippet .= '…';
    }

    return nammu_highlight_terms($snippet, array_merge([$needleUsed], $needles));
}

function nammu_highlight_terms(string $text, array $terms): string
{
    $uniqueTerms = array_values(array_unique(array_filter($terms, static fn ($term) => $term !== '')));
    foreach ($uniqueTerms as $term) {
        $escaped = preg_quote($term, '/');
        $text = preg_replace('/(' . $escaped . ')/iu', '<mark>$1</mark>', $text);
    }
    return $text;
}

/**
 * @param Itinerary[] $itineraries
 * @return array<int, array<string, mixed>>
 */
function nammu_collect_itinerary_documents(array $itineraries, MarkdownConverter $markdown): array
{
    $documents = [];
    foreach ($itineraries as $itinerary) {
        if (!$itinerary instanceof Itinerary) {
            continue;
        }
        $slug = $itinerary->getSlug();
        $itineraryTitle = trim($itinerary->getTitle()) !== '' ? trim($itinerary->getTitle()) : $slug;
        $description = trim($itinerary->getDescription());
        $classLabel = trim($itinerary->getClassLabel());
        $typeLabel = $classLabel !== '' ? nammu_uppercase_label($classLabel) : 'ITINERARIO';
        $bodyHtml = $markdown->toHtml($itinerary->getContent());
        $bodyText = trim(preg_replace('/\s+/u', ' ', strip_tags($bodyHtml)));
        $relativeUrl = nammu_build_itinerary_relative_url($slug);

        $documents[] = [
            'slug' => 'itinerarios/' . $slug,
            'relative_url' => $relativeUrl,
            'title' => $itineraryTitle,
            'description' => $description,
            'category' => $itineraryTitle,
            'template' => 'itinerary',
            'type' => 'itinerary',
            'date_raw' => '',
            'date_iso' => '',
            'date_display' => '',
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'fields' => [
                'title' => $itineraryTitle,
                'description' => $description,
                'category' => $itineraryTitle,
                'content' => $bodyText,
                'slug' => 'itinerarios/' . $slug,
            ],
            'type_label_override' => $typeLabel,
        ];

        foreach ($itinerary->getTopics() as $topic) {
            $topicSlug = $topic->getSlug();
            $topicTitle = trim($topic->getTitle()) !== '' ? trim($topic->getTitle()) : $topicSlug;
            $topicDescription = trim($topic->getDescription());
            $topicHtml = $markdown->toHtml($topic->getContent());
            $topicText = trim(preg_replace('/\s+/u', ' ', strip_tags($topicHtml)));
            $topicUrl = nammu_build_itinerary_relative_url($slug, $topicSlug);

            $documents[] = [
                'slug' => 'itinerarios/' . $slug . '/' . $topicSlug,
                'relative_url' => $topicUrl,
                'title' => $topicTitle,
                'description' => $topicDescription,
                'category' => $itineraryTitle,
                'template' => 'itinerary_topic',
                'type' => 'itinerary',
                'date_raw' => '',
                'date_iso' => '',
                'date_display' => '',
                'body_html' => $topicHtml,
                'body_text' => $topicText,
                'fields' => [
                    'title' => $topicTitle,
                    'description' => $topicDescription,
                    'category' => $itineraryTitle,
                    'content' => $topicText,
                    'slug' => 'itinerarios/' . $slug . '/' . $topicSlug,
                ],
                'type_label_override' => $typeLabel,
            ];
        }
    }

    return $documents;
}

function nammu_build_itinerary_relative_url(string $itinerarySlug, ?string $topicSlug = null): string
{
    $segments = [rawurlencode($itinerarySlug)];
    if ($topicSlug !== null && $topicSlug !== '') {
        $segments[] = rawurlencode($topicSlug);
    }
    return '/itinerarios/' . implode('/', $segments);
}

function nammu_uppercase_label(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text);
}
