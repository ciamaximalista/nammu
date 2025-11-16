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
        $quiz = $this->loadItineraryQuiz($directory);

        return new Itinerary($slug, $metadata, $content, $topics, $directory, $quiz);
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
        $quiz = $this->loadTopicQuiz(dirname($file), $topicSlug);
        return new ItineraryTopic($topicSlug, $metadata, $content, $file, $quiz);
    }

    public function saveItinerary(string $slug, array $metadata, string $content, ?array $quiz = null): Itinerary
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

        $quizFile = $this->itineraryQuizFile($directory);
        if ($quiz === null || empty($quiz['questions'])) {
            if (is_file($quizFile)) {
                @unlink($quizFile);
            }
        } else {
            $this->writeItineraryQuiz($quizFile, $quiz);
        }

        return $this->find($slug) ?? throw new RuntimeException('No se pudo cargar el itinerario guardado');
    }

    public function saveTopic(string $itinerarySlug, string $topicSlug, array $metadata, string $content, ?array $quiz = null): ItineraryTopic
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

        $quizFile = $this->topicQuizFile($directory, $topicSlug);
        if ($quiz === null || empty($quiz['questions'])) {
            if (is_file($quizFile)) {
                @unlink($quizFile);
            }
        } else {
            $this->writeTopicQuiz($quizFile, $this->sanitizeQuizData($quiz));
        }

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
            $topicSlug = basename($file, '.md');
            $quiz = $this->loadTopicQuiz(dirname($file), $topicSlug);
            $topics[] = new ItineraryTopic($topicSlug, $metadata, $content, $file, $quiz);
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

    private function itineraryQuizFile(string $directory): string
    {
        return rtrim($directory, '/') . '/index.quiz.json';
    }

    private function topicQuizFile(string $directory, string $topicSlug): string
    {
        return rtrim($directory, '/') . '/' . $topicSlug . '.quiz.json';
    }

    private function loadItineraryQuiz(string $directory): array
    {
        return $this->readQuizFile($this->itineraryQuizFile($directory));
    }

    private function loadTopicQuiz(string $directory, string $topicSlug): array
    {
        return $this->readQuizFile($this->topicQuizFile($directory, $topicSlug));
    }

    private function writeItineraryQuiz(string $file, array $quiz): void
    {
        $this->writeQuizFile($file, $quiz);
    }

    private function writeTopicQuiz(string $file, array $quiz): void
    {
        $this->writeQuizFile($file, $quiz);
    }

    private function readQuizFile(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            return [];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }
        return $this->sanitizeQuizData($data);
    }

    private function writeQuizFile(string $file, array $quiz): void
    {
        $payload = json_encode(
            $this->sanitizeQuizData($quiz),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($payload === false) {
            throw new RuntimeException("No se pudo serializar la autoevaluación para {$file}");
        }
        if (file_put_contents($file, $payload) === false) {
            throw new RuntimeException("No se pudo escribir la autoevaluación {$file}");
        }
    }

    public function getItineraryStats(string $slug): array
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            return ['started' => 0, 'topics' => [], 'updated_at' => 0];
        }
        $directory = "{$this->baseDir}/{$slug}";
        if (!is_dir($directory)) {
            return ['started' => 0, 'topics' => [], 'updated_at' => 0];
        }
        return $this->readItineraryStats($directory);
    }

    public function resetItineraryStats(string $slug): void
    {
        $slug = $this->sanitizeSlug($slug);
        if ($slug === '') {
            return;
        }
        $directory = "{$this->baseDir}/{$slug}";
        if (!is_dir($directory)) {
            return;
        }
        $this->writeItineraryStats($directory, [
            'started' => 0,
            'topics' => [],
            'updated_at' => time(),
        ]);
    }

    public function recordTopicStat(string $itinerarySlug, ItineraryTopic $topic, bool $incrementStart): void
    {
        $itinerarySlug = $this->sanitizeSlug($itinerarySlug);
        if ($itinerarySlug === '') {
            return;
        }
        $directory = "{$this->baseDir}/{$itinerarySlug}";
        if (!is_dir($directory)) {
            return;
        }
        $stats = $this->readItineraryStats($directory);
        if ($incrementStart) {
            $stats['started'] = (int) ($stats['started'] ?? 0) + 1;
        }
        $topicSlug = $this->sanitizeSlug($topic->getSlug());
        if ($topicSlug === '') {
            $this->writeItineraryStats($directory, $stats);
            return;
        }
        if (!isset($stats['topics'][$topicSlug])) {
            $stats['topics'][$topicSlug] = [
                'slug' => $topicSlug,
                'number' => $topic->getNumber(),
                'title' => $topic->getTitle(),
                'count' => 0,
            ];
        } else {
            $stats['topics'][$topicSlug]['number'] = $topic->getNumber();
            $stats['topics'][$topicSlug]['title'] = $topic->getTitle();
        }
        $stats['topics'][$topicSlug]['count'] = (int) ($stats['topics'][$topicSlug]['count'] ?? 0) + 1;
        $stats['updated_at'] = time();
        $this->writeItineraryStats($directory, $stats);
    }

    private function itineraryStatsFile(string $directory): string
    {
        return rtrim($directory, '/') . '/stats.json';
    }

    private function readItineraryStats(string $directory): array
    {
        $file = $this->itineraryStatsFile($directory);
        if (!is_file($file)) {
            return ['started' => 0, 'topics' => [], 'updated_at' => 0];
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            return ['started' => 0, 'topics' => [], 'updated_at' => 0];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['started' => 0, 'topics' => [], 'updated_at' => 0];
        }
        $started = (int) ($data['started'] ?? 0);
        $topics = [];
        foreach ($data['topics'] ?? [] as $slug => $topicData) {
            $topicSlug = $this->sanitizeSlug(is_array($topicData) ? ($topicData['slug'] ?? $slug) : (string) $slug);
            if ($topicSlug === '') {
                continue;
            }
            $topics[$topicSlug] = [
                'slug' => $topicSlug,
                'number' => isset($topicData['number']) ? (int) $topicData['number'] : 0,
                'title' => is_array($topicData) ? ($topicData['title'] ?? $topicSlug) : $topicSlug,
                'count' => isset($topicData['count']) ? (int) $topicData['count'] : 0,
            ];
        }
        return [
            'started' => $started,
            'topics' => $topics,
            'updated_at' => (int) ($data['updated_at'] ?? 0),
        ];
    }

    private function writeItineraryStats(string $directory, array $stats): void
    {
        $file = $this->itineraryStatsFile($directory);
        $payload = json_encode([
            'started' => (int) ($stats['started'] ?? 0),
            'topics' => $stats['topics'] ?? [],
            'updated_at' => (int) ($stats['updated_at'] ?? time()),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException("No se pudieron serializar las estadísticas para {$file}");
        }
        if (file_put_contents($file, $payload) === false) {
            throw new RuntimeException("No se pudieron escribir las estadísticas {$file}");
        }
    }

    private function sanitizeQuizData(?array $quiz): array
    {
        if (!is_array($quiz)) {
            return [];
        }
        $questions = [];
        foreach ($quiz['questions'] ?? [] as $question) {
            if (!is_array($question)) {
                continue;
            }
            $text = trim((string) ($question['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $answers = [];
            foreach ($question['answers'] ?? [] as $answer) {
                if (!is_array($answer)) {
                    continue;
                }
                $answerText = trim((string) ($answer['text'] ?? ''));
                if ($answerText === '') {
                    continue;
                }
                $answers[] = [
                    'text' => $answerText,
                    'correct' => !empty($answer['correct']),
                ];
            }
            if (count($answers) === 0) {
                continue;
            }
            $hasCorrect = false;
            foreach ($answers as $answer) {
                if (!empty($answer['correct'])) {
                    $hasCorrect = true;
                    break;
                }
            }
            if (!$hasCorrect) {
                continue;
            }
            $questions[] = [
                'text' => $text,
                'answers' => $answers,
            ];
        }
        if (empty($questions)) {
            return [];
        }
        $minimum = (int) ($quiz['minimum_correct'] ?? count($questions));
        if ($minimum < 1) {
            $minimum = 1;
        }
        if ($minimum > count($questions)) {
            $minimum = count($questions);
        }
        return [
            'minimum_correct' => $minimum,
            'questions' => array_values($questions),
        ];
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
