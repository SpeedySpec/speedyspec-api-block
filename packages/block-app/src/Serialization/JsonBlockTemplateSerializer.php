<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Serialization;

use JsonException;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentSerializerInterface;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTemplateException;
use SpeedySpec\Block\Domain\Exceptions\InvalidDocumentException;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;

/**
 * Stores a template as JSON holding its details and its blocks in the document serializer's own form.
 *
 * Reusing the document serializer means a template's blocks are stored exactly as a document's are, and a host
 * that replaces the document format changes both.
 *
 * @since 0.1.0
 */
class JsonBlockTemplateSerializer implements BlockTemplateSerializerInterface
{
    private const string KIND = 'template';

    /**
     * @since 0.1.0
     */
    public function __construct(private readonly DocumentSerializerInterface $documents)
    {
    }

    public function serialize(BlockTemplate $template): string
    {
        try
        {
            return json_encode([
                'name' => $template->name,
                'title' => $template->title,
                'description' => $template->description,
                'category' => $template->category,
                'document' => $this->documents->serialize(new Document($template->name, self::KIND, $template->tree)),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        catch (JsonException | InvalidDocumentException $e)
        {
            throw new InvalidBlockTemplateException(
                sprintf('Template "%s" cannot be encoded: %s', $template->name, $e->getMessage()),
                0,
                $e,
            );
        }
    }

    public function parse(string $serialized): BlockTemplate
    {
        try
        {
            $data = json_decode($serialized, true, 8, JSON_THROW_ON_ERROR);
        }
        catch (JsonException $e)
        {
            throw new InvalidBlockTemplateException('The stored template is not valid JSON.', 0, $e);
        }

        if (!is_array($data) || array_is_list($data))
        {
            throw new InvalidBlockTemplateException('The stored template is not an object.');
        }

        foreach (['name', 'title', 'document'] as $key)
        {
            if (!is_string($data[$key] ?? null))
            {
                throw new InvalidBlockTemplateException(sprintf('The stored template has no text "%s".', $key));
            }
        }

        try
        {
            $tree = $this->documents->parse($data['document'])->tree;
        }
        catch (InvalidDocumentException $e)
        {
            throw new InvalidBlockTemplateException(
                sprintf('The blocks of template "%s" cannot be read: %s', $data['name'], $e->getMessage()),
                0,
                $e,
            );
        }

        return new BlockTemplate(
            $data['name'],
            $data['title'],
            $tree,
            is_string($data['description'] ?? null) ? $data['description'] : '',
            is_string($data['category'] ?? null) ? $data['category'] : '',
        );
    }
}
