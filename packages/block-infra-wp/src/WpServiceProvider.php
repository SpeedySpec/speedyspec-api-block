<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Infra\WP\Ports\WpBindingsInterface;
use SpeedySpec\Block\Infra\WP\Ports\WpBlockTypesInterface;
use SpeedySpec\Block\Infra\WP\Ports\WpPostsInterface;
use SpeedySpec\Block\Infra\WP\Ports\WpRendererInterface;
use SpeedySpec\Block\Infra\WP\WordPress\WordPressBindings;
use SpeedySpec\Block\Infra\WP\WordPress\WordPressBlockTypes;
use SpeedySpec\Block\Infra\WP\WordPress\WordPressPosts;
use SpeedySpec\Block\Infra\WP\WordPress\WordPressRenderer;
use Throwable;

/**
 * Connects the block system to WordPress: posts as documents, its block types, and its binding sources.
 *
 * Everything it needs from WordPress comes through four small ports. With no arguments it uses the ones that
 * call WordPress; a host or a test may pass its own for any of them.
 *
 * @since 0.1.0
 */
class WpServiceProvider
{
    private readonly WpPostsInterface $posts;

    private readonly WpBlockTypesInterface $types;

    private readonly WpRendererInterface $renderer;

    private readonly WpBindingsInterface $bindings;

    /**
     * @since 0.1.0
     */
    public function __construct(
        ?WpPostsInterface $posts = null,
        ?WpBlockTypesInterface $types = null,
        ?WpRendererInterface $renderer = null,
        ?WpBindingsInterface $bindings = null,
    )
    {
        $this->posts = $posts ?? new WordPressPosts();
        $this->types = $types ?? new WordPressBlockTypes();
        $this->renderer = $renderer ?? new WordPressRenderer();
        $this->bindings = $bindings ?? new WordPressBindings();
    }

    /**
     * Binds the document repository to WordPress's posts.
     *
     * @since 0.1.0
     */
    public function register(BlockServiceContainer $container): void
    {
        $container->add(
            DocumentRepositoryInterface::class,
            fn () => new WpPostDocumentRepository($this->posts, new WpMarkup()),
        );
    }

    /**
     * Copies WordPress's block types and binding sources into the registries.
     *
     * Call it once WordPress has registered its blocks, which is after its `init` action. A type or source the
     * registries refuse is skipped, so one odd plugin cannot stop the rest from loading.
     *
     * @return list<string>
     *   The names of the block types and sources that were skipped.
     *
     * @since 0.1.0
     */
    public function boot(BlockServiceContainer $container): array
    {
        $types = $container->get(BlockTypeRegistryInterface::class);
        $outputs = $container->get(BlockOutputRegistryInterface::class);
        $sources = $container->get(DataSourceRegistryInterface::class);
        $bridge = new WpBlockTypeBridge();
        $skipped = [];
        $all = [...$this->types->all(), ['name' => WpMarkup::FREEFORM, 'title' => 'Classic']];

        foreach ($all as $definition)
        {
            try
            {
                $type = $bridge->toBlockType($definition);

                if ($types->has($type->name))
                {
                    continue;
                }

                $types->add($type);
                $dynamic = ($definition['dynamic'] ?? false) === true;
                $outputs->set($type->name, new WpBlockOutput($this->renderer, $dynamic));
            }
            catch (Throwable)
            {
                $skipped[] = is_string($definition['name'] ?? null) ? $definition['name'] : '(unnamed)';
            }
        }

        foreach ($this->bindings->sources() as $name)
        {
            try
            {
                $sources->add(new WpBindingSource($this->bindings, $name));
            }
            catch (Throwable)
            {
                $skipped[] = $name;
            }
        }

        return $skipped;
    }
}
