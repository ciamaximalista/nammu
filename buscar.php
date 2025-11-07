<?php

declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/helpers.php';

use Nammu\Core\ContentRepository;
use Nammu\Core\MarkdownConverter;
use Nammu\Core\TemplateRenderer;

$contentDir = __DIR__ . '/content';
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

$renderer = new TemplateRenderer(__DIR__ . '/template', [
    'siteTitle' => $displaySiteTitle,
    'siteDescription' => $siteDescription,
    'rssUrl' => $rssUrl !== '' ? $rssUrl : '/rss.xml',
    'baseUrl' => $homeUrl,
    'theme' => $theme,
]);

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
    $resultsForView[] = [
        'slug' => $item['slug'],
        'url' => ($publicBaseUrl !== '' ? $publicBaseUrl : '') . '/' . rawurlencode($item['slug']),
        'title' => nammu_highlight_terms($item['title'], $item['highlight_terms']),
        'description' => $item['description'] !== '' ? nammu_highlight_terms($item['description'], $item['highlight_terms']) : '',
        'snippet' => $item['snippet'],
        'type_label' => $item['type_label'],
        'category' => $item['category'],
        'date' => $item['date_display'],
        'score' => $item['score'],
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
    ],
    'tips' => [
        'Usa comillas para buscar frases exactas, por ejemplo "bosque mediterráneo".',
        'Filtra por campos: title:"Plantación", category:educación, content:escuela.',
        'Excluye términos con el prefijo -, por ejemplo bosque -urbano.',
        'Limita por tipo con tipo:entrada o tipo:página.',
    ],
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
        $type = match ($template) {
            'page' => 'page',
            'single', 'post' => 'post',
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
            'fields' => [
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'content' => $bodyText,
                'slug' => $slug,
            ],
        ];
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
            'type_label' => match ($doc['type']) {
                'page' => 'Página',
                'post' => 'Entrada',
                default => 'Documento',
            },
            'date_display' => $doc['date_display'],
            'score' => $score,
            'snippet' => $snippet,
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
