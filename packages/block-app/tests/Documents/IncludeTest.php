<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Documents\DocumentsPackage;
use SpeedySpec\Block\App\Flow\FlowPackage;
use SpeedySpec\Block\App\Registries\BlockTemplateLibraryRegistry;
use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateLibraryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateLibraryRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\CollectionSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentIncluderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

$text = fn (string $id, string $value) => block($id, 'acme/text', ['text' => $value]);
$include = fn (string $id, string $document) => block($id, 'core/include', ['document' => $document]);
$stored = fn (string $id, Block ...$roots) => new Document($id, 'part', new BlockTree(...$roots));
$host = function (Document ...$documents): array {
    [$renderer, $c] = renderer(function (BlockServiceContainer $c) use ($documents) {
        $loader = $c->get(BlockPackageLoaderInterface::class);
        $loader->load(FlowPackage::from($c));
        $loader->load(DocumentsPackage::from($c));
        types($c)->add(type('acme/text', ['assets' => ['text-style'], 'settings' => [
            'type' => 'object',
            'properties' => ['text' => ['type' => 'string', 'default' => '', 'maxLength' => 20]],
        ]]));
        outputs($c)->set(BlockName::fromString('acme/text'), tag('p'));

        foreach ($documents as $document)
        {
            $c->get(DocumentRepositoryInterface::class)->save($document);
        }
    });

    return [$renderer, $c, $c->get(DocumentIncluderInterface::class)];
};

test('an included document is drawn in place of the block that includes it', function () use (
    $host,
    $text,
    $include,
    $stored,
) {
    [$renderer] = $host($stored('header', $text('h', 'Site')), $stored('footer', $text('f', 'Bye')));

    $result = $renderer->render(
        doc($include('a', 'header'), $text('b', 'Body'), $include('c', 'footer')),
        new RenderRequest(),
    );

    expect($result->output)->toBe('<p>Site</p><p>Body</p><p>Bye</p>')->and($result->issues)->toBe([]);
});

test('an included document can include another', function () use ($host, $text, $include, $stored) {
    [$renderer] = $host(
        $stored('outer', $text('o', 'outer'), $include('i', 'inner')),
        $stored('inner', $text('n', 'inner')),
    );

    expect($renderer->render(doc($include('a', 'outer')), new RenderRequest())->output)
        ->toBe('<p>outer</p><p>inner</p>');
});

test('documents that include each other stop instead of looping, and say so', function () use (
    $host,
    $text,
    $include,
    $stored,
) {
    [$renderer, , $includer] = $host(
        $stored('self', $text('s', 'self'), $include('again', 'self')),
        $stored('ping', $text('p', 'ping'), $include('to-pong', 'pong')),
        $stored('pong', $text('q', 'pong'), $include('to-ping', 'ping')),
    );

    $self = $renderer->render(doc($include('a', 'self')), new RenderRequest())->output;
    $kinds = kinds($includer->issues());
    $includer->clear();
    $mutual = $renderer->render(doc($include('a', 'ping')), new RenderRequest())->output;

    expect($self)->toBe('<p>self</p>')
        ->and($kinds)->toBe([IssueKind::DocumentLoop])
        ->and($mutual)->toBe('<p>ping</p><p>pong</p>')
        ->and(idsAndKinds($includer->issues()))->toBe([['to-ping', IssueKind::DocumentLoop]]);
});

test('a document that is not stored draws nothing and is reported on the block', function () use (
    $host,
    $text,
    $include,
) {
    [$renderer] = $host();

    $result = $renderer->render(doc($include('a', 'gone'), $text('b', 'Body')), new RenderRequest());
    $unset = $renderer->render(doc($include('a', ''), $text('b', 'Body')), new RenderRequest());

    expect($result->output)->toBe('<p>Body</p>')
        ->and(idsAndKinds($result->issues))->toBe([['a', IssueKind::UnknownDocument]])
        ->and($result->issues[0]->path)->toBe('document')
        ->and($unset->output)->toBe('<p>Body</p>')
        ->and($unset->issues)->toBe([]);
});

