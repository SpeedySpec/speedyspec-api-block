<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\App\Flow\FlowPackage;
use SpeedySpec\Block\App\Presentation\PresentationPackage;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BuilderAccessInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DesignTokenRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BuilderAbility;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Infra\Memory\ArrayDataSource;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;
use SpeedySpec\Block\Presentation\ApiRequest;
use SpeedySpec\Block\Presentation\BuilderApi;
use SpeedySpec\Block\Presentation\PresentationServiceProvider;

$block = fn (string $id, string $type, array $settings = [], Block ...$children) => new Block(
    $id,
    BlockName::fromString($type),
    1,
    $settings,
    [],
    [],
    array_values($children),
);
$host = function (array $abilities = ['view', 'edit', 'manage-templates'], bool $editor = false) use ($block): array {
    $c = new BlockServiceContainer();

    if ($editor)
    {
        $c->add(\SpeedySpec\Block\Domain\Contracts\EditorInterface::class, fn () => new class implements
            \SpeedySpec\Block\Domain\Contracts\EditorInterface {
            public function name(): string
            {
                return 'acme/editor';
            }

            public function title(): string
            {
                return 'Acme editor';
            }

            public function open(\SpeedySpec\Block\Domain\ValueObjects\EditorRequest $request): \SpeedySpec\Block\Domain\ValueObjects\EditorPage
            {
                return new \SpeedySpec\Block\Domain\ValueObjects\EditorPage([], '');
            }
        });
    }

    (new MemoryServiceProvider())->register($c);
    (new AppServiceProvider())->register($c);
    (new PresentationServiceProvider())->register($c);
    $c->add(BuilderAccessInterface::class, fn () => new class($abilities) implements BuilderAccessInterface {
        public array $asked = [];

        public function __construct(private readonly array $abilities)
        {
        }

        public function allows(BuilderAbility $ability, ?string $documentId = null): bool
        {
            $this->asked[] = [$ability->value, $documentId];

            return in_array($ability->value, $this->abilities, true) && $documentId !== 'locked';
        }
    });
    $loader = $c->get(BlockPackageLoaderInterface::class);
    $loader->load(FlowPackage::from($c));
    $loader->load(PresentationPackage::from($c));
    $types = $c->get(BlockTypeRegistryInterface::class);
    $types->add(BlockType::fromArray(['name' => 'acme/box', 'title' => 'Box', 'children' => true]));
    $types->add(BlockType::fromArray(['name' => 'acme/text', 'title' => 'Text', 'settings' => [
        'type' => 'object',
        'properties' => [
            'text' => ['type' => 'string', 'default' => '', 'maxLength' => 20],
            'note' => ['type' => 'string', 'default' => '', 'x-private' => true],
        ],
    ], 'transforms' => [['to' => 'acme/box', 'settings' => []]]]));
    $draw = fn (string $tag) => new class($tag) implements BlockOutputInterface {
        public function __construct(private readonly string $tag)
        {
        }

        public function render(RenderInput $input): string
        {
            return sprintf(
                '<%1$s%2$s>%3$s%4$s</%1$s>',
                $this->tag,
                $input->escaper->attributes($input->attributes),
                $input->escaper->text((string) ($input->view->values['text'] ?? '')),
                $input->children(),
            );
        }
    };
    $outputs = $c->get(BlockOutputRegistryInterface::class);
    $outputs->set(BlockName::fromString('acme/box'), $draw('div'));
    $outputs->set(BlockName::fromString('acme/text'), $draw('p'));
    $documents = $c->get(DocumentRepositoryInterface::class);
    $documents->save(new Document('home', 'page', new BlockTree(
        $block('b', 'acme/box', [], $block('t', 'acme/text', ['text' => 'Hi', 'note' => 'secret'])),
    )));
    $documents->save(new Document('locked', 'page', new BlockTree($block('x', 'acme/text'))));

    return [$c->get(BuilderApi::class), $c];
};
$call = function (BuilderApi $api, string $method, string $path, array $query = [], ?array $body = null): array {
    $response = $api->handle(new ApiRequest($method, $path, $query, $body === null ? '' : json_encode($body)));

    return [$response->status, json_decode($response->json(), true), $response->json()];
};

