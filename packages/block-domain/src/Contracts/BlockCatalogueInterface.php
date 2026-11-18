<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\CatalogueEntry;
use SpeedySpec\Block\Domain\ValueObjects\Position;

/**
 * Lists the block types a builder can offer, without the builder knowing any of them in advance.
 *
 * @since 0.1.0
 */
interface BlockCatalogueInterface
{
    /**
     * Lists every registered block type.
     *
     * @return list<CatalogueEntry>
     *   Entries in registration order.
     *
     * @since 0.1.0
     */
    public function all(): array;

    /**
     * Finds one block type's entry.
     *
     * @return CatalogueEntry|null
     *   Null when the type is not registered.
     *
     * @since 0.1.0
     */
    public function find(BlockName $name): ?CatalogueEntry;

    /**
     * Lists the block types that may be placed at a position in a document.
     *
     * @return list<CatalogueEntry>
     *   Entries in registration order; empty when the position's parent is not in the document or is of a type
     *   that is not registered.
     *
     * @since 0.1.0
     */
    public function placeableAt(Document $document, Position $position): array;
}
