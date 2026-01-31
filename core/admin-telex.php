<?php

function admin_telex_normalize_feed_url(string $url): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

function admin_telex_strip_scripts(string $html): string {
    $html = preg_replace('~<script\\b[^>]*>.*?</script>~is', '', $html);
    $html = preg_replace('~on\\w+\\s*=\\s*"[^"]*"~i', '', $html);
    $html = preg_replace("~on\\w+\\s*=\\s*'[^']*'~i", '', $html);
    return $html;
}

function admin_telex_display_content(string $html): string {
    $html = admin_telex_strip_scripts($html);
    $html = preg_replace('~<a\\b[^>]*>(.*?)</a>~is', '$1', $html);
    return strip_tags($html, '<p><br><strong><em><ul><ol><li><blockquote><h2><h3><h4>');
}

function admin_telex_prepare_insert_content(string $content): string {
    $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (trim($decoded) === '') {
        return '';
    }

    $normalized = preg_replace('~<br\\s*/?>~i', "\n", $decoded);
    $normalized = preg_replace('~</p\\s*>~i', "\n\n", $normalized);
    $normalized = preg_replace('~<p\\b[^>]*>~i', '', $normalized);
    $normalized = strip_tags($normalized);
    $normalized = trim($normalized);
    if ($normalized === '') {
        return '';
    }

    $lines = preg_split("/\\r?\\n/", $normalized);
    $lines = array_map(static function (string $line): string {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return '';
        }
        if (preg_match('/^«(.+)»$/u', $trimmed, $matches)) {
            $inner = trim($matches[1]);
            return $inner === '' ? '' : '> ' . $inner;
        }
        return $trimmed;
    }, $lines);

    $prepared = implode("\n", $lines);
    $prepared = preg_replace("/\\n{3,}/", "\n\n", $prepared);
    $prepared = trim($prepared);
    return $prepared;
}

function admin_telex_fetch_notes(array $urls, int $days = 14): array {
    $urls = array_values(array_filter(array_map('trim', $urls ?? [])));
    if (empty($urls)) {
        return [];
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 6,
            'user_agent' => 'Nammu/1.0 (+https://ruralnext.org/nammu)',
        ],
    ]);

    $cutoff = (new DateTimeImmutable('now'))->modify('-' . max(1, $days) . ' days')->getTimestamp();
    $notes = [];

    foreach ($urls as $url) {
        $feedUrl = admin_telex_normalize_feed_url($url);
        if ($feedUrl === '' || !preg_match('~\\.xml(\\?|#|$)~i', $feedUrl)) {
            continue;
        }
        $raw = @file_get_contents($feedUrl, false, $context);
        if (!is_string($raw) || trim($raw) === '') {
            continue;
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            continue;
        }
        $items = [];
        if (isset($xml->channel->item)) {
            $items = $xml->channel->item;
        } elseif (isset($xml->entry)) {
            $items = $xml->entry;
        }
        foreach ($items as $item) {
            $title = trim((string) ($item->title ?? ''));
            $link = trim((string) ($item->link ?? ''));
            if ($link === '' && isset($item->link['href'])) {
                $link = trim((string) $item->link['href']);
            }
            $content = '';
            if (isset($item->children('content', true)->encoded)) {
                $content = (string) $item->children('content', true)->encoded;
            } elseif (isset($item->children('http://purl.org/rss/1.0/modules/content/')->encoded)) {
                $content = (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded;
            } elseif (isset($item->description)) {
                $content = (string) $item->description;
            } elseif (isset($item->content)) {
                $content = (string) $item->content;
            } elseif (isset($item->summary)) {
                $content = (string) $item->summary;
            }
            $content = trim((string) $content);
            if ($content === '' && isset($item->description)) {
                $descXml = $item->description->asXML();
                if (is_string($descXml) && $descXml !== '') {
                    $inner = preg_replace('~^<description[^>]*>|</description>$~i', '', trim($descXml));
                    $inner = preg_replace('~^<!\\[CDATA\\[(.*)\\]\\]>$~s', '$1', trim($inner));
                    $content = trim($inner);
                }
            }
            $content = admin_telex_strip_scripts($content);
            $displayContent = admin_telex_display_content($content);
            $insertContent = admin_telex_prepare_insert_content($content);

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
                'title' => $title !== '' ? $title : 'Nota de Telex',
                'content' => $content,
                'insert_content' => $insertContent,
                'display_content' => $displayContent,
                'link' => $link,
                'timestamp' => $timestamp,
            ];
        }
    }

    usort($notes, static function (array $a, array $b): int {
        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
    });

    return $notes;
}