test('the catalogue lists block types, and narrows to what fits at a position', function () use ($host, $call) {
    [$api] = $host();

    [$status, $all] = $call($api, 'GET', '/catalogue');
    [, $inside] = $call($api, 'GET', '/catalogue', ['document' => 'home', 'parent' => 'b', 'index' => '0']);
    [$bad] = $call($api, 'GET', '/catalogue', ['document' => 'home', 'parent' => 'nope', 'index' => '0']);

    expect($status)->toBe(200)
        ->and(array_column($all['blocks'], 'name'))->toBe(['core/repeat', 'acme/box', 'acme/text'])
        ->and($all['blocks'][2]['settings']['properties']['text']['maxLength'])->toBe(20)
        ->and($all['blocks'][2]['transforms'])->toBe(['acme/box'])
        ->and(array_column($inside['blocks'], 'name'))->toBe(['core/repeat', 'acme/box', 'acme/text'])
        ->and($bad)->toBe(200);
});

test('the vocabulary gives a builder the breakpoints, tokens, conditions and collections', function () use (
    $host,
    $call,
) {
    [$api, $c] = $host();
    $c->get(DesignTokenRegistryInterface::class)->set('color.primary', '#0055ff');

    [$status, $body] = $call($api, 'GET', '/vocabulary');

    expect($status)->toBe(200)
        ->and($body['breakpoints'])->toBe([
            ['name' => 'tablet', 'minWidth' => 768],
            ['name' => 'desktop', 'minWidth' => 1024],
        ])
        ->and($body['tokens'])->toBe(['color.primary' => '#0055ff'])
        ->and(array_column($body['conditions'], 'name'))->toBe(['core/current-item-is'])
        ->and($body['conditions'][0]['arguments']['required'])->toBe(['field'])
        ->and($body['collections'])->toBe([])
        ->and(array_column($body['decorators'], 'namespace'))->toBe(['core/visibility', 'core/style']);
});

test('the vocabulary lists the data sources a setting can be bound to, with what each takes', function () use (
    $host,
    $call,
) {
    [$api, $c] = $host();
    $c->get(DataSourceRegistryInterface::class)
        ->add(new ArrayDataSource('acme/site', ['name' => 'x'], ['type' => 'string']));

    [, $body] = $call($api, 'GET', '/vocabulary');
    $site = array_values(array_filter(
        $body['sources'],
        static fn (array $source): bool => $source['name'] === 'acme/site',
    ));

    expect(array_column($body['sources'], 'name'))->toBe(['acme/site', 'core/current-item'])
        ->and($site[0]['arguments']['properties'])->toHaveKey('key')
        ->and($site[0]['value'])->toBe(['type' => 'string']);
});

test('a document is read with its private settings and its issues, for someone who may edit it', function () use (
    $host,
    $call,
) {
    [$api] = $host();

    [$status, $body, $json] = $call($api, 'GET', '/documents/home');
    [$list, $ids] = $call($api, 'GET', '/documents');

    expect($status)->toBe(200)
        ->and($body['document']['id'])->toBe('home')
        ->and($body['document']['blocks'][0]['children'][0]['settings'])->toBe(['text' => 'Hi', 'note' => 'secret'])
        ->and($body['issues'])->toBe([])
        ->and($json)->toContain('"bindings":{}')
        ->and($list)->toBe(200)
        ->and($ids['documents'])->toBe(['home']);
});

test('nothing is served without the ability it needs', function () use ($host, $call) {
    [$viewer] = $host(['view']);
    [$nobody] = $host([]);

    expect($call($viewer, 'GET', '/documents/home')[0])->toBe(403)
        ->and($call($viewer, 'GET', '/catalogue')[0])->toBe(403)
        ->and($call($viewer, 'GET', '/documents/home/render')[0])->toBe(200)
        ->and($call($viewer, 'GET', '/documents/home/render', ['mode' => 'admin'])[0])->toBe(403)
        ->and($call($viewer, 'POST', '/documents/home/operations', [], ['operation' => 'remove', 'block' => 't'])[0])
        ->toBe(403)
        ->and($call($viewer, 'DELETE', '/documents/home')[0])->toBe(403)
        ->and($call($viewer, 'POST', '/templates', [], ['name' => 'acme/x'])[0])->toBe(403)
        ->and($call($nobody, 'GET', '/documents/home/render')[0])->toBe(403)
        ->and($call($nobody, 'GET', '/documents')[0])->toBe(403);
});

