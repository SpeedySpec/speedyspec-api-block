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

use SpeedySpec\Block\Actions\ActionsPackage;
use SpeedySpec\Block\Actions\Domain\ActionsTypesPackage;
use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\App\Documents\DocumentsPackage;
use SpeedySpec\Block\App\Flow\FlowPackage;
use SpeedySpec\Block\App\Presentation\PresentationPackage;
use SpeedySpec\Block\App\Storage\StorageServiceProvider;
use SpeedySpec\Block\Builder\PackageFiles;
use SpeedySpec\Block\Builder\ReferenceEditorServiceProvider;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\AssetLocatorInterface;
use SpeedySpec\Block\Domain\Contracts\AssetRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BuilderAccessInterface;
use SpeedySpec\Block\Domain\Contracts\CollectionSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\EditorInterface;
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\Contracts\MediaUploadPolicyInterface;
use SpeedySpec\Block\Domain\Contracts\SiteMapInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\AssetKind;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BuilderAbility;
use SpeedySpec\Block\Domain\ValueObjects\Crumb;
use SpeedySpec\Block\Domain\ValueObjects\EditorRequest;
use SpeedySpec\Block\Domain\ValueObjects\MediaKind;
use SpeedySpec\Block\Domain\ValueObjects\MediaQuery;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\SearchTarget;
use SpeedySpec\Block\Infra\Filesystem\FilesystemMediaLibrary;
use SpeedySpec\Block\Infra\Filesystem\FilesystemServiceProvider;
use SpeedySpec\Block\Infra\Memory\ArrayCollectionSource;
use SpeedySpec\Block\Infra\Memory\ArrayDataSource;
use SpeedySpec\Block\Infra\Memory\InMemorySiteMap;
use SpeedySpec\Block\Interactive\Domain\InteractiveTypesPackage;
use SpeedySpec\Block\Interactive\InteractivePackage;
use SpeedySpec\Block\Media\Domain\MediaTypesPackage;
use SpeedySpec\Block\Media\MediaPackage;
use SpeedySpec\Block\Presentation\ApiRequest;
use SpeedySpec\Block\Presentation\BuilderApi;
use SpeedySpec\Block\Presentation\PresentationServiceProvider;
use SpeedySpec\Block\Site\Domain\PostListTemplate;
use SpeedySpec\Block\Site\Domain\SiteTypesPackage;
use SpeedySpec\Block\Site\SitePackage;
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

// Every loaded package's scripts and stylesheets are served from /packages/<name>/, and only those.
$roots = [];

foreach (glob(__DIR__ . '/vendor/speedyspec/*', GLOB_ONLYDIR) ?: [] as $directory)
{
    $roots['speedyspec/' . basename($directory)] = $directory;
}

$packages = new PackageFiles($roots);

