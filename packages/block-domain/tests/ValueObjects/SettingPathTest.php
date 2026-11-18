<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Exceptions\InvalidSettingPathException;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

test('setting paths address settings and decorator extensions', function () {
    $own = SettingPath::fromString('style.color');
    $ext = SettingPath::fromString('@acme/visibility:rules.0');
    expect($own->namespace())->toBeNull()->and($own->segments())->toBe(['style', 'color'])
        ->and($ext->namespace())->toBe('acme/visibility')->and($ext->segments())->toBe(['rules', '0'])
        ->and($ext->toString())->toBe('@acme/visibility:rules.0');
});

test('malformed setting paths are rejected', function (string $path) {
    expect(fn () => SettingPath::fromString($path))->toThrow(InvalidSettingPathException::class);
})->with(['', '.a', 'a..b', '@:a', '@Bad/Ns:a', '@acme/x:', "a\n", 'a.b.', 'a b']);
