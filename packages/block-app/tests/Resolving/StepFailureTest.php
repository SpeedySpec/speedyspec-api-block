<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\DataSourceInterface;
use SpeedySpec\Block\Domain\Contracts\GateStepInterface;
use SpeedySpec\Block\Domain\Contracts\PrepareStepInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\ValidateStepInterface;
use SpeedySpec\Block\Domain\Contracts\ViewStepInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\DataSourceResult;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

/**
 * Builds a step of the given contract whose only method throws a LogicException.
 */
function throwingStep(string $contract): object
{
    return match ($contract)
    {
        PrepareStepInterface::class => new class implements PrepareStepInterface {
            public function prepare(Block $block, BlockType $type, Context $context, Mode $mode): Block
            {
                throw new LogicException('prepare');
            }
        },
        GateStepInterface::class => new class implements GateStepInterface {
            public function shown(Block $block, BlockType $type, Context $context, Mode $mode): bool
            {
                throw new LogicException('gate');
            }
        },
        ValidateStepInterface::class => new class implements ValidateStepInterface {
            public function validate(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): array
            {
                throw new LogicException('validate');
            }
        },
        ViewStepInterface::class => new class implements ViewStepInterface {
            public function extend(
                BlockView $view,
                Block $block,
                BlockType $type,
                array $extensions,
                Context $context,
                Mode $mode,
            ): BlockView
            {
                throw new LogicException('view');
            }
        },
    };
}

test('a migration that throws on stored content is recorded and stops only its block', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/form', ['version' => 2, 'settings' => ['type' => 'object', 'properties' => [
            'to' => ['type' => 'string']]]]));
        types($c)->add(type('acme/box'));
        $c->get(SettingsMigrationRegistryInterface::class)->add(
            BlockName::fromString('acme/form'),
            new class implements SettingsMigrationInterface {
                public function from(): int
                {
                    return 1;
                }

                public function migrate(array $settings): array
                {
                    return ['to' => strtolower($settings['to'])];
                }
            },
        );
    });
    $result = $resolver->resolve(doc(block('f', 'acme/form', ['to' => ['not', 'a', 'string']]), block('b')), Mode::Front);
    expect(idsAndKinds($result->issues))->toBe([['f', IssueKind::MigrationFailed]])
        ->and($result->issues[0]->message)->toContain('TypeError')
        ->and($result->find('f')->isResolved())->toBeFalse()
        ->and($result->find('b')->isResolved())->toBeTrue();
});

test('a prepare step that throws is recorded and stops its block', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box'));
        steps($c)->add(PrepareStepInterface::class, throwingStep(PrepareStepInterface::class));
    });
    $result = $resolver->resolve(doc(block('a')), Mode::Front);
    expect(idsAndKinds($result->issues))->toBe([['a', IssueKind::StepFailed]])
        ->and($result->issues[0]->message)->toContain('LogicException')
        ->and($result->issues[0]->message)->toContain('PrepareStepInterface@anonymous')
        ->and($result->find('a')->isResolved())->toBeFalse();
});

test('a gate step that throws hides its block on the front end', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box'));
        steps($c)->add(GateStepInterface::class, throwingStep(GateStepInterface::class));
    });
    $result = $resolver->resolve(doc(block('a')), Mode::Front);
    expect(idsAndKinds($result->issues))->toBe([['a', IssueKind::StepFailed]])
        ->and($result->issues[0]->message)->toContain('LogicException')
        ->and($result->find('a')->isResolved())->toBeFalse();
});

test('a gate step that throws marks its block hidden in the admin and lets it continue', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box'));
        steps($c)->add(GateStepInterface::class, throwingStep(GateStepInterface::class));
    });
    $resolved = $resolver->resolve(doc(block('a')), Mode::Admin)->find('a');
    expect($resolved->isResolved())->toBeTrue()
        ->and($resolved->admin->hiddenOnFront)->toBeTrue()
        ->and(kinds($resolved->admin->issues))->toBe([IssueKind::StepFailed]);
});

test('a data source whose arguments schema throws while resolving is recorded', function () {
    $source = new class implements DataSourceInterface {
        private int $calls = 0;

        public function name(): string
        {
            return 'acme/flaky';
        }

        public function argumentsSchema(): array
        {
            if ($this->calls++ > 0)
            {
                throw new RuntimeException('schema');
            }

            return ['type' => 'object'];
        }

        public function valueSchema(): array
        {
            return [];
        }

        public function resolve(array $arguments, Context $context, Mode $mode): DataSourceResult
        {
            return DataSourceResult::value('x');
        }
    };
    [$resolver] = resolver(function ($c) use ($source) {
        types($c)->add(type('acme/box', ['settings' => ['type' => 'object', 'properties' => ['n' => ['type' => 'string']]]]));
        sources($c)->add($source);
    });
    $b = block('a', 'acme/box', ['n' => 'kept'])->withBinding(SettingPath::fromString('n'), Binding::to('acme/flaky'));
    $result = $resolver->resolve(doc($b), Mode::Front);
    expect(idsAndKinds($result->issues))->toBe([['a', IssueKind::InvalidBinding]])
        ->and($result->issues[0]->message)->toContain('RuntimeException')
        ->and($result->find('a')->settings)->toBe(['n' => 'kept']);
});

test('a validate step that throws is recorded and the block continues', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box'));
        steps($c)->add(ValidateStepInterface::class, throwingStep(ValidateStepInterface::class));
    });
    $result = $resolver->resolve(doc(block('a')), Mode::Front);
    expect(idsAndKinds($result->issues))->toBe([['a', IssueKind::StepFailed]])
        ->and($result->issues[0]->message)->toContain('LogicException')
        ->and($result->find('a')->isResolved())->toBeTrue();
});

test('a validate step returning values that are not issues has them ignored', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box'));
        steps($c)->add(ValidateStepInterface::class, new class implements ValidateStepInterface {
            public function validate(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): array
            {
                return ['nope', new Issue($block->id, IssueKind::InvalidSettings, 'kept'), 3];
            }
        });
    });
    $result = $resolver->resolve(doc(block('a')), Mode::Front);
    expect(idsAndKinds($result->issues))->toBe([['a', IssueKind::InvalidSettings]])
        ->and($result->find('a')->isResolved())->toBeTrue();
});

test('a view step that throws is recorded and only its contribution is skipped', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box'));
        steps($c)->add(ViewStepInterface::class, throwingStep(ViewStepInterface::class), 5);
        steps($c)->add(ViewStepInterface::class, new class implements ViewStepInterface {
            public function extend(
                BlockView $view,
                Block $block,
                BlockType $type,
                array $extensions,
                Context $context,
                Mode $mode,
            ): BlockView
            {
                return new BlockView([], ['acme/vis' => ['on' => true]]);
            }
        }, 10);
    });
    $result = $resolver->resolve(doc(block('a', 'acme/box', ['n' => 1])), Mode::Front);
    $view = $result->find('a')->view;
    expect(idsAndKinds($result->issues))->toBe([['a', IssueKind::StepFailed]])
        ->and($result->issues[0]->message)->toContain('LogicException')
        ->and($view->values)->toBe(['n' => 1])
        ->and($view->extensions)->toBe(['acme/vis' => ['on' => true]]);
});
