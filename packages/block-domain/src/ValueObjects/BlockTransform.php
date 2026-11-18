<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidBlockNameException;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTypeException;

/**
 * Declares, as data, that a block of one type can be turned into a block of another, and which settings carry over.
 *
 * It sits on the type being turned, so a definitions package says what its blocks can become without any editor
 * code; the server carries it out, so every builder gets the same result.
 *
 * @since 0.1.0
 */
class BlockTransform
{
    /**
     * @param array<string, string> $settings
     *   Maps each setting of the type turned into to the setting of this type whose value it takes. Settings left
     *   out start empty.
     *
     * @throws InvalidBlockTypeException
     *   When the map is not setting names to setting names.
     *
     * @since 0.1.0
     */
    public function __construct(
        public readonly BlockName $to,
        public readonly array $settings,
    )
    {
        foreach ($settings as $target => $source)
        {
            if (!is_string($target) || $target === '' || !is_string($source) || $source === '')
            {
                throw new InvalidBlockTypeException(sprintf(
                    'A transform to "%s" must map setting names to setting names.',
                    $to->toString(),
                ));
            }
        }
    }

    /**
     * Builds a transform from its serialised form, `{to, settings}`.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidBlockTypeException
     *   When `to` is not a block name or `settings` is not a map.
     *
     * @since 0.1.0
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['to']) || !is_string($data['to']))
        {
            throw new InvalidBlockTypeException('A transform needs the type it turns into, as "to".');
        }

        $settings = $data['settings'] ?? [];

        if (!is_array($settings))
        {
            throw new InvalidBlockTypeException(sprintf('A transform to "%s" needs a "settings" map.', $data['to']));
        }

        try
        {
            $to = BlockName::fromString($data['to']);
        }
        catch (InvalidBlockNameException $e)
        {
            throw new InvalidBlockTypeException($e->getMessage(), 0, $e);
        }

        return new self($to, $settings);
    }

    /**
     * @return array{to: string, settings: array<string, string>}
     *
     * @since 0.1.0
     */
    public function toArray(): array
    {
        return ['to' => $this->to->toString(), 'settings' => $this->settings];
    }
}
