<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockLock;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

$block = fn (array $extensions = []) => new Block('a', BlockName::fromString('acme/box'), 1, [], $extensions);

test('a block is unlocked until its lock extension says otherwise, and only true locks', function () use ($block) {
    $none = BlockLock::of($block());
    $move = BlockLock::of($block(['core/lock' => ['move' => true]]));
    $loose = BlockLock::of($block(['core/lock' => ['remove' => 'yes', 'move' => 1]]));

    expect([$none->move, $none->remove, $none->isLocked()])->toBe([false, false, false])
        ->and([$move->move, $move->remove, $move->isLocked()])->toBe([true, false, true])
        ->and([$loose->move, $loose->remove, $loose->isLocked()])->toBe([false, false, false])
        ->and(BlockLock::NAMESPACE)->toBe('core/lock');
});

test('the lock is a decorator for every type, so any builder can show and change it', function () {
    $decorator = BlockLock::decorator();

    expect($decorator->namespace)->toBe('core/lock')
        ->and($decorator->appliesTo)->toBeNull()
        ->and($decorator->settingsSchema)->toBe([
            'type' => 'object',
            'properties' => [
                'move' => ['type' => 'boolean', 'default' => false, 'title' => 'Prevent moving'],
                'remove' => ['type' => 'boolean', 'default' => false, 'title' => 'Prevent removing'],
            ],
        ]);
});
