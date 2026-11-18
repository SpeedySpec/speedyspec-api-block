<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\BuilderAbility;

/**
 * Decides what the person behind a request may do through a builder.
 *
 * The block system knows nothing about users. The host implements this against its own accounts and roles, and
 * every way of reaching a builder asks it first. Where a host binds nothing, everything is refused.
 *
 * @since 0.1.0
 */
interface BuilderAccessInterface
{
    /**
     * Tells whether the current person has an ability.
     *
     * @param string|null $documentId
     *   The document the request is about; null for a request about no document in particular, such as listing
     *   block types.
     *
     * @since 0.1.0
     */
    public function allows(BuilderAbility $ability, ?string $documentId = null): bool;
}