test('access is asked per document, and the list leaves out what may not be opened', function () use ($host, $call) {
    [$api] = $host();

    expect($call($api, 'GET', '/documents/locked')[0])->toBe(403)
        ->and($call($api, 'GET', '/documents/locked/render')[0])->toBe(403)
        ->and($call($api, 'GET', '/documents')[1]['documents'])->toBe(['home']);
});

test('without an access rule from the host, everything is refused', function () use ($call) {
    $c = new BlockServiceContainer();
    (new MemoryServiceProvider())->register($c);
    (new AppServiceProvider())->register($c);
    (new PresentationServiceProvider())->register($c);

    expect($call($c->get(BuilderApi::class), 'GET', '/catalogue')[0])->toBe(403);
});

test('an operation changes the stored document and answers with the result', function () use ($host, $call) {
    [$api, $c] = $host();

    [$status, $body] = $call($api, 'POST', '/documents/home/operations', [], [
        'operation' => 'insert',
        'type' => 'acme/text',
        'position' => ['parent' => 'b', 'index' => 1],
        'settings' => ['text' => 'New'],
    ]);
    $stored = $c->get(DocumentRepositoryInterface::class)->find('home');

    expect($status)->toBe(200)
        ->and($body['affected'])->toHaveCount(1)
        ->and($body['document']['blocks'][0]['children'][1]['settings']['text'])->toBe('New')
        ->and($stored->tree->find($body['affected'][0])->settings)->toBe(['text' => 'New'])
        ->and($body['issues'])->toBe([]);
});

test('a refused operation changes nothing and lists every reason', function () use ($host, $call) {
    [$api, $c] = $host();

    [$status, $body] = $call($api, 'POST', '/documents/home/operations', [], [
        'operation' => 'updateSettings',
        'block' => 't',
        'settings' => ['text' => str_repeat('x', 30)],
    ]);

    expect($status)->toBe(422)
        ->and($body['refusals'][0])->toMatchArray(['kind' => 'invalid-settings', 'block' => 't', 'path' => 'text'])
        ->and($c->get(DocumentRepositoryInterface::class)->find('home')->tree->find('t')->settings['text'])->toBe('Hi');
});

