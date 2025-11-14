<?php

namespace Nammu\Core;

class ItineraryTopic
{
    private string $slug;
    private array $metadata;
    private string $content;
    private string $filePath;

    public function __construct(string $slug, array $metadata, string $content, string $filePath)
    {
        $this->slug = $slug;
        $this->metadata = $metadata;
        $this->content = $content;
        $this->filePath = $filePath;
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
        $testIndicator = $this->metadata['Test'] ?? $this->metadata['test'] ?? null;
        return is_array($testIndicator) ? !empty($testIndicator) : (trim((string) $testIndicator) !== '');
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
