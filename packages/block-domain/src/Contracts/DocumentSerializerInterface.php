<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\InvalidDocumentException;

/**
 * Converts documents to and from their stored text form, so storage can change without touching the model.
 *
 * @since 0.1.0
 */
interface DocumentSerializerInterface
{
    /**
     * Encodes a document so that parsing the result yields an equal document.
     *
     * @since 0.1.0
     */
    public function serialize(Document $document): string;

    /**
     * Decodes stored text into a document without consulting any registry, keeping unknown block types as stored.
     *
     * @param string $serialized
     *   The stored text.
     *
     * @throws InvalidDocumentException
     *   When the text is not a document.
     *
     * @since 0.1.0
     */
    public function parse(string $serialized): Document;
}
