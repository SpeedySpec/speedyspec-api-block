<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Registries\StepRegistry;
use SpeedySpec\Block\Domain\Contracts\DataSourceInterface;
use SpeedySpec\Block\Domain\Contracts\GateStepInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\DataSourceResult;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

$field = fn () => type('acme/field', ['version' => 2, 'settings' => ['type' => 'object', 'required' => ['label'], 'properties' => [
    'label' => ['type' => 'string', 'maxLength' => 10, 'default' => 'Untitled'],
    'value' => ['type' => 'string', 'maxLength' => 5, 'default' => ''],
    'to' => ['type' => 'string', 'x-private' => true],
]]]);

test('a block with no gate steps is shown', function () use ($field) {
    $s = stages(fn ($s) => $s->types->add($field()));
    $prepared = $s->prepare->run(block('a', 'acme/field')->withTypeVersion(2), Context::empty(), Mode::Front);
    $out = $s->gate->run($prepared, $field(), Context::empty(), Mode::Front);
    expect($out->continue)->toBeTrue()->and($out->hiddenOnFront)->toBeFalse();
});

test('a gate step hides a block on the front end and only marks it in the admin', function (Mode $mode, bool $continue, bool $hidden) use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->steps->add(GateStepInterface::class, new class implements GateStepInterface {
            public function shown(Block $block, BlockType $type, Context $context, Mode $mode): bool
            {
                return false;
            }
        });
    });
    $prepared = $s->prepare->run(block('a', 'acme/field')->withTypeVersion(2), Context::empty(), $mode);
    $out = $s->gate->run($prepared, $field(), Context::empty(), $mode);
    expect($out->continue)->toBe($continue)->and($out->hiddenOnFront)->toBe($hidden)->and($out->issues)->toBe([]);
})->with([[Mode::Front, false, false], [Mode::Admin, true, true]]);

test('a gate step sees the working extensions', function () use ($field) {
    $s = stages(function ($s) use ($field) {
        $s->types->add($field());
        $s->steps->add(GateStepInterface::class, new class implements GateStepInterface {
            public function shown(Block $block, BlockType $type, Context $context, Mode $mode): bool
            {
                return ($block->extensions['acme/vis']['hide'] ?? false) !== true;
            }
        });
    });
    $block = block('a', 'acme/field')->withTypeVersion(2)->withExtension('acme/vis', ['hide' => true]);
    $out = $s->gate->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    expect($out->continue)->toBeFalse();
});

test('a stopped block passes through later stages untouched and calls no data source', function () use ($field) {
    $counter = new class implements DataSourceInterface {
        public int $calls = 0;

        public function name(): string
        {
            return 'acme/count';
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
            $this->calls++;

            return DataSourceResult::value('x');
        }
    };
    $s = stages(function ($s) use ($field, $counter) {
        $s->types->add($field());
        $s->sources->add($counter);
        $s->steps->add(GateStepInterface::class, gateStep(false));
    });
    $block = block('a', 'acme/field', ['label' => 'L'])->withTypeVersion(2)
        ->withBinding(SettingPath::fromString('value'), Binding::to('acme/count'));
    $gated = $s->gate->run($s->prepare->run($block, Context::empty(), Mode::Front), $field(), Context::empty(), Mode::Front);
    $resolved = $s->resolve->run($gated, $field(), Context::empty(), Mode::Front);
    $validated = $s->validate->run($resolved, $field(), Context::empty(), Mode::Front);
    expect($resolved)->toBe($gated)->and($validated)->toBe($gated)->and($counter->calls)->toBe(0);
});

test('step registry orders by priority then insertion and rejects the wrong type', function () {
    $r = new StepRegistry();
    $a = gateStep(true);
    $b = gateStep(true);
    $c = gateStep(true);
    $r->add(GateStepInterface::class, $a, 20);
    $r->add(GateStepInterface::class, $b, 10);
    $r->add(GateStepInterface::class, $c, 10);
    expect($r->for(GateStepInterface::class))->toBe([$b, $c, $a])
        ->and(fn () => $r->add(GateStepInterface::class, new stdClass()))->toThrow(InvalidArgumentException::class);
});
