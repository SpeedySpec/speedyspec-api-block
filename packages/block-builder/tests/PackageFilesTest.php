<?php

declare(strict_types=1);

use SpeedySpec\Block\Builder\PackageFiles;

/**
 * Builds two small packages in a scratch directory, with a file outside them and a link that leads out.
 *
 * @return array{0: PackageFiles, 1: string}
 */
function packageFiles(): array
{
    $root = sys_get_temp_dir() . '/speedyspec-package-files-' . bin2hex(random_bytes(6));
    mkdir($root . '/a/assets', 0775, true);
    mkdir($root . '/b', 0775, true);
    file_put_contents($root . '/a/assets/app.js', 'export {};');
    file_put_contents($root . '/a/assets/look.css', '.a{}');
    file_put_contents($root . '/a/assets/secret.php', '<?php');
    file_put_contents($root . '/outside.js', 'nope');
    symlink($root . '/outside.js', $root . '/a/assets/escape.js');

    return [new PackageFiles(['acme/a' => $root . '/a', 'acme/b' => $root . '/b']), $root];
}

test('a package file has an address under the prefix', function () {
    [$files] = packageFiles();

    expect($files->url('acme/a', 'assets/app.js'))->toBe('/packages/acme/a/assets/app.js')
        ->and($files->url('acme/unknown', 'assets/app.js'))->toBe('')
        ->and((new PackageFiles(['acme/a' => '/x'], '/static/'))->url('acme/a', 'y.js'))->toBe('/static/acme/a/y.js');
});

test('an address gives the package file it names, with its type', function () {
    [$files, $root] = packageFiles();

    expect($files->file('/packages/acme/a/assets/app.js'))
        ->toBe(['path' => realpath($root . '/a/assets/app.js'), 'type' => 'text/javascript'])
        ->and($files->file('/packages/acme/a/assets/look.css')['type'] ?? null)->toBe('text/css');
});

test('an address that leaves its package, or names a file it should not serve, gives nothing', function (string $path) {
    [$files] = packageFiles();

    expect($files->file($path))->toBeNull();
})->with([
    'up and out' => ['/packages/acme/a/../outside.js'],
    'up and out, encoded' => ['/packages/acme/a/%2e%2e/outside.js'],
    'into the other package' => ['/packages/acme/b/../a/assets/app.js'],
    'a PHP file' => ['/packages/acme/a/assets/secret.php'],
    'a missing file' => ['/packages/acme/a/assets/missing.js'],
    'an unknown package' => ['/packages/acme/unknown/assets/app.js'],
    'a link that leads out' => ['/packages/acme/a/assets/escape.js'],
    'not under the prefix' => ['/other/acme/a/assets/app.js'],
    'no file at all' => ['/packages/acme/a'],
]);
