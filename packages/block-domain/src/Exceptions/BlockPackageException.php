<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use RuntimeException;

/**
 * Signals a block package that cannot be loaded: a bad name, a second load, or a block type it may not claim.
 *
 * @since 0.1.0
 */
class BlockPackageException extends RuntimeException
{
}
