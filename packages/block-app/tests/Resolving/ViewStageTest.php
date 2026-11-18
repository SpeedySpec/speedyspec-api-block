<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Registries\BlockBehaviourRegistry;
use SpeedySpec\Block\App\Registries\StepRegistry;
use SpeedySpec\Block\App\Resolving\StageOutcome;
use SpeedySpec\Block\App\Resolving\ViewStage;
use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Contracts\ViewStepInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\AdminContext;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Builds a view stage over fresh registries; $setup receives the behaviour and step registries.
 */
function viewStage(?callable $setup = null): ViewStage
{
    $behaviours = new BlockBehaviourRegistry();
    $steps = new StepRegistry();

    if ($setup !== null)
    {
        $setup($behaviours, $steps);
    }

    $validator = new SubsetSchemaValidator();

    return new ViewStage(
        $behaviours,
        $validator,
        $steps,
        new \SpeedySpec\Block\App\Registries\DecoratorRegistry($validator),
    );
}

/**
 * Builds a behaviour from a closure, which receives the type, settings, context, mode and block.
 */
function viewBehaviour(Closure $view): BlockBehaviourInterface
{
    return new class($view) implements BlockBehaviourInterface {
        public function __construct(private readonly Closure $view)
        {
        }

        public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
        {
            return ($this->view)($type, $settings, $context, $mode, $block);
        }
    };
}

/**
 * Builds a validated outcome for a block with the given working settings.
 */
function validated(array $settings, array $issues = [], bool $hidden = false, array $info = []): StageOutcome
{
    return new StageOutcome(block('a', 'acme/box'), $settings, [], $issues, true, $hidden, $info);
}

$secret = fn () => type('acme/box', ['settings' => ['type' => 'object', 'properties' => [
    'n' => ['type' => 'integer'], 'to' => ['type' => 'string', 'x-private' => true]]]]);

test('with no behaviour the view values are the settings, without private ones on the front end', function () use ($secret) {
    $outcome = validated(['n' => 1, 'to' => 'me@example.test']);
    [$front, $frontIssues] = viewStage()->run($outcome, $secret(), Context::empty(), Mode::Front);
    [$admin] = viewStage()->run($outcome, $secret(), Context::empty(), Mode::Admin);
    expect($front->values)->toBe(['n' => 1])->and($front->admin)->toBeNull()->and($frontIssues)->toBe([])
        ->and($admin->values)->toBe(['n' => 1, 'to' => 'me@example.test'])
        ->and($admin->admin)->toBeNull();
});

test('a behaviour view is not filtered for private settings', function () use ($secret) {
    $stage = viewStage(function ($behaviours) {
        $behaviours->set(BlockName::fromString('acme/box'), viewBehaviour(fn ($t, array $s) => new BlockView($s)));
    });
    [$view] = $stage->run(validated(['n' => 1, 'to' => 'me@example.test']), $secret(), Context::empty(), Mode::Front);
    expect($view->values)->toBe(['n' => 1, 'to' => 'me@example.test']);
});

test('the behaviour admin values are handed back in admin mode and the view keeps no admin context', function () {
    $stage = viewStage(function ($behaviours) {
        $behaviours->set(BlockName::fromString('acme/box'), viewBehaviour(
            fn () => new BlockView([], admin: new AdminContext(['hint' => 'h'], [], ['ignored' => []], false))
        ));
    });
    $earlier = new Issue('a', IssueKind::InvalidBinding, 'bad');
    $info = ['n' => ['source' => 'acme/values', 'used' => 'fallback']];
    [$view, $issues, $values] = $stage->run(validated([], [$earlier], true, $info), type('acme/box'), Context::empty(), Mode::Admin);
    [$front, , $frontValues] = $stage->run(validated([]), type('acme/box'), Context::empty(), Mode::Front);
    expect($view->admin)->toBeNull()->and($values)->toBe(['hint' => 'h'])->and($issues)->toBe([$earlier])
        ->and($front->admin)->toBeNull()->and($frontValues)->toBe([]);
});

test('a behaviour receives the working block with its id and children', function () {
    $seen = null;
    $stage = viewStage(function ($behaviours) use (&$seen) {
        $behaviours->set(BlockName::fromString('acme/box'), viewBehaviour(function ($t, $s, $c, $m, Block $block) use (&$seen) {
            $seen = [$block->id, array_map(fn (Block $child) => $child->id, $block->children), $block->settings];

            return new BlockView([]);
        }));
    });
    $outcome = new StageOutcome(block('a', 'acme/box', [], block('c1'), block('c2')), ['n' => 2], [], [], true);
    $stage->run($outcome, type('acme/box'), Context::empty(), Mode::Front);
    expect($seen)->toBe(['a', ['c1', 'c2'], ['n' => 2]]);
});

