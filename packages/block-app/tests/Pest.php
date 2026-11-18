<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
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
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Contracts\GateStepInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\DataSourceResult;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;

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

if (!function_exists('idsAndKinds'))
{
    /**
     * Lists each issue as its block id and kind, in order.
     */
    function idsAndKinds(array $issues): array
    {
        return array_map(static fn (Issue $issue) => [$issue->blockId, $issue->kind], $issues);
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

if (!function_exists('resolver'))
{
    /**
     * Builds a document resolver from a fresh container with the memory and application providers registered.
     *
     * $setup receives the container before the resolver is resolved, so fixtures and replacements can be bound.
     *
     * @return array{0: DocumentResolverInterface, 1: BlockServiceContainer}
     */
    function resolver(?callable $setup = null): array
    {
        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);

        if ($setup !== null)
        {
            $setup($container);
        }

        return [$container->get(DocumentResolverInterface::class), $container];
    }
}

if (!function_exists('doc'))
{
    /**
     * Builds a page document holding the given root blocks.
     */
    function doc(Block ...$roots): Document
    {
        return new Document('d', 'page', new BlockTree(...array_values($roots)));
    }
}

if (!function_exists('types'))
{
    /**
     * Gets the block type registry bound in the container.
     */
    function types(BlockServiceContainer $container): BlockTypeRegistryInterface
    {
        return $container->get(BlockTypeRegistryInterface::class);
    }
}

if (!function_exists('sources'))
{
    /**
     * Gets the data source registry bound in the container.
     */
    function sources(BlockServiceContainer $container): DataSourceRegistryInterface
    {
        return $container->get(DataSourceRegistryInterface::class);
    }
}

if (!function_exists('behaviours'))
{
    /**
     * Gets the block behaviour registry bound in the container.
     */
    function behaviours(BlockServiceContainer $container): BlockBehaviourRegistryInterface
    {
        return $container->get(BlockBehaviourRegistryInterface::class);
    }
}

if (!function_exists('steps'))
{
    /**
     * Gets the step registry bound in the container.
     */
    function steps(BlockServiceContainer $container): StepRegistryInterface
    {
        return $container->get(StepRegistryInterface::class);
    }
}

if (!function_exists('countingSource'))
{
    /**
     * Builds a data source that counts how often it is asked for a value and always answers "x".
     */
    function countingSource(string $name, int &$calls): DataSourceInterface
    {
        return new class($name, $calls) implements DataSourceInterface {
            public function __construct(private readonly string $name, private int &$calls)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function argumentsSchema(): array
            {
                return ['type' => 'object'];
            }

            public function valueSchema(): array
            {
                return [];
            }

            public function resolve(array $arguments, Context $context, Mode $mode): DataSourceResult
            {
                $this->calls++;

                return DataSourceResult::value('x');
            }
        };
    }
}

if (!function_exists('renderer'))
{
    /**
     * Builds a document renderer from a fresh container with the memory and application providers registered.
     *
     * @return array{0: \SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface, 1: BlockServiceContainer}
     */
    function renderer(?callable $setup = null): array
    {
        $container = new BlockServiceContainer();
        (new MemoryServiceProvider())->register($container);
        (new AppServiceProvider())->register($container);

        if ($setup !== null)
        {
            $setup($container);
        }

        return [$container->get(\SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface::class), $container];
    }
}

if (!function_exists('outputs'))
{
    /**
     * Gets the block output registry bound in the container.
     */
    function outputs(BlockServiceContainer $container): \SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface
    {
        return $container->get(\SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface::class);
    }
}

if (!function_exists('tag'))
{
    /**
     * Builds an output that draws one element holding the view's escaped `text` value and the children.
     */
    function tag(string $element): \SpeedySpec\Block\Domain\Contracts\BlockOutputInterface
    {
        return new class($element) implements \SpeedySpec\Block\Domain\Contracts\BlockOutputInterface {
            public function __construct(private readonly string $element)
            {
            }

            public function render(\SpeedySpec\Block\Domain\ValueObjects\RenderInput $input): string
            {
                return sprintf(
                    '<%1$s%2$s>%3$s%4$s</%1$s>',
                    $this->element,
                    $input->escaper->attributes($input->attributes),
                    $input->escaper->text((string) ($input->view->values['text'] ?? '')),
                    $input->children(),
                );
            }
        };
    }
}

if (!function_exists('fixedOutput'))
{
    /**
     * Builds an output that always draws the same text.
     */
    function fixedOutput(string $text): \SpeedySpec\Block\Domain\Contracts\BlockOutputInterface
    {
        return new class($text) implements \SpeedySpec\Block\Domain\Contracts\BlockOutputInterface {
            public function __construct(private readonly string $text)
            {
            }

            public function render(\SpeedySpec\Block\Domain\ValueObjects\RenderInput $input): string
            {
                return $this->text;
            }
        };
    }
}

if (!function_exists('spyOutput'))
{
    /**
     * Builds an output that records every input it is given.
     */
    function spyOutput(array &$seen): \SpeedySpec\Block\Domain\Contracts\BlockOutputInterface
    {
        return new class($seen) implements \SpeedySpec\Block\Domain\Contracts\BlockOutputInterface {
            public function __construct(private array &$seen)
            {
            }

            public function render(\SpeedySpec\Block\Domain\ValueObjects\RenderInput $input): string
            {
                $this->seen[] = $input;

                return '';
            }
        };
    }
}

if (!function_exists('renderStep'))
{
    /**
     * Builds a render step from a callable that receives the bag and returns the next one.
     */
    function renderStep(callable $change): \SpeedySpec\Block\Domain\Contracts\RenderStepInterface
    {
        return new class($change) implements \SpeedySpec\Block\Domain\Contracts\RenderStepInterface {
            private $change;

            public function __construct(callable $change)
            {
                $this->change = $change;
            }

            public function attributes(
                \SpeedySpec\Block\Domain\ValueObjects\AttributeBag $bag,
                \SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock $block,
                \SpeedySpec\Block\Domain\ValueObjects\RenderRequest $request,
            ): \SpeedySpec\Block\Domain\ValueObjects\AttributeBag
            {
                return ($this->change)($bag, $block, $request);
            }
        };
    }
}

if (!function_exists('collectStep'))
{
    /**
     * Builds a collect step that names the same assets for every drawn block.
     */
    function collectStep(array $assets): \SpeedySpec\Block\Domain\Contracts\CollectStepInterface
    {
        return new class($assets) implements \SpeedySpec\Block\Domain\Contracts\CollectStepInterface {
            public function __construct(private readonly array $assets)
            {
            }

            public function assets(
                \SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock $block,
                \SpeedySpec\Block\Domain\ValueObjects\RenderRequest $request,
            ): array
            {
                return $this->assets;
            }
        };
    }
}

if (!function_exists('viewStep'))
{
    /**
     * Builds a view step that contributes the given extensions.
     */
    function viewStep(array $extensions): \SpeedySpec\Block\Domain\Contracts\ViewStepInterface
    {
        return new class($extensions) implements \SpeedySpec\Block\Domain\Contracts\ViewStepInterface {
            public function __construct(private readonly array $extensions)
            {
            }

            public function extend(
                \SpeedySpec\Block\Domain\ValueObjects\BlockView $view,
                Block $block,
                BlockType $type,
                array $extensions,
                Context $context,
                Mode $mode,
            ): \SpeedySpec\Block\Domain\ValueObjects\BlockView
            {
                return new \SpeedySpec\Block\Domain\ValueObjects\BlockView([], $this->extensions);
            }
        };
    }
}
