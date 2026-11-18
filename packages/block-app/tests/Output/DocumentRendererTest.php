<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\CollectStepInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Contracts\OutputEscaperInterface;
use SpeedySpec\Block\Domain\Contracts\RenderStepInterface;
use SpeedySpec\Block\Domain\Contracts\UnresolvedBlockOutputInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Exceptions\UnsupportedTargetException;
use SpeedySpec\Block\Domain\ValueObjects\AdminContext;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\CurrentItem;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;

$text = ['type' => 'object', 'properties' => ['text' => ['type' => 'string']]];

test('draws a tree with escaped values and children inside their parent', function () use ($text) {
    [$renderer] = renderer(function ($c) use ($text) {
        types($c)->add(type('acme/box', ['children' => true, 'settings' => $text]));
        outputs($c)->set(BlockName::fromString('acme/box'), tag('div'));
    });
    $document = doc(block('a', 'acme/box', ['text' => '<b>hi</b>'], block('b', 'acme/box', ['text' => 'in'])));
    $result = $renderer->render($document, new RenderRequest());

    expect($result->output)->toBe('<div>&lt;b&gt;hi&lt;/b&gt;<div>in</div></div>')
        ->and($result->hasIssues())->toBeFalse()
        ->and($result->resolved->find('b'))->not->toBeNull();
});

test('a type with no output passes its children through and reports it', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/wrap', ['children' => true]));
        types($c)->add(type('acme/box'));
        outputs($c)->set(BlockName::fromString('acme/box'), tag('p'));
    });
    $result = $renderer->render(doc(block('w', 'acme/wrap', [], block('b', 'acme/box'))), new RenderRequest());

    expect($result->output)->toBe('<p></p>')
        ->and(kinds($result->issues))->toBe([IssueKind::MissingOutput])
        ->and($result->issues[0]->blockId)->toBe('w');
});

test('admin mode uses the admin output when there is one and the front output otherwise', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/a'));
        types($c)->add(type('acme/b'));
        outputs($c)->set(BlockName::fromString('acme/a'), fixedOutput('[a front]'));
        outputs($c)->set(BlockName::fromString('acme/a'), fixedOutput('[a admin]'), Mode::Admin);
        outputs($c)->set(BlockName::fromString('acme/b'), fixedOutput('[b front]'));
    });
    $document = doc(block('1', 'acme/a'), block('2', 'acme/b'));

    expect($renderer->render($document, new RenderRequest(Mode::Admin))->output)->toBe('[a admin][b front]')
        ->and($renderer->render($document, new RenderRequest(Mode::Front))->output)->toBe('[a front][b front]');
});

test('a block with only an admin output is not drawn for a visitor', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/a'));
        outputs($c)->set(BlockName::fromString('acme/a'), fixedOutput('[editor only]'), Mode::Admin);
    });
    $result = $renderer->render(doc(block('1', 'acme/a')), new RenderRequest(Mode::Front));

    expect($result->output)->toBe('')->and(kinds($result->issues))->toBe([IssueKind::MissingOutput]);
});

test('an output that throws blanks only its own block', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/bad'));
        types($c)->add(type('acme/ok'));
        outputs($c)->set(BlockName::fromString('acme/bad'), new class implements BlockOutputInterface {
            public function render(RenderInput $input): string
            {
                throw new RuntimeException('boom');
            }
        });
        outputs($c)->set(BlockName::fromString('acme/ok'), fixedOutput('ok'));
    });
    $result = $renderer->render(doc(block('1', 'acme/bad'), block('2', 'acme/ok')), new RenderRequest());

    expect($result->output)->toBe('ok')
        ->and(kinds($result->issues))->toBe([IssueKind::OutputFailed])
        ->and($result->issues[0]->message)->toContain('RuntimeException');
});

test('a block that did not resolve draws nothing and adds no render issue', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/ok'));
        outputs($c)->set(BlockName::fromString('acme/ok'), fixedOutput('ok'));
    });
    $result = $renderer->render(doc(block('1', 'gone/away'), block('2', 'acme/ok')), new RenderRequest());

    expect($result->output)->toBe('ok')->and(kinds($result->issues))->toBe([IssueKind::UnknownType]);
});

test('render steps add attributes, and a rejected attribute is reported without breaking the block', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/box'));
        outputs($c)->set(BlockName::fromString('acme/box'), tag('div'));
        steps($c)->add(RenderStepInterface::class, renderStep(
            fn (AttributeBag $bag) => $bag->withClass('is-animated')->withAttribute('data-fx', 'fade')
        ));
        steps($c)->add(RenderStepInterface::class, renderStep(fn (AttributeBag $bag) => $bag->withAttribute('onclick', 'x()')));
    });
    $result = $renderer->render(doc(block('a', 'acme/box')), new RenderRequest());

    expect($result->output)->toBe('<div class="is-animated" data-fx="fade"></div>')
        ->and(kinds($result->issues))->toBe([IssueKind::InvalidAttribute]);
});

