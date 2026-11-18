<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

/**
 * Upgrades stored settings by one type version, so old documents keep loading after a type changes.
 *
 * @since 0.1.0
 */
interface SettingsMigrationInterface
{
    /**
     * Identifies the version this migration upgrades from.
     *
     * @since 0.1.0
     */
    public function from(): int;

    /**
     * Moves settings from version {@link SettingsMigrationInterface::from()} to the next version.
     *
     * @param array<string, mixed> $settings
     *   The settings at the older version.
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function migrate(array $settings): array;
}
