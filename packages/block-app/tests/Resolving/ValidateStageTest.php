<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Resolving\StageOutcome;
use SpeedySpec\Block\Domain\Contracts\ValidateStepInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;
use SpeedySpec\Block\Infra\Memory\ArrayDataSource;

$field = fn () => type('acme/field', ['version' => 2, 'settings' => ['type' => 'object', 'required' => ['label'], 'properties' => [
    'label' => ['type' => 'string', 'maxLength' => 10, 'default' => 'Untitled'],
    'value' => ['type' => 'string', 'maxLength' => 5, 'default' => ''],
    'to' => ['type' => 'string', 'x-private' => true],
]]]);

$issueStep = fn (array &$calls) => new class($calls) implements ValidateStepInterface {
    public function __construct(private array &$calls)
    {
    }

    public function validate(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): array
    {
        $this->calls[] = $settings;

        return [new Issue($block->id, IssueKind::InvalidSettings, 'from a step', 'value')];
    }
};

test('valid settings keep the block going', function () use ($field) {
    $s = stages(fn ($s) => $s->types->add($field()));
    $block = block('a', 'acme/field', ['label' => 'L'])->withTypeVersion(2);
    $resolved = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    $out = $s->validate->run($resolved, $field(), Context::empty(), Mode::Front);
    expect($out->continue)->toBeTrue()->and($out->issues)->toBe([]);
});

test('invalid settings stop the block and name the path', function () use ($field) {
    $s = stages(fn ($s) => $s->types->add($field()));
    $block = block('a', 'acme/field', ['label' => 'this label is far too long'])->withTypeVersion(2);
    $resolved = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    $out = $s->validate->run($resolved, $field(), Context::empty(), Mode::Front);
    expect($out->continue)->toBeFalse()->and($out->issues[0]->kind)->toBe(IssueKind::InvalidSettings)->and($out->issues[0]->path)->toBe('label');
});

test('an invalid extension stops the block with a namespaced path and skips validate steps', function () use ($field, $issueStep) {
    $calls = [];
    $s = stages(function ($s) use ($field, $issueStep, &$calls) {
        $s->types->add($field());
        $s->decorators->add(new Decorator('acme/vis', ['type' => 'object', 'properties' => ['hide' => ['type' => 'boolean', 'default' => false]]]));
        $s->steps->add(ValidateStepInterface::class, $issueStep($calls));
    });
    $block = block('a', 'acme/field', ['label' => 'L'])->withTypeVersion(2)->withExtension('acme/vis', ['hide' => 'yes']);
    $resolved = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    $out = $s->validate->run($resolved, $field(), Context::empty(), Mode::Front);
    expect($out->continue)->toBeFalse()->and(kinds($out->issues))->toBe([IssueKind::InvalidSettings])
        ->and($out->issues[0]->path)->toBe('@acme/vis:hide')->and($calls)->toBe([]);
});

test('a validate step appends its issues without stopping the block', function () use ($field, $issueStep) {
    $calls = [];
    $s = stages(function ($s) use ($field, $issueStep, &$calls) {
        $s->types->add($field());
        $s->steps->add(ValidateStepInterface::class, $issueStep($calls));
    });
    $block = block('a', 'acme/field', ['label' => 'L'])->withTypeVersion(2);
    $resolved = $s->resolve->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    $out = $s->validate->run($resolved, $field(), Context::empty(), Mode::Front);
    expect($out->continue)->toBeTrue()->and(kinds($out->issues))->toBe([IssueKind::InvalidSettings])
        ->and($out->issues[0]->message)->toBe('from a step')->and($calls)->toBe([['label' => 'L', 'value' => '']]);
});

test('issues accumulate in order across prepare, resolve and validate', function () use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->sources->add(new ArrayDataSource('acme/values', ['v' => 'far too long']));
    });
    $block = block('a', 'acme/field', ['label' => 'this label is far too long'])->withTypeVersion(2)
        ->withBinding(SettingPath::fromString('value'), Binding::to('acme/values', ['key' => 'v']));
    $earlier = new Issue('a', IssueKind::UnknownDataSource, 'seeded by an earlier stage');
    $prepared = $s->prepare->run($block, Context::empty(), Mode::Front);
    $prepared = $prepared->with(issues: [$earlier]);
    $resolved = $s->resolve->run($prepared, $field(), Context::empty(), Mode::Front);
    $out = $s->validate->run($resolved, $field(), Context::empty(), Mode::Front);
    expect($out->issues[0])->toBe($earlier)
        ->and(kinds($out->issues))->toBe([IssueKind::UnknownDataSource, IssueKind::InvalidBinding, IssueKind::InvalidSettings]);
});

test('a settings error at the schema root has no path', function () {
    $type = type('acme/box', ['settings' => ['type' => 'object']]);
    $outcome = new StageOutcome(block('a'), ['x', 'y'], [], [], true);
    $out = stages(fn ($s) => $s->types->add($type))->validate->run($outcome, $type, Context::empty(), Mode::Front);
    expect(kinds($out->issues))->toBe([IssueKind::InvalidSettings])->and($out->issues[0]->path)->toBeNull();
});