test('every editing operation is reachable', function (array $body, callable $check) use ($host, $call, $block) {
    [$api, $c] = $host();
    $c->get(BlockTemplateRegistryInterface::class)->add(new BlockTemplate(
        'acme/pair',
        'Pair',
        new BlockTree($block('x', 'acme/text'), $block('y', 'acme/text')),
    ));

    [$status, $result] = $call($api, 'POST', '/documents/home/operations', [], $body);

    expect($status)->toBe(200);
    $check($result['document']['blocks'], $result);
})->with([
    'move' => [
        ['operation' => 'move', 'block' => 't', 'position' => ['parent' => null, 'index' => 0]],
        fn (array $blocks) => expect(array_column($blocks, 'id'))->toBe(['t', 'b']),
    ],
    'remove' => [
        ['operation' => 'remove', 'block' => 't'],
        fn (array $blocks) => expect($blocks[0]['children'])->toBe([]),
    ],
    'remove several' => [
        ['operation' => 'removeBlocks', 'ids' => ['t', 'b']],
        fn (array $blocks) => expect($blocks)->toBe([]),
    ],
    'lock several' => [
        ['operation' => 'lockBlocks', 'ids' => ['t', 'b'], 'lock' => ['move' => true, 'remove' => false]],
        fn (array $blocks, array $result) => expect($blocks[0]['children'][0]['extensions']['core/lock'])
            ->toBe(['move' => true, 'remove' => false])
            ->and($result['affected'])->toBe(['t', 'b']),
    ],
    'transform' => [
        ['operation' => 'transform', 'block' => 't', 'type' => 'acme/box'],
        fn (array $blocks, array $result) => expect($blocks[0]['children'][0]['type'])->toBe('acme/box')
            ->and($blocks[0]['children'][0]['id'])->toBe('t')
            ->and($result['affected'])->toBe(['t']),
    ],
    'duplicate' => [
        ['operation' => 'duplicate', 'block' => 't'],
        fn (array $blocks) => expect($blocks[0]['children'])->toHaveCount(2),
    ],
    'paste' => [
        [
            'operation' => 'insertBlocks',
            'position' => ['parent' => null, 'index' => 1],
            'blocks' => [['id' => 't', 'type' => 'acme/text', 'settings' => ['text' => 'pasted']]],
        ],
        fn (array $blocks) => expect($blocks[1]['settings']['text'])->toBe('pasted')
            ->and($blocks[1]['id'])->not->toBe('t'),
    ],
    'template' => [
        ['operation' => 'insertTemplate', 'template' => 'acme/pair', 'position' => ['parent' => 'b', 'index' => 0]],
        fn (array $blocks) => expect($blocks[0]['children'])->toHaveCount(3),
    ],
    'extension' => [
        [
            'operation' => 'updateExtension',
            'block' => 't',
            'namespace' => 'core/style',
            'settings' => ['margin' => '0'],
        ],
        fn (array $blocks) => expect($blocks[0]['children'][0]['extensions'])
            ->toBe(['core/style' => ['margin' => '0']]),
    ],
    'bind and clear' => [
        [
            'operation' => 'setBinding',
            'block' => 't',
            'path' => 'text',
            'binding' => ['source' => 'core/current-item', 'arguments' => ['field' => 'name'], 'fallback' => 'x'],
        ],
        fn (array $blocks) => expect($blocks[0]['children'][0]['bindings']['text']['fallback'])->toBe('x'),
    ],
    'clear' => [
        ['operation' => 'clearBinding', 'block' => 't', 'path' => 'text'],
        fn (array $blocks) => expect($blocks[0]['children'][0]['bindings'])->toBe([]),
    ],
]);

test('a request that makes no sense is a bad request, never a server error', function (
    string $method,
    string $path,
    ?array $body,
    int $expected,
) use ($host, $call) {
    [$api] = $host();

    expect($call($api, $method, $path, [], $body)[0])->toBe($expected);
})->with([
    ['POST', '/documents/home/operations', ['operation' => 'explode'], 400],
    ['POST', '/documents/home/operations', [], 400],
    ['POST', '/documents/home/operations', ['operation' => 'insert', 'type' => 'Not A Name'], 400],
    ['POST', '/documents/home/operations', ['operation' => 'insert', 'type' => 'acme/text', 'position' => 'x'], 400],
    ['POST', '/documents/home/operations', ['operation' => 'move', 'block' => 't', 'position' => ['index' => -1]], 400],
    ['POST', '/documents/home/operations', ['operation' => 'setBinding', 'block' => 't', 'path' => '..'], 400],
    ['POST', '/documents/home/operations', ['operation' => 'insertBlocks', 'blocks' => 'x'], 400],
    ['POST', '/documents/home/operations', ['operation' => 'removeBlocks', 'ids' => 't'], 400],
    ['POST', '/documents/home/operations', ['operation' => 'removeBlocks', 'ids' => ['t', 3]], 400],
    ['POST', '/documents/home/operations', ['operation' => 'removeBlocks', 'ids' => ['t', '']], 400],
    ['POST', '/documents/home/operations', ['operation' => 'removeBlocks', 'ids' => ['a' => 't']], 400],
    ['POST', '/documents/home/operations', ['operation' => 'lockBlocks', 'ids' => ['t']], 400],
    ['POST', '/documents/home/operations', ['operation' => 'lockBlocks', 'ids' => ['t'], 'lock' => ['move' => 1]], 400],
    [
        'POST',
        '/documents/home/operations',
        ['operation' => 'insertTemplate', 'template' => 'acme/gone', 'position' => ['index' => 0]],
        404,
    ],
    ['POST', '/documents/gone/operations', ['operation' => 'remove', 'block' => 't'], 404],
    ['GET', '/documents/gone', null, 404],
    ['GET', '/nowhere', null, 404],
    ['PATCH', '/catalogue', null, 404],
    ['POST', '/documents', ['id' => '', 'kind' => 'page'], 400],
    ['POST', '/documents', ['id' => 'home', 'kind' => 'page'], 409],
]);

