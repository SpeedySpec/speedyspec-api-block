<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\DataSourceInterface;
use SpeedySpec\Block\Domain\Contracts\PrepareStepInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\DataSourceResult;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;
use SpeedySpec\Block\Infra\Memory\ArrayDataSource;

$field = fn () => type('acme/field', ['version' => 2, 'settings' => ['type' => 'object', 'required' => ['label'], 'properties' => [
    'label' => ['type' => 'string', 'maxLength' => 10, 'default' => 'Untitled'],
    'value' => ['type' => 'string', 'maxLength' => 5, 'default' => ''],
    'to' => ['type' => 'string', 'x-private' => true],
]]]);

$styled = fn () => type('acme/styled', ['settings' => ['type' => 'object', 'properties' => [
    'style' => ['type' => 'object', 'properties' => ['color' => ['type' => 'string', 'maxLength' => 5, 'default' => 'black']]],
]]]);

test('a bound value replaces the stored setting', function () use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->sources->add(new ArrayDataSource('acme/values', ['v' => 'abc']));
    });
    $block = block('a', 'acme/field', ['label' => 'L'])->withTypeVersion(2)
        ->withBinding(SettingPath::fromString('value'), Binding::to('acme/values', ['key' => 'v']));
    $out = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    expect($out->settings['value'])->toBe('abc')->and($out->bindingInfo['value'])->toBe(['source' => 'acme/values', 'used' => 'source']);
});

test('a rejected bound value falls back and never stops the block', function (array $stored, ?string $fallback, string $expected, string $used) use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->sources->add(new ArrayDataSource('acme/values', ['v' => 'far too long']));
    });
    $binding = Binding::to('acme/values', ['key' => 'v']);
    $block = block('a', 'acme/field', $stored)->withTypeVersion(2)
        ->withBinding(SettingPath::fromString('value'), null === $fallback ? $binding : $binding->withFallback($fallback));
    $out = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    expect($out->settings['value'])->toBe($expected)->and($out->bindingInfo['value']['used'])->toBe($used)
        ->and($out->continue)->toBeTrue()->and(kinds($out->issues))->toContain(IssueKind::InvalidBinding);
})->with([
    [['label' => 'L'], 'fb', 'fb', 'fallback'],
    [['label' => 'L', 'value' => 'kept'], null, 'kept', 'stored'],
    [['label' => 'L'], 'also too long', '', 'stored'],
]);

test('a binding can target a decorator setting through a namespaced path', function () use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->decorators->add(new Decorator('acme/vis', ['type' => 'object', 'properties' => ['hide' => ['type' => 'boolean', 'default' => false]]]));
        $s->sources->add(new ArrayDataSource('acme/values', ['h' => true]));
    });
    $block = block('a', 'acme/field', ['label' => 'L'])->withTypeVersion(2)
        ->withBinding(SettingPath::fromString('@acme/vis:hide'), Binding::to('acme/values', ['key' => 'h']));
    $out = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    expect($out->extensions['acme/vis']['hide'])->toBeTrue()->and($out->settings)->not->toHaveKey('hide');
});

test('a binding to a decorator that does not apply to the type is reported and skipped', function () use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->decorators->add(new Decorator('acme/anim', ['type' => 'object', 'properties' => ['fx' => ['type' => 'string']]], ['acme/box']));
        $s->sources->add(new ArrayDataSource('acme/values', ['f' => 'spin']));
    });
    $block = block('a', 'acme/field', ['label' => 'L'])->withTypeVersion(2)
        ->withBinding(SettingPath::fromString('@acme/anim:fx'), Binding::to('acme/values', ['key' => 'f']))
        ->withBinding(SettingPath::fromString('@acme/none:fx'), Binding::to('acme/values', ['key' => 'f']));
    $out = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    expect(kinds($out->issues))->toBe([IssueKind::InvalidBinding, IssueKind::InvalidBinding])
        ->and($out->extensions)->toBe([])->and($out->continue)->toBeTrue();
});

test('a private setting cannot be bound', function () use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->sources->add(new ArrayDataSource('acme/values', ['v' => 'attacker@example.test']));
    });
    $block = block('a', 'acme/field', ['label' => 'L', 'to' => 'owner@example.test'])->withTypeVersion(2)
        ->withBinding(SettingPath::fromString('to'), Binding::to('acme/values', ['key' => 'v']));
    $out = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    expect($out->settings['to'])->toBe('owner@example.test')->and(kinds($out->issues))->toBe([IssueKind::PrivateBinding]);
});

