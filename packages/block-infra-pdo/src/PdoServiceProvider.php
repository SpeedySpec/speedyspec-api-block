<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\Pdo;

use PDO;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\RecordStoreInterface;

/**
 * Binds the record store to a database table.
 *
 * It binds only the record store; the repositories that use it come from the application's storage provider. The
 * table is not created here: call PdoRecordStore::install() when the host sets itself up.
 *
 * @since 0.1.0
 */
class PdoServiceProvider
{
    /**
     * @since 0.1.0
     */
    public function __construct(private readonly PDO $pdo, private readonly string $table = 'block_records')
    {
    }

    /**
     * @since 0.1.0
     */
    public function register(BlockServiceContainer $container): void
    {
        $container->add(RecordStoreInterface::class, fn () => new PdoRecordStore($this->pdo, $this->table));
    }
}
