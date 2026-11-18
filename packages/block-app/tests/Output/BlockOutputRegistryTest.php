<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Output\BlockOutputRegistry;
use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;

if (!function_exists('registryOutput'))
{
    function registryOutput(string $text): BlockOutputInterface
    {
        return new class($text) implements BlockOutputInterface {
            public function __construct(private readonly string $text)
            {
            }

            public function render(RenderInput $input): string
            {
                return $this->text;
            }
        };
    }
}

test('admin falls back to the front output and prefers an admin one', function () {
    $registry = new BlockOutputRegistry();
    $name = BlockName::fromString('acme/box');
    $front = registryOutput('front');
    $registry->set($name, $front);

    expect($registry->get($name, Mode::Admin))->toBe($front)->and($registry->has($name, Mode::Admin))->toBeFalse();

    $admin = registryOutput('admin');
    $registry->set($name, $admin, Mode::Admin);

    expect($registry->get($name, Mode::Admin))->toBe($admin)
        ->and($registry->has($name, Mode::Admin))->toBeTrue()
        ->and($registry->get($name, Mode::Front))->toBe($front);
});

test('front never falls back to an admin output', function () {
    $registry = new BlockOutputRegistry();
    $name = BlockName::fromString('acme/box');
    $registry->set($name, registryOutput('admin'), Mode::Admin);

    expect($registry->get($name, Mode::Front))->toBeNull()->and($registry->has($name, Mode::Front))->toBeFalse();
});

test('outputs are kept per target and can be replaced and removed', function () {
    $registry = new BlockOutputRegistry();
    $name = BlockName::fromString('acme/box');
    $registry->set($name, registryOutput('one'));
    $two = registryOutput('two');
    $registry->set($name, $two);

    expect($registry->get($name, Mode::Front))->toBe($two)->and($registry->get($name, Mode::Front, 'text'))->toBeNull();

    $registry->remove($name, Mode::Front);

    expect($registry->get($name, Mode::Front))->toBeNull();
});

test('outputs are kept per block type', function () {
    $registry = new BlockOutputRegistry();
    $registry->set(BlockName::fromString('acme/box'), registryOutput('box'));

    expect($registry->get(BlockName::fromString('acme/other'), Mode::Front))->toBeNull();
});

test('removing an output that was never set does nothing', function () {
    $registry = new BlockOutputRegistry();
    $registry->remove(BlockName::fromString('acme/box'), Mode::Admin);

    expect($registry->has(BlockName::fromString('acme/box'), Mode::Admin))->toBeFalse();
});
