<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\RenderStepInterface;
use SpeedySpec\Block\Domain\Exceptions\BlockPackageException;
use SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Infra\Memory\ArrayDataSource;

if (!function_exists('package'))
{
    function package(string $name, callable $register): BlockPackageInterface
    {
        return new class($name, $register) implements BlockPackageInterface {
            private $register;

            public function __construct(private readonly string $name, callable $register)
            {
                $this->register = $register;
            }

            public function name(): string
            {
                return $this->name;
            }

            public function register(BlockPackageRegistrarInterface $registrar): void
            {
                ($this->register)($registrar);
            }
        };
    }
}

$text = ['type' => 'object', 'properties' => ['text' => ['type' => 'string']]];

test('a package registers its types, behaviours and outputs through the registrar', function () use ($text) {
    [$renderer, $c] = renderer();
    $loader = $c->get(BlockPackageLoaderInterface::class);
    $loader->load(package('acme/basic', function (BlockPackageRegistrarInterface $r) use ($text) {
        $r->type(type('acme/note', ['settings' => $text]));
        $r->output(BlockName::fromString('acme/note'), tag('p'));
    }));

    expect($renderer->render(doc(block('a', 'acme/note', ['text' => 'hi'])), new RenderRequest())->output)->toBe('<p>hi</p>')
        ->and($loader->loaded())->toBe(['acme/basic'])
        ->and(array_map(fn (BlockName $n) => $n->toString(), $loader->typesOf('acme/basic')))->toBe(['acme/note'])
        ->and($loader->typesOf('never/loaded'))->toBe([]);
});

test('a package cannot register a type under another vendor or reuse a loaded type name', function () {
    [, $c] = renderer();
    $loader = $c->get(BlockPackageLoaderInterface::class);

    expect(fn () => $loader->load(package('acme/basic', fn ($r) => $r->type(type('other/note')))))
        ->toThrow(BlockPackageException::class);

    $loader->load(package('acme/one', fn ($r) => $r->type(type('acme/note'))));

    expect(fn () => $loader->load(package('acme/two', fn ($r) => $r->type(type('acme/note')))))
        ->toThrow(BlockPackageException::class);
});

test('a package cannot be loaded twice and needs a valid name', function () {
    [, $c] = renderer();
    $loader = $c->get(BlockPackageLoaderInterface::class);
    $loader->load(package('acme/basic', fn ($r) => null));

    expect(fn () => $loader->load(package('acme/basic', fn ($r) => null)))->toThrow(BlockPackageException::class)
        ->and(fn () => $loader->load(package('Not A Name', fn ($r) => null)))->toThrow(BlockPackageException::class)
        ->and($loader->loaded())->toBe(['acme/basic']);
});

test('another package may replace the output of a block it did not add', function () {
    [$renderer, $c] = renderer();
    $loader = $c->get(BlockPackageLoaderInterface::class);
    $loader->load(package('acme/basic', function ($r) {
        $r->type(type('acme/note'));
        $r->output(BlockName::fromString('acme/note'), fixedOutput('plain'));
    }));
    $loader->load(package('theme/fancy', fn ($r) => $r->output(BlockName::fromString('acme/note'), fixedOutput('fancy'))));

    expect($renderer->render(doc(block('a', 'acme/note')), new RenderRequest())->output)->toBe('fancy')
        ->and($loader->typesOf('theme/fancy'))->toBe([]);
});

test('a package can add decorators, data sources, migrations, admin outputs and steps', function () use ($text) {
    [$renderer, $c] = renderer();
    $c->get(BlockPackageLoaderInterface::class)->load(package('acme/full', function (BlockPackageRegistrarInterface $r) use ($text) {
        $name = BlockName::fromString('acme/note');
        $r->type(type('acme/note', ['version' => 2, 'settings' => $text]));
        $r->migration($name, migrationTo(['text' => 'migrated']));
        $r->output($name, tag('p'));
        $r->output($name, fixedOutput('[admin]'), Mode::Admin);
        $r->decorator(new Decorator('acme/fx', ['type' => 'object']));
        $r->dataSource(new ArrayDataSource('acme/values', ['k' => 'bound']));
        $r->step(RenderStepInterface::class, renderStep(fn (AttributeBag $bag) => $bag->withClass('fx')));
    }));
    $old = block('a', 'acme/note', ['title' => 'x']);

    expect($renderer->render(doc($old), new RenderRequest())->output)->toBe('<p class="fx">migrated</p>')
        ->and($renderer->render(doc($old), new RenderRequest(Mode::Admin))->output)->toBe('[admin]')
        ->and($c->get(DataSourceRegistryInterface::class)->get('acme/values'))->not->toBeNull()
        ->and($c->get(DecoratorRegistryInterface::class)->get('acme/fx'))->not->toBeNull();
});

test('a registration the registries refuse is not swallowed', function () {
    [, $c] = renderer();
    $loader = $c->get(BlockPackageLoaderInterface::class);
    $bad = type('acme/bad', ['settings' => ['type' => 'object', 'properties' => ['a' => ['format' => 'email']]]]);

    expect(fn () => $loader->load(package('acme/broken', fn ($r) => $r->type($bad))))->toThrow(UnsupportedSchemaException::class);
});

test('a package that registers nothing still loads', function () {
    [, $c] = renderer();
    $loader = $c->get(BlockPackageLoaderInterface::class);
    $loader->load(package('acme/empty', fn ($r) => null));

    expect($loader->loaded())->toBe(['acme/empty'])->and($loader->typesOf('acme/empty'))->toBe([]);
});

test('a package that fails to load is not listed as loaded', function () {
    [, $c] = renderer();
    $loader = $c->get(BlockPackageLoaderInterface::class);

    try
    {
        $loader->load(package('acme/basic', fn ($r) => $r->type(type('other/note'))));
    }
    catch (BlockPackageException)
    {
    }

    expect($loader->loaded())->toBe([]);
});
