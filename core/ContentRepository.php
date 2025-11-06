<?php

namespace Nammu\Core;

use RuntimeException;

class ContentRepository
{
    private string $contentDir;

    public function __construct(string $contentDir)
    {
        if (!is_dir($contentDir)) {
            throw new RuntimeException("Content directory '{$contentDir}' not found");
        }

        $this->contentDir = rtrim($contentDir, '/');
    }

    /**
     * @return Post[]
     */
    public function all(): array
    {
        $posts = [];
        foreach ($this->listMarkdownFiles() as $file) {
            $post = $this->buildPostFromFile($file);
            if ($post !== null) {
                $posts[] = $post;
            }
        }

        usort($posts, [$this, 'sortPosts']);

        return $posts;
    }

    public function findBySlug(string $slug): ?Post
    {
        $filepath = "{$this->contentDir}/{$slug}.md";
        if (!is_file($filepath)) {
            return null;
        }

        return $this->buildPostFromFile($filepath);
    }

    public function getDocument(string $slug): ?array
    {
        $filepath = "{$this->contentDir}/{$slug}.md";
        if (!is_file($filepath)) {
            return null;
        }

        $raw = file_get_contents($filepath);
        if ($raw === false) {
            return null;
        }

        [$metadata, $content] = $this->extractFrontMatter($raw);

        return [
            'metadata' => $metadata,
            'content' => $content,
        ];
    }

    /**
     * @return string[]
     */
    private function listMarkdownFiles(): array
    {
        $files = glob($this->contentDir . '/*.md') ?: [];
        sort($files);
        return $files;
    }

    private function buildPostFromFile(string $file): ?Post
    {
        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        [$metadata, $content] = $this->extractFrontMatter($raw);

        $template = strtolower($metadata['Template'] ?? $metadata['template'] ?? '');
        if (!in_array($template, ['single', 'post'], true)) {
            return null;
        }

        $slug = basename($file, '.md');

        return new Post($slug, $metadata, $content);
    }

    /**
     * @return array{array<string, string>, string}
     */
    private function extractFrontMatter(string $raw): array
    {
        if (!preg_match('/^---\s*\R(.*?)\R---\s*\R?(.*)$/s', $raw, $matches)) {
            return [[], trim($raw)];
        }

        $rawMeta = $matches[1];
        $body = $matches[2] ?? '';

        $metadata = [];
        foreach (preg_split("/\R/", $rawMeta) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($value !== '') {
                $metadata[$key] = $this->cleanValue($value);
            }
        }

        return [$metadata, ltrim($body)];
    }

    private function cleanValue(string $value): string
    {
        if (($value[0] ?? '') === '"' && substr($value, -1) === '"') {
            return stripcslashes(substr($value, 1, -1));
        }

        if (($value[0] ?? '') === "'" && substr($value, -1) === "'") {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private function sortPosts(Post $a, Post $b): int
    {
        $dateA = $a->getDate();
        $dateB = $b->getDate();

        if ($dateA && $dateB) {
            return $dateA < $dateB ? 1 : -1;
        }

        if ($dateA) {
            return -1;
        }

        if ($dateB) {
            return 1;
        }

        return strcmp($a->getSlug(), $b->getSlug());
    }
}
