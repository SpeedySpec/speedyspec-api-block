<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App;

use SpeedySpec\Block\App\Building\BlockCatalogue;
use SpeedySpec\Block\App\Building\BlockPreviewer;
use SpeedySpec\Block\App\Building\BlockTemplateRegistry;
use SpeedySpec\Block\App\Building\DocumentEditor;
use SpeedySpec\Block\App\Building\PlaceholderOutput;
use SpeedySpec\Block\App\Building\PlacementRules;
use SpeedySpec\Block\App\Ids\BlockDuplicator;
use SpeedySpec\Block\App\Ids\RandomBlockIdGenerator;
use SpeedySpec\Block\App\Output\BlockOutputRegistry;
use SpeedySpec\Block\App\Output\DocumentRenderer;
use SpeedySpec\Block\App\Output\HtmlEscaper;
use SpeedySpec\Block\App\Output\OutputEscaperRegistry;
use SpeedySpec\Block\App\Packages\BlockPackageLoader;
use SpeedySpec\Block\App\Registries\BlockBehaviourRegistry;
use SpeedySpec\Block\App\Registries\BlockTypeRegistry;
use SpeedySpec\Block\App\Registries\DataSourceRegistry;
use SpeedySpec\Block\App\Registries\DecoratorRegistry;
use SpeedySpec\Block\App\Registries\SettingsMigrationRegistry;
use SpeedySpec\Block\App\Registries\StepRegistry;
use SpeedySpec\Block\App\Resolving\DocumentResolver;
use SpeedySpec\Block\App\Resolving\GateStage;
use SpeedySpec\Block\App\Resolving\PrepareStage;
use SpeedySpec\Block\App\Resolving\ResolveStage;
use SpeedySpec\Block\App\Resolving\ValidateStage;
use SpeedySpec\Block\App\Resolving\ViewStage;
use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\App\Serialization\ClientExport;
use SpeedySpec\Block\App\Serialization\JsonDocumentSerializer;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockCatalogueInterface;
use SpeedySpec\Block\Domain\Contracts\BlockDuplicatorInterface;
use SpeedySpec\Block\Domain\Contracts\BlockIdGeneratorInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPreviewInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\ClientExportInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentEditorInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\OutputEscaperRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\PlacementRulesInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\UnresolvedBlockOutputInterface;

/**
 * Binds the application implementations of the block contracts, one binding each so a host can replace any alone.
 *
 * Storage is left to an infrastructure provider; nothing here needs one.
 *
 * @since 0.1.0
 */
