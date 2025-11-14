<?php

namespace Nammu\Core;

class ItineraryTopic
{
    private string $slug;
    private array $metadata;
    private string $content;
    private string $filePath;
    private array $quiz;

    public function __construct(string $slug, array $metadata, string $content, string $filePath, array $quiz = [])
    {
        $this->slug = $slug;
        $this->metadata = $metadata;
        $this->content = $content;
        $this->filePath = $filePath;
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

    public function getNumber(): int
    {
        return (int) ($this->metadata['Number'] ?? 0);
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

    public function hasTest(): bool
    {
        return !empty($this->quiz['questions']);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getQuiz(): array
    {
        return $this->quiz;
    }

    public function getQuizMinimumCorrect(): int
    {
        if (!$this->hasTest()) {
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
}
