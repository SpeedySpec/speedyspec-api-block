<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTemplateException;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

/**
 * Converts block templates to and from their stored text form, so storage can change without touching the model.
 *
 * @since 0.1.0
 */
interface BlockTemplateSerializerInterface
{
    /**
     * Encodes a template so that parsing the result yields an equal template.
     *
     * @throws InvalidBlockTemplateException
     *   When the template cannot be encoded.
     *
     * @since 0.1.0
     */
    public function serialize(BlockTemplate $template): string;

    /**
     * Decodes stored text into a template without consulting any registry.
     *
     * @throws InvalidBlockTemplateException
     *   When the text is not a template.
     *
     * @since 0.1.0
     */
    public function parse(string $serialized): BlockTemplate;
}
