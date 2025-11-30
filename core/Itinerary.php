<?php

namespace Nammu\Core;

class Itinerary
{
    public const USAGE_LOGIC_FREE = 'free';
    public const USAGE_LOGIC_SEQUENTIAL = 'sequential';
    public const USAGE_LOGIC_ASSESSMENT = 'assessment';

    private const USAGE_LOGIC_ALLOWED = [
        self::USAGE_LOGIC_FREE => true,
        self::USAGE_LOGIC_SEQUENTIAL => true,
        self::USAGE_LOGIC_ASSESSMENT => true,
    ];

    private string $slug;
    private array $metadata;
    private string $content;
    /** @var ItineraryTopic[] */
    private array $topics;
    private string $directory;
    private array $quiz;

    /**
     * @param ItineraryTopic[] $topics
     */
    public function __construct(string $slug, array $metadata, string $content, array $topics, string $directory, array $quiz = [])
    {
        $this->slug = $slug;
        $this->metadata = $metadata;
        $this->content = $content;
        $this->topics = $this->sortTopics($topics);
        $this->directory = $directory;
        $this->quiz = $quiz;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->metadata['Title'] ?? $this->slug;
    }

    public function getDescription(): string
    {
        return $this->metadata['Description'] ?? '';
    }

    public function getStatus(): string
    {
        $value = strtolower(trim((string) ($this->metadata['Status'] ?? 'published')));
        if (in_array($value, ['draft', 'borrador'], true)) {
            return 'draft';
        }
        return 'published';
    }

    public function isDraft(): bool
    {
        return $this->getStatus() === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->getStatus() === 'published';
    }

    public function getClassLabel(): string
    {
        $label = trim((string) ($this->metadata['ItineraryClass'] ?? ''));
        if ($label === '') {
            return 'Itinerario';
        }
        return $label;
    }

    public function getUsageLogic(): string
    {
        $value = strtolower(trim((string) ($this->metadata['UsageLogic'] ?? '')));
        if (!isset(self::USAGE_LOGIC_ALLOWED[$value])) {
            return self::USAGE_LOGIC_FREE;
        }
        return $value;
    }

    public function getImage(): ?string
    {
        $image = $this->metadata['Image'] ?? null;
        if ($image === null || trim((string) $image) === '') {
            return null;
        }
        return trim((string) $image);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return ItineraryTopic[]
     */
    public function getTopics(): array
    {
        return $this->topics;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function getTopicCount(): int
    {
        return count($this->topics);
    }

    public function getFirstTopic(): ?ItineraryTopic
    {
        return $this->topics[0] ?? null;
    }

    public function getQuiz(): array
    {
        return $this->quiz;
    }

    public function hasQuiz(): bool
    {
        return !empty($this->quiz['questions']);
    }

    public function getQuizMinimumCorrect(): int
    {
        if (!$this->hasQuiz()) {
            return 0;
        }
        $questions = $this->quiz['questions'] ?? [];
        $questionCount = max(1, count($questions));
        $minimum = (int) ($this->quiz['minimum_correct'] ?? $questionCount);
        if ($minimum < 1) {
            $minimum = 1;
        }
        if ($minimum > $questionCount) {
            $minimum = $questionCount;
        }
        return $minimum;
    }

    private function sortTopics(array $topics): array
    {
        usort($topics, static function (ItineraryTopic $a, ItineraryTopic $b): int {
            $valA = $a->getNumber();
            $valB = $b->getNumber();
            if ($valA === $valB) {
                return strcmp($a->getSlug(), $b->getSlug());
            }
            return $valA <=> $valB;
        });
        return $topics;
    }

    public function getOrder(): int
    {
        $value = (int) ($this->metadata['Order'] ?? 0);
        return $value > 0 ? $value : PHP_INT_MAX;
    }
}