test('the assets and issues of an included document reach the page that includes it', function () use (
    $host,
    $text,
    $include,
    $stored,
) {
    [$renderer, , $includer] = $host($stored('part', $text('ok', 'fine'), $text('bad', str_repeat('x', 30))));

    $result = $renderer->render(doc($include('a', 'part')), new RenderRequest());

    expect($result->output)->toBe('<p>fine</p>')
        ->and($result->assets)->toBe(['text-style'])
        ->and(idsAndKinds($includer->issues()))->toBe([['bad', IssueKind::InvalidSettings]]);

    $includer->clear();

    expect($includer->issues())->toBe([]);
});

test('an editor sees the included document inside a wrapper that names it', function () use (
    $host,
    $text,
    $include,
    $stored,
) {
    [$renderer] = $host($stored('header', $text('h', 'Site')));

    $admin = $renderer->render(doc($include('a', 'header'), $include('b', '<x>')), new RenderRequest(Mode::Admin));

    expect($admin->output)->toContain('<div data-included-document="header"><p>Site</p></div>')
        ->not->toContain('<x>');
});

test('an included document sees the item of the repeat it is included in', function () use ($host, $include, $stored) {
    $name = block('n', 'acme/text')
        ->withBinding(SettingPath::fromString('text'), Binding::to('core/current-item', ['field' => 'name']));
    [$renderer, $c] = $host($stored('card', $name));
    $c->get(CollectionSourceRegistryInterface::class)
        ->add(collection('acme/people', fn () => [['name' => 'Ann'], ['name' => 'Bob']]));

    $page = doc(block('r', 'core/repeat', ['source' => 'acme/people'], $include('i', 'card')));

    expect($renderer->render($page, new RenderRequest())->output)->toBe('<p>Ann</p><p>Bob</p>');
});

test('a page cannot include without end', function () use ($host, $text, $include, $stored) {
    $many = array_map(fn (int $n) => $include('i' . $n, 'leaf'), range(1, 30));
    [$renderer, , $includer] = $host(
        $stored('leaf', $text('l', '.')),
        $stored('wide', ...$many),
        $stored('wider', ...array_map(fn (int $n) => $include('w' . $n, 'wide'), range(1, 30))),
    );

    $output = $renderer->render(doc($include('a', 'wider')), new RenderRequest())->output;

    expect(substr_count($output, '<p>.</p>'))->toBeLessThanOrEqual(500)
        ->and(in_array(IssueKind::DocumentLoop, kinds($includer->issues()), true))->toBeTrue();
});

test('template libraries give a builder one list of collections to offer', function () {
    [, $c] = renderer(fn ($c) => $c->get(BlockPackageLoaderInterface::class)->load(DocumentsPackage::from($c)));
    $template = fn (string $name) => new BlockTemplate($name, ucfirst($name), new BlockTree(block('a')));
    $c->get(BlockTemplateRegistryInterface::class)->add($template('acme/hero'));
    $c->get(BlockTemplateRepositoryInterface::class)->save($template('acme/saved'));
    $libraries = $c->get(BlockTemplateLibraryRegistryInterface::class);
    $libraries->add(new class($template('shop/card')) implements BlockTemplateLibraryInterface {
        public function __construct(private readonly BlockTemplate $template)
        {
        }

        public function name(): string
        {
            return 'shop/library';
        }

        public function title(): string
        {
            return 'Shop';
        }

        public function all(): array
        {
            return [$this->template];
        }

        public function find(string $name): ?BlockTemplate
        {
            return $name === $this->template->name ? $this->template : null;
        }
    });
    $names = fn (BlockTemplateLibraryInterface $library) => array_map(fn ($t) => $t->name, $library->all());

    expect(array_map(fn ($library) => [$library->name(), $library->title()], $libraries->all()))
        ->toBe([['core/registered', 'Built in'], ['core/saved', 'Saved'], ['shop/library', 'Shop']])
        ->and($names($libraries->get('core/registered')))->toBe(['acme/hero'])
        ->and($names($libraries->get('core/saved')))->toBe(['acme/saved'])
        ->and($libraries->get('core/saved')->find('acme/saved')->name)->toBe('acme/saved')
        ->and($libraries->get('shop/library')->find('nope'))->toBeNull()
        ->and($libraries->get('nope'))->toBeNull();

    $libraries->remove('shop/library');

    expect(count($libraries->all()))->toBe(2);
});