test('a behaviour that throws yields no view and an issue naming the exception', function () {
    $stage = viewStage(function ($behaviours) {
        $behaviours->set(BlockName::fromString('acme/box'), viewBehaviour(fn () => throw new LogicException('boom')));
    });
    [$view, $issues] = $stage->run(validated([]), type('acme/box'), Context::empty(), Mode::Front);
    expect($view)->toBeNull()->and(kinds($issues))->toBe([IssueKind::InvalidView])
        ->and($issues[0]->blockId)->toBe('a')
        ->and($issues[0]->message)->toContain('LogicException');
});

test('a view failing its schema records one issue per failing rule with its path', function () {
    $type = type('acme/box', ['view' => ['type' => 'object', 'required' => ['total'],
        'properties' => ['total' => ['type' => 'integer'], 'label' => ['type' => 'string']]]]);
    [$view, $issues] = viewStage()->run(validated(['label' => 3]), $type, Context::empty(), Mode::Front);
    expect($view)->toBeNull()->and(kinds($issues))->toBe([IssueKind::InvalidView, IssueKind::InvalidView])
        ->and(array_map(fn (Issue $i) => $i->path, $issues))->toContain('label');
});

test('child passes that are not current items yield no view', function () {
    $stage = viewStage(function ($behaviours) {
        $behaviours->set(BlockName::fromString('acme/box'), viewBehaviour(fn () => new BlockView([], childPasses: ['x'])));
    });
    [$view, $issues] = $stage->run(validated([]), type('acme/box'), Context::empty(), Mode::Front);
    expect($view)->toBeNull()->and(kinds($issues))->toBe([IssueKind::InvalidView]);
});

test('view steps run in registry order and the later step wins per namespace', function () {
    $step = fn (array $extensions) => new class($extensions) implements ViewStepInterface {
        public function __construct(private readonly array $extensions)
        {
        }

        public function extend(
            BlockView $view,
            Block $block,
            BlockType $type,
            array $extensions,
            Context $context,
            Mode $mode,
        ): BlockView
        {
            return new BlockView([], $this->extensions, new AdminContext(['x' => 1]), []);
        }
    };
    $stage = viewStage(function ($behaviours, $steps) use ($step) {
        $behaviours->set(BlockName::fromString('acme/box'), viewBehaviour(
            fn () => new BlockView(['n' => 1], ['acme/own' => ['k' => 1], 'acme/vis' => ['class' => 'a']])
        ));
        $steps->add(ViewStepInterface::class, $step(['acme/vis' => ['class' => 'c']]), 20);
        $steps->add(ViewStepInterface::class, $step(['acme/vis' => ['class' => 'b'], 'acme/b' => ['on' => true]]), 5);
    });
    [$view] = $stage->run(validated([]), type('acme/box'), Context::empty(), Mode::Front);
    expect($view->values)->toBe(['n' => 1])->and($view->admin)->toBeNull()->and($view->childPasses)->toBeNull()
        ->and($view->extensions)->toBe(['acme/own' => ['k' => 1], 'acme/vis' => ['class' => 'c'], 'acme/b' => ['on' => true]]);
});

test('a stopped outcome yields no view and keeps its issues', function () {
    $issue = new Issue('a', IssueKind::InvalidSettings, 'bad');
    $outcome = validated([], [$issue])->with(continue: false);
    expect(viewStage()->run($outcome, type('acme/box'), Context::empty(), Mode::Front))->toBe([null, [$issue], []]);
});

test('a decorator extension that fails its view schema is dropped and reported', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box'));
        $c->get(\SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface::class)->add(
            new \SpeedySpec\Block\Domain\ValueObjects\Decorator(
                'acme/fx',
                ['type' => 'object'],
                null,
                ['type' => 'object', 'required' => ['class'], 'properties' => ['class' => ['type' => 'string']]],
            )
        );
        steps($c)->add(
            \SpeedySpec\Block\Domain\Contracts\ViewStepInterface::class,
            viewStep(['acme/fx' => ['class' => 5], 'acme/other' => ['kept' => true]]),
        );
    });
    $result = $resolver->resolve(doc(block('a', 'acme/box')), Mode::Front);

    expect($result->find('a')->view->extensions)->toBe(['acme/other' => ['kept' => true]])
        ->and($result->issues[0]->kind)->toBe(IssueKind::InvalidView)
        ->and($result->issues[0]->path)->toBe('@acme/fx:class');
});

test('a decorator extension that passes its view schema is kept', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box'));
        $c->get(\SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface::class)->add(
            new \SpeedySpec\Block\Domain\ValueObjects\Decorator(
                'acme/fx',
                ['type' => 'object'],
                null,
                ['type' => 'object', 'properties' => ['class' => ['type' => 'string']]],
            )
        );
        steps($c)->add(\SpeedySpec\Block\Domain\Contracts\ViewStepInterface::class, viewStep(['acme/fx' => ['class' => 'fade']]));
    });
    $result = $resolver->resolve(doc(block('a', 'acme/box')), Mode::Front);

    expect($result->find('a')->view->extensions)->toBe(['acme/fx' => ['class' => 'fade']])->and($result->hasIssues())->toBeFalse();
});
