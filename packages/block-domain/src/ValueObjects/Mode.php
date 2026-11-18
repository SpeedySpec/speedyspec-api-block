<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * The surface a block is rendered for, so one definition can serve both the public site and the admin.
 *
 * @since 0.1.0
 */
enum Mode: string
{
    case Front = 'front';
    case Admin = 'admin';
}
