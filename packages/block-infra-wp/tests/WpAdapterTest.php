<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentEditorInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Position;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Infra\WP\Ports\WpBindingsInterface;
use SpeedySpec\Block\Infra\WP\Ports\WpBlockTypesInterface;
use SpeedySpec\Block\Infra\WP\Ports\WpPostsInterface;
use SpeedySpec\Block\Infra\WP\Ports\WpRendererInterface;
use SpeedySpec\Block\Infra\WP\WpBlockTypeBridge;
use SpeedySpec\Block\Infra\WP\WpMarkup;
use SpeedySpec\Block\Infra\WP\WpServiceProvider;

use function SpeedySpec\Block\ContractTests\documentRepositoryContract;

$markup = <<<'HTML'
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Hello</h3>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p>One</p>
<!-- /wp:paragraph -->

<!-- wp:acme/card {"title":"A \u003cb\u003e \u002d\u002d card"} /--></div>
<!-- /wp:group -->
HTML;

$posts = fn (array $content = []) => new class($content) implements WpPostsInterface {
    public array $kinds = [];

    public function __construct(public array $content)
    {
    }

    public function content(string $id): ?string
    {
        return $this->content[$id] ?? null;
    }

    public function kind(string $id): string
    {
        return $this->kinds[$id] ?? 'page';
    }

    public function save(string $id, string $kind, string $content): void
    {
        $this->content[$id] = $content;
        $this->kinds[$id] = $kind;
    }

    public function delete(string $id): void
    {
        unset($this->content[$id]);
    }

    public function ids(): array
    {
        return array_map(strval(...), array_keys($this->content));
    }
};

describe('WpPostDocumentRepository', function () use ($posts) {
    documentRepositoryContract(
        fn () => new \SpeedySpec\Block\Infra\WP\WpPostDocumentRepository($posts(), new WpMarkup())
    );
});

test('block markup becomes a tree of blocks with their attributes as settings', function () use ($markup) {
    $tree = (new WpMarkup())->parse($markup);
    [$heading, $group] = $tree->roots();

    expect(count($tree->roots()))->toBe(2)
        ->and($heading->type->toString())->toBe('core/heading')
        ->and($heading->settings['level'])->toBe(3)
        ->and($heading->settings['wpContent'])->toBe(["\n<h3 class=\"wp-block-heading\">Hello</h3>\n"])
        ->and($group->settings['layout'])->toBe(['type' => 'constrained'])
        ->and(array_map(fn (Block $b) => $b->type->toString(), $group->children))->toBe(['core/paragraph', 'acme/card'])
        ->and($group->children[1]->settings['title'])->toBe('A <b> -- card')
        ->and($group->settings['wpContent'])->toBe(["\n<div class=\"wp-block-group\">", null, "\n\n", null, "</div>\n"])
        ->and($tree->ids())->toBe(['wp-0', 'wp-1', 'wp-1-0', 'wp-1-1']);
});

test('a tree written back is the same markup, plus an id on every block', function () use ($markup) {
    $wp = new WpMarkup();

    $written = $wp->serialize($wp->parse($markup));
    $again = $wp->parse($written);

    expect($written)->toContain('<!-- wp:heading {"level":3,"metadata":{"blockId":"wp-0"}} -->')
        ->toContain("<h3 class=\"wp-block-heading\">Hello</h3>\n<!-- /wp:heading -->\n\n<!-- wp:group")
        ->toContain('<!-- wp:acme/card {"title":"A \u003cb\u003e \u002d\u002d card","metadata":{"blockId":"wp-1-1"}} /-->')
        ->toContain("</div>\n<!-- /wp:group -->")
        ->and($wp->serialize($again))->toBe($written)
        ->and($again->ids())->toBe(['wp-0', 'wp-1', 'wp-1-0', 'wp-1-1']);
});

test('an id written into the markup survives the block being moved, and a copied id is not trusted twice', function () {
    $wp = new WpMarkup();
    $first = '<!-- wp:paragraph {"metadata":{"blockId":"keep-me"}} --><p>a</p><!-- /wp:paragraph -->';

    $moved = $wp->parse("<!-- wp:spacer /-->\n\n" . $first);
    $copied = $wp->parse($first . "\n\n" . $first);

    expect($moved->ids())->toBe(['wp-0', 'keep-me'])
        ->and($copied->ids())->toBe(['keep-me', 'wp-1'])
        ->and(array_key_exists('metadata', $moved->find('keep-me')->settings))->toBeFalse();
});

