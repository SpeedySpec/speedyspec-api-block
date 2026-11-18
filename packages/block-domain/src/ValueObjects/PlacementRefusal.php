<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * Why a block may not sit where it was put.
 *
 * @since 0.1.0
 */
enum PlacementRefusal: string
{
    case ParentRefusesChild = 'parent-refuses-child';
    case ChildRefusesParent = 'child-refuses-parent';
    case ChildRefusesRoot = 'child-refuses-root';
}
