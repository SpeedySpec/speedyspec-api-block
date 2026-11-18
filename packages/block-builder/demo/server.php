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
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\Contracts\MediaUploadPolicyInterface;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;
use SpeedySpec\Block\Domain\ValueObjects\MediaQuery;
use SpeedySpec\Block\Infra\Filesystem\FilesystemMediaLibrary;
use SpeedySpec\Block\Infra\Filesystem\FilesystemServiceProvider;
use SpeedySpec\Block\Media\Domain\MediaTypesPackage;
use SpeedySpec\Block\Media\MediaPackage;
use SpeedySpec\Block\Presentation\ApiRequest;
use SpeedySpec\Block\Presentation\BuilderApi;
use SpeedySpec\Block\Presentation\PresentationServiceProvider;
use SpeedySpec\Block\Structure\Domain\StructureTypesPackage;
use SpeedySpec\Block\Structure\StructurePackage;
use SpeedySpec\Block\Text\Domain\TextTypesPackage;
use SpeedySpec\Block\Text\TextPackage;

require __DIR__ . '/vendor/autoload.php';

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$storage = sys_get_temp_dir() . '/speedyspec-block-builder-demo';
$mediaUrl = '/media-files/';

// The demo answers only to the machine it runs on.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true))
{
    http_response_code(403);

    return;
}

// Uploaded media are served from the library's own directory, under the type found in their content when they were
// uploaded, and never sniffed again by the browser.
if (str_starts_with($path, $mediaUrl))
{
    $library = new FilesystemMediaLibrary($storage . '/media', $mediaUrl);
    $name = substr($path, strlen($mediaUrl));
    $id = preg_match('/^([0-9a-f]{16})\.[a-z0-9]{2,4}$/', $name, $match) === 1 ? $match[1] : '';
    $item = $id === '' ? null : $library->find($id);
    $file = $item === null ? null : $library->path($id);

    if ($item === null || $file === null || basename($file) !== $name)
    {
        http_response_code(404);

        return;
    }

    header('Content-Type: ' . $item->mimeType);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline');
    header('Content-Length: ' . (string) filesize($file));
    readfile($file);

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
    // A demo is for trying changes, so scripts are fetched afresh on every load.
    header('Cache-Control: no-store');
    readfile($file);

    return;
}

$container = new BlockServiceContainer();
(new AppServiceProvider())->register($container);
(new StorageServiceProvider())->register($container);
(new FilesystemServiceProvider($storage, $mediaUrl))->register($container);
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
$loader->load(new TextTypesPackage());
$loader->load(TextPackage::from($container));
$loader->load(MediaTypesPackage::from($container));
$loader->load(MediaPackage::from($container));
$container->get(DesignTokenRegistryInterface::class)->set('color.primary', '#2456d6');
$container->get(DesignTokenRegistryInterface::class)->set('space.md', '1.5rem');

$documents = $container->get(DocumentRepositoryInterface::class);
$media = $container->get(MediaLibraryInterface::class);

// The library starts with three pictures drawn here, so there is something to choose; they are flat shapes, made
// for the demo.
if ($media->search(new MediaQuery())->total === 0 && function_exists('imagecreatetruecolor'))
{
    $policy = $container->get(MediaUploadPolicyInterface::class);
    $samples = [
        ['Teal circle.png', [15, 123, 108], [227, 243, 240], 'circle'],
        ['Blue squares.png', [36, 86, 214], [228, 235, 252], 'squares'],
        ['Sun stripes.png', [214, 140, 36], [252, 241, 222], 'stripes'],
    ];

    foreach ($samples as [$name, $ink, $paper, $shape])
    {
        $image = imagecreatetruecolor(960, 640);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, ...$paper));
        $colour = (int) imagecolorallocate($image, ...$ink);

        match ($shape)
        {
            'circle' => imagefilledellipse($image, 480, 320, 380, 380, $colour),
            'squares' => array_map(
                static fn (int $i): bool => imagefilledrectangle($image, 140 + $i * 240, 200, 320 + $i * 240, 440, $colour),
                [0, 1, 2],
            ),
            default => array_map(
                static fn (int $i): bool => imagefilledrectangle($image, 0, 80 + $i * 140, 960, 140 + $i * 140, $colour),
                [0, 1, 2, 3],
            ),
        };

        ob_start();
        imagepng($image);
        $media->upload($policy->check($name, (string) ob_get_clean()));
    }
}

if ($documents->find('home') === null)
{
    $pictures = array_reverse(array_map(
        static fn ($item): string => $item->id,
        $media->search(new MediaQuery(kind: MediaKind::Image, perPage: 3))->items,
    ));
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

    $text = fn (string $id, string $type, array $settings, Block ...$children) => new Block(
        $id,
        BlockName::fromString($type),
        1,
        $settings,
        [],
        [],
        array_values($children),
    );

    $documents->save(new Document('home', 'page', new BlockTree(
        $text('title', 'core/heading', ['level' => 1, 'text' => 'A page made of blocks']),
        $text('intro', 'core/paragraph', ['text' => 'Click any text and type. Select words to make them <strong>bold</strong>, <em>italic</em> or a link.']),
        $text('enter', 'core/paragraph', ['text' => 'Press Enter at the end of a paragraph for a new one; Backspace in an empty one removes it.']),
        new Block('columns', BlockName::fromString('core/columns'), 1, [], [], [], [
            $column('left', $text('left-text', 'core/paragraph', ['text' => 'Drag blocks to move them.'])),
            $column('right', $text('right-text', 'core/paragraph', ['text' => 'Undo with Ctrl or Cmd and Z.'])),
        ]),
        $text('list', 'core/list', [], $text('one', 'core/list-item', ['text' => 'A list item']), $text(
            'two',
            'core/list-item',
            ['text' => 'An item with a list inside'],
            $text('inner', 'core/list', ['ordered' => true], $text('three', 'core/list-item', ['text' => 'A numbered item'])),
        )),
        $text('quote', 'core/quote', ['text' => 'Less, but better.', 'citation' => 'Dieter Rams']),
        $text('code', 'core/code', ['code' => "echo 'Hello';", 'language' => 'php']),
        $text('table', 'core/table', ['rows' => [['Tea', 'Price'], ['Green', '3'], ['Black', '2']], 'caption' => 'Prices']),
        $text('rule', 'core/separator', []),
        $text('video', 'core/embed', ['url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'title' => 'Big Buck Bunny']),
        $text(
            'features',
            'core/icon-list',
            ['icon' => 'check'],
            $text('feature-one', 'core/icon-list-item', ['text' => 'Media are stored by id, so moving files breaks nothing.']),
            $text('feature-two', 'core/icon-list-item', ['icon' => 'star', 'text' => 'Uploads are checked by what they contain.']),
        ),
        $text('map', 'core/map', ['latitude' => 51.5074, 'longitude' => -0.1278, 'zoom' => 13, 'title' => 'London']),
        ...array_values(array_filter([
            $pictures === [] ? null : $text('photo', 'core/image', ['media' => $pictures[0], 'caption' => 'Choose another image in the inspector.']),
            $pictures === [] ? null : $text('gallery', 'core/gallery', ['media' => $pictures, 'columns' => ['base' => 1, 'tablet' => 3]]),
        ])),
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
    function_exists('getallheaders') ? getallheaders() : array_filter([
        'content-type' => $_SERVER['CONTENT_TYPE'] ?? null,
    ]),
));

http_response_code($response->status);
header('Content-Type: application/json; charset=utf-8');
echo $response->json();
