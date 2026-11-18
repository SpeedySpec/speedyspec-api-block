<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Resolving\DocumentResolver;
use SpeedySpec\Block\App\Resolving\GateStage;
use SpeedySpec\Block\App\Resolving\PrepareStage;
use SpeedySpec\Block\App\Resolving\ResolveStage;
use SpeedySpec\Block\App\Resolving\ValidateStage;
use SpeedySpec\Block\App\Resolving\ViewStage;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Contracts\GateStepInterface;
use SpeedySpec\Block\Domain\Contracts\ViewStepInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\AdminContext;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\CurrentItem;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;
use SpeedySpec\Block\Infra\Memory\ArrayDataSource;

$form = fn () => type('acme/form', ['children' => ['acme/field'], 'provides' => ['acme/form-id' => 'formId'],
    'settings' => ['type' => 'object', 'properties' => [
        'formId' => ['type' => 'string', 'default' => 'f1'], 'to' => ['type' => 'string', 'x-private' => true, 'default' => 'owner@example.test']]]]);
$field = fn () => type('acme/field', ['parents' => ['acme/form'], 'uses' => ['acme/form-id'],
    'settings' => ['type' => 'object', 'properties' => ['key' => ['type' => 'string'], 'required' => ['type' => 'boolean', 'default' => false]]]]);

/**
 * Builds a behaviour that always returns the same view.
 */
function fixedView(BlockView $view): BlockBehaviourInterface
{
    return new class($view) implements BlockBehaviourInterface {
        public function __construct(private readonly BlockView $view)
        {
        }

        public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
        {
            return $this->view;
        }
    };
}

test('resolves a form and its fields without drawing anything', function () use ($form, $field) {
    [$resolver, $c] = resolver(function ($c) use ($form, $field) { types($c)->add($form()); types($c)->add($field()); });
    $result = $resolver->resolve(doc(block('f', 'acme/form', [], block('n', 'acme/field', ['key' => 'name']))), Mode::Front);
    expect($result->hasIssues())->toBeFalse()
        ->and($result->find('n')->settings)->toBe(['key' => 'name', 'required' => false])
        ->and($result->find('n')->context->get('acme/form-id'))->toBe('f1');
});

test('a private setting is absent from a front view and present in an admin view', function () use ($form, $field) {
    [$resolver] = resolver(function ($c) use ($form, $field) { types($c)->add($form()); types($c)->add($field()); });
    $document = doc(block('f', 'acme/form'));
    expect($resolver->resolve($document, Mode::Front)->find('f')->view->values)->toBe(['formId' => 'f1'])
        ->and($resolver->resolve($document, Mode::Front)->find('f')->admin)->toBeNull()
        ->and($resolver->resolve($document, Mode::Admin)->find('f')->view->values['to'])->toBe('owner@example.test');
});

test('a behaviour sees only the context keys its type uses', function () use ($form, $field) {
    $seen = null;
    [$resolver] = resolver(function ($c) use ($form, $field, &$seen) {
        types($c)->add($form()); types($c)->add($field());
        behaviours($c)->set(BlockName::fromString('acme/field'), new class($seen) implements BlockBehaviourInterface {
            public function __construct(private mixed &$seen)
            {
            }

            public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
            {
                $this->seen = $context->all();
                return new BlockView(['key' => $settings['key']], admin: new AdminContext(['hint' => 'h']));
            }
        });
    });
    $context = Context::empty()->with('acme/unrelated', 1);
    $result = $resolver->resolve(doc(block('f', 'acme/form', [], block('n', 'acme/field', ['key' => 'name']))), Mode::Front, $context);
    expect($seen)->toBe(['acme/form-id' => 'f1'])->and($result->find('n')->admin)->toBeNull();
});

test('the admin context carries the behaviour values plus issues, bindings and the hidden flag', function () use ($form, $field) {
    [$resolver] = resolver(function ($c) use ($form, $field) {
        types($c)->add($form()); types($c)->add($field());
        sources($c)->add(new ArrayDataSource('acme/values', ['k' => 'email']));
        steps($c)->add(GateStepInterface::class, gateStep(false));
    });
    $fieldBlock = block('n', 'acme/field')->withBinding(SettingPath::fromString('key'), Binding::to('acme/values', ['key' => 'k']));
    $resolved = $resolver->resolve(doc(block('f', 'acme/form', [], $fieldBlock)), Mode::Admin)->find('n');
    $admin = $resolved->admin;
    expect($admin->hiddenOnFront)->toBeTrue()->and($admin->bindings['key'])->toBe(['source' => 'acme/values', 'used' => 'source'])
        ->and($resolved->view->admin)->toBeNull();
});

