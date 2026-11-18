<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidBlockNameException;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockTypeException;

/**
 * Declares what a kind of block is: its settings, its view, where it may nest and what context it shares.
 *
 * @since 0.1.0
 */
class BlockType
{
    public readonly ChildrenPolicy $children;

    public readonly ParentPolicy $parents;

    /**
     * Declares a block type, checking the shape of its context, asset and example lists so code cannot bypass them.
     *
     * @param array<string, mixed> $settingsSchema
     *   Schema for the block's settings.
     * @param array<string, mixed>|null $viewSchema
     *   Schema for the resolved view, or null when the block has no view.
     * @param array<string, string> $provides
     *   Maps a namespaced context key to the view key that supplies it.
     * @param list<string> $uses
     *   Context keys the block reads from its ancestors.
     * @param list<string> $assets
     *   Asset handles the block needs.
     * @param list<array<string, mixed>> $examples
     *   Sample settings arrays.
     * @param list<BlockTransform> $transforms
     *   The types a block of this type can be turned into, at most one transform for each.
     *
     * @throws InvalidBlockTypeException
     *   When `provides`, `uses`, `assets` or `examples` has the wrong shape, or a transform could not be carried
     *   out: one to this type itself, two to the same type, or one taking a setting this type does not declare.
     */
    public function __construct(
        public readonly BlockName $name,
        public readonly CatalogueMetadata $catalogue,
        public readonly int $version = 1,
        public readonly array $settingsSchema = ['type' => 'object'],
        public readonly ?array $viewSchema = null,
        ?ChildrenPolicy $children = null,
        ?ParentPolicy $parents = null,
        public readonly array $provides = [],
        public readonly array $uses = [],
        public readonly array $assets = [],
        public readonly array $examples = [],
        public readonly array $transforms = [],
    )
    {
        $subject = sprintf('Block type "%s"', $name->toString());

        if (!self::isStringMap($provides))
        {
            throw new InvalidBlockTypeException(sprintf(
                '%s needs "provides" to map non-empty context keys to non-empty view keys.',
                $subject,
            ));
        }

        foreach (['uses' => $uses, 'assets' => $assets] as $field => $list)
        {
            if (!self::isStringList($list))
            {
                throw new InvalidBlockTypeException(
                    sprintf('%s needs "%s" to be a list of strings.', $subject, $field)
                );
            }
        }

        if (!array_is_list($examples) || array_filter($examples, 'is_array') !== $examples)
        {
            throw new InvalidBlockTypeException(
                sprintf('%s needs "examples" to be a list of settings arrays.', $subject)
            );
        }

        $this->assertTransforms($subject);
        $this->children = $children ?? ChildrenPolicy::none();
        $this->parents = $parents ?? ParentPolicy::any();
    }

