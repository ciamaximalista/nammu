<?php

namespace Nammu\Core;

use DateTimeImmutable;

class Post
{
    private string $slug;
    private array $metadata;
    private string $content;
    private ?DateTimeImmutable $date;

    public function __construct(string $slug, array $metadata, string $content)
    {
        $this->slug = $slug;
        $this->metadata = $metadata;
        $this->content = $content;
        $this->date = $this->parseDate($metadata['Date'] ?? null);
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
        return $image !== '' ? $image : null;
    }

    public function getCategory(): string
    {
        return $this->metadata['Category'] ?? '';
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getTemplate(): string
    {
        $template = $this->metadata['Template'] ?? $this->metadata['template'] ?? '';
        return strtolower(trim((string) $template));
    }

    public function getDate(): ?DateTimeImmutable
    {
        return $this->date;
    }

    public function getRawDate(): ?string
    {
        return $this->metadata['Date'] ?? null;
    }

    private function parseDate(?string $date): ?DateTimeImmutable
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        $date = trim($date);
        $knownFormats = [
            'd/m/Y',
            'd-m-Y',
            'Y-m-d',
            'Y/m/d',
        ];

        foreach ($knownFormats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $date);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return (new DateTimeImmutable())->setTimestamp($timestamp);
        }

        return null;
    }
}