test('text outside any block is kept as a freeform block, and blank space between blocks is not', function () {
    $wp = new WpMarkup();

    $tree = $wp->parse("<p>Old post</p>\n\n<!-- wp:separator /-->\n\n  \n");

    expect(array_map(fn (Block $b) => $b->type->toString(), $tree->roots()))->toBe(['core/freeform', 'core/separator'])
        ->and($tree->roots()[0]->settings['wpContent'])->toBe(['<p>Old post</p>'])
        ->and($wp->serialize($tree))->toStartWith("<p>Old post</p>\n\n<!-- wp:separator");
});

test('bindings in block metadata become bindings, and are written back', function () {
    $wp = new WpMarkup();
    $source = '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"sub"}}},'
        . '"name":"Subtitle"}} --><p></p><!-- /wp:paragraph -->';

    $block = $wp->parse($source)->roots()[0];
    $written = $wp->serialize(new BlockTree($block));

    expect($block->bindings['content']->source)->toBe('core/post-meta')
        ->and($block->bindings['content']->arguments)->toBe(['key' => 'sub'])
        ->and($block->settings['metadata'])->toBe(['name' => 'Subtitle'])
        ->and($written)->toContain('"bindings":{"content":{"source":"core/post-meta","args":{"key":"sub"}}}')
        ->toContain('"name":"Subtitle"');
});

test('markup that is not well formed is read as far as it makes sense, never thrown on', function (string $broken) {
    $wp = new WpMarkup();

    $tree = $wp->parse($broken);

    expect($wp->parse($wp->serialize($tree))->ids())->toBe($tree->ids());
})->with([
    'unclosed' => ['<!-- wp:group --><div><!-- wp:paragraph --><p>x</p>'],
    'stray closer' => ['<p>x</p><!-- /wp:paragraph -->'],
    'wrong closer' => ['<!-- wp:group --><!-- /wp:columns -->'],
    'bad json' => ['<!-- wp:paragraph {"a":} --><p>x</p><!-- /wp:paragraph -->'],
    'empty' => [''],
    'only space' => ["  \n "],
]);

test('blocks added through the editor are written inside their parent', function () use ($markup) {
    $wp = new WpMarkup();
    $tree = $wp->parse($markup);
    $added = new Block('new', BlockName::fromString('core/paragraph'), 1, ['wpContent' => ['<p>Added</p>']]);

    $written = $wp->serialize($tree->insert($added, Position::inside('wp-1', 2)));

    expect($written)->toContain('<p>Added</p><!-- /wp:paragraph --></div>');
});

test('a WordPress block type is described as a block type any builder can show', function () {
    $type = (new WpBlockTypeBridge())->toBlockType([
        'name' => 'acme/card',
        'title' => 'Card',
        'description' => 'A card.',
        'category' => 'widgets',
        'keywords' => ['box'],
        'attributes' => [
            'title' => ['type' => 'string', 'default' => ''],
            'level' => ['type' => 'number', 'default' => 2],
            'align' => ['type' => 'string', 'enum' => ['left', 'right']],
            'items' => ['type' => 'array', 'items' => ['type' => 'string']],
            'content' => ['type' => 'rich-text', 'source' => 'rich-text', 'selector' => 'p'],
            'weird' => ['type' => ['string', 'object'], 'role' => 'content'],
        ],
        'parent' => ['core/group'],
        'allowed_blocks' => ['core/paragraph'],
        'provides_context' => ['acme/cardId' => 'title'],
        'uses_context' => ['postId'],
        'example' => ['attributes' => ['title' => 'Example']],
    ]);

    expect($type->name->toString())->toBe('acme/card')
        ->and($type->catalogue->title)->toBe('Card')
        ->and($type->settingsSchema['properties']['title'])->toBe(['type' => 'string', 'default' => ''])
        ->and($type->settingsSchema['properties']['level'])->toBe(['type' => 'number', 'default' => 2])
        ->and($type->settingsSchema['properties']['align']['enum'])->toBe(['left', 'right'])
        ->and($type->settingsSchema['properties']['content'])->toBe(['type' => 'string'])
        ->and(array_keys($type->settingsSchema['properties']))->toContain('wpContent', 'metadata')
        ->and($type->children->allows(BlockName::fromString('core/paragraph')))->toBeTrue()
        ->and($type->children->allows(BlockName::fromString('core/image')))->toBeFalse()
        ->and($type->parents->allows(BlockName::fromString('core/group')))->toBeTrue()
        ->and($type->parents->allows(null))->toBeFalse()
        ->and($type->provides)->toBe(['acme/cardId' => 'title'])
        ->and($type->examples)->toBe([['title' => 'Example']]);
});