test('a body that is not a JSON object is a bad request', function () use ($host) {
    [$api] = $host();

    $status = fn (string $body) => $api
        ->handle(new ApiRequest('POST', '/documents/home/operations', [], $body))->status;

    expect($status('{not json'))->toBe(400)->and($status('[1,2]'))->toBe(400)->and($status('"text"'))->toBe(400);
});

test('a document can be created and deleted', function () use ($host, $call) {
    [$api, $c] = $host();

    [$created, $body] = $call($api, 'POST', '/documents', [], ['id' => 'about', 'kind' => 'page']);
    [$deleted] = $call($api, 'DELETE', '/documents/about');

    expect($created)->toBe(201)
        ->and($body['document'])->toMatchArray(['id' => 'about', 'kind' => 'page', 'blocks' => []])
        ->and($deleted)->toBe(204)
        ->and($c->get(DocumentRepositoryInterface::class)->find('about'))->toBeNull();
});

test('a page is drawn for a visitor without private settings, and with its stylesheet', function () use (
    $host,
    $call,
) {
    [$api] = $host();
    $call($api, 'POST', '/documents/home/operations', [], [
        'operation' => 'updateExtension',
        'block' => 't',
        'namespace' => 'core/style',
        'settings' => ['margin' => '0 auto'],
    ]);

    [$status, $front, $json] = $call($api, 'GET', '/documents/home/render');
    [, $again] = $call($api, 'GET', '/documents/home/render');

    expect($status)->toBe(200)
        ->and($front['output'])->toMatch('#^<div><p class="b-[0-9a-f]{10}">Hi</p></div>$#')
        ->and($front['css'])->toMatch('#^\.b-[0-9a-f]{10}\{margin:0 auto\}$#')
        ->and($again['css'])->toBe($front['css'])
        ->and($front['issues'])->toBe([])
        ->and($json)->not->toContain('secret');
});

test('one block is previewed on its own, in either mode', function () use ($host, $call) {
    [$api] = $host();

    [$status, $body] = $call($api, 'POST', '/documents/home/preview', [], ['block' => 't', 'mode' => 'admin']);
    [$missing] = $call($api, 'POST', '/documents/home/preview', [], ['block' => 'nope']);
    [$mode] = $call($api, 'POST', '/documents/home/preview', [], ['block' => 't', 'mode' => 'sideways']);

    expect($status)->toBe(200)
        ->and($body['output'])->toBe('<p>Hi</p>')
        ->and($body['block'])->toBe('t')
        ->and($missing)->toBe(404)
        ->and($mode)->toBe(400);
});

test('the template list says whether the person may save and remove templates', function () use ($host, $call) {
    [$manager] = $host();
    [$editor] = $host(['view', 'edit']);

    expect($call($manager, 'GET', '/templates')[1]['manage'])->toBeTrue()
        ->and($call($editor, 'GET', '/templates')[1]['manage'])->toBeFalse();
});

test('templates are listed by library, saved from a block, and removed', function () use ($host, $call) {
    [$api] = $host();

    [$saved] = $call($api, 'POST', '/templates', [], [
        'name' => 'acme/greeting',
        'title' => 'Greeting',
        'document' => 'home',
        'block' => 'b',
    ]);
    [, $list] = $call($api, 'GET', '/templates');
    [$removed] = $call($api, 'DELETE', '/templates/acme/greeting');
    [, $after] = $call($api, 'GET', '/templates');
    [$bad] = $call($api, 'POST', '/templates', [], [
        'name' => 'Bad',
        'title' => 'x',
        'document' => 'home',
        'block' => 'b',
    ]);

    expect($saved)->toBe(201)
        ->and(array_column($list['libraries'], 'name'))->toBe(['core/registered', 'core/saved'])
        ->and($list['libraries'][1]['templates'][0])->toMatchArray(['name' => 'acme/greeting', 'title' => 'Greeting'])
        ->and($list['libraries'][1]['templates'][0]['blocks'][0]['children'][0]['settings']['text'])->toBe('Hi')
        ->and($removed)->toBe(204)
        ->and($after['libraries'][1]['templates'])->toBe([])
        ->and($bad)->toBe(400);
});

