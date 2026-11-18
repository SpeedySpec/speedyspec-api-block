<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Signals a block template that cannot be listed or inserted: a bad name, no title, or no blocks.
 *
 * @since 0.1.0
 */
class InvalidBlockTemplateException extends InvalidArgumentException
{
}
