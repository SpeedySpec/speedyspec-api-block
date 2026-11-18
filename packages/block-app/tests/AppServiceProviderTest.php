<?php

declare(strict_types=1);

use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\App\Ids\RandomBlockIdGenerator;
use SpeedySpec\Block\App\Resolving\GateStage;
use SpeedySpec\Block\App\Resolving\PrepareStage;
use SpeedySpec\Block\App\Resolving\ResolveStage;
use SpeedySpec\Block\App\Resolving\StageOutcome;
use SpeedySpec\Block\App\Resolving\ValidateStage;
use SpeedySpec\Block\App\Resolving\ViewStage;
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
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\OutputEscaperInterface;
use SpeedySpec\Block\Domain\Contracts\OutputEscaperRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\PlacementRulesInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Contracts\SettingsMigrationRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\StepRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\UnresolvedBlockOutputInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;

test('every application contract resolves and can be replaced alone', function () {
    $c = new BlockServiceContainer();
    (new MemoryServiceProvider())->register($c);
    (new AppServiceProvider())->register($c);
    $mine = new RandomBlockIdGenerator();
    $c->add(BlockIdGeneratorInterface::class, fn () => $mine);
    foreach ([SchemaValidatorInterface::class, BlockTypeRegistryInterface::class, BlockBehaviourRegistryInterface::class,
        DecoratorRegistryInterface::class, SettingsMigrationRegistryInterface::class, DataSourceRegistryInterface::class,
        StepRegistryInterface::class, BlockDuplicatorInterface::class, DocumentSerializerInterface::class,
        ClientExportInterface::class, DocumentResolverInterface::class, OutputEscaperRegistryInterface::class,
        BlockOutputRegistryInterface::class, DocumentRendererInterface::class, BlockPackageLoaderInterface::class,
        PlacementRulesInterface::class, BlockCatalogueInterface::class, BlockTemplateRegistryInterface::class,
        DocumentEditorInterface::class, BlockPreviewInterface::class,
        UnresolvedBlockOutputInterface::class] as $contract)
    {
        expect($c->get($contract))->toBeInstanceOf($contract);
    }
    expect($c->get(BlockIdGeneratorInterface::class))->toBe($mine);
});

test('each resolving stage is bound under its own class name', function () {
    $c = new BlockServiceContainer();
    (new AppServiceProvider())->register($c);
    foreach ([PrepareStage::class, GateStage::class, ResolveStage::class, ValidateStage::class, ViewStage::class] as $stage)
    {
        expect($c->get($stage))->toBeInstanceOf($stage);
    }
});

test('the application provider alone resolves a document and binds no repository', function () {
    $c = new BlockServiceContainer();
    (new AppServiceProvider())->register($c);
    types($c)->add(type('acme/box'));
    $result = $c->get(DocumentResolverInterface::class)->resolve(doc(block('a', 'acme/box', ['n' => 1])), Mode::Front);
    expect($result->find('a')->view->values)->toBe(['n' => 1])
        ->and($c->has(DocumentRepositoryInterface::class))->toBeFalse();
});

test('replacing one stage binding changes how the resolver resolves', function () {
    [$resolver] = resolver(function (BlockServiceContainer $c) {
        types($c)->add(type('acme/box'));
        $c->add(GateStage::class, fn (BlockServiceContainer $c) => new class($c->get(StepRegistryInterface::class)) extends GateStage {
            public function run(StageOutcome $prepared, BlockType $type, Context $context, Mode $mode): StageOutcome
            {
                return $prepared->with(continue: false);
            }
        });
    });
    expect($resolver->resolve(doc(block('a', 'acme/box')), Mode::Front)->find('a')->isResolved())->toBeFalse();
});

test('replacing the unresolved-block output changes what the renderer draws for an unresolved block', function () {
    [$renderer] = renderer(function (BlockServiceContainer $c) {
        $c->add(UnresolvedBlockOutputInterface::class, fn () => new class implements UnresolvedBlockOutputInterface {
            public function render(
                ResolvedBlock $block,
                RenderRequest $request,
                OutputEscaperInterface $escaper,
            ): string
            {
                return '[missing ' . $escaper->text($block->block->id) . ']';
            }
        });
    });

    expect($renderer->render(doc(block('a', 'gone/away')), new RenderRequest(Mode::Admin))->output)
        ->toBe('[missing a]');
});
