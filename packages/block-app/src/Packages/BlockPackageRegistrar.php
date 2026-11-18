<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Packages;

use InvalidArgumentException;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\Exceptions\BlockPackageException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Decorator;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use Throwable;

/**
 * Collects one package's registrations and writes them to the host's registries only once the package is done.
 *
 * Nothing reaches a registry while the package is still registering, so a package that fails part-way leaves no
 * half of itself behind.
 *
 * @since 0.1.0
 */
class BlockPackageRegistrar implements BlockPackageRegistrarInterface
{
    /**
     * @var list<BlockName>
     */
    private array $types = [];

    /**
     * @var list<BlockType>
     */
    private array $pendingTypes = [];

    /**
     * @var list<Decorator>
     */
    private array $pendingDecorators = [];

    /**
     * @var list<DataSourceInterface>
     */
    private array $pendingSources = [];

    /**
     * @var list<callable(): void>
     */
    private array $pendingOther = [];

    /**
     * Binds the registrar to one package so it can enforce what that package may claim.
     *
     * @param string $vendor
     *   The vendor part of the package's name.
     * @param array<string, string> $claimed
     *   Block type names other loaded packages added, mapped to the package that added each.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly string $packageName,
        private readonly string $vendor,
        private readonly array $claimed,
        private readonly BlockTypeRegistryInterface $typeRegistry,
        private readonly BlockBehaviourRegistryInterface $behaviours,
        private readonly BlockOutputRegistryInterface $outputs,
        private readonly DecoratorRegistryInterface $decorators,
        private readonly SettingsMigrationRegistryInterface $migrations,
        private readonly DataSourceRegistryInterface $sources,
        private readonly StepRegistryInterface $steps,
    )
    {
    }

    public function type(BlockType $type): void
    {
        $name = $type->name->toString();

        if ($type->name->vendor() !== $this->vendor)
        {
            throw new BlockPackageException(sprintf(
                'Package "%s" may only add block types under "%s/"; "%s" is not one.',
                $this->packageName,
                $this->vendor,
                $name,
            ));
        }

        if (isset($this->claimed[$name]))
        {
            throw new BlockPackageException(sprintf(
                'Block type "%s" was already added by package "%s".',
                $name,
                $this->claimed[$name],
            ));
        }

        $this->pendingTypes[] = $type;

        foreach ($this->types as $added)
        {
            if ($added->equals($type->name))
            {
                return;
            }
        }

        $this->types[] = $type->name;
    }

    public function behaviour(BlockName $type, BlockBehaviourInterface $behaviour): void
    {
        $this->pendingOther[] = fn () => $this->behaviours->set($type, $behaviour);
    }

    public function output(
        BlockName $type,
        BlockOutputInterface $output,
        Mode $mode = Mode::Front,
        string $target = 'html',
    ): void
    {
        $this->pendingOther[] = fn () => $this->outputs->set($type, $output, $mode, $target);
    }

    public function decorator(Decorator $decorator): void
    {
        $this->pendingDecorators[] = $decorator;
    }

    public function migration(BlockName $type, SettingsMigrationInterface $migration): void
    {
        $this->pendingOther[] = fn () => $this->migrations->add($type, $migration);
    }

    public function dataSource(DataSourceInterface $source): void
    {
        $this->pendingSources[] = $source;
    }

    public function step(string $stepInterface, object $step, int $priority = 10): void
    {
        if (!$step instanceof $stepInterface)
        {
            throw new InvalidArgumentException(sprintf(
                'Package "%s" registered a step that is not a %s.',
                $this->packageName,
                $stepInterface,
            ));
        }

        $this->pendingOther[] = fn () => $this->steps->add($stepInterface, $step, $priority);
    }

    /**
     * Writes everything the package registered to the host's registries, once the package finished without error.
     *
     * Types, decorators and data sources go first because a registry can refuse them; if one is refused, those
     * already written by this call are removed again, so a refused package leaves nothing behind.
     *
     * @since 0.1.0
     */
    public function commit(): void
    {
        $types = [];
        $decorators = [];
        $sources = [];

        try
        {
            foreach ($this->pendingTypes as $type)
            {
                $this->typeRegistry->add($type);
                $types[] = $type->name;
            }

            foreach ($this->pendingDecorators as $decorator)
            {
                $this->decorators->add($decorator);
                $decorators[] = $decorator->namespace;
            }

            foreach ($this->pendingSources as $source)
            {
                $this->sources->add($source);
                $sources[] = $source->name();
            }
        }
        catch (Throwable $e)
        {
            foreach ($types as $name)
            {
                $this->typeRegistry->remove($name);
            }

            foreach ($decorators as $namespace)
            {
                $this->decorators->remove($namespace);
            }

            foreach ($sources as $name)
            {
                $this->sources->remove($name);
            }

            throw $e;
        }

        foreach ($this->pendingOther as $write)
        {
            $write();
        }
    }

    /**
     * Lists the block types this package added, in order, for the loader to remember.
     *
     * @return list<BlockName>
     *
     * @since 0.1.0
     */
    public function types(): array
    {
        return $this->types;
    }
}
