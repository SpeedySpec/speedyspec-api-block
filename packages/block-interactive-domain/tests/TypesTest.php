<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;

const INTERACTIVE_BLOCKS = [
    'core/accordion', 'core/accordion-item', 'core/tabs', 'core/tab', 'core/slider', 'core/slide',
    'core/countdown', 'core/progress', 'core/counter', 'core/modal',
];

function interactiveType(string $name): BlockType
{
    $type = interactiveContainer()->get(BlockTypeRegistryInterface::class)->get(BlockName::fromString($name));

    expect($type)->not->toBeNull();

    return $type;
}

function interactiveSettingsValid(string $name, array $settings): bool
{
    return interactiveContainer()->get(SchemaValidatorInterface::class)
        ->validate($settings, interactiveType($name)->settingsSchema)->isValid();
}

function holds(string $parent, string $child): bool
{
    return interactiveType($parent)->children->allows(BlockName::fromString($child));
}

test('every interactive block is in the interactive category with a title, description and example', function () {
    foreach (INTERACTIVE_BLOCKS as $name)
    {
        $type = interactiveType($name);

        expect($type->catalogue->category)->toBe('interactive')
            ->and($type->catalogue->title)->not->toBe('')
            ->and($type->catalogue->description)->not->toBe('')
            ->and($type->examples)->not->toBe([]);
    }
});

test('the blocks hold only what belongs in them', function () {
    expect(holds('core/accordion', 'core/accordion-item'))->toBeTrue()
        ->and(holds('core/accordion', 'core/tab'))->toBeFalse()
        ->and(holds('core/tabs', 'core/tab'))->toBeTrue()
        ->and(holds('core/tabs', 'core/slide'))->toBeFalse()
        ->and(holds('core/slider', 'core/slide'))->toBeTrue()
        ->and(holds('core/slider', 'core/tab'))->toBeFalse()
        ->and(holds('core/accordion-item', 'core/tabs'))->toBeTrue()
        ->and(holds('core/tab', 'core/tabs'))->toBeTrue()
        ->and(holds('core/slide', 'core/counter'))->toBeTrue()
        ->and(holds('core/modal', 'core/progress'))->toBeTrue();

    $parents = ['core/accordion-item' => 'core/accordion', 'core/tab' => 'core/tabs', 'core/slide' => 'core/slider'];

    foreach ($parents as $child => $parent)
    {
        expect(interactiveType($child)->parents->allows(BlockName::fromString($parent)))->toBeTrue()
            ->and(interactiveType($child)->parents->allows(null))->toBeFalse();
    }

    expect(interactiveType('core/accordion')->provides)->toBe(['core/accordion-group' => 'group'])
        ->and(interactiveType('core/accordion-item')->uses)->toBe(['core/accordion-group']);
});

test('defaults are fixed', function () {
    $defaults = fn (string $name): array => array_map(
        fn (array $property) => $property['default'] ?? null,
        interactiveType($name)->settingsSchema['properties'] ?? [],
    );

    expect($defaults('core/accordion'))->toBe(['single' => false])
        ->and($defaults('core/accordion-item'))->toBe(['title' => 'Question', 'open' => false])
        ->and($defaults('core/tabs'))->toBe([])
        ->and($defaults('core/tab'))->toBe(['label' => 'Tab'])
        ->and($defaults('core/slider'))->toBe(['label' => 'Slides', 'autoplay' => false, 'interval' => 6])
        ->and($defaults('core/slide'))->toBe(['label' => ''])
        ->and($defaults('core/countdown'))
        ->toBe(['label' => 'Starts in', 'target' => '', 'timeZone' => 'UTC', 'after' => 'It has started.'])
        ->and($defaults('core/progress'))->toBe(['label' => 'Progress', 'value' => 40, 'max' => 100])
        ->and($defaults('core/counter'))
        ->toBe(['from' => 0, 'to' => 100, 'prefix' => '', 'suffix' => '', 'duration' => 2000, 'decimals' => 0])
        ->and($defaults('core/modal'))->toBe(['trigger' => 'Open', 'title' => 'Details']);
});

test('the settings refuse what the blocks cannot use', function (string $name, array $settings) {
    expect(interactiveSettingsValid($name, $settings))->toBeFalse();
})->with([
    'too short an interval' => ['core/slider', ['interval' => 2]],
    'too long an interval' => ['core/slider', ['interval' => 31]],
    'too long a count' => ['core/counter', ['duration' => 10001]],
    'too many decimals' => ['core/counter', ['decimals' => 4]],
    'a thirteenth month' => ['core/countdown', ['target' => '2026-13-01T10:00']],
    'a date with no time' => ['core/countdown', ['target' => '2026-12-31']],
    'a zone that climbs out' => ['core/countdown', ['timeZone' => '../etc']],
    'no maximum' => ['core/progress', ['max' => 0]],
    'a negative value' => ['core/progress', ['value' => -1]],
]);
