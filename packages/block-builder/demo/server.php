<?php

declare(strict_types=1);

/**
 * A local host for trying the reference builder. Not for production: it lets anyone who can reach it edit.
 *
 * Run from the package directory:
 *
 *     composer install --working-dir=demo
 *     php -S 127.0.0.1:8765 demo/server.php
 */

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\App\Documents\DocumentsPackage;
use SpeedySpec\Block\App\Flow\FlowPackage;
use SpeedySpec\Block\App\Presentation\PresentationPackage;
use SpeedySpec\Block\App\Storage\StorageServiceProvider;
use SpeedySpec\Block\Builder\ReferenceEditor;
use SpeedySpec\Block\Builder\ReferenceEditorServiceProvider;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\AssetLocatorInterface;
use SpeedySpec\Block\Domain\Contracts\AssetRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BuilderAccessInterface;
use SpeedySpec\Block\Domain\Contracts\DesignTokenRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\EditorInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\AssetKind;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BuilderAbility;
use SpeedySpec\Block\Domain\ValueObjects\EditorRequest;
use SpeedySpec\Block\Infra\Filesystem\FilesystemServiceProvider;
use SpeedySpec\Block\Presentation\ApiRequest;
use SpeedySpec\Block\Presentation\BuilderApi;
use SpeedySpec\Block\Presentation\PresentationServiceProvider;
use SpeedySpec\Block\Structure\Domain\StructureTypesPackage;
use SpeedySpec\Block\Structure\StructurePackage;

require __DIR__ . '/vendor/autoload.php';

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// The demo answers only to the machine it runs on.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true))
{
    http_response_code(403);

    return;
}

if ($path !== '/' && !str_starts_with($path, '/api/'))
{
    $root = dirname(__DIR__);
    $file = realpath($root . $path);
    $types = ['js' => 'text/javascript'];
    $extension = is_string($file) ? pathinfo($file, PATHINFO_EXTENSION) : '';

    // Only the package's own scripts are served: they are what the asset locator below points at.
    if (!is_string($file) || !str_starts_with($file, $root . '/src/') || !isset($types[$extension]) || !is_file($file))
    {
        http_response_code(404);

        return;
    }

    header('Content-Type: ' . $types[$extension] . '; charset=utf-8');
    readfile($file);

    return;
}

$container = new BlockServiceContainer();
(new AppServiceProvider())->register($container);
(new StorageServiceProvider())->register($container);
(new FilesystemServiceProvider(sys_get_temp_dir() . '/speedyspec-block-builder-demo'))->register($container);
(new PresentationServiceProvider())->register($container);
(new ReferenceEditorServiceProvider())->register($container);
$container->add(AssetLocatorInterface::class, fn () => new class implements AssetLocatorInterface {
    public function url(string $package, string $path): string
    {
        // This demo serves only the builder's own files, from the root of the site.
        return $package === ReferenceEditor::PACKAGE ? '/' . $path : '';
    }
});
$container->add(BuilderAccessInterface::class, fn () => new class implements BuilderAccessInterface {
    public function allows(BuilderAbility $ability, ?string $documentId = null): bool
    {
        return true;
    }
});

$loader = $container->get(BlockPackageLoaderInterface::class);
$loader->load(FlowPackage::from($container));
$loader->load(PresentationPackage::from($container));
$loader->load(DocumentsPackage::from($container));
$loader->load(new StructureTypesPackage());
$loader->load(StructurePackage::from($container));
$container->get(DesignTokenRegistryInterface::class)->set('color.primary', '#2456d6');
$container->get(DesignTokenRegistryInterface::class)->set('space.md', '1.5rem');

$documents = $container->get(DocumentRepositoryInterface::class);

if ($documents->find('home') === null)
{
    $element = fn (string $id, string $tag, string $text) => new Block(
        $id,
        BlockName::fromString('core/element'),
        1,
        ['tag' => $tag, 'text' => $text],
    );
    $column = fn (string $id, Block ...$children) => new Block(
        $id,
        BlockName::fromString('core/column'),
        1,
        [],
        [],
        [],
        array_values($children),
    );

    $documents->save(new Document('home', 'page', new BlockTree(
        $element('title', 'h1', 'A page made of blocks'),
        new Block('columns', BlockName::fromString('core/columns'), 1, [], [], [], [
            $column('left', $element('left-text', 'p', 'Select a block on the left to change it.')),
            $column('right', $element('right-text', 'p', 'Add, move and remove blocks; the page redraws.')),
        ]),
    )));
}

if ($path === '/')
{
    $page = $container->get(EditorInterface::class)->open(new EditorRequest('home', '/api'));
    $locator = $container->get(AssetLocatorInterface::class);
    $tags = '';

    foreach ($container->get(AssetRegistryInterface::class)->resolve($page->assets) as $asset)
    {
        $url = $asset->source === 'file' ? $locator->url($asset->package, $asset->path) : $asset->url;
        $tags .= match ($asset->kind)
        {
            AssetKind::Style => sprintf('<link rel="stylesheet" href="%s">', htmlspecialchars($url)),
            AssetKind::Module => sprintf('<script type="module" src="%s"></script>', htmlspecialchars($url)),
            AssetKind::Script => sprintf('<script src="%s"></script>', htmlspecialchars($url)),
        };
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Block builder demo</title>'
        . '<style>html, body { height: 100%; margin: 0; }</style>' . $tags . '</head><body>'
        . $page->markup . '</body></html>';

    return;
}

$response = $container->get(BuilderApi::class)->handle(new ApiRequest(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    substr($path, 4),
    array_map(strval(...), array_filter($_GET, is_scalar(...))),
    (string) file_get_contents('php://input'),
));

http_response_code($response->status);
header('Content-Type: application/json; charset=utf-8');
echo $response->json();
