<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\ValueObjects\MediaUpload;
use SpeedySpec\Block\Infra\Filesystem\FilesystemMediaLibrary;
use SpeedySpec\Block\Infra\Filesystem\FilesystemServiceProvider;

use function SpeedySpec\Block\ContractTests\mediaLibraryContract;

function mediaDirectory(): string
{
    $directory = sys_get_temp_dir() . '/speedyspec-media-test-' . bin2hex(random_bytes(6));
    register_shutdown_function(static function () use ($directory): void
    {
        foreach (glob($directory . '/{,.}*', GLOB_BRACE) ?: [] as $file)
        {
            if (is_file($file))
            {
                unlink($file);
            }
        }

        if (is_dir($directory))
        {
            rmdir($directory);
        }
    });

    return $directory;
}

function pngOf(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

describe('FilesystemMediaLibrary', function () {
    mediaLibraryContract(fn () => new FilesystemMediaLibrary(mediaDirectory(), 'https://e.test/media/'));
});

test('a file name never chooses where the file is stored', function () {
    $directory = mediaDirectory();
    $library = new FilesystemMediaLibrary($directory, 'https://e.test/media/');
    $item = $library->upload(new MediaUpload('../../etc/passwd.jpg', 'image/png', "\x89PNG\r\n\x1A\n"));
    $path = $library->path($item->id);

    expect($item->title)->toBe('passwd.jpg')
        ->and($path)->not->toBeNull()
        ->and(dirname((string) $path))->toBe(realpath($directory))
        ->and(basename((string) $path))->toMatch('/^[0-9a-f]{16}\.png$/')
        ->and($item->url)->toBe('https://e.test/media/' . basename((string) $path));
});

test('a file name is cleaned into a title', function (string $name, string $title) {
    $library = new FilesystemMediaLibrary(mediaDirectory(), 'https://e.test/media/');

    expect($library->upload(new MediaUpload($name, 'application/pdf', '%PDF-1.7'))->title)->toBe($title);
})->with([
    'only dots' => ['...', 'upload'],
    'control characters' => ["re\x00port\n.pdf", 'report.pdf'],
    'back slashes' => ['C:\\Users\\me\\cv.pdf', 'cv.pdf'],
    'markup with a slash' => ['<b>x</b>.pdf', 'b.pdf'],
    'long' => [str_repeat('a', 300) . '.pdf', str_repeat('a', 120)],
    'accents kept' => ['Résumé 2026.pdf', 'Résumé 2026.pdf'],
]);

test('an image upload has its real width and height', function () {
    $library = new FilesystemMediaLibrary(mediaDirectory(), 'https://e.test/media/');
    $item = $library->upload(new MediaUpload('a.png', 'image/png', pngOf(30, 20)));

    expect($item->width)->toBe(30)->and($item->height)->toBe(20)->and($library->find($item->id)?->width)->toBe(30);
});

test('a second library over the same directory finds the first one\'s uploads', function () {
    $directory = mediaDirectory();
    $item = (new FilesystemMediaLibrary($directory, 'https://e.test/media/'))
        ->upload(new MediaUpload('a.pdf', 'application/pdf', '%PDF-1.7'));

    expect((new FilesystemMediaLibrary($directory, 'https://e.test/media/'))->find($item->id)?->title)->toBe('a.pdf');
});

test('an id that is not one of the library\'s names no file', function () {
    $library = new FilesystemMediaLibrary(mediaDirectory(), 'https://e.test/media/');

    expect($library->path('../index'))->toBeNull()->and($library->find('../index'))->toBeNull();
});

test('the provider binds the media library only when given where media is served', function () {
    $with = new BlockServiceContainer();
    $without = new BlockServiceContainer();
    (new FilesystemServiceProvider(mediaDirectory(), '/media-files/'))->register($with);
    (new FilesystemServiceProvider(mediaDirectory()))->register($without);

    expect($with->get(MediaLibraryInterface::class))->toBeInstanceOf(FilesystemMediaLibrary::class)
        ->and($without->has(MediaLibraryInterface::class))->toBeFalse();
});
