<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Interactive\Domain;

use Closure;
use DateTimeImmutable;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;

/**
 * Defines the blocks a visitor opens, switches or watches: accordions, tabs, sliders, countdowns, progress bars,
 * counters and modals.
 *
 * Their markup must work with no script, so these definitions hold only what to show; how it is drawn and what a
 * script adds are another package's. The settings are fixed; changing one needs a migration.
 *
 * @since 0.1.0
 */
class InteractiveTypesPackage implements BlockPackageInterface
{
    private readonly Closure $now;

    /**
     * @param (Closure(): DateTimeImmutable)|null $now
     *   Tells the time for countdowns; the system clock when null.
     *
     * @since 0.1.0
     */
    public function __construct(?Closure $now = null)
    {
        $this->now = $now ?? static fn (): DateTimeImmutable => new DateTimeImmutable('now');
    }

    public function name(): string
    {
        return 'core/interactive-types';
    }

    public function title(): string
    {
        return 'Interactive';
    }

    public function description(): string
    {
        return 'Accordions, tabs, sliders, countdowns, progress bars, counters and modals.';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $type = fn (array $definition) => $registrar->type(
            BlockType::fromArray(['category' => 'interactive', ...$definition]),
        );
        $behaviour = fn (string $name, object $behaviour) => $registrar->behaviour(
            BlockName::fromString($name),
            $behaviour,
        );
        $text = fn (string $default, int $max, string $title): array => [
            'type' => 'string',
            'default' => $default,
            'maxLength' => $max,
            'title' => $title,
        ];
        $flag = fn (bool $default, string $title): array => [
            'type' => 'boolean',
            'default' => $default,
            'title' => $title,
        ];
        $ids = new IdBehaviour();

        $type([
            'name' => 'core/accordion',
            'title' => 'Accordion',
            'description' => 'Questions or sections that open when clicked.',
            'keywords' => ['faq', 'collapse', 'details'],
            'settings' => ['type' => 'object', 'properties' => ['single' => $flag(false, 'One open at a time')]],
            'children' => ['core/accordion-item'],
            'provides' => ['core/accordion-group' => 'group'],
            'examples' => [['single' => false]],
        ]);
        $behaviour('core/accordion', new AccordionBehaviour());

        $type([
            'name' => 'core/accordion-item',
            'title' => 'Accordion item',
            'description' => 'One part of an accordion: a title and what it opens to show.',
            'settings' => ['type' => 'object', 'properties' => [
                'title' => $text('Question', 200, 'Title'),
                'open' => $flag(false, 'Open at first'),
            ]],
            'parents' => ['core/accordion'],
            'children' => true,
            'uses' => ['core/accordion-group'],
            'examples' => [['title' => 'How long does it take?']],
        ]);
        $behaviour('core/accordion-item', new AccordionItemBehaviour());

        $type([
            'name' => 'core/tabs',
            'title' => 'Tabs',
            'description' => 'Panels shown one at a time, chosen by their labels.',
            'keywords' => ['tabbed', 'panels'],
            'children' => ['core/tab'],
            'examples' => [[]],
        ]);
        $behaviour('core/tabs', $ids);

        $type([
            'name' => 'core/tab',
            'title' => 'Tab',
            'description' => 'One panel of a set of tabs.',
            'settings' => ['type' => 'object', 'properties' => ['label' => $text('Tab', 80, 'Label')]],
            'parents' => ['core/tabs'],
            'children' => true,
            'examples' => [['label' => 'Overview']],
        ]);
        $behaviour('core/tab', $ids);

        $type([
            'name' => 'core/slider',
            'title' => 'Slider',
            'description' => 'Slides side by side, one in view at a time.',
            'keywords' => ['carousel', 'slideshow', 'gallery'],
            'settings' => ['type' => 'object', 'properties' => [
                'label' => [
                    ...$text('Slides', 120, 'Name'),
                    'description' => 'What the slides are, for screen readers.',
                ],
                'autoplay' => [
                    ...$flag(false, 'Move on by itself'),
                    'description' => 'It always has a Pause button, and stays still for visitors who ask for less '
                        . 'motion.',
                ],
                'interval' => [
                    'type' => 'integer',
                    'default' => 6,
                    'minimum' => 3,
                    'maximum' => 30,
                    'title' => 'Seconds per slide',
                ],
            ]],
            'children' => ['core/slide'],
            'examples' => [['label' => 'Our work']],
        ]);
        $behaviour('core/slider', $ids);

        $type([
            'name' => 'core/slide',
            'title' => 'Slide',
            'description' => 'One slide of a slider.',
            'settings' => ['type' => 'object', 'properties' => [
                'label' => [...$text('', 120, 'Name'), 'description' => 'Optional; otherwise it is "Slide 2 of 5".'],
            ]],
            'parents' => ['core/slider'],
            'children' => true,
            'examples' => [['label' => '']],
        ]);
        $behaviour('core/slide', $ids);

        $type([
            'name' => 'core/countdown',
            'title' => 'Countdown',
            'description' => 'The time left until a date, and what to say once it has passed.',
            'keywords' => ['timer', 'launch', 'deadline'],
            'settings' => ['type' => 'object', 'properties' => [
                'label' => $text('Starts in', 120, 'Label'),
                'target' => [
                    'type' => 'string',
                    'default' => '',
                    'pattern' => '^(\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])T([01]\d|2[0-3]):[0-5]\d)?$',
                    'title' => 'Date and time',
                    'description' => 'In the time zone below, such as 2026-12-31T23:59.',
                ],
                'timeZone' => [
                    'type' => 'string',
                    'default' => 'UTC',
                    'maxLength' => 64,
                    'pattern' => '^[A-Za-z]+(?:[/_+-][A-Za-z0-9]+)*$',
                    'title' => 'Time zone',
                    'description' => 'Such as Europe/London or America/New_York.',
                ],
                'after' => [
                    'type' => 'string',
                    'default' => 'It has started.',
                    'x-control' => 'rich-text',
                    'title' => 'Once it has passed',
                ],
            ]],
            'examples' => [['target' => '2099-01-01T00:00', 'timeZone' => 'UTC']],
        ]);
        $behaviour('core/countdown', new CountdownBehaviour($this->now));

        $type([
            'name' => 'core/progress',
            'title' => 'Progress',
            'description' => 'How far along something is, as a bar and a percentage.',
            'keywords' => ['bar', 'percent', 'goal'],
            'settings' => ['type' => 'object', 'properties' => [
                'label' => $text('Progress', 120, 'Label'),
                'value' => ['type' => 'number', 'default' => 40, 'minimum' => 0, 'title' => 'Value'],
                'max' => ['type' => 'number', 'default' => 100, 'minimum' => 1, 'title' => 'Out of'],
            ]],
            'examples' => [['label' => 'Funded', 'value' => 65, 'max' => 100]],
        ]);
        $behaviour('core/progress', new ProgressBehaviour());

        $type([
            'name' => 'core/counter',
            'title' => 'Counter',
            'description' => 'A number that counts up when it comes into view.',
            'keywords' => ['number', 'stat', 'figure'],
            'settings' => ['type' => 'object', 'properties' => [
                'from' => ['type' => 'number', 'default' => 0, 'title' => 'From'],
                'to' => ['type' => 'number', 'default' => 100, 'title' => 'To'],
                'prefix' => $text('', 10, 'Before the number'),
                'suffix' => $text('', 10, 'After the number'),
                'duration' => [
                    'type' => 'integer',
                    'default' => 2000,
                    'minimum' => 0,
                    'maximum' => 10000,
                    'title' => 'Milliseconds to count',
                ],
                'decimals' => [
                    'type' => 'integer',
                    'default' => 0,
                    'minimum' => 0,
                    'maximum' => 3,
                    'title' => 'Decimals',
                ],
            ]],
            'examples' => [['to' => 1200, 'suffix' => '+']],
        ]);
        $behaviour('core/counter', new CounterBehaviour());

        $type([
            'name' => 'core/modal',
            'title' => 'Modal',
            'description' => 'A button that opens a panel over the page.',
            'keywords' => ['popup', 'dialog', 'lightbox'],
            'settings' => ['type' => 'object', 'properties' => [
                'trigger' => $text('Open', 80, 'Button text'),
                'title' => $text('Details', 200, 'Panel title'),
            ]],
            'children' => true,
            'examples' => [['trigger' => 'Read more', 'title' => 'More about us']],
        ]);
        $behaviour('core/modal', $ids);
    }
}
