<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Filesystem;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;

/**
 * Binds the record store to files under one directory.
 *
 * It binds only the record store; the repositories that use it come from the application's storage provider.
 *
 * @since 0.1.0
 */
class FilesystemServiceProvider
{
    /**
     * @param string $directory
     *   Where records are kept. It must not be served to visitors: records may hold private settings.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @since 0.1.0
     */
    public function register(BlockServiceContainer $container): void
    {
        $container->add(RecordStoreInterface::class, fn () => new FilesystemRecordStore($this->directory));
    }
}
