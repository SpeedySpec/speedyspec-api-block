<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Documents;

use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Contracts\CollectStepInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentIncluderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\ValidateStepInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

/**
 * The block package for documents made of other documents: the block that includes one stored document in another.
 *
 * @since 0.1.0
 */
class DocumentsPackage implements BlockPackageInterface
{
    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly DocumentIncluderInterface $includer,
    )
    {
    }

    /**
     * Builds the package from the repository and includer bound in a container, so it shares them with the host.
     *
     * @since 0.1.0
     */
    public static function from(BlockServiceContainer $container): self
    {
        return new self(
            $container->get(DocumentRepositoryInterface::class),
            $container->get(DocumentIncluderInterface::class),
        );
    }

    public function name(): string
    {
        return 'core/documents';
    }

    public function register(BlockPackageRegistrarInterface $registrar): void
    {
        $include = new IncludeBlock($this->documents, $this->includer);
        $name = BlockName::fromString(IncludeBlock::NAME);

        $registrar->type(IncludeBlock::type());
        $registrar->output($name, $include);
        $registrar->step(ValidateStepInterface::class, $include);
        $registrar->step(CollectStepInterface::class, $include);
    }
}