test('a server failure is reported without its details', function () use ($host, $call) {
    [$api, $c] = $host();
    $c->get(BlockOutputRegistryInterface::class);
    $broken = new BuilderApi(new class($c) extends BlockServiceContainer {
        public function __construct(private readonly BlockServiceContainer $inner)
        {
        }

        public function get(string $contract): object
        {
            if ($contract === DocumentRepositoryInterface::class)
            {
                throw new RuntimeException('/var/secret/path is not writable');
            }

            return $this->inner->get($contract);
        }
    });

    [$status, , $json] = $call($broken, 'GET', '/documents/home');

    expect($status)->toBe(500)->and($json)->not->toContain('secret');
});

test('an included document is only drawn for someone who may see it', function () use ($host, $call) {
    [$api, $c] = $host();
    $c->get(BlockPackageLoaderInterface::class)
        ->load(\SpeedySpec\Block\App\Documents\DocumentsPackage::from($c));
    $include = fn (string $id) => $call($api, 'POST', '/documents/home/operations', [], [
        'operation' => 'insert',
        'type' => 'core/include',
        'position' => ['parent' => null, 'index' => 0],
        'settings' => ['document' => $id],
    ]);

    [$locked, $lockedBody] = $include('locked');
    [$missing, $missingBody] = $include('never-stored');

    expect($locked)->toBe(422)
        ->and($missing)->toBe(422)
        ->and($lockedBody['refusals'][0]['kind'])->toBe($missingBody['refusals'][0]['kind'])
        ->and(str_replace('locked', 'X', $lockedBody['refusals'][0]['message']))
        ->toBe(str_replace('never-stored', 'X', $missingBody['refusals'][0]['message']));

    // Even when the block is already stored, drawing asks again.
    $documents = $c->get(DocumentRepositoryInterface::class);
    $home = $documents->find('home');
    $smuggled = new Block('inc', BlockName::fromString('core/include'), 1, ['document' => 'locked']);
    $first = \SpeedySpec\Block\Domain\ValueObjects\Position::root(0);
    $documents->save($home->withTree($home->tree->insert($smuggled, $first)));
    $documents->save(new Document('locked', 'page', new BlockTree(
        new Block('x', BlockName::fromString('acme/text'), 1, ['text' => 'LOCKEDTEXT']),
    )));

    expect($call($api, 'GET', '/documents/home/render')[2])->not->toContain('LOCKEDTEXT')
        ->and($call($api, 'GET', '/documents/home/render', ['mode' => 'admin'])[2])->not->toContain('LOCKEDTEXT')
        ->and($call($api, 'POST', '/documents/home/preview', [], ['block' => 'inc'])[2])->not->toContain('LOCKEDTEXT');
});

test('an update with no settings in it is a mistake, not an instruction to clear them', function () use ($host, $call) {
    [$api, $c] = $host();
    $send = fn (array $body) => $call($api, 'POST', '/documents/home/operations', [], $body)[0];

    expect($send(['operation' => 'updateSettings', 'block' => 't']))->toBe(400)
        ->and($send(['operation' => 'updateSettings', 'block' => 't', 'settings' => null]))->toBe(400)
        ->and($send(['operation' => 'updateExtension', 'block' => 't', 'namespace' => 'core/style']))->toBe(400)
        ->and($c->get(DocumentRepositoryInterface::class)->find('home')->tree->find('t')->settings['text'])->toBe('Hi')
        ->and($send(['operation' => 'updateSettings', 'block' => 't', 'settings' => []]))->toBe(200)
        ->and($send(['operation' => 'insert', 'type' => 'acme/box', 'position' => ['index' => 0]]))->toBe(200);
});

