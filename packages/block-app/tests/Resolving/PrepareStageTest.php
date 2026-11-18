<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\PrepareStepInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

$field = fn () => type('acme/field', ['version' => 2, 'settings' => ['type' => 'object', 'required' => ['label'], 'properties' => [
    'label' => ['type' => 'string', 'maxLength' => 10, 'default' => 'Untitled'],
    'value' => ['type' => 'string', 'maxLength' => 5, 'default' => ''],
    'to' => ['type' => 'string', 'x-private' => true],
]]]);

test('prepare stops at an unknown type', function () {
    $out = stages()->prepare->run(block('a', 'gone/away', ['x' => 1]), Context::empty(), Mode::Front);
    expect($out->continue)->toBeFalse()->and(kinds($out->issues))->toBe([IssueKind::UnknownType])
        ->and($out->settings)->toBe(['x' => 1])->and($out->extensions)->toBe([]);
});

test('prepare migrates old settings, then overlays defaults', function () use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->migrations->add(BlockName::fromString('acme/field'), new class implements SettingsMigrationInterface {
            public function from(): int
            {
                return 1;
            }

            public function migrate(array $settings): array
            {
                return ['label' => $settings['title'] ?? 'x'];
            }
        });
    });
    $out = $s->prepare->run(block('a', 'acme/field', ['title' => 'Name']), Context::empty(), Mode::Front);
    expect($out->settings)->toBe(['label' => 'Name', 'value' => ''])->and($out->block->typeVersion)->toBe(2)
        ->and($out->block->settings)->toBe(['label' => 'Name'])->and($out->continue)->toBeTrue();
});

test('prepare fails when a migration step is missing or the stored version is newer', function (int $stored) use ($field) {
    $s = stages(fn ($s) => $s->types->add($field()));
    $out = $s->prepare->run(block('a', 'acme/field')->withTypeVersion($stored), Context::empty(), Mode::Front);
    expect($out->continue)->toBeFalse()->and(kinds($out->issues))->toBe([IssueKind::MigrationFailed]);
})->with([1, 3]);

test('prepare fills decorator defaults for applicable decorators only', function () use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->decorators->add(new Decorator('acme/vis', ['type' => 'object', 'properties' => ['hide' => ['type' => 'boolean', 'default' => false]]]));
        $s->decorators->add(new Decorator('acme/anim', ['type' => 'object', 'properties' => ['fx' => ['type' => 'string', 'default' => 'none']]], ['acme/box']));
    });
    $out = $s->prepare->run(block('a', 'acme/field')->withTypeVersion(2), Context::empty(), Mode::Front);
    expect($out->extensions)->toBe(['acme/vis' => ['hide' => false]]);
});

test('prepare leaves extensions with no applicable decorator as stored', function () use ($field) {
    $s = stages(fn ($s) => $s->types->add($field()));
    $block = block('a', 'acme/field')->withTypeVersion(2)->withExtension('acme/gone', ['k' => 1]);
    $out = $s->prepare->run($block, Context::empty(), Mode::Front);
    expect($out->extensions)->toBe(['acme/gone' => ['k' => 1]]);
});

test('prepare steps change settings and extensions but never the id, type or children', function () use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->steps->add(PrepareStepInterface::class, new class implements PrepareStepInterface {
            public function prepare(Block $block, BlockType $type, Context $context, Mode $mode): Block
            {
                return new Block('other', BlockName::fromString('acme/box'), 1, ['label' => 'Stepped'], ['acme/x' => ['on' => true]]);
            }
        });
        $s->steps->add(PrepareStepInterface::class, new class implements PrepareStepInterface {
            public function prepare(Block $block, BlockType $type, Context $context, Mode $mode): Block
            {
                expect($block->id)->toBe('a')->and($block->type->toString())->toBe('acme/field')
                    ->and($block->children)->toHaveCount(1);

                return $block->withSettings($block->settings + ['value' => 'v2']);
            }
        });
    });
    $out = $s->prepare->run(block('a', 'acme/field', ['label' => 'L'], block('c'))->withTypeVersion(2), Context::empty(), Mode::Front);
    expect($out->settings)->toBe(['label' => 'Stepped', 'value' => 'v2'])
        ->and($out->extensions)->toBe(['acme/x' => ['on' => true]])
        ->and($out->block->id)->toBe('a')->and($out->block->type->toString())->toBe('acme/field')
        ->and($out->block->settings)->toBe(['label' => 'L'])->and($out->block->children)->toHaveCount(1);
});