if (str_starts_with($path, '/packages/'))
{
    $file = $packages->file($path);

    if ($file === null)
    {
        http_response_code(404);

        return;
    }

    header('Content-Type: ' . $file['type'] . '; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    // A demo is for trying changes, so files are fetched afresh on every load.
    header('Cache-Control: no-store');
    readfile($file['path']);

    return;
}

if (!in_array($path, ['/', '/view'], true) && !str_starts_with($path, '/api/'))
{
    http_response_code(404);

    return;
}

$container = new BlockServiceContainer();
(new AppServiceProvider())->register($container);
// The demo's tokens are where the site starts; once they are changed on the site styles screen, the stored ones
// are used instead.
(new StorageServiceProvider(tokens: ['color.primary' => '#2456d6', 'space.md' => '1.5rem']))->register($container);
(new FilesystemServiceProvider($storage, $mediaUrl))->register($container);
(new PresentationServiceProvider())->register($container);
(new ReferenceEditorServiceProvider())->register($container);
$container->add(AssetLocatorInterface::class, fn () => $packages);
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
$loader->load(ActionsTypesPackage::from($container));
$loader->load(ActionsPackage::from($container));
$loader->load(new InteractiveTypesPackage());
$loader->load(new InteractivePackage());

// Where the demo's one page sits, so menus, breadcrumbs and search have something to show, and three sample posts
// for the post list to repeat over.
$container->add(SiteMapInterface::class, fn () => new InMemorySiteMap(
    ['home' => '/view'],
    ['home' => [new Crumb('Demo', '/'), new Crumb('Pages', '/view#pages'), new Crumb('Home')]],
    new SearchTarget('/view', 'q'),
));
$container->get(CollectionSourceRegistryInterface::class)->add(new ArrayCollectionSource(PostListTemplate::COLLECTION, [
    ['title' => 'Blocks that open', 'excerpt' => 'Accordions, tabs and modals, with no script needed.', 'url' => '/view#faq'],
    ['title' => 'Pictures and video', 'excerpt' => 'Media are stored by id, so moving files breaks nothing.', 'url' => '/view#photo'],
    ['title' => 'Find your way', 'excerpt' => 'Menus, breadcrumbs and a table of contents.', 'url' => '/view#contents'],
]));
// A source for the inspector's Bind button: the site's name and tagline, which the seed's tagline is bound to.
$container->get(DataSourceRegistryInterface::class)->add(new ArrayDataSource(
    'demo/site',
    ['name' => 'SpeedySpec demo', 'tagline' => 'Blocks for any editor'],
    ['type' => 'string'],
));
$loader->load(SiteTypesPackage::from($container));
$loader->load(new SitePackage());

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

    $link = fn (string $id, string $label, string $url, array $extra = [], Block ...$children) => $text(
        $id,
        'core/navigation-link',
        ['label' => $label, 'url' => $url, ...$extra],
        ...$children,
    );
    $postList = PostListTemplate::template()->tree->roots()[0];

    $documents->save(new Document('home', 'page', new BlockTree(
        $text(
            'menu',
            'core/navigation',
            ['label' => 'Main'],
            $link('menu-home', 'Home', '/view'),
            $link(
                'menu-blocks',
                'Blocks',
                '',
                [],
                $link('menu-text', 'Text', '/view#a-page-made-of-blocks'),
                $link('menu-media', 'Media', '/view#photo'),
                $link('menu-actions', 'Actions', '/view#pricing'),
            ),
            $link('menu-docs', 'Docs', 'https://example.com/docs', ['newTab' => true]),
        ),
        $text('trail', 'core/breadcrumbs', []),
        $text('find', 'core/search', ['placeholder' => 'Search the demo']),
        $text('contents', 'core/table-of-contents', ['from' => 1, 'to' => 3]),
        $text('title', 'core/heading', ['level' => 1, 'text' => 'A page made of blocks']),
        new Block('tagline', BlockName::fromString('core/paragraph'), 1, ['text' => 'A tagline'], [], [
            'text' => Binding::to('demo/site', ['key' => 'tagline']),
        ]),
        $text('again', 'core/heading', ['level' => 2, 'text' => 'A page made of blocks']),
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
        $text(
            'cta',
            'core/call-to-action',
            ['heading' => 'Try the actions blocks', 'text' => 'Click a button\'s text to change it.'],
            $text(
                'cta-buttons',
                'core/buttons',
                [],
                $text('cta-start', 'core/button', ['text' => 'Get started', 'link' => '/start']),
                $text('cta-docs', 'core/button', [
                    'text' => 'Read the docs',
                    'link' => 'https://example.com/docs',
                    'variant' => 'outline',
                    'newTab' => true,
                ]),
            ),
        ),
        $text('quote-card', 'core/testimonial', [
            'quote' => 'We rebuilt our pages in an afternoon.',
            'name' => 'Ann Lee',
            'role' => 'Designer, Example Co',
            'media' => $pictures[0] ?? '',
        ]),
        $text(
            'pricing',
            'core/pricing-table',
            ['columns' => ['base' => 1, 'tablet' => 3]],
            $text('plan-free', 'core/pricing-plan', [
                'name' => 'Free',
                'price' => '$0',
                'features' => ['One site', 'Community help'],
            ], $text('plan-free-button', 'core/button', ['text' => 'Start free', 'link' => '/free', 'variant' => 'outline'])),
            $text('plan-pro', 'core/pricing-plan', [
                'name' => 'Pro',
                'price' => '$19',
                'features' => ['Ten sites', 'Email help', 'Saved templates'],
                'highlighted' => true,
                'badge' => 'Most popular',
            ], $text('plan-pro-button', 'core/button', ['text' => 'Choose Pro', 'link' => '/pro'])),
            $text('plan-team', 'core/pricing-plan', [
                'name' => 'Team',
                'price' => '$49',
                'features' => ['Unlimited sites', 'Priority help', 'Shared styles'],
            ], $text('plan-team-button', 'core/button', ['text' => 'Talk to us', 'link' => 'mailto:team@example.com', 'variant' => 'secondary'])),
        ),
        $text('score', 'core/rating', ['value' => 4.5, 'outOf' => 5, 'label' => 'Reviews']),
        $text(
            'faq',
            'core/accordion',
            ['single' => true],
            $text('faq-one', 'core/accordion-item', ['title' => 'Does it work without JavaScript?', 'open' => true], $text(
                'faq-one-text',
                'core/paragraph',
                ['text' => 'Yes. Every block here is readable and usable with scripts turned off.'],
            )),
            $text('faq-two', 'core/accordion-item', ['title' => 'Can I open two at once?'], $text(
                'faq-two-text',
                'core/paragraph',
                ['text' => 'Not in this accordion: it is set to one open at a time.'],
            )),
            $text('faq-three', 'core/accordion-item', ['title' => 'Where is the script?'], $text(
                'faq-three-text',
                'core/paragraph',
                ['text' => 'In the interactive outputs package, served from /packages/.'],
            )),
        ),
        $text(
            'tabs',
            'core/tabs',
            [],
            $text('tab-one', 'core/tab', ['label' => 'Overview'], $text(
                'tab-one-text',
                'core/paragraph',
                ['text' => 'Use the arrow keys, Home and End to move between tabs.'],
            )),
            $text('tab-two', 'core/tab', ['label' => 'Details'], $text(
                'inner-tabs',
                'core/tabs',
                [],
                $text('inner-a', 'core/tab', ['label' => 'Inside A'], $text(
                    'inner-a-text',
                    'core/paragraph',
                    ['text' => 'A set of tabs inside a tab keeps its own.'],
                )),
                $text('inner-b', 'core/tab', ['label' => 'Inside B'], $text(
                    'inner-b-text',
                    'core/paragraph',
                    ['text' => 'Second inner tab.'],
                )),
            )),
            $text('tab-three', 'core/tab', ['label' => 'Questions'], $text(
                'tab-three-text',
                'core/paragraph',
                ['text' => 'Without the script, the tabs read as headed sections in order.'],
            )),
        ),
        $text(
            'slides',
            'core/slider',
            ['label' => 'Highlights', 'autoplay' => true, 'interval' => 5],
            $text('slide-one', 'core/slide', [], $text('slide-one-text', 'core/heading', ['text' => 'First slide'])),
            $text('slide-two', 'core/slide', [], $text('slide-two-text', 'core/heading', ['text' => 'Second slide'])),
            $text('slide-three', 'core/slide', [], $text('slide-three-text', 'core/heading', ['text' => 'Third slide'])),
        ),
        $text('launch', 'core/countdown', [
            'label' => 'The new year in London in',
            'target' => '2027-01-01T00:00',
            'timeZone' => 'Europe/London',
            'after' => 'Happy new year!',
        ]),
        $text('funding', 'core/progress', ['label' => 'Funded', 'value' => 65, 'max' => 100]),
        $text('customers', 'core/counter', ['from' => 0, 'to' => 1200, 'suffix' => '+']),
        $text(
            'more',
            'core/modal',
            ['trigger' => 'About this demo', 'title' => 'About this demo'],
            $text('more-text', 'core/paragraph', ['text' => 'This panel is the browser\'s own popover: Escape closes it.']),
        ),
        $text(
            'social',
            'core/social-links',
            [],
            $text('social-site', 'core/social-link', ['service' => 'website', 'url' => 'https://example.com']),
            $text('social-mail', 'core/social-link', ['service' => 'email', 'url' => 'mailto:hello@example.com']),
            $text('social-github', 'core/social-link', ['service' => 'github', 'url' => 'https://github.com/example']),
            $text('social-mastodon', 'core/social-link', ['service' => 'mastodon', 'url' => 'https://mastodon.social/@example']),
        ),
        $text('posts-title', 'core/heading', ['level' => 2, 'text' => 'Recent posts']),
        $postList,
        ...array_values(array_filter([
            $pictures === [] ? null : $text('photo', 'core/image', ['media' => $pictures[0], 'caption' => 'Choose another image in the inspector.']),
            $pictures === [] ? null : $text('gallery', 'core/gallery', ['media' => $pictures, 'columns' => ['base' => 1, 'tablet' => 3]]),
        ])),
    )));
}

