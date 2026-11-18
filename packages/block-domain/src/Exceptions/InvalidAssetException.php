<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Signals an asset that cannot be used safely, or a set of assets whose dependencies cannot be put in order.
 *
 * @since 0.1.0
 */
class InvalidAssetException extends InvalidArgumentException
{
}
