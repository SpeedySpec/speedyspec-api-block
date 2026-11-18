<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\BlockName;

/**
 * Holds settings migrations per block type, so version upgrades can be chained without the caller knowing them.
 *
 * @since 0.1.0
 */
interface SettingsMigrationRegistryInterface
{
    /**
     * Registers a migration, replacing any migration of the same type and starting version.
     *
     * @since 0.1.0
     */
    public function add(BlockName $type, SettingsMigrationInterface $migration): void;

    /**
     * Finds the ordered migrations that take settings from one version to another.
     *
     * @param BlockName $type
     *   The block type.
     * @param int $from
     *   The stored version.
     * @param int $to
     *   The wanted version.
     *
     * @return list<SettingsMigrationInterface>|null
     *   Empty when the versions are equal; null when a step is missing or `$from` is above `$to`.
     *
     * @since 0.1.0
     */
    public function chain(BlockName $type, int $from, int $to): ?array;
}
