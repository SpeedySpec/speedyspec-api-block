<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\PlacementDecision;

/**
 * Decides which block types may sit inside which, for the resolver and for builders alike.
 *
 * One contract answers the question everywhere, so a document a builder is allowed to make never loses a block to
 * a placement issue when it is resolved.
 *
 * @since 0.1.0
 */
interface PlacementRulesInterface
{
    /**
     * Decides whether a block type may be placed directly inside a parent type.
     *
     * A type that is not registered is not a placement problem: a rule that needs it is skipped, and the unknown
     * type is reported where it is resolved or inserted.
     *
     * @param BlockName|null $parent
     *   The parent's type; null means the document root.
     *
     * @since 0.1.0
     */
    public function decide(BlockName $child, ?BlockName $parent): PlacementDecision;

    /**
     * Lists the registered block types that may be placed directly inside a parent type.
     *
     * @param BlockName|null $parent
     *   The parent's type; null means the document root.
     *
     * @return list<BlockName>
     *   Block type names, in registration order.
     *
     * @since 0.1.0
     */
    public function allowedChildren(?BlockName $parent): array;
}
