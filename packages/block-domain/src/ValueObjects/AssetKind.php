<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * Says how a page loads an asset.
 *
 * @since 0.1.0
 */
enum AssetKind: string
{
    case Script = 'script';
    case Style = 'style';
}
