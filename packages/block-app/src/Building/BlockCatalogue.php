<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Building;

use SpeedySpec\Block\Domain\Contracts\BlockCatalogueInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\PlacementRulesInterface;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\CatalogueEntry;
use SpeedySpec\Block\Domain\ValueObjects\Position;

/**
 * Builds catalogue entries from the type registry each time it is asked, so it never goes stale.
 *
 * @since 0.1.0
 */
class BlockCatalogue implements BlockCatalogueInterface
{
    /**
     * Takes the registry, the placement rules and the package loader as contracts.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly BlockTypeRegistryInterface $types,
        private readonly PlacementRulesInterface $placement,
        private readonly BlockPackageLoaderInterface $packages,
    )
    {
    }

    public function all(): array
    {
        return array_map(
            fn (BlockType $type): CatalogueEntry => $this->entry($type),
            array_values($this->types->all()),
        );
    }

    public function find(BlockName $name): ?CatalogueEntry
    {
        $type = $this->types->get($name);

        return $type === null ? null : $this->entry($type);
    }

    public function placeableAt(Document $document, Position $position): array
    {
        $parent = null;

        if ($position->parentId !== null)
        {
            $block = $document->tree->find($position->parentId);

            if ($block === null || !$this->types->has($block->type))
            {
                return [];
            }

            $parent = $block->type;
        }

        $entries = [];

        foreach ($this->placement->allowedChildren($parent) as $name)
        {
            $entry = $this->find($name);

            if ($entry !== null)
            {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Pairs a block type with the package that added it.
     *
     * @since 0.1.0
     */
    private function entry(BlockType $type): CatalogueEntry
    {
        $package = $this->packages->packageOf($type->name);
        $title = $package === null ? '' : ($this->packages->details($package)['title'] ?? '');

        return new CatalogueEntry($type, $package, $title);
    }
}
