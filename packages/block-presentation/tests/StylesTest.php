<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\AssetLocatorInterface;
use SpeedySpec\Block\Domain\Contracts\AssetRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BuilderAccessInterface;
use SpeedySpec\Block\Domain\Contracts\DeclaresAssetsInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\Asset;
use SpeedySpec\Block\Domain\ValueObjects\AssetKind;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BuilderAbility;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;
use SpeedySpec\Block\Presentation\ApiRequest;
use SpeedySpec\Block\Presentation\BuilderApi;
use SpeedySpec\Block\Presentation\PresentationServiceProvider;

/**
 * Builds a host whose one block type declares a style, a style it depends on, a file style and a script.
 */
function stylesHost(?string $located = null): BuilderApi
{
    $c = new BlockServiceContainer();
    (new MemoryServiceProvider())->register($c);
    (new AppServiceProvider())->register($c);
    (new PresentationServiceProvider())->register($c);
    $c->add(BuilderAccessInterface::class, fn () => new class implements BuilderAccessInterface {
        public function allows(BuilderAbility $ability, ?string $documentId = null): bool
        {
            return true;
        }
    });

    if ($located !== null)
    {
        $c->add(AssetLocatorInterface::class, fn () => new class($located) implements AssetLocatorInterface {
            public function __construct(private readonly string $located)
            {
            }

            public function url(string $package, string $path): string
            {
                return $this->located;
            }
        });
    }

    $assets = $c->get(AssetRegistryInterface::class);
    $assets->add(Asset::url('acme/base', AssetKind::Style, 'https://cdn.test/base.css'));
    $look = '.a{color:red}</style><script>x</script>';
    $assets->add(Asset::inline('acme/look', AssetKind::Style, $look, ['acme/base']));
    $assets->add(Asset::file('acme/file', AssetKind::Style, 'acme/blocks', 'css/file.css'));
    $assets->add(Asset::inline('acme/run', AssetKind::Script, 'alert(1)'));
    $c->get(BlockTypeRegistryInterface::class)->add(BlockType::fromArray(['name' => 'acme/box', 'title' => 'Box']));
    $c->get(BlockOutputRegistryInterface::class)->set(
        BlockName::fromString('acme/box'),
        new class implements BlockOutputInterface, DeclaresAssetsInterface {
            public function render(RenderInput $input): string
            {
                return '<div>box</div>';
            }

            public function assets(): array
            {
                return ['acme/look', 'acme/file', 'acme/run'];
            }
        },
    );
    $c->get(DocumentRepositoryInterface::class)->save(new Document('home', 'page', new BlockTree(
        new Block('b', BlockName::fromString('acme/box'), 1),
    )));

    return $c->get(BuilderApi::class);
}

test('a page is drawn with its stylesheets, in order, and never its scripts', function () {
    $body = stylesHost('/assets/file.css')
        ->handle(new ApiRequest('GET', '/documents/home/render', ['mode' => 'admin']))
        ->body;

    expect($body['styles'])->toBe([
        ['name' => 'acme/base', 'href' => 'https://cdn.test/base.css'],
        ['name' => 'acme/look', 'content' => '.a{color:red}</style><script>x</script>'],
        ['name' => 'acme/file', 'href' => '/assets/file.css'],
    ]);
});

test('a file stylesheet the host cannot place is left out', function (?string $located) {
    $styles = stylesHost($located)->handle(new ApiRequest('GET', '/documents/home/render'))->body['styles'];

    expect(array_column($styles, 'name'))->toBe(['acme/base', 'acme/look']);
})->with([
    'no locator' => [null],
    'an empty address' => [''],
]);

test('a block preview carries the stylesheets too', function () {
    $body = stylesHost()
        ->handle(new ApiRequest('POST', '/documents/home/preview', [], '{"block":"b","mode":"admin"}'))
        ->body;

    expect(array_column($body['styles'], 'name'))->toBe(['acme/base', 'acme/look']);
});

/**
 * Builds a host whose tokens and breakpoints are stored, for a person with the given abilities.
 *
 * @param list<string> $abilities
 */
