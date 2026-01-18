<?php

use Nammu\Core\ContentRepository;
use Nammu\Core\Post;

function admin_ideas_join_list(array $items): string
{
    $items = array_values(array_filter(array_map('trim', $items), static fn($item) => $item !== ''));
    $count = count($items);
    if ($count === 0) {
        return '';
    }
    if ($count === 1) {
        return $items[0];
    }
    if ($count === 2) {
        return $items[0] . ' y ' . $items[1];
    }
    $last = array_pop($items);
    return implode(', ', $items) . ' y ' . $last;
}

function admin_ideas_post_timestamp(Post $post): ?int
{
    $date = $post->getDate();
    if ($date) {
        return $date->getTimestamp();
    }
    $raw = $post->getRawDate();
    if ($raw !== '') {
        $timestamp = strtotime($raw);
        if ($timestamp !== false) {
            return $timestamp;
        }
    }
    return null;
}

function admin_ideas_top_posts(array $analytics, int $days = 30, int $limit = 3): array
{
    $posts = is_array($analytics['content']['posts'] ?? null) ? $analytics['content']['posts'] : [];
    if (empty($posts)) {
        return [];
    }
    $startKey = date('Y-m-d', strtotime('-' . $days . ' days'));
    $ranked = [];
    foreach ($posts as $slug => $payload) {
        if (!is_array($payload)) {
            continue;
        }
        $title = trim((string) ($payload['title'] ?? $slug));
        $daily = is_array($payload['daily'] ?? null) ? $payload['daily'] : [];
        $views = 0;
        foreach ($daily as $day => $dayData) {
            if (!is_string($day) || $day < $startKey) {
                continue;
            }
            if (is_array($dayData)) {
                $views += (int) ($dayData['views'] ?? 0);
            } else {
                $views += (int) $dayData;
            }
        }
        if ($views > 0) {
            $ranked[] = ['title' => $title !== '' ? $title : $slug, 'views' => $views];
        }
    }
    if (empty($ranked)) {
        return [];
    }
    usort($ranked, static function (array $a, array $b): int {
        return $b['views'] <=> $a['views'];
    });
    $ranked = array_slice($ranked, 0, $limit);
    return array_map(static fn($item) => $item['title'], $ranked);
}

function admin_ideas_inactive_categories(array $posts, int $months = 6): array
{
    if (empty($posts)) {
        return [];
    }
    $cutoff = strtotime('-' . $months . ' months');
    if ($cutoff === false) {
        return [];
    }
    $latestByCategory = [];
    foreach ($posts as $post) {
        if (!$post instanceof Post) {
            continue;
        }
        $category = trim($post->getCategory());
        if ($category === '') {
            $category = 'Sin Categoría';
        }
        $timestamp = admin_ideas_post_timestamp($post);
        if ($timestamp === null) {
            continue;
        }
        if (!isset($latestByCategory[$category]) || $timestamp > $latestByCategory[$category]) {
            $latestByCategory[$category] = $timestamp;
        }
    }
    if (empty($latestByCategory)) {
        return [];
    }
    $inactive = [];
    foreach ($latestByCategory as $category => $timestamp) {
        if ($timestamp < $cutoff) {
            $inactive[] = $category;
        }
    }
    sort($inactive);
    return $inactive;
}

function admin_ideas_top_searches(array $analytics, int $days = 30, int $limit = 3): array
{
    $searches = is_array($analytics['searches']['daily'] ?? null) ? $analytics['searches']['daily'] : [];
    if (empty($searches)) {
        return [];
    }
    $startKey = date('Y-m-d', strtotime('-' . $days . ' days'));
    $counts = [];
    foreach ($searches as $day => $payload) {
        if (!is_string($day) || $day < $startKey) {
            continue;
        }
        if (!is_array($payload)) {
            continue;
        }
        foreach ($payload as $term => $data) {
            $termKey = trim((string) $term);
            if ($termKey === '') {
                continue;
            }
            $count = is_array($data) ? (int) ($data['count'] ?? 0) : (int) $data;
            if ($count <= 0) {
                continue;
            }
            $counts[$termKey] = ($counts[$termKey] ?? 0) + $count;
        }
    }
    if (empty($counts)) {
        return [];
    }
    arsort($counts);
    return array_slice(array_keys($counts), 0, $limit);
}

function admin_ideas_days_since_last_post(array $posts): ?int
{
    $latest = null;
    foreach ($posts as $post) {
        if (!$post instanceof Post) {
            continue;
        }
        $timestamp = admin_ideas_post_timestamp($post);
        if ($timestamp === null) {
            continue;
        }
        if ($latest === null || $timestamp > $latest) {
            $latest = $timestamp;
        }
    }
    if ($latest === null) {
        return null;
    }
    $diff = time() - $latest;
    if ($diff < 0) {
        return 0;
    }
    return (int) floor($diff / 86400);
}

function admin_ideas_build(string $contentDir, int $days = 30): array
{
    $analytics = function_exists('nammu_load_analytics') ? nammu_load_analytics() : [];
    $posts = [];
    if (class_exists(ContentRepository::class) && is_dir($contentDir)) {
        try {
            $repo = new ContentRepository($contentDir);
            $posts = $repo->all();
        } catch (Throwable $e) {
            $posts = [];
        }
    }
    $suggestions = [];

    $topPosts = admin_ideas_top_posts($analytics, $days, 3);
    if (!empty($topPosts)) {
        $list = admin_ideas_join_list($topPosts);
        if (count($topPosts) === 1) {
            $suggestions[] = 'En el último mes el artículo más leído fue: ' . $list . '. ¿Por qué no escribes algo en continuidad?';
        } else {
            $suggestions[] = 'En el último mes los artículos más leídos fueron: ' . $list . '. ¿Por qué no escribes algo en continuidad?';
        }
    }

    $inactive = admin_ideas_inactive_categories($posts, 6);
    if (!empty($inactive)) {
        $list = admin_ideas_join_list(array_slice($inactive, 0, 5));
        if (count($inactive) === 1) {
            $suggestions[] = 'Hace ya más de 6 meses que no publicas en la categoría: ' . $list . '. ¿Por qué no la retomas?';
        } else {
            $suggestions[] = 'Hace ya más de 6 meses que no publicas en las categorías: ' . $list . '. ¿Por qué no las retomas?';
        }
    }

    $searches = admin_ideas_top_searches($analytics, $days, 3);
    if (!empty($searches)) {
        $list = admin_ideas_join_list($searches);
        $suggestions[] = 'Las búsquedas internas más frecuentes del último mes fueron: ' . $list . '. Podrías profundizar en esos temas.';
    }

    $daysSince = admin_ideas_days_since_last_post($posts);
    if ($daysSince !== null && $daysSince >= 45) {
        $suggestions[] = 'Hace ' . $daysSince . ' días que no publicas una entrada. Tus lectores agradecerán una nueva actualización.';
    }

    return $suggestions;
}
