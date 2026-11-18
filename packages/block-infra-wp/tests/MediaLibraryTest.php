<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\ValueObjects\MediaSize;
use SpeedySpec\Block\Infra\WP\Ports\WpAttachmentsInterface;
use SpeedySpec\Block\Infra\WP\WpMediaLibrary;
use SpeedySpec\Block\Infra\WP\WpServiceProvider;

use function SpeedySpec\Block\ContractTests\mediaLibraryContract;

/**
 * Keeps attachments in an array the way WordPress would describe them, so the library is tested without WordPress.
 */
class FakeAttachments implements WpAttachmentsInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $attachments = [];

    public int $calls = 0;

    private int $next = 10;

    public function attachment(int $id): ?array
    {
        $this->calls++;

        return $this->attachments[$id] ?? null;
    }

    public function search(string $search, ?string $mimePrefix, int $page, int $perPage): array
    {
        $this->calls++;
        $ids = array_keys(array_filter(
            $this->attachments,
            fn (array $a): bool => ($mimePrefix === null || str_starts_with($a['mimeType'], $mimePrefix))
                && ($search === '' || mb_stripos($a['title'], $search) !== false),
        ));
        rsort($ids);

        return ['ids' => array_slice($ids, ($page - 1) * $perPage, $perPage), 'total' => count($ids)];
    }

    public function insert(string $name, string $mimeType, string $bytes): int
    {
        $id = $this->next++;
        $this->attachments[$id] = [
            'id' => $id,
            'url' => "https://wp.test/uploads/{$id}-{$name}",
            'mimeType' => $mimeType,
            'title' => $name,
            'alt' => '',
            'width' => null,
            'height' => null,
            'sizes' => [],
        ];

        return $id;
    }
}

describe('WpMediaLibrary', function () {
    mediaLibraryContract(fn () => new WpMediaLibrary(new FakeAttachments()));
});

test('an id that is not a positive whole number is not looked up', function (string $id) {
    $attachments = new FakeAttachments();

    expect((new WpMediaLibrary($attachments))->find($id))->toBeNull()->and($attachments->calls)->toBe(0);
})->with(['12abc', '-1', '0', '1.5', ' 12', '']);

test('an attachment\'s sizes and alt text come through', function () {
    $attachments = new FakeAttachments();
    $attachments->attachments[7] = [
        'id' => 7,
        'url' => 'https://wp.test/uploads/a.jpg',
        'mimeType' => 'image/jpeg',
        'title' => 'A',
        'alt' => 'A cat',
        'width' => 1200,
        'height' => 800,
        'sizes' => [
            ['name' => 'thumbnail', 'url' => 'https://wp.test/uploads/a-150.jpg', 'width' => 150, 'height' => 100],
        ],
    ];
    $item = (new WpMediaLibrary($attachments))->find('7');

    expect($item?->alt)->toBe('A cat')
        ->and($item?->width)->toBe(1200)
        ->and($item?->size('thumbnail'))
        ->toEqual(new MediaSize('thumbnail', 'https://wp.test/uploads/a-150.jpg', 150, 100));
});

test('the provider binds the WordPress media library', function () {
    $container = new BlockServiceContainer();
    (new WpServiceProvider(attachments: new FakeAttachments()))->register($container);

    expect($container->get(MediaLibraryInterface::class))->toBeInstanceOf(WpMediaLibrary::class);
});

test('WordPress is given a file name that matches what the file is', function () {
    $attachments = new FakeAttachments();
    (new WpMediaLibrary($attachments))->upload(
        new \SpeedySpec\Block\Domain\ValueObjects\MediaUpload('photo.html', 'image/png', "\x89PNG\r\n\x1A\n"),
    );

    expect(end($attachments->attachments)['title'])->toBe('photo.png');
});