test('a render step that throws or returns the wrong thing is reported and skipped', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/box'));
        outputs($c)->set(BlockName::fromString('acme/box'), tag('div'));
        steps($c)->add(RenderStepInterface::class, renderStep(fn () => throw new RuntimeException('boom')));
        steps($c)->add(RenderStepInterface::class, renderStep(fn (AttributeBag $bag) => $bag->withClass('kept')));
    });
    $result = $renderer->render(doc(block('a', 'acme/box')), new RenderRequest());

    expect($result->output)->toBe('<div class="kept"></div>')->and(kinds($result->issues))->toBe([IssueKind::StepFailed]);
});

test('each child pass is rendered separately and in order', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/loop', ['children' => true]));
        types($c)->add(type('acme/item'));
        behaviours($c)->set(BlockName::fromString('acme/loop'), new class implements BlockBehaviourInterface {
            public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
            {
                return new BlockView([], childPasses: [new CurrentItem('x', 0, 'q'), new CurrentItem('y', 1, 'q')]);
            }
        });
        outputs($c)->set(BlockName::fromString('acme/loop'), new class implements BlockOutputInterface {
            public function render(RenderInput $input): string
            {
                return '<ul>' . implode('|', $input->childPasses) . '</ul>';
            }
        });
        outputs($c)->set(BlockName::fromString('acme/item'), new class implements BlockOutputInterface {
            public function render(RenderInput $input): string
            {
                return '<li>' . $input->escaper->text((string) $input->context->currentItem()->item) . '</li>';
            }
        });
    });
    $document = doc(block('l', 'acme/loop', [], block('i', 'acme/item')));

    expect($renderer->render($document, new RenderRequest())->output)->toBe('<ul><li>x</li>|<li>y</li></ul>');
});

test('assets are collected once, in order, only for blocks that were drawn', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/a', ['assets' => ['a-style', 'shared']]));
        types($c)->add(type('acme/b', ['assets' => ['shared', 'b-script']]));
        types($c)->add(type('acme/none', ['assets' => ['never']]));
        outputs($c)->set(BlockName::fromString('acme/a'), fixedOutput('a'));
        outputs($c)->set(BlockName::fromString('acme/b'), fixedOutput('b'));
        steps($c)->add(CollectStepInterface::class, collectStep(['from-step']));
    });
    $document = doc(block('1', 'acme/a'), block('2', 'acme/b'), block('3', 'acme/none'));

    expect($renderer->render($document, new RenderRequest())->assets)->toBe(['a-style', 'shared', 'from-step', 'b-script']);
});

test('a collect step that throws is reported and contributes nothing', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/a', ['assets' => ['a-style']]));
        outputs($c)->set(BlockName::fromString('acme/a'), fixedOutput('a'));
        steps($c)->add(CollectStepInterface::class, new class implements CollectStepInterface {
            public function assets(\SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock $block, RenderRequest $request): array
            {
                throw new RuntimeException('boom');
            }
        });
        steps($c)->add(CollectStepInterface::class, collectStep(['ok', 5, null]));
    });
    $result = $renderer->render(doc(block('1', 'acme/a')), new RenderRequest());

    expect($result->assets)->toBe(['a-style'])->and(kinds($result->issues))->toBe([IssueKind::StepFailed, IssueKind::StepFailed]);
});

test('the admin context reaches the output in admin mode only', function () {
    $seen = [];
    [$renderer] = renderer(function ($c) use (&$seen) {
        types($c)->add(type('acme/box'));
        outputs($c)->set(BlockName::fromString('acme/box'), spyOutput($seen));
    });
    $renderer->render(doc(block('a', 'acme/box')), new RenderRequest(Mode::Front));
    $renderer->render(doc(block('a', 'acme/box')), new RenderRequest(Mode::Admin));

    expect($seen[0]->admin)->toBeNull()
        ->and($seen[1]->admin)->toBeInstanceOf(AdminContext::class)
        ->and($seen[0]->blockId)->toBe('a')
        ->and($seen[0]->request->mode)->toBe(Mode::Front);
});

test('a private setting is not in the view an output receives for a visitor', function () {
    $seen = [];
    [$renderer] = renderer(function ($c) use (&$seen) {
        types($c)->add(type('acme/form', ['settings' => ['type' => 'object', 'properties' => [
            'title' => ['type' => 'string'], 'to' => ['type' => 'string', 'x-private' => true],
        ]]]));
        outputs($c)->set(BlockName::fromString('acme/form'), spyOutput($seen));
    });
    $renderer->render(doc(block('f', 'acme/form', ['title' => 'Contact', 'to' => 'owner@example.test'])), new RenderRequest());

    expect($seen[0]->view->values)->toBe(['title' => 'Contact']);
});

test('render issues follow resolve issues, in tree order', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/wrap', ['children' => true]));
        types($c)->add(type('acme/leaf'));
    });
    $document = doc(block('w', 'acme/wrap', [], block('x', 'gone/away'), block('l', 'acme/leaf')));
    $result = $renderer->render($document, new RenderRequest());

    expect(idsAndKinds($result->issues))->toBe([
        ['x', IssueKind::UnknownType],
        ['w', IssueKind::MissingOutput],
        ['l', IssueKind::MissingOutput],
    ]);
});

