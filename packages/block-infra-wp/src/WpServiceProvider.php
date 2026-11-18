<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockBehaviourRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\MediaLibraryInterface;
use SpeedySpec\Block\Domain\Contracts\SiteMapInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Infra\WP\Ports\WpAttachmentsInterface;
use SpeedySpec\Block\Infra\WP\Ports\WpBindingsInterface;
use SpeedySpec\Block\Infra\WP\Ports\WpBlockTypesInterface;
use SpeedySpec\Block\Infra\WP\Ports\WpPostsInterface;
use SpeedySpec\Block\Infra\WP\Ports\WpRendererInterface;
use SpeedySpec\Block\Infra\WP\Ports\WpSiteInterface;
use SpeedySpec\Block\Infra\WP\WordPress\WordPressAttachments;
use SpeedySpec\Block\Infra\WP\WordPress\WordPressBindings;
use SpeedySpec\Block\Infra\WP\WordPress\WordPressBlockTypes;
use SpeedySpec\Block\Infra\WP\WordPress\WordPressPosts;
use SpeedySpec\Block\Infra\WP\WordPress\WordPressRenderer;
use SpeedySpec\Block\Infra\WP\WordPress\WordPressSite;
use Throwable;

/**
 * Connects the block system to WordPress: posts as documents, its block types, and its binding sources.
 *
 * Experimental. It reads and writes block markup faithfully, but it does not yet draw every post as WordPress
 * does; the package README lists what differs.
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

    private readonly WpAttachmentsInterface $attachments;

    private readonly WpSiteInterface $site;

    /**
     * @since 0.1.0
     */
    public function __construct(
        ?WpPostsInterface $posts = null,
        ?WpBlockTypesInterface $types = null,
        ?WpRendererInterface $renderer = null,
        ?WpBindingsInterface $bindings = null,
        ?WpAttachmentsInterface $attachments = null,
        ?WpSiteInterface $site = null,
    )
    {
        $this->posts = $posts ?? new WordPressPosts();
        $this->types = $types ?? new WordPressBlockTypes();
        $this->renderer = $renderer ?? new WordPressRenderer();
        $this->bindings = $bindings ?? new WordPressBindings();
        $this->attachments = $attachments ?? new WordPressAttachments();
        $this->site = $site ?? new WordPressSite();
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
        $container->add(MediaLibraryInterface::class, fn () => new WpMediaLibrary($this->attachments));
        $container->add(SiteMapInterface::class, fn () => new WpSiteMap($this->site));
    }

    /**
     * Copies WordPress's block types and binding sources into the registries.
     *
     * Call it once WordPress has registered its blocks, which is after its `init` action. A type or source the
     * registries refuse is skipped, so one odd plugin cannot stop the rest from loading.
     *
     * @return list<string>
     *   The names of the block types and sources that were not added: refused by a registry, or, for a block type,
     *   already registered by another package.
     *
     * @since 0.1.0
     */
    public function boot(BlockServiceContainer $container): array
    {
        $types = $container->get(BlockTypeRegistryInterface::class);
        $outputs = $container->get(BlockOutputRegistryInterface::class);
        $sources = $container->get(DataSourceRegistryInterface::class);
        $behaviours = $container->get(BlockBehaviourRegistryInterface::class);
        $bridge = new WpBlockTypeBridge();
        $skipped = [];
        $all = $this->types->all();

        if (!$types->has(BlockName::fromString(WpMarkup::FREEFORM)))
        {
            $all[] = ['name' => WpMarkup::FREEFORM, 'title' => 'Classic'];
        }

        foreach ($all as $definition)
        {
            try
            {
                $type = $bridge->toBlockType($definition);

                // Another package owns this name; its blocks will be drawn by that package, not as WordPress saved
                // them, so the host is told.
                if ($types->has($type->name))
                {
                    $skipped[] = $type->name->toString();

                    continue;
                }

                $types->add($type);
                $behaviours->set($type->name, new WpBlockBehaviour());
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