test('a hidden block and its children are not resolved on the front end and call no data source', function () use ($form, $field) {
    $calls = 0;
    [$resolver] = resolver(function ($c) use ($form, $field, &$calls) {
        types($c)->add($form()); types($c)->add($field());
        sources($c)->add(countingSource('acme/count', $calls));
        steps($c)->add(GateStepInterface::class, gateStep(false));
    });
    $child = block('n', 'acme/field')->withBinding(SettingPath::fromString('key'), Binding::to('acme/count'));
    $result = $resolver->resolve(doc(block('f', 'acme/form', [], $child)), Mode::Front);
    expect($result->find('f')->isResolved())->toBeFalse()->and($result->find('n'))->toBeNull()->and($calls)->toBe(0)->and($result->hasIssues())->toBeFalse();
});

test('disallowed children and misplaced roots are skipped with an issue', function () use ($form, $field) {
    [$resolver] = resolver(function ($c) use ($form, $field) { types($c)->add($form()); types($c)->add($field()); types($c)->add(type('acme/box')); });
    $result = $resolver->resolve(doc(block('f', 'acme/form', [], block('x', 'acme/box')), block('n', 'acme/field')), Mode::Front);
    expect(array_map(fn (Issue $i) => [$i->blockId, $i->kind], $result->issues))
        ->toBe([['x', IssueKind::DisallowedChild], ['n', IssueKind::DisallowedChild]])
        ->and($result->find('f')->isResolved())->toBeTrue();
});

test('one bad block does not stop its siblings', function () use ($form, $field) {
    [$resolver] = resolver(function ($c) { types($c)->add(type('acme/box')); });
    $result = $resolver->resolve(doc(block('a', 'gone/away'), block('b', 'acme/box')), Mode::Front);
    expect($result->find('a')->isResolved())->toBeFalse()->and($result->find('b')->isResolved())->toBeTrue()
        ->and($result->issuesFor('a')[0]->kind)->toBe(IssueKind::UnknownType);
});

test('a view that fails its declared schema yields no view', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/count', ['view' => ['type' => 'object', 'required' => ['total'], 'properties' => ['total' => ['type' => 'integer']]]]));
    });
    $result = $resolver->resolve(doc(block('a', 'acme/count')), Mode::Front);
    expect($result->find('a')->isResolved())->toBeFalse()->and($result->issues[0]->kind)->toBe(IssueKind::InvalidView);
});

test('a view can ask for its children once per item', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/loop', ['children' => true]));
        types($c)->add(type('acme/item'));
        behaviours($c)->set(BlockName::fromString('acme/loop'), new class implements BlockBehaviourInterface {
            public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
            {
                return new BlockView([], childPasses: [new CurrentItem('x', 0, 'q'), new CurrentItem('y', 1, 'q')]);
            }
        });
    });
    $loop = $resolver->resolve(doc(block('l', 'acme/loop', [], block('i', 'acme/item'))), Mode::Front)->find('l');
    expect(count($loop->passes))->toBe(2)
        ->and($loop->passes[0][0]->context->currentItem()->item)->toBe('x')
        ->and($loop->passes[1][0]->context->currentItem()->index)->toBe(1);
});

test('a view step can add extension values but not change the block view values', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box'));
        steps($c)->add(ViewStepInterface::class, new class implements ViewStepInterface {
            public function extend(BlockView $view, Block $block, BlockType $type, array $extensions, Context $context, Mode $mode): BlockView
            {
                return new BlockView(['hijacked' => true], ['acme/vis' => ['class' => 'is-hidden']]);
            }
        });
    });
    $view = $resolver->resolve(doc(block('a', 'acme/box', ['n' => 1])), Mode::Front)->find('a')->view;
    expect($view->values)->toBe(['n' => 1])->and($view->extensions)->toBe(['acme/vis' => ['class' => 'is-hidden']]);
});

test('a provided context key whose view key is absent is skipped', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/wrap', ['children' => true, 'provides' => ['acme/a' => 'a', 'acme/b' => 'b']]));
        types($c)->add(type('acme/box'));
    });
    $context = Context::empty()->with('acme/b', 'kept');
    $result = $resolver->resolve(doc(block('w', 'acme/wrap', ['a' => 1], block('x', 'acme/box'))), Mode::Front, $context);
    expect($result->find('x')->context->all())->toBe(['acme/b' => 'kept', 'acme/a' => 1])
        ->and($result->find('w')->context->all())->toBe(['acme/b' => 'kept']);
});