class AppServiceProvider
{
    /**
     * Binds every application contract and resolving stage; collaborators are fetched when first used.
     *
     * @param BlockServiceContainer $container
     *   The container to bind into.
     *
     * @since 0.1.0
     */
    public function register(BlockServiceContainer $container): void
    {
        $container->add(SchemaValidatorInterface::class, fn () => new SubsetSchemaValidator());
        $container->add(
            BlockTypeRegistryInterface::class,
            fn (BlockServiceContainer $c) => new BlockTypeRegistry($c->get(SchemaValidatorInterface::class)),
        );
        $container->add(BlockBehaviourRegistryInterface::class, fn () => new BlockBehaviourRegistry());
        $container->add(
            DecoratorRegistryInterface::class,
            fn (BlockServiceContainer $c) => new DecoratorRegistry($c->get(SchemaValidatorInterface::class)),
        );
        $container->add(SettingsMigrationRegistryInterface::class, fn () => new SettingsMigrationRegistry());
        $container->add(
            DataSourceRegistryInterface::class,
            fn (BlockServiceContainer $c) => new DataSourceRegistry($c->get(SchemaValidatorInterface::class)),
        );
        $container->add(StepRegistryInterface::class, fn () => new StepRegistry());
        $container->add(BlockIdGeneratorInterface::class, fn () => new RandomBlockIdGenerator());
        $container->add(
            BlockDuplicatorInterface::class,
            fn (BlockServiceContainer $c) => new BlockDuplicator($c->get(BlockIdGeneratorInterface::class)),
        );
        $container->add(DocumentSerializerInterface::class, fn () => new JsonDocumentSerializer());
        $container->add(ClientExportInterface::class, fn (BlockServiceContainer $c) => new ClientExport(
            $c->get(BlockTypeRegistryInterface::class),
            $c->get(SchemaValidatorInterface::class),
        ));
        $container->add(PrepareStage::class, fn (BlockServiceContainer $c) => new PrepareStage(
            $c->get(BlockTypeRegistryInterface::class),
            $c->get(SettingsMigrationRegistryInterface::class),
            $c->get(DecoratorRegistryInterface::class),
            $c->get(SchemaValidatorInterface::class),
            $c->get(StepRegistryInterface::class),
        ));
        $container->add(
            GateStage::class,
            fn (BlockServiceContainer $c) => new GateStage($c->get(StepRegistryInterface::class)),
        );
        $container->add(ResolveStage::class, fn (BlockServiceContainer $c) => new ResolveStage(
            $c->get(DataSourceRegistryInterface::class),
            $c->get(DecoratorRegistryInterface::class),
            $c->get(SchemaValidatorInterface::class),
        ));
        $container->add(ValidateStage::class, fn (BlockServiceContainer $c) => new ValidateStage(
            $c->get(DecoratorRegistryInterface::class),
            $c->get(SchemaValidatorInterface::class),
            $c->get(StepRegistryInterface::class),
        ));
        $container->add(ViewStage::class, fn (BlockServiceContainer $c) => new ViewStage(
            $c->get(BlockBehaviourRegistryInterface::class),
            $c->get(SchemaValidatorInterface::class),
            $c->get(StepRegistryInterface::class),
            $c->get(DecoratorRegistryInterface::class),
        ));
        $container->add(DocumentResolverInterface::class, fn (BlockServiceContainer $c) => new DocumentResolver(
            $c->get(PrepareStage::class),
            $c->get(GateStage::class),
            $c->get(ResolveStage::class),
            $c->get(ValidateStage::class),
            $c->get(ViewStage::class),
            $c->get(BlockTypeRegistryInterface::class),
            $c->get(PlacementRulesInterface::class),
        ));
        $container->add(PlacementRulesInterface::class, fn (BlockServiceContainer $c) => new PlacementRules(
            $c->get(BlockTypeRegistryInterface::class),
        ));
        $container->add(BlockCatalogueInterface::class, fn (BlockServiceContainer $c) => new BlockCatalogue(
            $c->get(BlockTypeRegistryInterface::class),
            $c->get(PlacementRulesInterface::class),
            $c->get(BlockPackageLoaderInterface::class),
        ));
        $container->add(OutputEscaperRegistryInterface::class, function (): OutputEscaperRegistryInterface {
            $escapers = new OutputEscaperRegistry();
            $escapers->add(new HtmlEscaper());

            return $escapers;
        });
        $container->add(BlockOutputRegistryInterface::class, fn () => new BlockOutputRegistry());
        $container->add(DocumentRendererInterface::class, fn (BlockServiceContainer $c) => new DocumentRenderer(
            $c->get(DocumentResolverInterface::class),
            $c->get(BlockOutputRegistryInterface::class),
            $c->get(OutputEscaperRegistryInterface::class),
            $c->get(StepRegistryInterface::class),
            $c->get(UnresolvedBlockOutputInterface::class),
        ));
        $container->add(UnresolvedBlockOutputInterface::class, fn () => new PlaceholderOutput());
        $container->add(BlockPreviewInterface::class, fn (BlockServiceContainer $c) => new BlockPreviewer(
            $c->get(DocumentResolverInterface::class),
            $c->get(DocumentRendererInterface::class),
        ));
        $container->add(BlockPackageLoaderInterface::class, fn (BlockServiceContainer $c) => new BlockPackageLoader(
            $c->get(BlockTypeRegistryInterface::class),
            $c->get(BlockBehaviourRegistryInterface::class),
            $c->get(BlockOutputRegistryInterface::class),
            $c->get(DecoratorRegistryInterface::class),
            $c->get(SettingsMigrationRegistryInterface::class),
            $c->get(DataSourceRegistryInterface::class),
            $c->get(StepRegistryInterface::class),
            $c->get(BlockTemplateRegistryInterface::class),
        ));
        $container->add(BlockTemplateRegistryInterface::class, fn () => new BlockTemplateRegistry());
        $container->add(DocumentEditorInterface::class, fn (BlockServiceContainer $c) => new DocumentEditor(
            $c->get(BlockTypeRegistryInterface::class),
            $c->get(PlacementRulesInterface::class),
            $c->get(SchemaValidatorInterface::class),
            $c->get(DecoratorRegistryInterface::class),
            $c->get(DataSourceRegistryInterface::class),
            $c->get(BlockIdGeneratorInterface::class),
            $c->get(BlockDuplicatorInterface::class),
            $c->get(SettingsMigrationRegistryInterface::class),
        ));
    }
}
