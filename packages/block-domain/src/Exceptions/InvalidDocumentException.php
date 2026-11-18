<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Exceptions;

use InvalidArgumentException;

/**
 * Signals stored input that is not a document, so callers can reject it without catching unrelated errors.
 *
 * @since 0.1.0
 */
class InvalidDocumentException extends InvalidArgumentException
{
}