    /**
     * Builds a block type from its serialised form.
     *
     * @param array<string, mixed> $data
     *   The serialised type; `name` and `title` are required.
     *
     * @throws InvalidBlockTypeException
     *   When a field is missing or has the wrong shape; the message names the field.
     *
     * @since 0.1.0
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['name']) || !is_string($data['name']))
        {
            throw new InvalidBlockTypeException('A block type needs a "name".');
        }

        if (!isset($data['title']) || !is_string($data['title']))
        {
            throw new InvalidBlockTypeException(sprintf('Block type "%s" needs a "title".', $data['name']));
        }

        $version = $data['version'] ?? 1;

        if (!is_int($version) || $version < 1)
        {
            throw new InvalidBlockTypeException(
                sprintf('Block type "%s" needs an integer "version" of 1 or more.', $data['name'])
            );
        }

        try
        {
            $name = BlockName::fromString($data['name']);
        }
        catch (InvalidBlockNameException $e)
        {
            throw new InvalidBlockTypeException($e->getMessage(), 0, $e);
        }

        $settings = $data['settings'] ?? ['type' => 'object'];
        $view = $data['view'] ?? null;

        if (!is_array($settings) || ($view !== null && !is_array($view)))
        {
            throw new InvalidBlockTypeException(
                sprintf('Block type "%s" needs array "settings" and an array or null "view".', $data['name'])
            );
        }

        foreach (['keywords', 'provides', 'uses', 'assets', 'examples', 'transforms'] as $key)
        {
            if (isset($data[$key]) && !is_array($data[$key]))
            {
                throw new InvalidBlockTypeException(
                    sprintf('Block type "%s" needs an array "%s".', $data['name'], $key)
                );
            }
        }

        return new self(
            $name,
            CatalogueMetadata::fromArray($data),
            $version,
            $settings,
            $view,
            ChildrenPolicy::fromArray($data['children'] ?? false),
            ParentPolicy::fromArray($data['parents'] ?? true),
            $data['provides'] ?? [],
            $data['uses'] ?? [],
            $data['assets'] ?? [],
            $data['examples'] ?? [],
            self::transformsFrom($data['name'], $data['transforms'] ?? []),
        );
    }

    /**
     * Finds how a block of this type is turned into another type.
     *
     * @return BlockTransform|null
     *   Null when this type declares no transform to that type.
     *
     * @since 0.1.0
     */
    public function transformTo(BlockName $to): ?BlockTransform
    {
        foreach ($this->transforms as $transform)
        {
            if ($transform->to->equals($to))
            {
                return $transform;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $transforms
     * @return list<BlockTransform>
     *
     * @throws InvalidBlockTypeException
     */
    private static function transformsFrom(string $name, array $transforms): array
    {
        if (!array_is_list($transforms) || array_filter($transforms, 'is_array') !== $transforms)
        {
            throw new InvalidBlockTypeException(
                sprintf('Block type "%s" needs "transforms" to be a list of transforms.', $name)
            );
        }

        return array_map(BlockTransform::fromArray(...), $transforms);
    }

    /**
     * @throws InvalidBlockTypeException
     */
    private function assertTransforms(string $subject): void
    {
        $seen = [];

        if (!array_is_list($this->transforms))
        {
            throw new InvalidBlockTypeException(sprintf('%s needs "transforms" to be a list.', $subject));
        }

        foreach ($this->transforms as $transform)
        {
            if (!$transform instanceof BlockTransform)
            {
                throw new InvalidBlockTypeException(sprintf('%s needs "transforms" to hold transforms.', $subject));
            }

            $to = $transform->to->toString();

            if ($transform->to->equals($this->name) || isset($seen[$to]))
            {
                throw new InvalidBlockTypeException(sprintf(
                    '%s declares a transform to "%s" that is %s.',
                    $subject,
                    $to,
                    isset($seen[$to]) ? 'already declared' : 'itself',
                ));
            }

            foreach ($transform->settings as $source)
            {
                if (!isset($this->settingsSchema['properties'][$source]))
                {
                    throw new InvalidBlockTypeException(sprintf(
                        '%s turns into "%s" using a setting it does not have, "%s".',
                        $subject,
                        $to,
                        $source,
                    ));
                }
            }

            $seen[$to] = true;
        }
    }

    /**
     * Tells whether a value maps non-empty keys to non-empty strings.
     *
     * Integer keys are accepted because PHP turns a key such as `"1"` into an integer.
     */
    private static function isStringMap(array $map): bool
    {
        if ($map !== [] && array_is_list($map))
        {
            return false;
        }

        foreach ($map as $key => $value)
        {
            if ((string) $key === '' || !is_string($value) || $value === '')
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Tells whether a value is a list holding only strings.
     */
    private static function isStringList(array $list): bool
    {
        return array_is_list($list) && array_filter($list, 'is_string') === $list;
    }

    /**
     * Serialises the type so it can be stored or shipped to an editor.
     *
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    public function toArray(): array
    {
        $catalogue = $this->catalogue->toArray();

        return [
            'name' => $this->name->toString(),
            'version' => $this->version,
            'title' => $catalogue['title'],
            'description' => $catalogue['description'],
            'category' => $catalogue['category'],
            'keywords' => $catalogue['keywords'],
            'icon' => $catalogue['icon'],
            'settings' => $this->settingsSchema,
            'view' => $this->viewSchema,
            'children' => $this->children->toArray(),
            'parents' => $this->parents->toArray(),
            'provides' => $this->provides,
            'uses' => $this->uses,
            'assets' => $this->assets,
            'examples' => $this->examples,
            'transforms' => array_map(
                static fn (BlockTransform $transform): array => $transform->toArray(),
                $this->transforms,
            ),
        ];
    }
}