test('a template saved from a block does not carry its private settings', function () use ($host, $call) {
    [$api] = $host();

    $call($api, 'POST', '/templates', [], ['name' => 'acme/t', 'title' => 'T', 'document' => 'home', 'block' => 'b']);
    [, $list, $json] = $call($api, 'GET', '/templates');

    expect($json)->not->toContain('secret')
        ->and($list['libraries'][1]['templates'][0]['blocks'][0]['children'][0]['settings'])->toBe(['text' => 'Hi']);
});

test('with no editor set up, an editor\'s drawing carries no editing marks', function () use ($host, $call) {
    [$api] = $host();

    [, $admin] = $call($api, 'GET', '/documents/home/render', ['mode' => 'admin']);
    [, $preview] = $call($api, 'POST', '/documents/home/preview', [], ['block' => 't', 'mode' => 'admin']);

    expect($admin['output'])->toBe('<div><p>Hi</p></div>')->and($preview['output'])->toBe('<p>Hi</p>');
});

test('an editor\'s drawing names each block on its element, and a visitor\'s does not', function () use ($host, $call) {
    [$api, $c] = $host(editor: true);

    [, $admin] = $call($api, 'GET', '/documents/home/render', ['mode' => 'admin']);
    [, $front] = $call($api, 'GET', '/documents/home/render');
    [, $preview] = $call($api, 'POST', '/documents/home/preview', [], ['block' => 't', 'mode' => 'admin']);

    expect($admin['output'])->toBe('<div data-block-id="b"><p data-block-id="t">Hi</p></div>')
        ->and($front['output'])->toBe('<div><p>Hi</p></div>')
        ->and($preview['output'])->toBe('<p data-block-id="t">Hi</p>');
});

test('a whole earlier state of a document can be restored, and is checked like any edit', function () use (
    $host,
    $call,
) {
    [$api, $c] = $host();
    [, $before, $raw] = $call($api, 'GET', '/documents/home');
    $call($api, 'POST', '/documents/home/operations', [], ['operation' => 'remove', 'block' => 't']);

    // Sent back as a browser would: decoded as objects, so an empty map stays a map.
    [$status, $body] = $call($api, 'POST', '/documents/home/operations', [], [
        'operation' => 'restore',
        'blocks' => json_decode($raw)->document->blocks,
    ]);
    [$bad] = $call($api, 'POST', '/documents/home/operations', [], [
        'operation' => 'restore',
        'blocks' => [['id' => 'x', 'type' => 'gone/away']],
    ]);
    [$twice] = $call($api, 'POST', '/documents/home/operations', [], [
        'operation' => 'restore',
        'blocks' => [['id' => 'x', 'type' => 'acme/box'], ['id' => 'x', 'type' => 'acme/box']],
    ]);
    [$missing] = $call($api, 'POST', '/documents/home/operations', [], ['operation' => 'restore']);
    $stored = $c->get(DocumentRepositoryInterface::class)->find('home');

    expect($status)->toBe(200)
        ->and($body['document']['blocks'])->toBe($before['document']['blocks'])
        ->and($stored->tree->find('t')->settings)->toBe(['text' => 'Hi', 'note' => 'secret'])
        ->and($bad)->toBe(422)
        ->and($twice)->toBe(400)
        ->and($missing)->toBe(400)
        ->and($stored->tree->ids())->toBe(['b', 't']);
});

test('the catalogue route lists each package that defined a block once, in load order', function () use (
    $host,
    $call,
) {
    [$api] = $host();

    [, $body] = $call($api, 'GET', '/catalogue');

    expect(array_column($body['packages'], 'name'))->toBe(['core/flow'])
        ->and($body['packages'][0]['title'])->not->toBe('')
        ->and(array_key_exists('description', $body['packages'][0]))->toBeTrue()
        ->and(array_column($body['blocks'], 'packageTitle'))->toBe([$body['packages'][0]['title'], '', '']);
});
