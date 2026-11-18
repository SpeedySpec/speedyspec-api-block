<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;

/**
 * Builds a block for tests; named children arguments are collected under string keys, so they are re-indexed.
 */
function block(string $id, string $type = 'acme/box', array $settings = [], Block ...$children): Block
{
    return new Block($id, BlockName::fromString($type), 1, $settings, [], [], array_values($children));
}

if (!function_exists('renderInput'))
{
    /**
     * Builds a render input with neutral defaults, so a test names only what it cares about.
     */
    function renderInput(
        string $blockId = 'a',
        ?\SpeedySpec\Block\Domain\ValueObjects\BlockView $view = null,
        ?\SpeedySpec\Block\Domain\ValueObjects\AttributeBag $attributes = null,
        array $childPasses = [],
        ?\SpeedySpec\Block\Domain\ValueObjects\RenderRequest $request = null,
        ?\SpeedySpec\Block\Domain\ValueObjects\AdminContext $admin = null,
    ): \SpeedySpec\Block\Domain\ValueObjects\RenderInput
    {
        $escaper = new class implements \SpeedySpec\Block\Domain\Contracts\OutputEscaperInterface {
            public function target(): string
            {
                return 'html';
            }

            public function text(string $value): string
            {
                return $value;
            }

            public function attribute(string $value): string
            {
                return $value;
            }

            public function url(string $value): string
            {
                return $value;
            }

            public function attributes(\SpeedySpec\Block\Domain\ValueObjects\AttributeBag $bag): string
            {
                return '';
            }

            public function richText(string $value): string
            {
                return $value;
            }
        };

        return new \SpeedySpec\Block\Domain\ValueObjects\RenderInput(
            $blockId,
            \SpeedySpec\Block\Domain\ValueObjects\BlockType::fromArray(['name' => 'acme/box', 'title' => 'Box']),
            $view ?? new \SpeedySpec\Block\Domain\ValueObjects\BlockView([]),
            $attributes ?? \SpeedySpec\Block\Domain\ValueObjects\AttributeBag::empty(),
            $childPasses,
            \SpeedySpec\Block\Domain\ValueObjects\Context::empty(),
            $request ?? new \SpeedySpec\Block\Domain\ValueObjects\RenderRequest(),
            $escaper,
            $admin,
        );
    }
}
