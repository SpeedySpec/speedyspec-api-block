<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Interactive\Domain;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Exceptions\UnavailableBlockException;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use Throwable;

/**
 * Turns a countdown's local date, time and zone into one moment, so every visitor counts down to the same instant.
 *
 * A local time the clocks skip, when summer time starts, becomes the next moment that exists; one they repeat,
 * when it ends, is the earlier of the two. The page also names the moment in words, so it says when even with no
 * script to count.
 *
 * @since 0.1.0
 */
class CountdownBehaviour implements BlockBehaviourInterface
{
    /**
     * @param Closure(): DateTimeImmutable $now
     *   Tells the time, so the page knows whether the moment has passed when it is drawn.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly Closure $now)
    {
    }

    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $text = fn (string $key, string $default): string => is_string($settings[$key] ?? null)
            ? $settings[$key]
            : $default;
        $local = $text('target', '');
        $zoneName = $text('timeZone', 'UTC');
        $values = ['label' => $text('label', ''), 'after' => $text('after', ''), 'timeZone' => $zoneName];

        // Without a date the block is not set up yet, and draws nothing for visitors.
        if ($local === '')
        {
            return new BlockView([...$values, 'target' => '', 'ended' => false, 'when' => '']);
        }

        try
        {
            $zone = new DateTimeZone($zoneName);
        }
        catch (Throwable)
        {
            throw new UnavailableBlockException(IssueKind::InvalidSettings, 'Unknown time zone.', 'timeZone');
        }

        $moment = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, $zone);
        $errors = DateTimeImmutable::getLastErrors();

        if ($moment === false || ($errors !== false && $errors['warning_count'] + $errors['error_count'] > 0))
        {
            throw new UnavailableBlockException(IssueKind::InvalidSettings, 'That date does not exist.', 'target');
        }

        $moment = $this->earliest($moment, $local, $zone);
        $utc = $moment->setTimezone(new DateTimeZone('UTC'));

        return new BlockView([
            ...$values,
            'target' => $utc->format('Y-m-d\TH:i:s\Z'),
            'ended' => $utc <= ($this->now)(),
            'when' => $moment->format('j F Y, H:i') . ' (' . $zoneName . ')',
        ]);
    }

    /**
     * Takes the earlier of two moments that share a local time, as when clocks go back and an hour repeats.
     *
     * PHP picks the later one; a countdown should end the first time its clock reads the target.
     */
    private function earliest(DateTimeImmutable $moment, string $local, DateTimeZone $zone): DateTimeImmutable
    {
        foreach ([3600, 1800] as $shift)
        {
            $earlier = (new DateTimeImmutable('@' . ($moment->getTimestamp() - $shift)))->setTimezone($zone);

            if ($earlier->format('Y-m-d\TH:i') === $local)
            {
                return $earlier;
            }
        }

        return $moment;
    }
}
