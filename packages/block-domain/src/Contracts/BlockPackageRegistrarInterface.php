<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Exceptions\BlockPackageException;
use SpeedySpec\Block\Domain\ValueObjects\Asset;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * The only thing a block package touches while registering, so a package never needs a host's registries.
 *
 * @since 0.1.0
 */
interface BlockPackageRegistrarInterface
{
    /**
     * Adds a block type.
     *
     * @throws BlockPackageException
     *   When the type's vendor differs from the package's, or another loaded package already added that name.
     *
     * @since 0.1.0
     */
    public function type(BlockType $type): void;

    /**
     * Sets the behaviour that turns a block type's settings into its view.
     *
     * @since 0.1.0
     */
    public function behaviour(BlockName $type, BlockBehaviourInterface $behaviour): void;

    /**
     * Sets how a block type is drawn; a package may also replace the output of a block another package added.
     *
     * @since 0.1.0
     */
    public function output(
        BlockName $type,
        BlockOutputInterface $output,
        Mode $mode = Mode::Front,
        string $target = 'html',
    ): void;

    /**
     * Adds a group of settings that applies to many block types.
     *
     * @since 0.1.0
     */
    public function decorator(Decorator $decorator): void;

    /**
     * Adds a migration for a block type's stored settings.
     *
     * @since 0.1.0
     */
    public function migration(BlockName $type, SettingsMigrationInterface $migration): void;

    /**
     * Adds a data source settings can be bound to.
     *
     * @since 0.1.0
     */
    public function dataSource(DataSourceInterface $source): void;

    /**
     * Adds a step to one of the resolving or drawing stages.
     *
     * @param string $stepInterface
     *   The step contract the stage runs, for example RenderStepInterface::class.
     *
     * @since 0.1.0
     */
    public function step(string $stepInterface, object $step, int $priority = 10): void;

    /**
     * Adds a ready-made arrangement of blocks a builder can offer.
     *
     * @throws BlockPackageException
     *   When the template's vendor differs from the package's.
     *
     * @since 0.1.0
     */
    public function template(BlockTemplate $template): void;

    /**
     * Adds a script or style the package's outputs need on the page.
     *
     * @throws BlockPackageException
     *   When the asset's vendor differs from the package's, or another package already registered the name.
     *
     * @since 0.1.0
     */
    public function asset(Asset $asset): void;
}