test('an unknown target is a configuration error, and a mode mismatch is rejected', function () {
    [$renderer, $c] = renderer(fn ($c) => types($c)->add(type('acme/box')));
    $document = doc(block('a', 'acme/box'));
    $resolved = $c->get(DocumentResolverInterface::class)->resolve($document, Mode::Front);

    expect(fn () => $renderer->render($document, new RenderRequest(Mode::Front, 'pdf')))->toThrow(UnsupportedTargetException::class)
        ->and(fn () => $renderer->renderResolved($resolved, new RenderRequest(Mode::Admin)))->toThrow(InvalidArgumentException::class);
});

test('rendering the same document twice gives the same result', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/box', ['assets' => ['box']]));
        outputs($c)->set(BlockName::fromString('acme/box'), fixedOutput('x'));
    });
    $document = doc(block('a', 'acme/box'), block('b', 'acme/box'));
    $first = $renderer->render($document, new RenderRequest());
    $second = $renderer->render($document, new RenderRequest());

    expect($second->output)->toBe($first->output)->and($second->assets)->toBe(['box']);
});

test('the renderer and its registries can be replaced one contract at a time', function () {
    [, $c] = renderer();

    expect($c->get(DocumentRendererInterface::class))->toBeInstanceOf(DocumentRendererInterface::class)
        ->and($c->get(\SpeedySpec\Block\Domain\Contracts\OutputEscaperRegistryInterface::class)->has('html'))->toBeTrue();
});

test('in admin mode an unresolved block is drawn as a placeholder, and for a visitor it is not', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/ok'));
        outputs($c)->set(BlockName::fromString('acme/ok'), fixedOutput('ok'));
    });
    $document = doc(block('1', 'gone/away'), block('2', 'acme/ok'));

    expect($renderer->render($document, new RenderRequest(Mode::Admin))->output)
        ->toBe('<div class="block-placeholder" data-block-id="1" data-block-type="gone/away"></div>ok')
        ->and($renderer->render($document, new RenderRequest(Mode::Front))->output)->toBe('ok');
});

test('a block id that holds markup is escaped in the placeholder', function () {
    [$renderer] = renderer();

    $out = $renderer->render(doc(block('"><script>', 'gone/away')), new RenderRequest(Mode::Admin))->output;

    expect($out)->not->toContain('<script>')->toContain('data-block-id="&quot;&gt;&lt;script&gt;"');
});

test('a placeholder that fails is recorded on the block and draws nothing', function () {
    [$renderer] = renderer(function ($c) {
        $c->add(UnresolvedBlockOutputInterface::class, fn () => new class implements UnresolvedBlockOutputInterface {
            public function render(
                ResolvedBlock $block,
                RenderRequest $request,
                OutputEscaperInterface $escaper,
            ): string
            {
                throw new RuntimeException('no');
            }
        });
    });

    $result = $renderer->render(doc(block('1', 'gone/away')), new RenderRequest(Mode::Admin));

    expect($result->output)->toBe('')
        ->and(idsAndKinds($result->issues))->toContain(['1', IssueKind::OutputFailed]);
});

if (!function_exists('assetOutput'))
{
    /**
     * Builds an output that draws fixed text and names the assets it needs.
     */
    function assetOutput(string $text, array $assets): BlockOutputInterface
    {
        return new class($text, $assets) implements BlockOutputInterface,
            \SpeedySpec\Block\Domain\Contracts\DeclaresAssetsInterface {
            public function __construct(private readonly string $text, private readonly array $assets)
            {
            }

            public function render(RenderInput $input): string
            {
                return $this->text;
            }

            public function assets(): array
            {
                return $this->assets;
            }
        };
    }
}

test('an output names the assets it needs, and a replaced output no longer brings its own', function () {
    [$renderer, $c] = renderer(function ($c) {
        types($c)->add(type('acme/tabs', ['assets' => ['acme/type-asset']]));
        outputs($c)->set(BlockName::fromString('acme/tabs'), assetOutput('default', ['acme/tabs-script', 'acme/type-asset']));
    });
    $before = $renderer->render(doc(block('a', 'acme/tabs'), block('b', 'acme/tabs')), new RenderRequest());
    outputs($c)->set(BlockName::fromString('acme/tabs'), assetOutput('other', ['other/tabs']));
    $after = $renderer->render(doc(block('a', 'acme/tabs')), new RenderRequest());

    expect($before->assets)->toBe(['acme/type-asset', 'acme/tabs-script'])
        ->and($after->assets)->toBe(['acme/type-asset', 'other/tabs']);
});

test('an output that names an asset that is not text is recorded on the block, and drawing goes on', function () {
    [$renderer] = renderer(function ($c) {
        types($c)->add(type('acme/tabs'));
        outputs($c)->set(BlockName::fromString('acme/tabs'), assetOutput('drawn', ['ok', 5]));
    });

    $result = $renderer->render(doc(block('a', 'acme/tabs')), new RenderRequest());

    expect($result->output)->toBe('drawn')
        ->and(idsAndKinds($result->issues))->toBe([['a', IssueKind::StepFailed]])
        ->and($result->assets)->toBe([]);
});
