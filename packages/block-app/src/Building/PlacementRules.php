<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Building;

use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\PlacementRulesInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\PlacementDecision;
use SpeedySpec\Block\Domain\ValueObjects\PlacementRefusal;

/**
 * Decides placement from the children and parent rules the registered block types declare.
 *
 * @since 0.1.0
 */
class PlacementRules implements PlacementRulesInterface
{
    /**
     * Reads the rules from the type registry, so they always match what is registered now.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly BlockTypeRegistryInterface $types)
    {
    }

    public function decide(BlockName $child, ?BlockName $parent): PlacementDecision
    {
        $parentType = $parent === null ? null : $this->types->get($parent);

        if ($parentType !== null && !$parentType->children->allows($child))
        {
            return PlacementDecision::refused(PlacementRefusal::ParentRefusesChild, sprintf(
                'Block type "%s" may not be placed inside "%s".',
                $child->toString(),
                $parentType->name->toString(),
            ));
        }

        $childType = $this->types->get($child);

        if ($childType === null || $childType->parents->allows($parent))
        {
            return PlacementDecision::allowed();
        }

        if ($parent === null)
        {
            return PlacementDecision::refused(PlacementRefusal::ChildRefusesRoot, sprintf(
                'Block type "%s" may not be placed at the document root.',
                $child->toString(),
            ));
        }

        return PlacementDecision::refused(PlacementRefusal::ChildRefusesParent, sprintf(
            'Block type "%s" may not be placed inside "%s".',
            $child->toString(),
            $parent->toString(),
        ));
    }

    public function allowedChildren(?BlockName $parent): array
    {
        $allowed = [];

        foreach ($this->types->all() as $type)
        {
            if ($this->decide($type->name, $parent)->allowed)
            {
                $allowed[] = $type->name;
            }
        }

        return $allowed;
    }
}
