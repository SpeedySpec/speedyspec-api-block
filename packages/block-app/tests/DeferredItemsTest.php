<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Resolving\DocumentResolver;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentEditorInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Contracts\PlacementRulesInterface;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Exceptions\BlockPackageException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;
use SpeedySpec\Block\Domain\ValueObjects\EditRefusalKind;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\Position;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;

$setup = function ($c) {
    types($c)->add(type('acme/form', ['children' => ['acme/field']]));
    types($c)->add(type('acme/field', ['parents' => ['acme/form']]));
    types($c)->add(type('acme/box', ['children' => true]));
    outputs($c)->set(BlockName::fromString('acme/box'), tag('div'));
    outputs($c)->set(BlockName::fromString('acme/field'), fixedOutput('field'));
};

test('an editor still gets a misplaced block to select, and a visitor does not', function () use ($setup) {
    [$renderer, $c] = renderer($setup);
    $document = doc(block('f', 'acme/field'), block('b', 'acme/box', [], block('n', 'acme/field')));
    $resolver = $c->get(DocumentResolverInterface::class);

    $admin = $resolver->resolve($document, Mode::Admin);
    $front = $resolver->resolve($document, Mode::Front);
    $drawn = $renderer->render($document, new RenderRequest(Mode::Admin))->output;

    expect($admin->find('f')->isResolved())->toBeFalse()
        ->and(kinds($admin->find('f')->admin->issues))->toBe([IssueKind::DisallowedChild])
        ->and(kinds($admin->find('n')->admin->issues))->toBe([IssueKind::DisallowedChild])
        ->and(idsAndKinds($admin->issues))
        ->toBe([['f', IssueKind::DisallowedChild], ['n', IssueKind::DisallowedChild]])
        ->and($front->find('f'))->toBeNull()
        ->and($front->find('n'))->toBeNull()
        ->and(idsAndKinds($front->issues))->toBe(idsAndKinds($admin->issues))
        ->and($drawn)->toBe(
            '<div class="block-placeholder" data-block-id="f" data-block-type="acme/field"></div>'
            . '<div><div class="block-placeholder" data-block-id="n" data-block-type="acme/field"></div></div>'
        )
        ->and($renderer->render($document, new RenderRequest())->output)->toBe('<div></div>');
});

test('a resolver cannot be built with a budget below zero', function () use ($setup) {
    [, $c] = renderer($setup);
    $make = fn (int $budget) => new DocumentResolver(
        ...array_map($c->get(...), [
            \SpeedySpec\Block\App\Resolving\PrepareStage::class,
            \SpeedySpec\Block\App\Resolving\GateStage::class,
            \SpeedySpec\Block\App\Resolving\ResolveStage::class,
            \SpeedySpec\Block\App\Resolving\ValidateStage::class,
            \SpeedySpec\Block\App\Resolving\ViewStage::class,
        ]),
        ...[$c->get(BlockTypeRegistryInterface::class), $c->get(PlacementRulesInterface::class), $budget],
    );

    expect(fn () => $make(-1))->toThrow(InvalidArgumentException::class)
        ->and($make(0))->toBeInstanceOf(DocumentResolver::class);
});

test('nothing can be placed inside a block whose type is not registered', function () use ($setup) {
    [, $c] = renderer($setup);
    $editor = $c->get(DocumentEditorInterface::class);
    $document = doc(block('g', 'gone/away'), block('b', 'acme/box'));
    $inside = Position::inside('g', 0);

    expect(refusals($editor->insert($document, BlockName::fromString('acme/box'), $inside)))
        ->toBe([EditRefusalKind::UnknownType])
        ->and(refusals($editor->insertBlocks($document, [block('x', 'acme/box')], $inside)))
        ->toBe([EditRefusalKind::UnknownType])
        ->and(refusals($editor->move($document, 'b', $inside)))->toBe([EditRefusalKind::UnknownType])
        ->and($editor->insert($document, BlockName::fromString('acme/box'), $inside)->refusals[0]->blockId)->toBe('g');
});

test('a package cannot take a template name that is already registered, and a failed load leaves none', function () {
    [, $c] = renderer();
    $loader = $c->get(BlockPackageLoaderInterface::class);
    $templates = $c->get(BlockTemplateRegistryInterface::class);
    $template = fn (string $name, string $title) => new BlockTemplate($name, $title, new BlockTree(block('a')));
    $loader->load(package('acme/one', function (BlockPackageRegistrarInterface $r) use ($template) {
        $r->type(type('acme/one'));
        $r->template($template('acme/hero', 'First'));
    }));

    $second = package('acme/two', function (BlockPackageRegistrarInterface $r) use ($template) {
        $r->type(type('acme/two'));
        $r->template($template('acme/extra', 'Extra'));
        $r->template($template('acme/hero', 'Second'));
    });

    expect(fn () => $loader->load($second))->toThrow(BlockPackageException::class)
        ->and($templates->find('acme/hero')->title)->toBe('First')
        ->and($templates->find('acme/extra'))->toBeNull()
        ->and(types($c)->has(BlockName::fromString('acme/two')))->toBeFalse();
});
