<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\SettingsMigrationInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;

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
