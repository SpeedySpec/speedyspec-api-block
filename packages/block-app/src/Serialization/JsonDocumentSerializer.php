<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Serialization;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use stdClass;
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

    private const NAMESPACE_PATTERN = '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*\z/';

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function serialize(Document $document): string
    {
        // Writing another format would produce a document that parse() then refuses.
        if ($document->formatVersion !== self::FORMAT)
        {
            throw new InvalidDocumentException(sprintf(
                'Cannot serialize document format %d; expected %d.',
                $document->formatVersion,
                self::FORMAT,
            ));
        }

        try
        {
            return json_encode(
                [
                    'format' => $document->formatVersion,
                    'id' => $document->id,
                    'kind' => $document->kind,
                    'blocks' => array_map($this->encodeBlock(...), $document->tree->roots()),
                ],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
                self::DEPTH,
            );
        }
        catch (JsonException $e)
        {
            throw new InvalidDocumentException('The document cannot be encoded: ' . $e->getMessage(), 0, $e);
        }
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
            $data = json_decode($serialized, false, self::DEPTH, JSON_THROW_ON_ERROR);

            if (!$data instanceof stdClass)
            {
                throw new InvalidDocumentException('A document must be a JSON object.');
            }

            if (($data->format ?? null) !== self::FORMAT)
            {
                throw new InvalidDocumentException('Unsupported or missing document "format"; expected 1.');
            }

            $id = $this->requireString($data, 'id', 'document');
            $kind = $this->requireString($data, 'kind', 'document');
            $blocks = $data->blocks ?? null;

            if (!is_array($blocks))
            {
                throw new InvalidDocumentException('A document needs a "blocks" array.');
            }

            $tree = new BlockTree(...array_map($this->decodeBlock(...), $blocks));

            return new Document($id, $kind, $tree, self::FORMAT);
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
        if (!$data instanceof stdClass)
        {
            throw new InvalidDocumentException('A block must be a JSON object.');
        }

        $id = $this->requireString($data, 'id', 'block');
        $type = $this->requireString($data, 'type', sprintf('block "%s"', $id));
        $version = $data->version ?? 1;

        if (!is_int($version) || $version < 1)
        {
            throw new InvalidDocumentException(sprintf('Block "%s" needs an integer "version" of 1 or more.', $id));
        }

        $extensions = [];

        foreach ($this->object($data, 'extensions', sprintf('block "%s"', $id)) as $namespace => $extension)
        {
            $namespace = (string) $namespace;

            if (preg_match(self::NAMESPACE_PATTERN, $namespace) !== 1)
            {
                throw new InvalidDocumentException(
                    sprintf('Extension "%s" of block "%s" is not a valid decorator namespace.', $namespace, $id)
                );
            }

            if (!$extension instanceof stdClass)
            {
                throw new InvalidDocumentException(
                    sprintf('Extension "%s" of block "%s" must be an object.', $namespace, $id)
                );
            }

            $extensions[$namespace] = $this->plain($extension);
        }

        $bindings = [];

        foreach ($this->object($data, 'bindings', sprintf('block "%s"', $id)) as $path => $binding)
        {
            $bindings[(string) $path] = $this->decodeBinding((string) $path, $binding, $id);
        }

        $children = property_exists($data, 'children') ? $data->children : [];

        if (!is_array($children))
        {
            throw new InvalidDocumentException(sprintf('Block "%s" needs a "children" array.', $id));
        }

        return new Block(
            $id,
            BlockName::fromString($type),
            $version,
            $this->plain($this->object($data, 'settings', sprintf('block "%s"', $id))),
            $extensions,
            $bindings,
            array_map($this->decodeBlock(...), $children),
        );
    }

    private function decodeBinding(string $path, mixed $binding, string $blockId): Binding
    {
        if (!$binding instanceof stdClass)
        {
            throw new InvalidDocumentException(
                sprintf('Binding "%s" of block "%s" must be an object.', $path, $blockId)
            );
        }

        $source = $binding->source ?? null;

        if (!is_string($source))
        {
            throw new InvalidDocumentException(
                sprintf('Binding "%s" of block "%s" needs a string "source".', $path, $blockId)
            );
        }

        SettingPath::fromString($path);

        $decoded = Binding::to(
            $source,
            $this->plain($this->object($binding, 'arguments', sprintf('binding "%s" of block "%s"', $path, $blockId))),
        );

        if (!property_exists($binding, 'fallback'))
        {
            return $decoded;
        }

        return $decoded->withFallback($this->plain($binding->fallback));
    }

    /**
     * Reads an optional JSON object; absent means empty, any other kind is an error.
     *
     */
    private function object(stdClass $data, string $key, string $subject): stdClass
    {
        if (!property_exists($data, $key))
        {
            return new stdClass();
        }

        $value = $data->$key;

        if (!$value instanceof stdClass)
        {
            throw new InvalidDocumentException(sprintf('"%s" of %s must be an object.', $key, $subject));
        }

        return $value;
    }

    /**
     * Converts decoded JSON to PHP values: objects become associative arrays, lists stay lists.
     *
     * @throws InvalidDocumentException
     *   When a number is not finite.
     */
    private function plain(mixed $value): mixed
    {
        if (is_float($value) && !is_finite($value))
        {
            throw new InvalidDocumentException('Numbers must be finite.');
        }

        if ($value instanceof stdClass)
        {
            return array_map($this->plain(...), (array) $value);
        }

        if (is_array($value))
        {
            return array_map($this->plain(...), $value);
        }

        return $value;
    }

    private function requireString(stdClass $data, string $key, string $subject): string
    {
        $value = $data->$key ?? null;

        if (!is_string($value) || $value === '')
        {
            throw new InvalidDocumentException(sprintf('The %s needs a non-empty string "%s".', $subject, $key));
        }

        return $value;
    }
}
