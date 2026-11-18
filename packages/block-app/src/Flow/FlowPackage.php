<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Flow;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\CollectionSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\ConditionRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\GateStepInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Contracts\ValidateStepInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

/**
 * The block package for what content does rather than what it is: showing blocks by rule and repeating them.
 *
 * It adds visibility rules to every block type, the repeating block, the data source that reads the item being
 * repeated, and a condition on that item. It is an ordinary block package, so a host that wants none of it does
 * not load it, and one that wants its own loads that instead.
 *
 * @since 0.1.0
 */
class FlowPackage implements BlockPackageInterface
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly ConditionRegistryInterface $conditions,
        private readonly CollectionSourceRegistryInterface $collections,
        private readonly SchemaValidatorInterface $validator,
    )
    {
    }

    /**
     * Builds the package from the registries bound in a container, so it shares them with the host.
     *
     * @since 0.1.0
     */
    public static function from(BlockServiceContainer $container): self
    {
        return new self(
            $container->get(ConditionRegistryInterface::class),
            $container->get(CollectionSourceRegistryInterface::class),
            $container->get(SchemaValidatorInterface::class),
        );
    }

    public function name(): string
    {
        return 'core/flow';
    }

    public function title(): string
    {
        return 'Show by rule and repeat';
    }

    public function description(): string
    {
        return 'Rules for when a block is shown, and a block that repeats its contents for each item of a list.';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $visibility = new Visibility($this->conditions, $this->validator);
        $repeat = new Repeat($this->collections, $this->validator);
        $name = BlockName::fromString(Repeat::NAME);

        $registrar->decorator(Visibility::decorator());
        $registrar->step(GateStepInterface::class, $visibility);
        $registrar->step(ValidateStepInterface::class, $visibility);

        $registrar->type(Repeat::type());
        $registrar->behaviour($name, $repeat);
        $registrar->output($name, $repeat);
        $registrar->step(ValidateStepInterface::class, $repeat);

        $registrar->dataSource(new CurrentItemSource());
        $this->conditions->add(new CurrentItemIs());
    }
}