test('a root whose registered type allows only certain parents is skipped', function () {
    [$resolver] = resolver(function ($c) { types($c)->add(type('acme/cell', ['parents' => ['acme/row']])); });
    $result = $resolver->resolve(doc(block('c', 'acme/cell')), Mode::Front);
    expect($result->roots)->toBe([])->and($result->find('c'))->toBeNull()
        ->and(kinds($result->issuesFor('c')))->toBe([IssueKind::DisallowedChild]);
});

test('a child of an unregistered type under a parent allowing any child is reported as unknown', function () {
    [$resolver] = resolver(function ($c) { types($c)->add(type('acme/wrap', ['children' => true])); });
    $result = $resolver->resolve(doc(block('w', 'acme/wrap', [], block('g', 'gone/away'))), Mode::Front);
    expect(kinds($result->issues))->toBe([IssueKind::UnknownType])
        ->and($result->find('g')->isResolved())->toBeFalse()
        ->and($result->find('g')->type)->toBeNull();
});

test('an empty list of child passes resolves no children', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/loop', ['children' => true]));
        types($c)->add(type('acme/item', ['parents' => ['acme/nowhere']]));
        behaviours($c)->set(BlockName::fromString('acme/loop'), fixedView(new BlockView([], childPasses: [])));
    });
    $result = $resolver->resolve(doc(block('l', 'acme/loop', [], block('i', 'acme/item'))), Mode::Front);
    expect($result->find('l')->passes)->toBe([])->and($result->find('i'))->toBeNull()->and($result->hasIssues())->toBeFalse();
});

test('a behaviour asking for more than 1000 passes gets the first 1000 and an issue', function () {
    $items = array_map(fn (int $i) => new CurrentItem($i, $i, 'q'), range(0, 1000));
    [$resolver] = resolver(function ($c) use ($items) {
        types($c)->add(type('acme/loop', ['children' => true]));
        types($c)->add(type('acme/item'));
        behaviours($c)->set(BlockName::fromString('acme/loop'), fixedView(new BlockView([], childPasses: $items)));
    });
    $result = $resolver->resolve(doc(block('l', 'acme/loop', [], block('i', 'acme/item'))), Mode::Front);
    $loop = $result->find('l');
    expect(count($loop->passes))->toBe(1000)
        ->and($loop->passes[999][0]->context->currentItem()->index)->toBe(999)
        ->and(array_map(fn (Issue $i) => [$i->blockId, $i->kind], $result->issues))->toBe([['l', IssueKind::InvalidView]]);
});

test('issues are collected in tree order, a block before its children, pass by pass', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/wrap', ['children' => true, 'settings' => ['type' => 'object', 'properties' => [
            'n' => ['type' => 'integer']]]]));
        types($c)->add(type('acme/loop', ['children' => true]));
        behaviours($c)->set(BlockName::fromString('acme/loop'), fixedView(
            new BlockView([], childPasses: [new CurrentItem('x', 0, 'q'), new CurrentItem('y', 1, 'q')])
        ));
    });
    $wrap = block('w', 'acme/wrap', [], block('u', 'gone/one'), block('l', 'acme/loop', [], block('v', 'gone/two')))
        ->withBinding(SettingPath::fromString('n'), Binding::to('acme/missing'));
    $result = $resolver->resolve(doc($wrap, block('z', 'gone/three')), Mode::Front);
    expect(array_map(fn (Issue $i) => [$i->blockId, $i->kind], $result->issues))->toBe([
        ['w', IssueKind::UnknownDataSource],
        ['u', IssueKind::UnknownType],
        ['v', IssueKind::UnknownType],
        ['v', IssueKind::UnknownType],
        ['z', IssueKind::UnknownType],
    ]);
});

test('a resolved document finds blocks through first passes and filters issues by block', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/wrap', ['children' => true]));
        types($c)->add(type('acme/box'));
    });
    $result = $resolver->resolve(doc(block('w', 'acme/wrap', [], block('a', 'acme/box'), block('g', 'gone/away'))), Mode::Front);
    expect($result->find('a')->block->id)->toBe('a')
        ->and($result->find('w')->children()[0])->toBe($result->find('a'))
        ->and($result->find('nope'))->toBeNull()
        ->and($result->issuesFor('a'))->toBe([])
        ->and(kinds($result->issuesFor('g')))->toBe([IssueKind::UnknownType])
        ->and($result->hasIssues())->toBeTrue()
        ->and($result->mode)->toBe(Mode::Front);
});

