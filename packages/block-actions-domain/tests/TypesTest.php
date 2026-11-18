<?php

declare(strict_types=1);

use SpeedySpec\Block\Actions\Domain\SocialServices;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;

const ACTION_BLOCKS = [
    'core/button', 'core/buttons', 'core/call-to-action', 'core/testimonial', 'core/pricing-table',
    'core/pricing-plan', 'core/rating', 'core/social-links', 'core/social-link',
];

function actionType(string $name): BlockType
{
    $type = actionsContainer()->get(BlockTypeRegistryInterface::class)->get(BlockName::fromString($name));

    expect($type)->not->toBeNull();

    return $type;
}

function actionSettingsValid(string $name, array $settings): bool
{
    return actionsContainer()->get(SchemaValidatorInterface::class)
        ->validate($settings, actionType($name)->settingsSchema)->isValid();
}

function allows(BlockType $type, string $child): bool
{
    return $type->children->allows(BlockName::fromString($child));
}

test('every actions block is in the actions category with a title, description and example', function () {
    foreach (ACTION_BLOCKS as $name)
    {
        $type = actionType($name);

        expect($type->catalogue->category)->toBe('actions')
            ->and($type->catalogue->title)->not->toBe('')
            ->and($type->catalogue->description)->not->toBe('')
            ->and($type->examples)->not->toBe([]);
    }
});

test('the blocks hold only what belongs in them', function () {
    expect(allows(actionType('core/buttons'), 'core/button'))->toBeTrue()
        ->and(allows(actionType('core/buttons'), 'core/rating'))->toBeFalse()
        ->and(allows(actionType('core/call-to-action'), 'core/buttons'))->toBeTrue()
        ->and(allows(actionType('core/call-to-action'), 'core/button'))->toBeTrue()
        ->and(allows(actionType('core/pricing-table'), 'core/pricing-plan'))->toBeTrue()
        ->and(allows(actionType('core/pricing-table'), 'core/button'))->toBeFalse()
        ->and(allows(actionType('core/pricing-plan'), 'core/button'))->toBeTrue()
        ->and(allows(actionType('core/pricing-plan'), 'core/buttons'))->toBeTrue()
        ->and(allows(actionType('core/social-links'), 'core/social-link'))->toBeTrue()
        ->and(actionType('core/pricing-plan')->parents->allows(BlockName::fromString('core/pricing-table')))->toBeTrue()
        ->and(actionType('core/pricing-plan')->parents->allows(null))->toBeFalse()
        ->and(actionType('core/social-link')->parents->allows(BlockName::fromString('core/social-links')))->toBeTrue()
        ->and(actionType('core/social-link')->parents->allows(null))->toBeFalse();
});

test('defaults are fixed', function () {
    $defaults = fn (string $name): array => array_map(
        fn (array $property) => $property['default'] ?? null,
        actionType($name)->settingsSchema['properties'],
    );

    expect($defaults('core/button'))
        ->toBe(['text' => 'Learn more', 'link' => '', 'newTab' => false, 'variant' => 'primary', 'size' => 'medium'])
        ->and($defaults('core/buttons'))->toBe(['align' => 'start'])
        ->and($defaults('core/call-to-action'))->toBe(['heading' => 'Ready to start?', 'level' => 2, 'text' => ''])
        ->and($defaults('core/testimonial'))->toBe(['quote' => '', 'name' => '', 'role' => '', 'media' => ''])
        ->and($defaults('core/pricing-table'))->toBe(['columns' => 3])
        ->and($defaults('core/pricing-plan'))->toBe([
            'name' => 'Plan',
            'price' => '$0',
            'period' => 'per month',
            'description' => '',
            'features' => ['First feature', 'Second feature'],
            'highlighted' => false,
            'badge' => '',
        ])
        ->and($defaults('core/rating'))->toBe(['value' => 5, 'outOf' => 5, 'label' => ''])
        ->and($defaults('core/social-links'))->toBe(['size' => 'medium'])
        ->and($defaults('core/social-link'))->toBe(['service' => 'website', 'url' => '']);
});

test('the features are edited as a list and the photo is chosen from images', function () {
    $features = actionType('core/pricing-plan')->settingsSchema['properties']['features'];
    $photo = actionType('core/testimonial')->settingsSchema['properties']['media'];

    expect($features['x-control'])->toBe('list')
        ->and($photo['x-control'])->toBe('media')
        ->and($photo['x-media-kind'])->toBe('image')
        ->and(actionType('core/social-link')->settingsSchema['properties']['service']['enum'])
        ->toBe(SocialServices::NAMES);
});

test('the settings refuse what the blocks cannot use', function (string $name, array $settings) {
    expect(actionSettingsValid($name, $settings))->toBeFalse();
})->with([
    'too many features' => ['core/pricing-plan', ['features' => array_fill(0, 31, 'x')]],
    'a long feature' => ['core/pricing-plan', ['features' => [str_repeat('a', 201)]]],
    'a variant not in the list' => ['core/button', ['variant' => 'giant']],
    'a size not in the list' => ['core/button', ['size' => 'huge']],
    'no scale' => ['core/rating', ['outOf' => 0]],
    'too long a scale' => ['core/rating', ['outOf' => 11]],
    'a negative rating' => ['core/rating', ['value' => -1]],
    'a service not in the list' => ['core/social-link', ['service' => 'myspace']],
    'a heading level of 1' => ['core/call-to-action', ['level' => 1]],
    'five plan columns' => ['core/pricing-table', ['columns' => 5]],
]);
