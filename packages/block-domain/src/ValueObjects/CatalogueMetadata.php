<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTypeException;

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
    )
    {
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
     * @throws InvalidBlockTypeException
     *   When a field has the wrong shape; the message names the field.
     *
     * @since 0.1.0
     */
    public static function fromArray(array $data): self
    {
        foreach (['title' => null, 'description' => '', 'category' => ''] as $field => $default)
        {
            $value = $data[$field] ?? $default;

            if (!is_string($value))
            {
                throw new InvalidBlockTypeException(sprintf('Block type metadata needs a string "%s".', $field));
            }
        }

        $keywords = $data['keywords'] ?? [];

        if (!is_array($keywords) || !array_is_list($keywords) || array_filter($keywords, 'is_string') !== $keywords)
        {
            throw new InvalidBlockTypeException('Block type metadata needs "keywords" to be a list of strings.');
        }

        $icon = $data['icon'] ?? null;

        if ($icon !== null && !is_string($icon))
        {
            throw new InvalidBlockTypeException('Block type metadata needs a string or null "icon".');
        }

        return new self(
            $data['title'],
            $data['description'] ?? '',
            $data['category'] ?? '',
            $keywords,
            $icon,
        );
    }
}
