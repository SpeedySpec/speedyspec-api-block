<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;

/**
 * Names extra assets a drawn block needs, beyond those its block type declares.
 *
 * @since 0.1.0
 */
interface CollectStepInterface
{
    /**
     * Lists asset names for a block that was drawn.
     *
     * @return list<string>
     *   Asset names, as opaque identifiers the host resolves.
     *
     * @since 0.1.0
     */
    public function assets(ResolvedBlock $block, RenderRequest $request): array;
}
