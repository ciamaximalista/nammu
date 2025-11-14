<?php

namespace Nammu\Core;

class Itinerary
{
    private string $slug;
    private array $metadata;
    private string $content;
    /** @var ItineraryTopic[] */
    private array $topics;
    private string $directory;

    /**
     * @param ItineraryTopic[] $topics
     */
    public function __construct(string $slug, array $metadata, string $content, array $topics, string $directory)
    {
        $this->slug = $slug;
        $this->metadata = $metadata;
        $this->content = $content;
        $this->topics = $this->sortTopics($topics);
        $this->directory = $directory;
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
}
