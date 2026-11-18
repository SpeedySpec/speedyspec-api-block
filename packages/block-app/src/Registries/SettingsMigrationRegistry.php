<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Registries;

use SpeedySpec\Block\Domain\Contracts\SettingsMigrationInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationRegistryInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

/**
 * Keeps settings migrations in memory, indexed by type and starting version so chains need no sorting by callers.
 *
 * @since 0.1.0
 */
class SettingsMigrationRegistry implements SettingsMigrationRegistryInterface
{
    /**
     * @var array<string, array<int, SettingsMigrationInterface>>
     */
    private array $migrations = [];

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function add(BlockName $type, SettingsMigrationInterface $migration): void
    {
        $this->migrations[$type->toString()][$migration->from()] = $migration;
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function chain(BlockName $type, int $from, int $to): ?array
    {
        if ($from > $to)
        {
            return null;
        }

        $chain = [];

        for ($version = $from; $version < $to; $version++)
        {
            $migration = $this->migrations[$type->toString()][$version] ?? null;

            if ($migration === null)
            {
                return null;
            }

            $chain[] = $migration;
        }

        return $chain;
    }
}
