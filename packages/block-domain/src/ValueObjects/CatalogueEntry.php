<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * One block type as a builder lists it: the type, and the package that added it.
 *
 * @since 0.1.0
 */
class CatalogueEntry
{
    /**
     * Pairs a block type with its owner so a builder can group blocks by package.
     *
     * @param string|null $package
     *   The name of the package that added the type; null when the host registered it directly.
     * @param string $packageTitle
     *   That package's title, empty when there is no package.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly BlockType $type,
        public readonly ?string $package,
        public readonly string $packageTitle = '',
    )
    {
    }

    /**
     * Flattens the entry to plain data, for a builder that is not written in PHP.
     *
     * The settings schema is included with its hints, so a builder can draw controls for a block it has never seen.
     *
     * @return array<string, mixed>
     *   Name, title, description, category, keywords, icon, package and its title, whether the block takes children,
     *   the settings schema and the examples.
     *
     * @since 0.1.0
     */
    public function toArray(): array
    {
        return [
            'name' => $this->type->name->toString(),
            'title' => $this->type->catalogue->title,
            'description' => $this->type->catalogue->description,
            'category' => $this->type->catalogue->category,
            'keywords' => $this->type->catalogue->keywords,
            'icon' => $this->type->catalogue->icon,
            'package' => $this->package,
            'packageTitle' => $this->packageTitle,
            'acceptsChildren' => $this->type->children->toArray() !== false,
            'settings' => $this->type->settingsSchema,
            'examples' => $this->type->examples,
        ];
    }
}
