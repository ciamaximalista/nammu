<?php

namespace Nammu\Core;

use DateTimeInterface;

class SitemapGenerator
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @param array<int, array{loc:string,lastmod?:DateTimeInterface|string|null,changefreq?:string|null,priority?:float|string|null}> $entries
     */
    public function generate(array $entries): string
    {
        $hasImages = false;
        foreach ($entries as $entry) {
            if (!empty($entry['image'])) {
                $hasImages = true;
                break;
            }
        }
        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            $hasImages
                ? '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'
                : '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $entry) {
            $loc = $this->normalizeUrl($entry['loc'] ?? '');
            if ($loc === '') {
                continue;
            }

            $xml[] = '  <url>';
            $xml[] = '    <loc>' . htmlspecialchars($loc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</loc>';

            if (isset($entry['lastmod']) && $entry['lastmod'] !== null && $entry['lastmod'] !== '') {
                $xml[] = '    <lastmod>' . $this->formatLastModified($entry['lastmod']) . '</lastmod>';
            }

            if (!empty($entry['changefreq'])) {
                $xml[] = '    <changefreq>' . htmlspecialchars((string) $entry['changefreq'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</changefreq>';
            }

            if (isset($entry['priority']) && $entry['priority'] !== null && $entry['priority'] !== '') {
                $priority = $this->normalizePriority($entry['priority']);
                if ($priority !== null) {
                    $xml[] = '    <priority>' . $priority . '</priority>';
                }
            }

            if (!empty($entry['image'])) {
                $images = is_array($entry['image']) ? $entry['image'] : [$entry['image']];
                foreach ($images as $image) {
                    $imageLoc = $this->normalizeUrl((string) $image);
                    if ($imageLoc === '') {
                        continue;
                    }
                    $xml[] = '    <image:image>';
                    $xml[] = '      <image:loc>' . htmlspecialchars($imageLoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</image:loc>';
                    $xml[] = '    </image:image>';
                }
            }

            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode("\n", $xml);
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $path = '/' . ltrim($url, '/');

        if ($this->baseUrl === '') {
            return $path;
        }

        return $this->baseUrl . $path;
    }

    /**
     * @param DateTimeInterface|string $value
     */
    private function formatLastModified(DateTimeInterface|string $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('c');
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[T\s]\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})?)?$/', $trimmed)) {
            return $trimmed;
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp !== false) {
            return gmdate('c', $timestamp);
        }

        return $trimmed;
    }

    private function normalizePriority(float|string $priority): ?string
    {
        if (is_string($priority)) {
            $priority = trim($priority);
            if ($priority === '') {
                return null;
            }
            if (!is_numeric($priority)) {
                return null;
            }
            $priority = (float) $priority;
        }

        if ($priority < 0.0 || $priority > 1.0) {
            $priority = max(0.0, min(1.0, $priority));
        }

        return number_format($priority, 1, '.', '');
    }
}
