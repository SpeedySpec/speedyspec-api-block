<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Actions\Domain;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Exceptions\UnavailableBlockException;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Checks that a social link goes where its name says, through {@link SocialServices}.
 *
 * A link not given yet is not set up rather than broken; one its service does not accept is unavailable.
 *
 * @since 0.1.0
 */
class SocialLinkBehaviour implements BlockBehaviourInterface
{
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $service = is_string($settings['service'] ?? null) ? $settings['service'] : 'website';
        $url = is_string($settings['url'] ?? null) ? trim($settings['url']) : '';

        if ($url !== '' && !SocialServices::accepts($service, $url))
        {
            throw new UnavailableBlockException(
                IssueKind::UnavailableMedia,
                $service === 'email'
                    ? 'Use a mailto: link for email.'
                    : sprintf('This link is not to %s.', SocialServices::title($service)),
                'url',
            );
        }

        return new BlockView(['service' => $service, 'title' => SocialServices::title($service), 'url' => $url]);
    }
}
