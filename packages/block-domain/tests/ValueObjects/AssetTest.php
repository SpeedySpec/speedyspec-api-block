<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Exceptions\InvalidAssetException;
use SpeedySpec\Block\Domain\ValueObjects\Asset;
use SpeedySpec\Block\Domain\ValueObjects\AssetKind;

test('an asset is inline content, a URL or a file inside a package', function () {
    $inline = Asset::inline('acme/inline', AssetKind::Style, '.x{}', ['acme/base']);
    $url = Asset::url('acme/remote', AssetKind::Script, 'https://cdn.example/a.js');
    $local = Asset::url('acme/local', AssetKind::Script, '/assets/a.js');
    $file = Asset::file('acme/tabs', AssetKind::Script, 'acme/blocks', 'dist/tabs.js');

    expect([$inline->source, $inline->content, $inline->dependencies])->toBe(['inline', '.x{}', ['acme/base']])
        ->and([$url->source, $url->url])->toBe(['url', 'https://cdn.example/a.js'])
        ->and($local->url)->toBe('/assets/a.js')
        ->and([$file->source, $file->package, $file->path, $file->kind])
        ->toBe(['file', 'acme/blocks', 'dist/tabs.js', AssetKind::Script]);
});

test('an asset refuses a name, path or URL that could reach somewhere else', function (callable $make) {
    expect($make)->toThrow(InvalidAssetException::class);
})->with([
    'bad name' => [fn () => Asset::file('Bad Name', AssetKind::Script, 'acme/blocks', 'tabs.js')],
    'parent path' => [fn () => Asset::file('acme/tabs', AssetKind::Script, 'acme/blocks', '../secrets.php')],
    'inner parent path' => [fn () => Asset::file('acme/tabs', AssetKind::Script, 'acme/blocks', 'a/../../b.js')],
    'absolute path' => [fn () => Asset::file('acme/tabs', AssetKind::Script, 'acme/blocks', '/etc/passwd')],
    'backslash path' => [fn () => Asset::file('acme/tabs', AssetKind::Script, 'acme/blocks', 'a\\b.js')],
    'empty path' => [fn () => Asset::file('acme/tabs', AssetKind::Script, 'acme/blocks', '')],
    'bad package' => [fn () => Asset::file('acme/tabs', AssetKind::Script, 'Acme', 'tabs.js')],
    'script url' => [fn () => Asset::url('acme/tabs', AssetKind::Script, 'javascript:alert(1)')],
    'protocol-relative url' => [fn () => Asset::url('acme/tabs', AssetKind::Script, '//evil.example/a.js')],
    'bad dependency' => [fn () => Asset::inline('acme/tabs', AssetKind::Script, 'x', ['Not A Name'])],
    'self dependency' => [fn () => Asset::inline('acme/tabs', AssetKind::Script, 'x', ['acme/tabs'])],
]);