/**
 * Writes the tags that place assets in a page's head, in the order the registry resolved them.
 *
 * @param list<string> $names
 */
$assetTags = function (array $names) use ($container): string
{
    $locator = $container->get(AssetLocatorInterface::class);
    $tags = '';

    foreach ($container->get(AssetRegistryInterface::class)->resolve($names) as $asset)
    {
        $url = $asset->source === 'file' ? $locator->url($asset->package, $asset->path) : $asset->url;
        $inline = $asset->source === 'inline' ? str_replace('</', '<\\/', $asset->content) : null;

        if ($inline === null && $url === '')
        {
            continue;
        }

        $tags .= match (true)
        {
            $asset->kind === AssetKind::Style && $inline !== null => '<style>' . $inline . '</style>',
            $asset->kind === AssetKind::Style => sprintf('<link rel="stylesheet" href="%s">', htmlspecialchars($url)),
            $inline !== null => sprintf(
                '<script%s>%s</script>',
                $asset->kind === AssetKind::Module ? ' type="module"' : '',
                $inline,
            ),
            $asset->kind === AssetKind::Module
                => sprintf('<script type="module" src="%s"></script>', htmlspecialchars($url)),
            default => sprintf('<script src="%s"></script>', htmlspecialchars($url)),
        };
    }

    return $tags;
};

// The home page as a visitor sees it, with its stylesheets and scripts placed as a host would.
if ($path === '/view')
{
    $document = $documents->find('home');
    $result = $container->get(DocumentRendererInterface::class)->render($document, new RenderRequest(Mode::Front));
    $css = str_replace('</', '<\\/', $container->get(StyleCollectorInterface::class)->css());

    header('Content-Type: text/html; charset=utf-8');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; frame-src https://www.youtube-nocookie.com https://player.vimeo.com "
        . 'https://www.openstreetmap.org');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>Block builder demo: view</title>'
        . '<style>body { max-width: 60rem; margin: 2rem auto; padding: 0 1.25rem;'
        . ' font: 16px/1.5 system-ui, sans-serif; } img, video { max-width: 100%; height: auto; }'
        . ' iframe { width: 100%; aspect-ratio: 16 / 9; border: 0; }</style>'
        . $assetTags($result->assets) . '<style>' . $css . '</style></head><body>' . $result->output . '</body></html>';

    return;
}

if ($path === '/')
{
    $page = $container->get(EditorInterface::class)->open(new EditorRequest('home', '/api'));
    $tags = $assetTags($page->assets);

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
