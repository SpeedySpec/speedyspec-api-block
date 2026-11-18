<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Presentation;

use SpeedySpec\Block\Domain\Contracts\BuilderAccessInterface;
use SpeedySpec\Block\Domain\ValueObjects\BuilderAbility;

/**
 * Refuses everything, which is what the API does until the host says who may do what.
 *
 * @since 0.1.0
 */
class DenyAll implements BuilderAccessInterface
{
    public function allows(BuilderAbility $ability, ?string $documentId = null): bool
    {
        return false;
    }
}
