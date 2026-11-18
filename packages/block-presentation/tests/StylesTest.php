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
