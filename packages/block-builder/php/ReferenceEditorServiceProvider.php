<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Builder;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\AssetRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\EditorInterface;
use SpeedySpec\Block\Domain\ValueObjects\Asset;
use SpeedySpec\Block\Domain\ValueObjects\AssetKind;

/**
 * Makes the reference editor the site's editor.
 *
 * Leaving this provider out leaves the site without an editor; binding another `EditorInterface` after it replaces
 * the reference editor. Register it after the application provider, which binds the asset registry it adds to.
 *
 * @since 0.1.0
 */
class ReferenceEditorServiceProvider
{
    /**
     * @since 0.1.0
     */
    public function register(BlockServiceContainer $container): void
    {
        $container->add(EditorInterface::class, fn () => new ReferenceEditor());

        // Added as the registry is first built, so the provider itself resolves nothing.
        if ($container->has(AssetRegistryInterface::class))
        {
            $container->extend(AssetRegistryInterface::class, function (object $assets): object {
                $assets->add(Asset::file(
                    ReferenceEditor::ASSET,
                    AssetKind::Module,
                    ReferenceEditor::PACKAGE,
                    ReferenceEditor::ENTRY,
                ));

                return $assets;
            });
        }
    }
}
