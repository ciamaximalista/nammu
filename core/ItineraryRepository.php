<?php

namespace Nammu\Core;

use RuntimeException;

class ItineraryRepository
{
    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '/');
        if ($this->baseDir === '') {
            throw new RuntimeException('Directorio base para itinerarios no válido');
        }
        if (!is_dir($this->baseDir) && !\nammu_ensure_directory($this->baseDir)) {
            throw new RuntimeException(
                'No se pudo preparar el directorio base de itinerarios. ' .
                'Crea manualmente la carpeta "' . $this->baseDir . '" y asigna permisos de escritura al usuario del servidor web.'
            );
        }
    }

    /**
     * @return Itinerary[]
     */
    public function all(): array
    {
        if (!is_dir($this->baseDir)) {
            return [];
        }

        $directories = glob($this->baseDir . '/*', GLOB_ONLYDIR) ?: [];
        $itineraries = [];
        foreach ($directories as $dir) {
            $slug = basename($dir);
            $itinerary = $this->find($slug);
            if ($itinerary !== null) {
                $itineraries[] = $itinerary;
            }
        }

        usort($itineraries, static function (Itinerary $a, Itinerary $b): int {
            return strcasecmp($a->getTitle(), $b->getTitle());
        });

        return $itineraries;
    }

    public function find(string $slug): ?Itinerary
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            return null;
        }

        $directory = "{$this->baseDir}/{$slug}";
        if (!is_dir($directory)) {
            return null;
        }

        $indexFile = "{$directory}/index.md";
        if (!is_file($indexFile)) {
            return null;
        }

        [$metadata, $content] = $this->readFrontMatter($indexFile);
        $topics = $this->loadTopics($directory);

        return new Itinerary($slug, $metadata, $content, $topics, $directory);
    }

    public function findTopic(string $itinerarySlug, string $topicSlug): ?ItineraryTopic
    {
        $itinerarySlug = $this->sanitizeSlug($itinerarySlug);
        $topicSlug = $this->sanitizeSlug($topicSlug);
        if ($itinerarySlug === '' || $topicSlug === '') {
            return null;
        }
        $file = "{$this->baseDir}/{$itinerarySlug}/{$topicSlug}.md";
        if (!is_file($file)) {
            return null;
        }

        [$metadata, $content] = $this->readFrontMatter($file);
        return new ItineraryTopic($topicSlug, $metadata, $content, $file);
    }

    public function saveItinerary(string $slug, array $metadata, string $content): Itinerary
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            throw new RuntimeException('El slug del itinerario no es válido');
        }
        $directory = "{$this->baseDir}/{$slug}";
        if (!is_dir($directory) && !\nammu_ensure_directory($directory)) {
            throw new RuntimeException("No se pudo crear el directorio del itinerario {$slug}. Comprueba los permisos de escritura.");
        }

        $metadata['Slug'] = $slug;
        $this->writeFrontMatter("{$directory}/index.md", $metadata, $content);

        return $this->find($slug) ?? throw new RuntimeException('No se pudo cargar el itinerario guardado');
    }

    public function saveTopic(string $itinerarySlug, string $topicSlug, array $metadata, string $content): ItineraryTopic
    {
        $itinerarySlug = $this->sanitizeSlug($itinerarySlug);
        if ($itinerarySlug === '') {
            throw new RuntimeException('Itinerario inválido');
        }

        $topicSlug = $this->sanitizeSlug($topicSlug);
        if ($topicSlug === '') {
            throw new RuntimeException('Slug de tema inválido');
        }

        $directory = "{$this->baseDir}/{$itinerarySlug}";
        if (!is_dir($directory)) {
            throw new RuntimeException('El itinerario no existe');
        }

        $metadata['Slug'] = $topicSlug;
        $file = "{$directory}/{$topicSlug}.md";
        $this->writeFrontMatter($file, $metadata, $content);

        return $this->findTopic($itinerarySlug, $topicSlug) ?? throw new RuntimeException('No se pudo cargar el tema guardado');
    }

    /**
     * @return ItineraryTopic[]
     */
    private function loadTopics(string $directory): array
    {
        $files = glob($directory . '/*.md') ?: [];
        $topics = [];
        foreach ($files as $file) {
            if (basename($file) === 'index.md') {
                continue;
            }
            [$metadata, $content] = $this->readFrontMatter($file);
            $topics[] = new ItineraryTopic(basename($file, '.md'), $metadata, $content, $file);
        }
        usort($topics, static function (ItineraryTopic $a, ItineraryTopic $b): int {
            $numberComparison = $a->getNumber() <=> $b->getNumber();
            if ($numberComparison !== 0) {
                return $numberComparison;
            }
            return strcmp($a->getSlug(), $b->getSlug());
        });
        return $topics;
    }

    private function readFrontMatter(string $file): array
    {
        $raw = file_get_contents($file);
        if ($raw === false) {
            return [[], ''];
        }

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
            if ($key === '') {
                continue;
            }
            $metadata[$key] = $this->cleanValue($value);
        }

        return [$metadata, ltrim($body)];
    }

    private function writeFrontMatter(string $file, array $metadata, string $content): void
    {
        $lines = ["---"];
        foreach ($metadata as $key => $value) {
            if ($value === null) {
                continue;
            }
            $value = (string) $value;
            $lines[] = "{$key}: {$value}";
        }
        $lines[] = "---";
        $body = ltrim($content);
        $payload = implode("\n", $lines) . "\n\n" . $body . "\n";
        if (file_put_contents($file, $payload) === false) {
            throw new RuntimeException("No se pudo escribir el archivo {$file}");
        }
    }

    private function cleanValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (($value[0] ?? '') === '"' && substr($value, -1) === '"') {
            return stripcslashes(substr($value, 1, -1));
        }
        if (($value[0] ?? '') === "'" && substr($value, -1) === "'") {
            return substr($value, 1, -1);
        }
        return $value;
    }

    private function sanitizeSlug(string $slug): string
    {
        return self::normalizeSlug($slug);
    }

    public static function normalizeSlug(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
        $slug = preg_replace('/-+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }
}