test('a type with no title, an odd attribute or no child list still bridges', function () {
    $bridge = new WpBlockTypeBridge();

    $bare = $bridge->toBlockType(['name' => 'acme/bare']);
    $odd = $bridge->toBlockType(['name' => 'acme/odd', 'attributes' => ['x' => 'nonsense', 'y' => ['type' => 'wat']]]);

    expect($bare->catalogue->title)->toBe('acme/bare')
        ->and($bare->children->allows(BlockName::fromString('core/anything')))->toBeTrue()
        ->and($odd->settingsSchema['properties']['x'])->toBe([])
        ->and($odd->settingsSchema['properties']['y'])->toBe([]);
});

test('a post is a document: read from its markup, drawn, edited and written back', function () use ($markup, $posts) {
    $store = $posts(['12' => $markup]);
    $c = new BlockServiceContainer();
    (new AppServiceProvider())->register($c);
    $provider = new WpServiceProvider(
        $store,
        new class implements WpBlockTypesInterface {
            public function all(): array
            {
                return [
                    ['name' => 'core/group'],
                    ['name' => 'core/paragraph'],
                    ['name' => 'core/heading', 'attributes' => ['level' => ['type' => 'number', 'default' => 2]]],
                    ['name' => 'acme/card', 'attributes' => ['title' => ['type' => 'string']], 'dynamic' => true],
                ];
            }
        },
        new class implements WpRendererInterface {
            public function render(string $name, array $attributes, string $content): string
            {
                return sprintf('<div class="card">%s</div>', htmlspecialchars((string) ($attributes['title'] ?? '')));
            }
        },
        new class implements WpBindingsInterface {
            public function sources(): array
            {
                return ['core/post-meta'];
            }

            public function value(string $source, array $arguments, array $context): mixed
            {
                return $arguments['key'] === 'sub' ? 'From meta' : null;
            }
        },
    );
    $provider->register($c);
    $provider->boot($c);
    $documents = $c->get(DocumentRepositoryInterface::class);

    $document = $documents->find('12');
    $result = $c->get(DocumentRendererInterface::class)->render($document, new RenderRequest());
    $edit = $c->get(DocumentEditorInterface::class)->updateSettings(
        $document,
        'wp-0',
        [...$document->tree->find('wp-0')->settings, 'level' => 2],
    );
    $documents->save($edit->document);

    expect($document->kind)->toBe('page')
        ->and($result->issues)->toBe([])
        ->and($result->output)->toBe(
            "\n<h3 class=\"wp-block-heading\">Hello</h3>\n"
            . "\n<div class=\"wp-block-group\">\n<p>One</p>\n"
            . "<div class=\"card\">A &lt;b&gt; -- card</div>\n\n</div>\n"
        )
        ->and($edit->isAccepted())->toBeTrue()
        ->and($store->content['12'])->toContain('<!-- wp:heading {"level":2,')
        ->and($documents->find('gone'))->toBeNull()
        ->and($documents->ids())->toBe(['12'])
        ->and($c->get(BlockTypeRegistryInterface::class)->has(BlockName::fromString('acme/card')))->toBeTrue()
        ->and($c->get(DataSourceRegistryInterface::class)->get('core/post-meta'))->not->toBeNull();
});

test('a WordPress binding source supplies a value, or none', function () use ($posts) {
    $c = new BlockServiceContainer();
    (new AppServiceProvider())->register($c);
    $provider = new WpServiceProvider(
        $posts(),
        new class implements WpBlockTypesInterface {
            public function all(): array
            {
                return [];
            }
        },
        new class implements WpRendererInterface {
            public function render(string $name, array $attributes, string $content): string
            {
                return $content;
            }
        },
        new class implements WpBindingsInterface {
            public function sources(): array
            {
                return ['core/post-meta'];
            }

            public function value(string $source, array $arguments, array $context): mixed
            {
                return ($arguments['key'] ?? '') === 'sub' ? 'From meta' : null;
            }
        },
    );
    $provider->register($c);
    $provider->boot($c);
    $source = $c->get(DataSourceRegistryInterface::class)->get('core/post-meta');
    $context = \SpeedySpec\Block\Domain\ValueObjects\Context::empty();
    $mode = \SpeedySpec\Block\Domain\ValueObjects\Mode::Front;

    expect($source->resolve(['key' => 'sub'], $context, $mode)->get())->toBe('From meta')
        ->and($source->resolve(['key' => 'other'], $context, $mode)->hasValue())->toBeFalse();
});
