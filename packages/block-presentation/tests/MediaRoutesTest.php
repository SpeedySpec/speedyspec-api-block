<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\App\Media\MediaTypeDetector;
use SpeedySpec\Block\App\Media\MediaUploadPolicy;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BuilderAccessInterface;
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\Contracts\MediaUploadPolicyInterface;
use SpeedySpec\Block\Domain\ValueObjects\BuilderAbility;
use SpeedySpec\Block\Domain\ValueObjects\MediaUpload;
use SpeedySpec\Block\Infra\Memory\InMemoryMediaLibrary;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;
use SpeedySpec\Block\Presentation\ApiRequest;
use SpeedySpec\Block\Presentation\BuilderApi;
use SpeedySpec\Block\Presentation\PresentationServiceProvider;

const MEDIA_PNG = "\x89PNG\r\n\x1A\n";

/**
 * Builds a host with an in-memory media library and the given abilities.
 *
 * @return array{0: BuilderApi, 1: InMemoryMediaLibrary}
 */
function mediaHost(array $abilities = ['edit', 'upload-media'], bool $library = true, ?int $maxBytes = null): array
{
    $c = new BlockServiceContainer();
    (new MemoryServiceProvider())->register($c);
    (new AppServiceProvider())->register($c);
    (new PresentationServiceProvider())->register($c);
    $media = new InMemoryMediaLibrary('https://e.test/m/');
    $c->remove(MediaLibraryInterface::class);

    if ($library)
    {
        $c->add(MediaLibraryInterface::class, fn () => $media);
    }

    if ($maxBytes !== null)
    {
        $c->remove(MediaUploadPolicyInterface::class);
        $c->add(
            MediaUploadPolicyInterface::class,
            fn () => new MediaUploadPolicy(new MediaTypeDetector(), $maxBytes),
        );
    }

    $c->add(BuilderAccessInterface::class, fn () => new class($abilities) implements BuilderAccessInterface {
        public function __construct(private readonly array $abilities)
        {
        }

        public function allows(BuilderAbility $ability, ?string $documentId = null): bool
        {
            return in_array($ability->value, $this->abilities, true);
        }
    });

    return [$c->get(BuilderApi::class), $media];
}

function seed(InMemoryMediaLibrary $media, string ...$names): void
{
    foreach ($names as $name)
    {
        $type = str_ends_with($name, '.pdf') ? 'application/pdf' : 'image/png';
        $media->upload(new MediaUpload($name, $type, 'x'));
    }
}

test('media are listed a page at a time, newest first', function () {
    [$api, $media] = mediaHost();
    seed($media, 'a.png', 'b.png', 'c.png');

    $response = $api->handle(new ApiRequest('GET', '/media', ['perPage' => '2', 'page' => '1']));

    expect($response->status)->toBe(200)
        ->and(array_column($response->body['items'], 'title'))->toBe(['c.png', 'b.png'])
        ->and($response->body['total'])->toBe(3)
        ->and($response->body['page'])->toBe(1)
        ->and($response->body['perPage'])->toBe(2);
});

test('paging is kept within bounds', function () {
    [$api] = mediaHost();
    $response = $api->handle(new ApiRequest('GET', '/media', ['perPage' => '1000', 'page' => '0']));

    expect($response->body['perPage'])->toBe(100)->and($response->body['page'])->toBe(1);
});

test('media are filtered by kind and searched by title', function () {
    [$api, $media] = mediaHost();
    seed($media, 'cat.png', 'cat.pdf', 'dog.png');

    $files = $api->handle(new ApiRequest('GET', '/media', ['kind' => 'file']));
    $cats = $api->handle(new ApiRequest('GET', '/media', ['search' => 'CAT', 'kind' => 'image']));

    expect(array_column($files->body['items'], 'title'))->toBe(['cat.pdf'])
        ->and(array_column($cats->body['items'], 'title'))->toBe(['cat.png']);
});

test('a kind that does not exist is refused', function () {
    [$api] = mediaHost();
    $response = $api->handle(new ApiRequest('GET', '/media', ['kind' => 'hologram']));

    expect($response->status)->toBe(400)->and($response->body['error'])->toBe('Unknown media kind.');
});

test('one item is found by its id, and an unknown id is not', function () {
    [$api, $media] = mediaHost();
    seed($media, 'a.png');

    $found = $api->handle(new ApiRequest('GET', '/media/m1'));
    $missing = $api->handle(new ApiRequest('GET', '/media/m9'));

    expect($found->status)->toBe(200)
        ->and($found->body['item']['title'])->toBe('a.png')
        ->and($missing->status)->toBe(404);
});

test('an upload is kept under the type its content shows and can then be found', function () {
    [$api] = mediaHost();
    $created = $api->handle(new ApiRequest(
        'POST',
        '/media',
        ['name' => 'notes.txt'],
        MEDIA_PNG . 'x',
        ['Content-Type' => 'text/html'],
    ));
    $found = $api->handle(new ApiRequest('GET', '/media/' . $created->body['item']['id']));

    expect($created->status)->toBe(201)
        ->and($created->body['item']['mimeType'])->toBe('image/png')
        ->and($created->body['item']['kind'])->toBe('image')
        ->and($found->body['item']['title'])->toBe('notes.txt');
});

test('an upload that breaks the rules is refused with its status and nothing is kept', function (
    array $query,
    string $body,
    int $status,
) {
    [$api, $media] = mediaHost(maxBytes: 16);
    $response = $api->handle(new ApiRequest('POST', '/media', $query, $body, ['content-type' => 'image/jpeg']));

    expect($response->status)->toBe($status)
        ->and($response->body['error'])->toBeString()
        ->and($media->search(new \SpeedySpec\Block\Domain\ValueObjects\MediaQuery())->total)->toBe(0);
})->with([
    'markup called a photo' => [['name' => 'photo.jpg'], '<p>x</p>', 415],
    'over the limit' => [['name' => 'a.png'], str_pad(MEDIA_PNG, 17, 'x'), 413],
    'empty' => [['name' => 'a.png'], '', 400],
    'no name' => [[], MEDIA_PNG, 400],
]);

test('uploading needs its own ability, looking does not', function () {
    [$api] = mediaHost(['edit']);

    expect($api->handle(new ApiRequest('POST', '/media', ['name' => 'a.png'], MEDIA_PNG))->status)->toBe(403)
        ->and($api->handle(new ApiRequest('GET', '/media'))->status)->toBe(200);
});

test('looking at media needs the edit ability', function () {
    [$api] = mediaHost(['view', 'upload-media']);

    expect($api->handle(new ApiRequest('GET', '/media'))->status)->toBe(403)
        ->and($api->handle(new ApiRequest('GET', '/media/m1'))->status)->toBe(403);
});

test('a site with no media library says so', function () {
    [$api] = mediaHost(library: false);
    $response = $api->handle(new ApiRequest('GET', '/media'));

    expect($response->status)->toBe(501)
        ->and($response->body['error'])->toBe('This site has no media library.')
        ->and($api->handle(new ApiRequest('POST', '/media', ['name' => 'a.png'], MEDIA_PNG))->status)->toBe(501);
});

test('request header names are read regardless of case', function () {
    $request = new ApiRequest('POST', '/media', [], '', ['Content-Type' => 'image/png', 'X-Thing' => 'a']);

    expect($request->header('content-type'))->toBe('image/png')
        ->and($request->header('X-THING'))->toBe('a')
        ->and($request->header('missing'))->toBeNull()
        ->and($request->headers)->toBe(['content-type' => 'image/png', 'x-thing' => 'a']);
});
