<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Packages;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\AssetRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\Exceptions\BlockPackageException;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\Mode;

/**
 * Loads block packages into the host's registries.
 *
 * Loading is all or nothing: a package that fails while registering, or whose block types, decorators or data
 * sources a registry refuses, adds nothing and is not listed as loaded.
 *
 * @since 0.1.0
 */
class BlockPackageLoader implements BlockPackageLoaderInterface
{
    /**
     * @var array<string, list<BlockName>>
     */
    private array $packages = [];

    /**
     * @var array<string, array{title: string, description: string}>
     */
    private array $details = [];

    /**
     * The package that last registered each output, by {@link self::outputKey()}.
     *
     * @var array<string, string>
     */
    private array $owners = [];

    /**
     * Takes each registry a package may write to, as a contract.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly BlockTypeRegistryInterface $types,
        private readonly BlockBehaviourRegistryInterface $behaviours,
        private readonly BlockOutputRegistryInterface $outputs,
        private readonly DecoratorRegistryInterface $decorators,
        private readonly SettingsMigrationRegistryInterface $migrations,
        private readonly DataSourceRegistryInterface $sources,
        private readonly StepRegistryInterface $steps,
        private readonly BlockTemplateRegistryInterface $templates,
        private readonly AssetRegistryInterface $assets,
    )
    {
    }

    public function load(BlockPackageInterface $package): void
    {
        $name = $package->name();

        if (preg_match('/^([a-z][a-z0-9-]*)\/[a-z][a-z0-9-]*\z/', $name, $match) !== 1)
        {
            throw new BlockPackageException(sprintf(
                '"%s" is not a valid package name; expected lower-case "vendor/package".',
                $name,
            ));
        }

        $title = trim($package->title());

        if ($title === '')
        {
            throw new BlockPackageException(sprintf('Package "%s" needs a title.', $name));
        }

        if (isset($this->packages[$name]))
        {
            throw new BlockPackageException(sprintf('Package "%s" is already loaded.', $name));
        }

        $registrar = new BlockPackageRegistrar(
            $name,
            $match[1],
            $this->claimed(),
            $this->types,
            $this->behaviours,
            $this->outputs,
            $this->decorators,
            $this->migrations,
            $this->sources,
            $this->steps,
            $this->templates,
            $this->assets,
        );

        $package->register($registrar);
        $registrar->commit();

        $this->packages[$name] = $registrar->types();
        $this->details[$name] = ['title' => $title, 'description' => trim($package->description())];

        foreach ($registrar->outputKeys() as $key)
        {
            $this->owners[$key] = $name;
        }
    }

    public function loaded(): array
    {
        return array_keys($this->packages);
    }

    public function typesOf(string $packageName): array
    {
        return $this->packages[$packageName] ?? [];
    }

    public function details(string $packageName): ?array
    {
        return $this->details[$packageName] ?? null;
    }

    public function outputOwner(BlockName $type, Mode $mode, string $target = 'html'): ?string
    {
        return $this->owners[self::outputKey($type, $mode, $target)] ?? null;
    }

    /**
     * Builds the key an output is recorded under; shared with the registrar so the two cannot disagree.
     *
     * @since 0.1.0
     */
    public static function outputKey(BlockName $type, Mode $mode, string $target): string
    {
        return $type->toString() . '|' . $mode->value . '|' . $target;
    }

    public function packageOf(BlockName $type): ?string
    {
        return $this->claimed()[$type->toString()] ?? null;
    }

    /**
     * Maps every block type a loaded package added to that package, so a later package cannot take the name.
     *
     * @return array<string, string>
     *
     * @since 0.1.0
     */
    private function claimed(): array
    {
        $claimed = [];

        foreach ($this->packages as $package => $types)
        {
            foreach ($types as $type)
            {
                $claimed[$type->toString()] = $package;
            }
        }

        return $claimed;
    }
}