test('an unknown data source and an undeclared path are reported and skipped', function () use ($field) {
    $s = stages(fn ($s) => $s->types->add($field()));
    $block = block('a', 'acme/field', ['label' => 'L'])->withTypeVersion(2)
        ->withBinding(SettingPath::fromString('value'), Binding::to('nope/source'))
        ->withBinding(SettingPath::fromString('missing'), Binding::to('nope/source'));
    $out = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    expect(kinds($out->issues))->toBe([IssueKind::UnknownDataSource, IssueKind::InvalidBinding])->and($out->continue)->toBeTrue();
});

test('arguments that fail the source schema are reported and the source is treated as absent', function () use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->sources->add(new ArrayDataSource('acme/values', ['v' => 'abc']));
    });
    $block = block('a', 'acme/field', ['label' => 'L'])->withTypeVersion(2)
        ->withBinding(SettingPath::fromString('value'), Binding::to('acme/values', ['key' => 5])->withFallback('fb'));
    $out = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    expect(kinds($out->issues))->toBe([IssueKind::InvalidBinding])->and($out->settings['value'])->toBe('fb')
        ->and($out->bindingInfo['value'])->toBe(['source' => 'acme/values', 'used' => 'fallback']);
});

test('a data source that throws is reported and never escapes', function () use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->sources->add(new class implements DataSourceInterface {
            public function name(): string
            {
                return 'acme/broken';
            }

            public function argumentsSchema(): array
            {
                return ['type' => 'object'];
            }

            public function valueSchema(): array
            {
                return [];
            }

            public function resolve(array $arguments, Context $context, Mode $mode): DataSourceResult
            {
                throw new RuntimeException('down');
            }
        });
    });
    $block = block('a', 'acme/field', ['label' => 'L', 'value' => 'kept'])->withTypeVersion(2)
        ->withBinding(SettingPath::fromString('value'), Binding::to('acme/broken'));
    $out = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    expect(kinds($out->issues))->toBe([IssueKind::InvalidBinding])->and($out->issues[0]->message)->toContain('RuntimeException')
        ->and($out->settings['value'])->toBe('kept')->and($out->bindingInfo['value']['used'])->toBe('stored');
});

test('a binding writes a nested path, creating the parents it needs', function () use ($styled) {
    $s = stages(function ($s) use ($styled) {
        $s->types->add($styled());
        $s->sources->add(new ArrayDataSource('acme/values', ['c' => 'red']));
    });
    $block = block('a', 'acme/styled')
        ->withBinding(SettingPath::fromString('style.color'), Binding::to('acme/values', ['key' => 'c']));
    $out = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $styled(), Context::empty(), Mode::Front);
    expect($out->settings)->toBe(['style' => ['color' => 'red']])->and($out->issues)->toBe([]);
});

test('the schema default is used when the working value was removed and every other candidate fails', function () use ($styled) {
    $s = stages(function ($s) use ($styled) {
        $s->types->add($styled());
        $s->sources->add(new ArrayDataSource('acme/values', ['c' => 'far too long']));
        $s->steps->add(PrepareStepInterface::class, new class implements PrepareStepInterface {
            public function prepare(Block $block, BlockType $type, Context $context, Mode $mode): Block
            {
                return $block->withSettings([]);
            }
        });
    });
    $block = block('a', 'acme/styled', ['style' => ['color' => 'blue']])
        ->withBinding(SettingPath::fromString('style.color'), Binding::to('acme/values', ['key' => 'c']));
    $out = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $styled(), Context::empty(), Mode::Front);
    expect($out->settings)->toBe(['style' => ['color' => 'black']])
        ->and($out->bindingInfo['style.color'])->toBe(['source' => 'acme/values', 'used' => 'default'])
        ->and(kinds($out->issues))->toBe([IssueKind::InvalidBinding]);
});

test('no candidate validating leaves the setting alone and records none', function () {
    $short = type('acme/short', ['settings' => ['type' => 'object', 'properties' => ['code' => ['type' => 'string', 'maxLength' => 2]]]]);
    $s = stages(function ($s) use ($short) {
        $s->types->add($short);
        $s->sources->add(new ArrayDataSource('acme/values', ['v' => 'long']));
    });
    $block = block('a', 'acme/short', ['code' => 'stored'])
        ->withBinding(SettingPath::fromString('code'), Binding::to('acme/values', ['key' => 'v'])->withFallback('fallback'));
    $out = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $short, Context::empty(), Mode::Front);
    expect($out->settings['code'])->toBe('stored')->and($out->bindingInfo['code']['used'])->toBe('none')
        ->and(kinds($out->issues))->toBe([IssueKind::InvalidBinding, IssueKind::InvalidBinding, IssueKind::InvalidBinding]);
});
