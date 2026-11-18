<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use RuntimeException;

/**
 * Signals a block id that appears more than once in one tree.
 *
 * @since 0.1.0
 */
class DuplicateBlockIdException extends RuntimeException
{
}
