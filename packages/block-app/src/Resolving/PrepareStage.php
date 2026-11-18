<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Resolving;

use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\PrepareStepInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Brings a stored block up to its type's current shape: migrated, adjusted by prepare steps and filled with defaults.
 *
 * @since 0.1.0
 */
class PrepareStage
{
    /**
     * Wires the stage to the registries it reads.
     *
     * @param BlockTypeRegistryInterface $types
     *   Finds the block's type.
     * @param SettingsMigrationRegistryInterface $migrations
     *   Upgrades settings stored under an older type version.
     * @param DecoratorRegistryInterface $decorators
     *   Supplies the extension defaults for the type.
     * @param SchemaValidatorInterface $validator
     *   Applies schema defaults.
     * @param StepRegistryInterface $steps
     *   Supplies the prepare steps.
     */
    public function __construct(
        private readonly BlockTypeRegistryInterface $types,
        private readonly SettingsMigrationRegistryInterface $migrations,
        private readonly DecoratorRegistryInterface $decorators,
        private readonly SchemaValidatorInterface $validator,
        private readonly StepRegistryInterface $steps,
    )
    {
    }

    /**
     * Prepares one block; an unknown type or a version that cannot be migrated stops it with an issue.
     *
     * @since 0.1.0
     */
    public function run(Block $block, Context $context, Mode $mode): StageOutcome
    {
        $type = $this->types->get($block->type);

        if ($type === null)
        {
            return $this->stopped($block, IssueKind::UnknownType, sprintf(
                'Block type "%s" is not registered.',
                $block->type->toString(),
            ));
        }

        $chain = $this->migrations->chain($block->type, $block->typeVersion, $type->version);

        if ($chain === null)
        {
            return $this->stopped($block, IssueKind::MigrationFailed, sprintf(
                'Settings of "%s" cannot be migrated from version %d to %d.',
                $block->type->toString(),
                $block->typeVersion,
                $type->version,
            ));
        }

        $settings = $block->settings;

        foreach ($chain as $migration)
        {
            $settings = $migration->migrate($settings);
        }

        $block = $block->withSettings($settings)->withTypeVersion($type->version);
        $working = $block;

        foreach ($this->steps->for(PrepareStepInterface::class) as $step)
        {
            $returned = $step->prepare($working, $type, $context, $mode);
            $working = new Block(
                $working->id,
                $working->type,
                $working->typeVersion,
                $returned->settings,
                $returned->extensions,
                $working->bindings,
                $working->children,
            );
        }

        $extensions = $working->extensions;

        foreach ($this->decorators->for($type->name) as $decorator)
        {
            $extensions[$decorator->namespace] = $this->validator->withDefaults(
                $extensions[$decorator->namespace] ?? [],
                $decorator->settingsSchema,
            );
        }

        return new StageOutcome(
            $block,
            $this->validator->withDefaults($working->settings, $type->settingsSchema),
            $extensions,
            [],
            true,
        );
    }

    private function stopped(Block $block, IssueKind $kind, string $message): StageOutcome
    {
        return new StageOutcome(
            $block,
            $block->settings,
            $block->extensions,
            [new Issue($block->id, $kind, $message)],
            false,
        );
    }
}
