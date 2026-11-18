<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Ids\BlockDuplicator;
use SpeedySpec\Block\App\Ids\RandomBlockIdGenerator;
use SpeedySpec\Block\Domain\Contracts\BlockIdGeneratorInterface;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Exceptions\DuplicateBlockIdException;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

test('duplicating gives the block and every descendant a new id and changes nothing else', function () {
    $original = block('a', 'acme/box', ['n' => 1], block('b', 'acme/box', ['n' => 2]));
    $copy = (new BlockDuplicator(new RandomBlockIdGenerator()))->duplicate($original);
    expect($copy->id)->not->toBe('a')->and($copy->children[0]->id)->not->toBe('b')
        ->and($copy->id)->not->toBe($copy->children[0]->id)
        ->and($copy->settings)->toBe(['n' => 1])->and($copy->children[0]->settings)->toBe(['n' => 2])
        ->and(fn () => new BlockTree($original, $copy))->not->toThrow(DuplicateBlockIdException::class);
});

test('generated ids are 16 hex characters and differ', function () {
    $g = new RandomBlockIdGenerator();
    expect($g->generate())->toMatch('/^[0-9a-f]{16}\z/')->and($g->generate())->not->toBe($g->generate());
});

test('duplicating preserves type, version, settings, extensions, bindings and child order', function () {
    $path = SettingPath::fromString('label');
    $binding = Binding::to('acme/title');
    $original = block('a', 'acme/box', ['n' => 1], block('b', 'acme/leaf'), block('c', 'acme/other'))
        ->withTypeVersion(3)
        ->withExtension('acme/anim', ['speed' => 2])
        ->withBinding($path, $binding);
    $copy = (new BlockDuplicator(new RandomBlockIdGenerator()))->duplicate($original);
    expect($copy->type->toString())->toBe('acme/box')
        ->and($copy->typeVersion)->toBe(3)
        ->and($copy->extensions)->toBe(['acme/anim' => ['speed' => 2]])
        ->and($copy->bindings)->toBe($original->bindings)
        ->and(array_map(fn ($b) => $b->type->toString(), $copy->children))->toBe(['acme/leaf', 'acme/other']);
});

test('a generator that repeats an id is retried until the copy is unique', function () {
    $ids = new class implements BlockIdGeneratorInterface {
        private array $queue = ['x', 'x', 'x', 'y', 'y', 'z'];

        public function generate(): string
        {
            return array_shift($this->queue);
        }
    };
    $copy = (new BlockDuplicator($ids))->duplicate(block('a', 'acme/box', [], block('b'), block('c')));
    expect($copy->id)->toBe('x')->and($copy->children[0]->id)->toBe('y')->and($copy->children[1]->id)->toBe('z');
});

test('an id from the original is never reused in the copy', function () {
    $ids = new class implements BlockIdGeneratorInterface {
        private array $queue = ['b', 'a', 'x', 'y'];

        public function generate(): string
        {
            return array_shift($this->queue);
        }
    };
    $original = block('a', 'acme/box', [], block('b'));
    $copy = (new BlockDuplicator($ids))->duplicate($original);
    expect($copy->id)->toBe('x')->and($copy->children[0]->id)->toBe('y');
});

test('a generator that never varies throws instead of hanging', function () {
    $ids = new class implements BlockIdGeneratorInterface {
        public function generate(): string
        {
            return 'same';
        }
    };
    expect(fn () => (new BlockDuplicator($ids))->duplicate(block('a', 'acme/box', [], block('b'))))
        ->toThrow(RuntimeException::class, 'failed to produce an unused id');
});

test('reserved ids are avoided', function () {
    $ids = new class implements BlockIdGeneratorInterface {
        private array $queue = ['r1', 'r2', 'ok'];

        public function generate(): string
        {
            return array_shift($this->queue);
        }
    };
    $copy = (new BlockDuplicator($ids))->duplicate(block('a'), ['r1', 'r2']);
    expect($copy->id)->toBe('ok');
});
