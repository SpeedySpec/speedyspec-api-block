<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Flow;

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\CollectionSourceInterface;
use SpeedySpec\Block\Domain\Contracts\CollectionSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Contracts\ValidateStepInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\AdminContext;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\CurrentItem;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;

/**
 * The repeating block: draws its children once for each item of a collection.
 *
 * It is the behaviour, the settings check and the output of one block type, kept together because none is of use
 * without the others. It adds no markup of its own, so it can sit inside any layout.
 *
 * @since 0.1.0
 */
class Repeat implements BlockBehaviourInterface, ValidateStepInterface, BlockOutputInterface
{
    public const string NAME = 'core/repeat';

    public const int MAX_ITEMS = 500;

    /**
     * @since 0.1.0
     */
    public function __construct(
        private readonly CollectionSourceRegistryInterface $sources,
        private readonly SchemaValidatorInterface $validator,
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
            'title' => 'Repeat',
            'description' => 'Repeats the blocks inside it for each item of a collection. A page resolves a limited '
                . 'number of blocks, so a repeat inside a repeat can use them up and cut the page short.',
            'category' => 'layout',
            'keywords' => ['loop', 'list', 'query', 'collection'],
            'settings' => [
                'type' => 'object',
                'properties' => [
                    'source' => [
                        'type' => 'string',
                        'default' => '',
                        'title' => 'Collection',
                        'description' => 'The collection to repeat over.',
                    ],
                    'arguments' => ['type' => 'object', 'default' => [], 'title' => 'Collection options'],
                    'limit' => [
                        'type' => 'integer',
                        'default' => 50,
                        'minimum' => 1,
                        'maximum' => self::MAX_ITEMS,
                        'title' => 'At most',
                    ],
                ],
            ],
            'children' => true,
            'examples' => [[]],
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * In admin mode a repeat with no items still produces its children once, with no current item, so the
     * blocks being repeated can always be selected and edited.
     *
     * @since 0.1.0
     */
    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
    {
        $name = (string) ($settings['source'] ?? '');
        $source = $this->sources->get($name);
        $arguments = $this->arguments($settings);

        // Checked here as well as in validate(): a source is promised arguments that fit its schema, and must
        // not be called with others whatever order the stages run in.
        $usable = $source !== null && $this->validator->validate($arguments, $source->argumentsSchema())->isValid();
        $items = !$usable ? [] : array_slice(
            array_values($source->items($arguments, $context, $mode)),
            0,
            max(1, min(self::MAX_ITEMS, (int) ($settings['limit'] ?? 50))),
        );
        $passes = [];

        foreach ($items as $index => $item)
        {
            $passes[] = new CurrentItem($item, $index, $block->id);
        }

        return new BlockView(
            ['source' => $name, 'count' => count($passes)],
            [],
            $mode === Mode::Admin ? new AdminContext(['collections' => $this->names()]) : null,
            $passes === [] && $mode === Mode::Admin ? null : $passes,
        );
    }

    /**
     * {@inheritDoc}
     *
     * Reports a collection that is not registered or is given options it refuses; an empty choice is not a
     * problem, it is a repeat nobody has set up yet.
     *
     * @since 0.1.0
     */
    public function validate(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): array
    {
        // Other block types may have a setting of the same name that means something else entirely.
        if ($type->name->toString() !== self::NAME)
        {
            return [];
        }

        $name = is_string($settings['source'] ?? null) ? $settings['source'] : '';

        if ($name === '')
        {
            return [];
        }

        $source = $this->sources->get($name);

        if ($source === null)
        {
            return [new Issue(
                $block->id,
                IssueKind::UnknownCollection,
                sprintf('Collection "%s" is not registered.', $name),
                'source',
            )];
        }

        $errors = $this->validator->validate($this->arguments($settings), $source->argumentsSchema())->errors();

        if ($errors === [])
        {
            return [];
        }

        return [new Issue(
            $block->id,
            IssueKind::InvalidCollection,
            sprintf('The options for collection "%s" do not match its schema: %s', $name, $errors[0]->message),
            'arguments',
        )];
    }

    public function render(RenderInput $input): string
    {
        return $input->children();
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function arguments(array $settings): array
    {
        return is_array($settings['arguments'] ?? null) ? $settings['arguments'] : [];
    }

    /**
     * @return list<string>
     *
     * @since 0.1.0
     */
    private function names(): array
    {
        return array_map(
            static fn (CollectionSourceInterface $source): string => $source->name(),
            $this->sources->all(),
        );
    }
}
