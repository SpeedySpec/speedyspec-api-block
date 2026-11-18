<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Registries\BlockTypeRegistry;
use SpeedySpec\Block\App\Registries\DataSourceRegistry;
use SpeedySpec\Block\App\Registries\DecoratorRegistry;
use SpeedySpec\Block\App\Registries\SettingsMigrationRegistry;
use SpeedySpec\Block\App\Registries\StepRegistry;
use SpeedySpec\Block\App\Resolving\GateStage;
use SpeedySpec\Block\App\Resolving\PrepareStage;
use SpeedySpec\Block\App\Resolving\ResolveStage;
use SpeedySpec\Block\App\Resolving\ValidateStage;
use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\Domain\Contracts\GateStepInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

if (!function_exists('type'))
{
    /**
     * Builds a block type for tests; the overrides win over the name and title defaults.
     */
    function type(string $name, array $over = []): BlockType
    {
        return BlockType::fromArray($over + ['name' => $name, 'title' => $name]);
    }
}

if (!function_exists('block'))
{
    /**
     * Builds a block for tests; named children arguments are collected under string keys, so they are re-indexed.
     */
    function block(string $id, string $type = 'acme/box', array $settings = [], Block ...$children): Block
    {
        return new Block($id, BlockName::fromString($type), 1, $settings, [], [], array_values($children));
    }
}

if (!function_exists('migration'))
{
    /**
     * Builds a migration for tests that leaves settings unchanged.
     */
    function migration(int $from): SettingsMigrationInterface
    {
        return new class($from) implements SettingsMigrationInterface {
            public function __construct(private readonly int $from)
            {
            }

            public function from(): int
            {
                return $this->from;
            }

            public function migrate(array $settings): array
            {
                return $settings;
            }
        };
    }
}

if (!function_exists('stages'))
{
    /**
     * Wires the four resolving stages over fresh registries sharing one validator; $setup registers fixtures.
     */
    function stages(?callable $setup = null): object
    {
        $validator = new SubsetSchemaValidator();
        $s = new stdClass();
        $s->types = new BlockTypeRegistry($validator);
        $s->migrations = new SettingsMigrationRegistry();
        $s->decorators = new DecoratorRegistry($validator);
        $s->sources = new DataSourceRegistry($validator);
        $s->steps = new StepRegistry();
        $s->prepare = new PrepareStage($s->types, $s->migrations, $s->decorators, $validator, $s->steps);
        $s->gate = new GateStage($s->steps);
        $s->resolve = new ResolveStage($s->sources, $s->decorators, $validator);
        $s->validate = new ValidateStage($s->decorators, $validator, $s->steps);

        if ($setup !== null)
        {
            $setup($s);
        }

        return $s;
    }
}

if (!function_exists('kinds'))
{
    /**
     * Lists the kinds of the given issues, in order.
     */
    function kinds(array $issues): array
    {
        return array_map(static fn (Issue $issue) => $issue->kind, $issues);
    }
}

if (!function_exists('gateStep'))
{
    /**
     * Builds a gate step that always gives the same answer.
     */
    function gateStep(bool $shown): GateStepInterface
    {
        return new class($shown) implements GateStepInterface {
            public function __construct(private readonly bool $shown)
            {
            }

            public function shown(Block $block, BlockType $type, Context $context, Mode $mode): bool
            {
                return $this->shown;
            }
        };
    }
}
