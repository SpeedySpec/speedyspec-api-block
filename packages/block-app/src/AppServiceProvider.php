<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App;

use SpeedySpec\Block\App\Assets\AssetRegistry;
use SpeedySpec\Block\App\Building\BlockCatalogue;
use SpeedySpec\Block\App\Building\BlockPreviewer;
use SpeedySpec\Block\App\Building\BlockTemplateRegistry;
use SpeedySpec\Block\App\Building\DocumentEditor;
use SpeedySpec\Block\App\Building\PlaceholderOutput;
use SpeedySpec\Block\App\Building\PlacementRules;
use SpeedySpec\Block\App\Documents\DocumentIncluder;
use SpeedySpec\Block\App\Documents\RegisteredTemplateLibrary;
use SpeedySpec\Block\App\Documents\SavedTemplateLibrary;
use SpeedySpec\Block\App\Ids\BlockDuplicator;
use SpeedySpec\Block\App\Ids\RandomBlockIdGenerator;
use SpeedySpec\Block\App\Media\MediaTypeDetector;
use SpeedySpec\Block\App\Media\MediaUploadPolicy;
use SpeedySpec\Block\App\Output\BlockOutputRegistry;
use SpeedySpec\Block\App\Output\DocumentRenderer;
use SpeedySpec\Block\App\Output\HtmlEscaper;
use SpeedySpec\Block\App\Output\OutputEscaperRegistry;
use SpeedySpec\Block\App\Output\RichTextSanitizer;
use SpeedySpec\Block\App\Packages\BlockPackageLoader;
use SpeedySpec\Block\App\Presentation\BreakpointRegistry;
use SpeedySpec\Block\App\Presentation\DesignTokenRegistry;
use SpeedySpec\Block\App\Presentation\StyleCollector;
use SpeedySpec\Block\App\Registries\BlockBehaviourRegistry;
use SpeedySpec\Block\App\Registries\BlockTemplateLibraryRegistry;
use SpeedySpec\Block\App\Registries\BlockTypeRegistry;
use SpeedySpec\Block\App\Registries\CollectionSourceRegistry;
use SpeedySpec\Block\App\Registries\ConditionRegistry;
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
use SpeedySpec\Block\App\Serialization\JsonBlockTemplateSerializer;
use SpeedySpec\Block\App\Serialization\JsonDocumentSerializer;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\AssetRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockCatalogueInterface;
use SpeedySpec\Block\Domain\Contracts\BlockDuplicatorInterface;
use SpeedySpec\Block\Domain\Contracts\BlockIdGeneratorInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPreviewInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateLibraryRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BreakpointRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BuilderAccessInterface;
use SpeedySpec\Block\Domain\Contracts\ClientExportInterface;
use SpeedySpec\Block\Domain\Contracts\CollectionSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\ConditionRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DesignTokenRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentEditorInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentIncluderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\MediaUploadPolicyInterface;
use SpeedySpec\Block\Domain\Contracts\OutputEscaperRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\PlacementRulesInterface;
use SpeedySpec\Block\Domain\Contracts\RichTextSanitizerInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
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
        // Uploads are checked here; where they are kept is the host's, so no media library is bound.
        $container->add(MediaTypeDetector::class, fn () => new MediaTypeDetector());
        $container->add(
            MediaUploadPolicyInterface::class,
            fn (BlockServiceContainer $c) => new MediaUploadPolicy($c->get(MediaTypeDetector::class)),
        );
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
        $container->add(
            BlockTemplateSerializerInterface::class,
            fn (BlockServiceContainer $c) => new JsonBlockTemplateSerializer(
                $c->get(DocumentSerializerInterface::class),
            ),
        );
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
            50000,
            $c->get(StepRegistryInterface::class),
        ));
        $container->add(PlacementRulesInterface::class, fn (BlockServiceContainer $c) => new PlacementRules(
            $c->get(BlockTypeRegistryInterface::class),
        ));
        $container->add(BlockCatalogueInterface::class, fn (BlockServiceContainer $c) => new BlockCatalogue(
            $c->get(BlockTypeRegistryInterface::class),
            $c->get(PlacementRulesInterface::class),
            $c->get(BlockPackageLoaderInterface::class),
        ));
        $container->add(RichTextSanitizerInterface::class, fn () => new RichTextSanitizer());
        $container->add(
            OutputEscaperRegistryInterface::class,
            function (BlockServiceContainer $c): OutputEscaperRegistryInterface {
                $escapers = new OutputEscaperRegistry();
                $escapers->add(new HtmlEscaper($c->get(RichTextSanitizerInterface::class)));

                return $escapers;
            },
        );
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
            $c->get(AssetRegistryInterface::class),
        ));
        $container->add(AssetRegistryInterface::class, fn () => new AssetRegistry());
        $container->add(BlockTemplateRegistryInterface::class, fn () => new BlockTemplateRegistry());
        $container->add(DocumentIncluderInterface::class, fn (BlockServiceContainer $c) => new DocumentIncluder(
            $c->get(DocumentRepositoryInterface::class),
            fn (): DocumentRendererInterface => $c->get(DocumentRendererInterface::class),
            $c->has(BuilderAccessInterface::class) ? $c->get(BuilderAccessInterface::class) : null,
        ));
        $container->add(
            BlockTemplateLibraryRegistryInterface::class,
            // The saved library needs a repository, which only a host with storage binds.
            fn (BlockServiceContainer $c) => new BlockTemplateLibraryRegistry(
                new RegisteredTemplateLibrary($c->get(BlockTemplateRegistryInterface::class)),
                ...($c->has(BlockTemplateRepositoryInterface::class)
                    ? [new SavedTemplateLibrary($c->get(BlockTemplateRepositoryInterface::class))]
                    : []),
            ),
        );
        $container->add(
            BreakpointRegistryInterface::class,
            fn () => new BreakpointRegistry(...BreakpointRegistry::defaults()),
        );
        $container->add(DesignTokenRegistryInterface::class, fn () => new DesignTokenRegistry());
        $container->add(StyleCollectorInterface::class, fn (BlockServiceContainer $c) => new StyleCollector(
            $c->get(BreakpointRegistryInterface::class),
            $c->get(DesignTokenRegistryInterface::class),
        ));
        $container->add(ConditionRegistryInterface::class, fn (BlockServiceContainer $c) => new ConditionRegistry(
            $c->get(SchemaValidatorInterface::class),
        ));
        $container->add(
            CollectionSourceRegistryInterface::class,
            fn (BlockServiceContainer $c) => new CollectionSourceRegistry($c->get(SchemaValidatorInterface::class)),
        );
        $container->add(DocumentEditorInterface::class, fn (BlockServiceContainer $c) => new DocumentEditor(
            $c->get(BlockTypeRegistryInterface::class),
            $c->get(PlacementRulesInterface::class),
            $c->get(SchemaValidatorInterface::class),
            $c->get(DecoratorRegistryInterface::class),
            $c->get(DataSourceRegistryInterface::class),
            $c->get(BlockIdGeneratorInterface::class),
            $c->get(BlockDuplicatorInterface::class),
            $c->get(SettingsMigrationRegistryInterface::class),
            $c->get(StepRegistryInterface::class),
            $c->get(RichTextSanitizerInterface::class),
        ));
    }
}
