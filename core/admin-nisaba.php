<?php

function admin_nisaba_normalize_base_url(string $url): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }
    return rtrim($url, '/');
}

function admin_nisaba_feed_url(string $baseUrl): string {
    $baseUrl = admin_nisaba_normalize_base_url($baseUrl);
    if ($baseUrl === '') {
        return '';
    }
    if (preg_match('~\\.xml$~i', $baseUrl)) {
        return $baseUrl;
    }
    return $baseUrl . '/notas.xml';
}

function admin_nisaba_strip_scripts(string $html): string {
    $html = preg_replace('~<script\\b[^>]*>.*?</script>~is', '', $html);
    $html = preg_replace('~on\\w+\\s*=\\s*"[^"]*"~i', '', $html);
    $html = preg_replace("~on\\w+\\s*=\\s*'[^']*'~i", '', $html);
    return $html;
}

function admin_nisaba_display_content(string $html): string {
    $html = admin_nisaba_strip_scripts($html);
    $html = preg_replace('~<a\\b[^>]*>(.*?)</a>~is', '$1', $html);
    return strip_tags($html, '<p><br><strong><em><ul><ol><li><blockquote><h2><h3><h4>');
}

function admin_nisaba_prepare_insert_content(string $content): string {
    $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!preg_match('/«[^»]+»/u', $decoded)) {
        return $content;
    }
    $prepared = preg_replace_callback('/«([^»]+)»/u', static function (array $matches): string {
        $text = trim($matches[1]);
        if ($text === '') {
            return '';
        }
        return "\n\n> " . $text . "\n";
    }, $decoded);
    $prepared = preg_replace("/\n{3,}/", "\n\n", $prepared);
    return htmlspecialchars_decode(trim($prepared), ENT_QUOTES);
}

function admin_nisaba_fetch_notes(string $baseUrl, int $days = 14): array {
    $feedUrl = admin_nisaba_feed_url($baseUrl);
    if ($feedUrl === '') {
        return [];
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 6,
            'user_agent' => 'Nammu/1.0 (+https://ruralnext.org/nammu)',
        ],
    ]);
    $raw = @file_get_contents($feedUrl, false, $context);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($raw);
    if ($xml === false) {
        return [];
    }

    $items = [];
    if (isset($xml->channel->item)) {
        $items = $xml->channel->item;
    } elseif (isset($xml->entry)) {
        $items = $xml->entry;
    }

    $cutoff = (new DateTimeImmutable('now'))->modify('-' . max(1, $days) . ' days')->getTimestamp();
    $notes = [];
    foreach ($items as $item) {
        $title = trim((string) ($item->title ?? ''));
        $link = trim((string) ($item->link ?? ''));
        if ($link === '' && isset($item->link['href'])) {
            $link = trim((string) $item->link['href']);
        }
        $content = '';
        if (isset($item->children('content', true)->encoded)) {
            $content = (string) $item->children('content', true)->encoded;
        } elseif (isset($item->description)) {
            $content = (string) $item->description;
        } elseif (isset($item->content)) {
            $content = (string) $item->content;
        }
        $content = admin_nisaba_strip_scripts($content);
        $displayContent = admin_nisaba_display_content($content);
        $insertContent = admin_nisaba_prepare_insert_content($content);

        $dateRaw = (string) ($item->pubDate ?? '');
        if ($dateRaw === '' && isset($item->updated)) {
            $dateRaw = (string) $item->updated;
        }
        if ($dateRaw === '' && isset($item->children('dc', true)->date)) {
            $dateRaw = (string) $item->children('dc', true)->date;
        }
        $timestamp = $dateRaw !== '' ? strtotime($dateRaw) : false;
        if ($timestamp === false || $timestamp < $cutoff) {
            continue;
        }

        if ($title === '' && $content === '') {
            continue;
        }

        $notes[] = [
            'title' => $title !== '' ? $title : 'Nota de Nisaba',
            'content' => $content,
            'insert_content' => $insertContent,
            'display_content' => $displayContent,
            'link' => $link,
            'timestamp' => $timestamp,
        ];
    }

    usort($notes, static function (array $a, array $b): int {
        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
    });

    return $notes;
}