test('resolving the same document twice gives equal results', function () use ($form, $field) {
    [$resolver] = resolver(function ($c) use ($form, $field) { types($c)->add($form()); types($c)->add($field()); });
    $document = doc(block('f', 'acme/form', [], block('n', 'acme/field', ['key' => 'name']), block('x', 'gone/away')));
    expect($resolver->resolve($document, Mode::Admin))->toEqual($resolver->resolve($document, Mode::Admin));
});

test('a disallowed child under a repeating block is reported once and left out of every pass', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/loop', ['children' => ['acme/item']]));
        types($c)->add(type('acme/item'));
        types($c)->add(type('acme/box'));
        behaviours($c)->set(BlockName::fromString('acme/loop'), fixedView(
            new BlockView([], childPasses: [new CurrentItem('x', 0, 'q'), new CurrentItem('y', 1, 'q')])
        ));
    });
    $result = $resolver->resolve(doc(block('l', 'acme/loop', [], block('b', 'acme/box'), block('i', 'acme/item'))), Mode::Front);
    $passes = $result->find('l')->passes;
    expect(array_map(fn (Issue $i) => [$i->blockId, $i->kind], $result->issues))->toBe([['b', IssueKind::DisallowedChild]])
        ->and(array_map(fn (array $pass) => array_map(fn ($r) => $r->block->id, $pass), $passes))->toBe([['i'], ['i']]);
});

test('a behaviour reads its block id and the ids of its children', function () {
    $seen = null;
    [$resolver] = resolver(function ($c) use (&$seen) {
        types($c)->add(type('acme/wrap', ['children' => true]));
        types($c)->add(type('acme/box'));
        behaviours($c)->set(BlockName::fromString('acme/wrap'), new class($seen) implements BlockBehaviourInterface {
            public function __construct(private mixed &$seen)
            {
            }

            public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
            {
                $this->seen = [$block->id, array_map(fn (Block $child) => $child->id, $block->children)];

                return new BlockView([]);
            }
        });
    });
    $resolver->resolve(doc(block('w', 'acme/wrap', [], block('a', 'acme/box'), block('b', 'acme/box'))), Mode::Front);
    expect($seen)->toBe(['w', ['a', 'b']]);
});

test('a behaviour admin value reaches the resolved block admin context', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box'));
        behaviours($c)->set(BlockName::fromString('acme/box'), fixedView(new BlockView([], admin: new AdminContext(['hint' => 'h']))));
    });
    $resolved = $resolver->resolve(doc(block('a', 'acme/box')), Mode::Admin)->find('a');
    expect($resolved->admin->values)->toBe(['hint' => 'h'])->and($resolved->view->admin)->toBeNull();
});

test('a block with invalid settings has an admin context with its issues in admin mode', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box', ['settings' => ['type' => 'object', 'properties' => ['n' => ['type' => 'integer']]]]));
        sources($c)->add(new ArrayDataSource('acme/values', ['k' => 'v']));
    });
    $b = block('a', 'acme/box', ['n' => 'nope'])->withBinding(SettingPath::fromString('n'), Binding::to('acme/values', ['key' => 'k']));
    $resolved = $resolver->resolve(doc($b), Mode::Admin)->find('a');
    expect($resolved->isResolved())->toBeFalse()
        ->and(kinds($resolved->admin->issues))->toContain(IssueKind::InvalidSettings)
        ->and($resolved->admin->bindings['n']['source'])->toBe('acme/values');
});

test('a block of an unknown type has an admin context with its issue in admin mode', function () {
    [$resolver] = resolver();
    $resolved = $resolver->resolve(doc(block('g', 'gone/away')), Mode::Admin)->find('g');
    expect(kinds($resolved->admin->issues))->toBe([IssueKind::UnknownType])
        ->and($resolved->admin->bindings)->toBe([])->and($resolved->admin->hiddenOnFront)->toBeFalse();
});

test('the over-cap pass issue is in the block admin issues', function () {
    $items = array_map(fn (int $i) => new CurrentItem($i, $i, 'q'), range(0, 1000));
    [$resolver] = resolver(function ($c) use ($items) {
        types($c)->add(type('acme/loop', ['children' => true]));
        behaviours($c)->set(BlockName::fromString('acme/loop'), fixedView(new BlockView([], childPasses: $items)));
    });
    $loop = $resolver->resolve(doc(block('l', 'acme/loop')), Mode::Admin)->find('l');
    expect(kinds($loop->admin->issues))->toBe([IssueKind::InvalidView]);
});

