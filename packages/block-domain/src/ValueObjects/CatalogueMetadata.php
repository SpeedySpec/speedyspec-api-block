<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * Describes a block type for humans, so editors can list and search it without touching its behaviour.
 *
 * @since 0.1.0
 */
class CatalogueMetadata
{
    /**
     * @param list<string> $keywords
     *   Search terms.
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description = '',
        public readonly string $category = '',
        public readonly array $keywords = [],
        public readonly ?string $icon = null,
    ) {
    }

    /**
     * Serialises the metadata with the same keys {@link BlockType::toArray()} uses.
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'keywords' => $this->keywords,
            'icon' => $this->icon,
        ];
    }

    /**
     * Builds metadata from its serialised form; the title is required.
     *
     * @param array<string, mixed> $data
     *   The serialised metadata.
     *
     * @since 0.1.0
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['title'],
            (string) ($data['description'] ?? ''),
            (string) ($data['category'] ?? ''),
            array_values((array) ($data['keywords'] ?? [])),
            isset($data['icon']) ? (string) $data['icon'] : null,
        );
    }
}