function siteStylesHost(array $abilities = ['view', 'edit', 'manage-styles']): BuilderApi
{
    $c = new BlockServiceContainer();
    (new MemoryServiceProvider())->register($c);
    (new AppServiceProvider())->register($c);
    (new \SpeedySpec\Block\App\Storage\StorageServiceProvider(['color.primary' => '#2456d6']))->register($c);
    (new PresentationServiceProvider())->register($c);
    $c->add(BuilderAccessInterface::class, fn () => new class($abilities) implements BuilderAccessInterface {
        public function __construct(private readonly array $abilities)
        {
        }

        public function allows(BuilderAbility $ability, ?string $documentId = null): bool
        {
            return in_array($ability->value, $this->abilities, true);
        }
    });

    return new BuilderApi($c);
}

/**
 * Sends one request and gives back its status and decoded body.
 *
 * @return array{0: int, 1: array<string, mixed>}
 */
function siteStylesCall(BuilderApi $api, string $method, string $path, ?string $body = null): array
{
    $response = $api->handle(new ApiRequest($method, $path, [], $body ?? ''));

    return [$response->status, json_decode($response->json(), true)];
}

test('a token can be set and removed, and the answer lists the tokens', function () {
    $api = siteStylesHost();

    [$set, $after] = siteStylesCall($api, 'PUT', '/tokens/color.accent', '{"value": "#ff0066"}');
    [$removed, $left] = siteStylesCall($api, 'DELETE', '/tokens/color.primary');
    [, $vocabulary] = siteStylesCall($api, 'GET', '/vocabulary');

    expect($set)->toBe(200)
        ->and($after['tokens'])->toBe(['color.accent' => '#ff0066', 'color.primary' => '#2456d6'])
        ->and($removed)->toBe(200)
        ->and($left['tokens'])->toBe(['color.accent' => '#ff0066'])
        ->and($vocabulary['tokens'])->toBe(['color.accent' => '#ff0066'])
        ->and($vocabulary['manageStyles'])->toBeTrue();
});

test('a breakpoint can be set and removed, and the answer lists the breakpoints', function () {
    $api = siteStylesHost();

    [$set, $after] = siteStylesCall($api, 'PUT', '/breakpoints/wide', '{"minWidth": 1280}');
    [$removed, $left] = siteStylesCall($api, 'DELETE', '/breakpoints/tablet');

    expect($set)->toBe(200)
        ->and($after['breakpoints'])->toBe([
            ['name' => 'tablet', 'minWidth' => 768],
            ['name' => 'desktop', 'minWidth' => 1024],
            ['name' => 'wide', 'minWidth' => 1280],
        ])
        ->and($removed)->toBe(200)
        ->and(array_column($left['breakpoints'], 'name'))->toBe(['desktop', 'wide']);
});

test('changing site styles needs the manage-styles ability', function (string $method, string $path, ?string $body) {
    $api = siteStylesHost(['view', 'edit']);

    expect(siteStylesCall($api, $method, $path, $body)[0])->toBe(403)
        ->and(siteStylesCall($api, 'GET', '/vocabulary')[1]['manageStyles'])->toBeFalse();
})->with([
    ['PUT', '/tokens/color.accent', '{"value": "#fff"}'],
    ['DELETE', '/tokens/color.primary', null],
    ['PUT', '/breakpoints/wide', '{"minWidth": 1280}'],
    ['DELETE', '/breakpoints/tablet', null],
]);

test('a style the site cannot use is refused with a reason, and a malformed request is a bad request', function (
    string $method,
    string $path,
    string $body,
    int $status,
) {
    [$got, $answer] = siteStylesCall(siteStylesHost(), $method, $path, $body);

    expect($got)->toBe($status)->and($answer['error'])->toBeString()->not->toBe('');
})->with([
    'bad token name' => ['PUT', '/tokens/Bad', '{"value": "red"}', 422],
    'unsafe value' => ['PUT', '/tokens/color.accent', '{"value": "red;}body{x:y"}', 422],
    'zero width' => ['PUT', '/breakpoints/wide', '{"minWidth": 0}', 422],
    'bad breakpoint name' => ['PUT', '/breakpoints/Wide', '{"minWidth": 900}', 422],
    'missing value' => ['PUT', '/tokens/color.accent', '{}', 400],
    'value not text' => ['PUT', '/tokens/color.accent', '{"value": 3}', 400],
    'width not a number' => ['PUT', '/breakpoints/wide', '{"minWidth": "900"}', 400],
    'width not whole' => ['PUT', '/breakpoints/wide', '{"minWidth": 7.5}', 400],
]);
