<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Documents;

use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\BuilderAccessInterface;
use SpeedySpec\Block\Domain\Contracts\CollectStepInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentIncluderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\ValidateStepInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\AttributeBag;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BuilderAbility;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\ResolvedBlock;

/**
 * The including block: stands in a page for another stored document, such as a header shared by every page.
 *
 * It is the settings check, the output and the asset step of one block type, kept together because none is of
 * use without the others.
 *
 * @since 0.1.0
 */
class IncludeBlock implements ValidateStepInterface, BlockOutputInterface, CollectStepInterface
{
    public const string NAME = 'core/include';

    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly DocumentIncluderInterface $includer,
        private readonly ?BuilderAccessInterface $access = null,
    )
    {
    }

    /**
     * Builds the block type's declaration.
     *
     * @since 0.1.0
     */
    public static function type(): BlockType
    {
        return BlockType::fromArray([
            'name' => self::NAME,
            'title' => 'Included document',
            'description' => 'Shows another stored document here, such as a header or a reusable section.',
            'category' => 'layout',
            'keywords' => ['reusable', 'header', 'footer', 'part', 'synced'],
            'settings' => [
                'type' => 'object',
                'properties' => [
                    'document' => [
                        'type' => 'string',
                        'default' => '',
                        'title' => 'Document',
                        'description' => 'The id of the document to show.',
                        'x-control' => 'document',
                    ],
                ],
            ],
            'examples' => [[]],
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * Reports a document that is not stored; an empty choice is not a problem, it is an inclusion nobody has
     * set up yet.
     *
     * @since 0.1.0
     */
    public function validate(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): array
    {
        $id = (string) ($settings['document'] ?? '');

        if ($type->name->toString() !== self::NAME || $id === '')
        {
            return [];
        }

        $ability = $mode === Mode::Admin ? BuilderAbility::Edit : BuilderAbility::View;
        $allowed = $this->access === null || $this->access->allows($ability, $id);

        // Refused and missing are reported alike, so the answer does not reveal which documents exist.
        if ($allowed && $this->documents->find($id) !== null)
        {
            return [];
        }

        return [new Issue(
            $block->id,
            IssueKind::UnknownDocument,
            sprintf('Document "%s" is not stored.', $id),
            'document',
        )];
    }

    /**
     * {@inheritDoc}
     *
     * For a visitor the included document is drawn bare, as if its blocks were written here. For an editor it is
     * wrapped in an element naming the document, so a builder can show where it comes from and that changing it
     * changes every page that includes it.
     *
     * @since 0.1.0
     */
    public function render(RenderInput $input): string
    {
        $id = (string) ($input->view->values['document'] ?? '');
        $inner = $id === '' ? '' : $this->includer->include($id, $input->blockId, $input->request, $input->context);

        if ($input->request->mode !== Mode::Admin)
        {
            return $inner;
        }

        $attributes = AttributeBag::empty()->withAttribute('data-included-document', $id);

        return sprintf('<div%s>%s</div>', $input->escaper->attributes($attributes), $inner);
    }

    public function assets(ResolvedBlock $block, RenderRequest $request): array
    {
        return $block->type?->name->toString() === self::NAME ? $this->includer->assets() : [];
    }
}
