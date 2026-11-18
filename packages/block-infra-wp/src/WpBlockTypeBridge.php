<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP;

use SpeedySpec\Block\Domain\ValueObjects\BlockType;

/**
 * Describes a WordPress block type as a block type of this system, so any builder can list and place it.
 *
 * @since 0.1.0
 */
class WpBlockTypeBridge
{
    private const array TYPES = ['string', 'number', 'integer', 'boolean', 'array', 'object', 'null'];

    /**
     * Converts one WordPress block type, given as the plain data WpBlockTypesInterface supplies.
     *
     * Attributes become the settings schema. WordPress reads some attributes out of a block's saved markup; here
     * those are ordinary settings, and the markup itself travels in the `wpContent` setting.
     *
     * @param array<string, mixed> $type
     *
     * @since 0.1.0
     */
    public function toBlockType(array $type): BlockType
    {
        $name = (string) ($type['name'] ?? '');
        $properties = [];

        foreach (is_array($type['attributes'] ?? null) ? $type['attributes'] : [] as $attribute => $definition)
        {
            $properties[(string) $attribute] = $this->schema($definition);
        }

        $properties[WpMarkup::CONTENT] = [
            'type' => 'array',
            'default' => [],
            'items' => ['type' => ['string', 'null']],
            'title' => 'Saved markup',
        ];
        $properties['metadata'] ??= ['type' => 'object'];
        $example = $type['example']['attributes'] ?? null;
        $strings = static fn (mixed $list): array => array_values(array_filter(
            is_array($list) ? $list : [],
            static fn (mixed $entry): bool => is_string($entry) && $entry !== '',
        ));

        return BlockType::fromArray([
            'name' => $name,
            'title' => is_string($type['title'] ?? null) && $type['title'] !== '' ? $type['title'] : $name,
            'description' => is_string($type['description'] ?? null) ? $type['description'] : '',
            'category' => is_string($type['category'] ?? null) ? $type['category'] : '',
            'keywords' => $strings($type['keywords'] ?? []),
            'settings' => ['type' => 'object', 'properties' => $properties],
            // WordPress lets any block hold inner blocks unless it lists the ones it allows.
            'children' => is_array($type['allowed_blocks'] ?? null) ? $strings($type['allowed_blocks']) : true,
            'parents' => is_array($type['parent'] ?? null) ? $strings($type['parent']) : true,
            'provides' => array_filter(
                is_array($type['provides_context'] ?? null) ? $type['provides_context'] : [],
                static fn (mixed $value, mixed $key): bool => is_string($value) && $value !== '' && is_string($key),
                ARRAY_FILTER_USE_BOTH,
            ),
            'uses' => $strings($type['uses_context'] ?? []),
            'examples' => is_array($example) && !array_is_list($example) ? [$example] : [],
        ]);
    }

    /**
     * Keeps the parts of an attribute definition the schema subset understands; anything else allows any value.
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function schema(mixed $definition): array
    {
        if (!is_array($definition))
        {
            return [];
        }

        $schema = [];
        $type = $definition['type'] ?? null;
        $type = $type === 'rich-text' ? 'string' : $type;
        $types = array_values(array_intersect(is_array($type) ? $type : [$type], self::TYPES));

        if ($types !== [])
        {
            $schema['type'] = count($types) === 1 ? $types[0] : $types;
        }

        if (is_array($definition['enum'] ?? null) && $definition['enum'] !== [])
        {
            $schema['enum'] = array_values($definition['enum']);
        }

        if (array_key_exists('default', $definition))
        {
            $schema['default'] = $definition['default'];
        }

        if (is_array($definition['items'] ?? null))
        {
            $schema['items'] = $this->schema($definition['items']);
        }

        return $schema;
    }
}