test('no resolved block has an admin context in front mode, stopped or not', function () {
    [$resolver] = resolver(function ($c) { types($c)->add(type('acme/box')); });
    $result = $resolver->resolve(doc(block('a', 'acme/box'), block('g', 'gone/away')), Mode::Front);
    expect($result->find('a')->admin)->toBeNull()->and($result->find('g')->admin)->toBeNull();
});

/**
 * Builds a document resolver over the container's stages with the given block budget.
 */
function budgeted(BlockServiceContainer $c, int $budget): DocumentResolver
{
    return new DocumentResolver(
        $c->get(PrepareStage::class),
        $c->get(GateStage::class),
        $c->get(ResolveStage::class),
        $c->get(ValidateStage::class),
        $c->get(ViewStage::class),
        types($c),
        $budget,
    );
}

test('an exhausted budget records one issue on the block where it ran out and resolves nothing further', function () {
    [, $c] = resolver(function ($c) {
        types($c)->add(type('acme/wrap', ['children' => true]));
        types($c)->add(type('acme/box'));
    });
    $resolver = budgeted($c, 3);
    $document = doc(block('w', 'acme/wrap', [], block('a'), block('b'), block('x'), block('y')), block('z', 'gone/away'));
    $result = $resolver->resolve($document, Mode::Admin);
    $wrap = $result->find('w');
    expect(idsAndKinds($result->issues))->toBe([['x', IssueKind::BudgetExceeded]])
        ->and(array_map(fn ($r) => $r->block->id, $wrap->children()))->toBe(['a', 'b', 'x'])
        ->and($result->find('x')->isResolved())->toBeFalse()
        ->and(kinds($result->find('x')->admin->issues))->toBe([IssueKind::BudgetExceeded])
        ->and($result->find('b')->isResolved())->toBeTrue()
        ->and(count($result->roots))->toBe(1)
        ->and($result->find('z'))->toBeNull()
        ->and($resolver->resolve($document, Mode::Admin))->toEqual($result);
});

test('the budget counts blocks across child passes', function () {
    [, $c] = resolver(function ($c) {
        types($c)->add(type('acme/loop', ['children' => true]));
        types($c)->add(type('acme/item'));
        behaviours($c)->set(BlockName::fromString('acme/loop'), fixedView(new BlockView([], childPasses: [
            new CurrentItem('x', 0, 'q'), new CurrentItem('y', 1, 'q'), new CurrentItem('z', 2, 'q'), new CurrentItem('w', 3, 'q'),
        ])));
    });
    $result = budgeted($c, 3)->resolve(doc(block('l', 'acme/loop', [], block('i', 'acme/item'))), Mode::Front);
    $passes = $result->find('l')->passes;
    expect(idsAndKinds($result->issues))->toBe([['i', IssueKind::BudgetExceeded]])
        ->and(count($passes))->toBe(3)
        ->and($passes[2][0]->isResolved())->toBeFalse()
        ->and($passes[1][0]->isResolved())->toBeTrue();
});

test('the default budget resolves a large flat document', function () {
    [$resolver] = resolver(function ($c) { types($c)->add(type('acme/box')); });
    $blocks = array_map(fn (int $i) => block('b' . $i), range(1, 2000));
    expect($resolver->resolve(doc(...$blocks), Mode::Front)->hasIssues())->toBeFalse();
});

test('in admin mode a block hidden by a gate step still resolves its bindings', function () {
    $calls = 0;
    [$resolver] = resolver(function ($c) use (&$calls) {
        types($c)->add(type('acme/box', ['settings' => ['type' => 'object', 'properties' => ['n' => ['type' => 'string']]]]));
        sources($c)->add(countingSource('acme/count', $calls));
        steps($c)->add(GateStepInterface::class, gateStep(false));
    });
    $b = block('a', 'acme/box')->withBinding(SettingPath::fromString('n'), Binding::to('acme/count'));
    $resolved = $resolver->resolve(doc($b), Mode::Admin)->find('a');
    expect($calls)->toBe(1)->and($resolved->settings)->toBe(['n' => 'x'])
        ->and($resolved->admin->hiddenOnFront)->toBeTrue()
        ->and($resolved->admin->bindings['n'])->toBe(['source' => 'acme/count', 'used' => 'source']);
});
