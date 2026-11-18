<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Serialization;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use SpeedySpec\Block\Domain\Contracts\DocumentSerializerInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\InvalidDocumentException;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

/**
 * Stores documents as JSON; see the README beside this class for the exact shape.
 *
 * Parsing never consults a registry, so blocks of types nobody knows survive a load and save unchanged.
 *
 * @since 0.1.0
 */
class JsonDocumentSerializer implements DocumentSerializerInterface
{
    private const FORMAT = 1;

    private const DEPTH = 2048;

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function serialize(Document $document): string
    {
        return json_encode(
            [
                'format' => $document->formatVersion,
                'id' => $document->id,
                'kind' => $document->kind,
                'blocks' => array_map($this->encodeBlock(...), $document->tree->roots()),
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            self::DEPTH,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function parse(string $serialized): Document
    {
        try
        {
            $data = json_decode($serialized, true, self::DEPTH, JSON_THROW_ON_ERROR);

            if (!is_array($data) || array_is_list($data) && $data !== [])
            {
                throw new InvalidDocumentException('A document must be a JSON object.');
            }

            if (($data['format'] ?? null) !== self::FORMAT)
            {
                throw new InvalidDocumentException('Unsupported or missing document "format"; expected 1.');
            }

            $id = $this->requireString($data, 'id', 'document');
            $kind = $this->requireString($data, 'kind', 'document');
            $blocks = $data['blocks'] ?? null;

            if (!is_array($blocks) || !array_is_list($blocks))
            {
                throw new InvalidDocumentException('A document needs a "blocks" array.');
            }

            return new Document($id, $kind, new BlockTree(...array_map($this->decodeBlock(...), $blocks)), self::FORMAT);
        }
        catch (InvalidDocumentException $e)
        {
            throw $e;
        }
        catch (JsonException $e)
        {
            throw new InvalidDocumentException('The document is not valid JSON: ' . $e->getMessage(), 0, $e);
        }
        catch (InvalidArgumentException | RuntimeException $e)
        {
            throw new InvalidDocumentException('The document is invalid: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function encodeBlock(Block $block): array
    {
        return [
            'id' => $block->id,
            'type' => $block->type->toString(),
            'version' => $block->typeVersion,
            'settings' => (object) $block->settings,
            'extensions' => (object) array_map(static fn (array $e): object => (object) $e, $block->extensions),
            'bindings' => (object) array_map(
                static function (Binding $binding): array {
                    $data = $binding->toArray();
                    $data['arguments'] = (object) $data['arguments'];

                    return $data;
                },
                $block->bindings,
            ),
            'children' => array_map($this->encodeBlock(...), $block->children),
        ];
    }

    private function decodeBlock(mixed $data): Block
    {
        if (!is_array($data) || array_is_list($data) && $data !== [])
        {
            throw new InvalidDocumentException('A block must be a JSON object.');
        }

        $id = $this->requireString($data, 'id', 'block');
        $type = $this->requireString($data, 'type', sprintf('block "%s"', $id));
        $version = $data['version'] ?? 1;

        if (!is_int($version) || $version < 1)
        {
            throw new InvalidDocumentException(sprintf('Block "%s" needs an integer "version" of 1 or more.', $id));
        }

        $extensions = $this->map($data, 'extensions', $id);

        foreach ($extensions as $namespace => $extension)
        {
            if (!is_array($extension) || array_is_list($extension) && $extension !== [])
            {
                throw new InvalidDocumentException(
                    sprintf('Extension "%s" of block "%s" must be an object.', $namespace, $id)
                );
            }
        }

        $bindings = [];

        foreach ($this->map($data, 'bindings', $id) as $path => $binding)
        {
            $bindings[(string) $path] = $this->decodeBinding((string) $path, $binding, $id);
        }

        $children = $data['children'] ?? [];

        if (!is_array($children) || !array_is_list($children))
        {
            throw new InvalidDocumentException(sprintf('Block "%s" needs a "children" array.', $id));
        }

        return new Block(
            $id,
            BlockName::fromString($type),
            $version,
            $this->map($data, 'settings', $id),
            $extensions,
            $bindings,
            array_map($this->decodeBlock(...), $children),
        );
    }

    private function decodeBinding(string $path, mixed $binding, string $blockId): Binding
    {
        if (!is_array($binding) || array_is_list($binding) && $binding !== [])
        {
            throw new InvalidDocumentException(
                sprintf('Binding "%s" of block "%s" must be an object.', $path, $blockId)
            );
        }

        if (!is_array($binding['arguments'] ?? []))
        {
            throw new InvalidDocumentException(
                sprintf('Binding "%s" of block "%s" needs object "arguments".', $path, $blockId)
            );
        }

        SettingPath::fromString($path);

        return Binding::fromArray($binding);
    }

    /**
     * Reads an optional JSON object as a map; absent means empty.
     *
     * @param array<string, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function map(array $data, string $key, string $blockId): array
    {
        $value = $data[$key] ?? [];

        if (!is_array($value) || array_is_list($value) && $value !== [])
        {
            throw new InvalidDocumentException(sprintf('"%s" of block "%s" must be an object.', $key, $blockId));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireString(array $data, string $key, string $subject): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) || $value === '')
        {
            throw new InvalidDocumentException(sprintf('The %s needs a non-empty string "%s".', $subject, $key));
        }

        return $value;
    }
}
