<?php

namespace Nammu\Core;

class RssGenerator
{
    private string $siteTitle;
    private string $siteDescription;
    private string $baseUrl;

    public function __construct(string $baseUrl, string $siteTitle = 'Nammu Blog', string $siteDescription = '')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->siteTitle = $siteTitle !== '' ? $siteTitle : 'Nammu Blog';
        $this->siteDescription = $siteDescription;
    }

    /**
     * @param Post[] $posts
     */
    public function generate(array $posts, callable $urlResolver, MarkdownConverter $markdownConverter): string
    {
        $items = [];
        foreach ($posts as $post) {
            $link = $this->normalizeUrl($urlResolver($post));
            $title = htmlspecialchars($post->getTitle(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $description = $post->getDescription();

            $contentHtml = $markdownConverter->toHtml($post->getContent());
            $contentHtml = $this->absolutizeHtmlLinks($contentHtml);
            $contentForFeed = $contentHtml !== '' ? $contentHtml : htmlspecialchars($post->getContent(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            if ($description === '') {
                $description = $this->excerpt($contentHtml !== '' ? strip_tags($contentHtml) : $post->getContent());
            }

            $description = htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $imageUrl = $this->resolveImageUrl($post->getImage());
            $enclosureXml = '';
            if ($imageUrl !== null) {
                $contentForFeed = '<p><img src="' . $imageUrl . '" alt="' . $title . '"></p>' . $contentForFeed;
                $mimeType = $this->guessImageMimeType($imageUrl);
                $enclosureXml = "\n        <enclosure url=\"{$imageUrl}\" type=\"{$mimeType}\" />";
            }

            $pubDate = $post->getDate();
            $dateString = $pubDate
                ? $pubDate->setTime(0, 0)->format(DATE_RSS)
                : gmdate(DATE_RSS);

            $guid = $link;

            $items[] = <<<XML
    <item>
        <title>{$title}</title>
        <link>{$link}</link>
        <guid isPermaLink="true">{$guid}</guid>{$enclosureXml}
        <pubDate>{$dateString}</pubDate>
        <description><![CDATA[{$description}]]></description>
        <content:encoded><![CDATA[{$contentForFeed}]]></content:encoded>
    </item>
XML;
        }

        $itemsXml = implode("\n", $items);
        $title = htmlspecialchars($this->siteTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $description = htmlspecialchars($this->siteDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $link = $this->baseUrl !== '' ? $this->baseUrl : '/';

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
    <title>{$title}</title>
    <link>{$link}</link>
    <description>{$description}</description>
    <lastBuildDate>{$this->lastBuildDate($posts)}</lastBuildDate>
{$itemsXml}
</channel>
</rss>
XML;
    }

    /**
     * @param Post[] $posts
     */
    private function lastBuildDate(array $posts): string
    {
        foreach ($posts as $post) {
            $date = $post->getDate();
            if ($date) {
                return $date->setTime(0, 0)->format(DATE_RSS);
            }
        }

        return gmdate(DATE_RSS);
    }

    private function excerpt(string $text, int $length = 200): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $length - 1)) . '…';
    }

    private function normalizeUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if ($this->baseUrl === '') {
            $trimmed = ltrim($url, '/');
            if ($trimmed === '') {
                return '';
            }

            if ($trimmed[0] === '?') {
                return '?' . substr($trimmed, 1);
            }

            return '/' . $trimmed;
        }

        $url = preg_replace('#^\./#', '', $url);

        return $this->baseUrl . '/' . ltrim($url, '/');
    }

    private function absolutizeHtmlLinks(string $html): string
    {
        $pattern = '~\s(href|src)=([\'"])([^\'"]+)\2~i';
        return preg_replace_callback($pattern, function (array $matches): string {
            $attribute = $matches[1];
            $quote = $matches[2];
            $url = $matches[3];

            if (preg_match('~^(?:https?:|mailto:|tel:|data:|#)~i', $url)) {
                return " {$attribute}={$quote}{$url}{$quote}";
            }

            $absolute = $this->normalizeUrl($url);
            return " {$attribute}={$quote}{$absolute}{$quote}";
        }, $html);
    }

    private function resolveImageUrl(?string $image): ?string
    {
        if ($image === null || trim($image) === '') {
            return null;
        }

        $image = trim($image);
        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }

        $image = ltrim($image, '/');
        if (!str_starts_with($image, 'assets/')) {
            $image = 'assets/' . $image;
        }

        if ($this->baseUrl === '') {
            return $image;
        }

        return $this->baseUrl . '/' . $image;
    }

    private function guessImageMimeType(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }
}
