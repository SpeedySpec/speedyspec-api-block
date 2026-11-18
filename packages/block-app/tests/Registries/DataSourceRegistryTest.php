<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Registries\DataSourceRegistry;
use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\Domain\Contracts\DataSourceInterface;
use SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\DataSourceResult;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

function source(string $name, array $value = ['type' => 'string']): DataSourceInterface
{
    return new class($name, $value) implements DataSourceInterface {
        public function __construct(private readonly string $name, private readonly array $value)
        {
        }

        public function name(): string
        {
            return $this->name;
        }

        public function argumentsSchema(): array
        {
            return ['type' => 'object'];
        }

        public function valueSchema(): array
        {
            return $this->value;
        }

        public function resolve(array $arguments, Context $context, Mode $mode): DataSourceResult
        {
            return DataSourceResult::none();
        }
    };
}

test('sources can be added, replaced by name, read and removed', function () {
    $registry = new DataSourceRegistry(new SubsetSchemaValidator());
    $first = source('acme/title');
    $second = source('acme/title');
    $registry->add($first);
    expect($registry->get('acme/title'))->toBe($first);
    $registry->add($second);
    expect($registry->get('acme/title'))->toBe($second);
    $registry->remove('acme/title');
    expect($registry->get('acme/title'))->toBeNull();
});

test('a source whose schema is unsupported is rejected', function () {
    $registry = new DataSourceRegistry(new SubsetSchemaValidator());
    expect(fn () => $registry->add(source('acme/bad', ['type' => 'string', 'format' => 'email'])))
        ->toThrow(UnsupportedSchemaException::class)
        ->and($registry->get('acme/bad'))->toBeNull();
});
