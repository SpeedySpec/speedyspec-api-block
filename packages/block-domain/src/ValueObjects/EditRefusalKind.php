<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * Names why an edit was refused, so a builder can react without reading the message.
 *
 * @since 0.1.0
 */
enum EditRefusalKind: string
{
    case UnknownBlock = 'unknown-block';
    case UnknownType = 'unknown-type';
    case UnknownDecorator = 'unknown-decorator';
    case UnknownDataSource = 'unknown-data-source';
    case InvalidPosition = 'invalid-position';
    case DisallowedPlacement = 'disallowed-placement';
    case MoveIntoSelf = 'move-into-self';
    case InvalidSettings = 'invalid-settings';
    case InvalidBinding = 'invalid-binding';
    case PrivateBinding = 'private-binding';
}
