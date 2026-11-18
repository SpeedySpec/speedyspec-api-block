<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Resolving;

use SpeedySpec\Block\Domain\Contracts\DataSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Exceptions\InvalidSettingPathException;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;
use Throwable;

/**
 * Replaces bound settings with values from their data sources, falling back so a bad value never stops the block.
 *
 * Each binding tries, in order, the source's value, the binding's fallback, the current setting and the schema
 * default; the first that satisfies the setting's schema is written and every rejected one is reported.
 *
 * @since 0.1.0
 */
class ResolveStage
{
    /**
     * Wires the stage to the registries it reads.
     *
     * @param DataSourceRegistryInterface $sources
     *   Finds the sources bindings name.
     * @param DecoratorRegistryInterface $decorators
     *   Supplies the schemas of namespaced paths.
     * @param SchemaValidatorInterface $validator
     *   Checks arguments and candidate values.
     */
    public function __construct(
        private readonly DataSourceRegistryInterface $sources,
        private readonly DecoratorRegistryInterface $decorators,
        private readonly SchemaValidatorInterface $validator,
    )
    {
    }

    /**
     * Resolves every binding of one block in stored order.
     *
     * @since 0.1.0
     */
    public function run(StageOutcome $gated, BlockType $type, Context $context, Mode $mode): StageOutcome
    {
        if (!$gated->continue)
        {
            return $gated;
        }

        $blockId = $gated->block->id;
        $settings = $gated->settings;
        $extensions = $gated->extensions;
        $issues = $gated->issues;
        $info = $gated->bindingInfo;

        foreach ($gated->block->bindings as $rawPath => $binding)
        {
            $rawPath = (string) $rawPath;
            $info[$rawPath] = ['source' => $binding->source, 'used' => 'none'];

            try
            {
                $path = SettingPath::fromString($rawPath);
            }
            catch (InvalidSettingPathException $e)
            {
                $issues[] = new Issue($blockId, IssueKind::InvalidBinding, $e->getMessage(), $rawPath);

                continue;
            }

            $namespace = $path->namespace();
            $rootSchema = $type->settingsSchema;

            if ($namespace !== null)
            {
                $decorator = $this->decorators->get($namespace);

                if ($decorator === null || !$decorator->appliesTo($type->name))
                {
                    $issues[] = new Issue($blockId, IssueKind::InvalidBinding, sprintf(
                        'No decorator "%s" applies to "%s".',
                        $namespace,
                        $type->name->toString(),
                    ), $rawPath);

                    continue;
                }

                $rootSchema = $decorator->settingsSchema;
            }

            $schema = $this->validator->subschema($rootSchema, $path->segments());

            if ($schema === null)
            {
                $issues[] = new Issue($blockId, IssueKind::InvalidBinding, sprintf(
                    'Setting "%s" is not declared.',
                    $rawPath,
                ), $rawPath);

                continue;
            }

            if ($namespace === null
                && in_array($path->segments()[0], $this->validator->privateProperties($type->settingsSchema), true))
            {
                $issues[] = new Issue($blockId, IssueKind::PrivateBinding, sprintf(
                    'Setting "%s" is private and cannot be bound.',
                    $rawPath,
                ), $rawPath);

                continue;
            }

            $target = $namespace === null ? $settings : ($extensions[$namespace] ?? []);
            $candidates = [];
            $source = $this->sourceValue($binding, $rawPath, $blockId, $context, $mode, $issues);

            if ($source !== null)
            {
                $candidates[] = ['source', $source[0]];
            }

            if ($binding->hasFallback)
            {
                $candidates[] = ['fallback', $binding->fallback];
            }

            if ($this->has($target, $path->segments()))
            {
                $candidates[] = ['stored', $this->get($target, $path->segments())];
            }

            if (array_key_exists('default', $schema))
            {
                $candidates[] = ['default', $schema['default']];
            }

            foreach ($candidates as [$used, $value])
            {
                if (!$this->validator->validate($value, $schema)->isValid())
                {
                    $issues[] = new Issue($blockId, IssueKind::InvalidBinding, sprintf(
                        'The %s value for "%s" does not match its schema.',
                        $used,
                        $rawPath,
                    ), $rawPath);

                    continue;
                }

                // A value from a binding is data. Where the setting is formatted text, it is turned into text, so a
                // data source can never put markup on the page however its value is drawn.
                if (($used === 'source' || $used === 'fallback') && is_string($value)
                    && ($schema['x-control'] ?? null) === 'rich-text')
                {
                    $value = htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
                }

                $target = $this->set($target, $path->segments(), $value);
                $info[$rawPath]['used'] = $used;

                break;
            }

            if ($namespace === null)
            {
                $settings = $target;
            }
            else
            {
                $extensions[$namespace] = $target;
            }
        }

        return $gated->with(settings: $settings, extensions: $extensions, issues: $issues, bindingInfo: $info);
    }

    /**
     * Asks the binding's source for its value, reporting why when there is none to try.
     *
     * @param list<Issue> $issues
     *   Receives the issue when the source is unknown, refuses the arguments or throws.
     *
     * @return array{mixed}|null
     *   The value wrapped in a one-element list, so a null value is told apart from no value.
     */
    private function sourceValue(
        Binding $binding,
        string $path,
        string $blockId,
        Context $context,
        Mode $mode,
        array &$issues,
    ): ?array
    {
        $source = $this->sources->get($binding->source);

        if ($source === null)
        {
            $issues[] = new Issue($blockId, IssueKind::UnknownDataSource, sprintf(
                'Data source "%s" is not registered.',
                $binding->source,
            ), $path);

            return null;
        }

        try
        {
            if (!$this->validator->validate($binding->arguments, $source->argumentsSchema())->isValid())
            {
                $issues[] = new Issue($blockId, IssueKind::InvalidBinding, sprintf(
                    'The arguments for data source "%s" do not match its schema.',
                    $binding->source,
                ), $path);

                return null;
            }

            $result = $source->resolve($binding->arguments, $context, $mode);
        }
        catch (Throwable $e)
        {
            $issues[] = new Issue($blockId, IssueKind::InvalidBinding, sprintf(
                'Data source "%s" failed with %s.',
                $binding->source,
                $e::class,
            ), $path);

            return null;
        }

        return $result->hasValue() ? [$result->get()] : null;
    }

    /**
     * @param list<string> $segments
     */
    private function has(array $values, array $segments): bool
    {
        foreach ($segments as $segment)
        {
            if (!is_array($values) || !array_key_exists($segment, $values))
            {
                return false;
            }

            $values = $values[$segment];
        }

        return true;
    }

    /**
     * @param list<string> $segments
     */
    private function get(array $values, array $segments): mixed
    {
        foreach ($segments as $segment)
        {
            $values = $values[$segment];
        }

        return $values;
    }

    /**
     * Writes a value at a path, replacing any non-array parent with an array so the path can exist.
     *
     * @param list<string> $segments
     */
    private function set(array $values, array $segments, mixed $value): array
    {
        $segment = array_shift($segments);

        if ($segments === [])
        {
            $values[$segment] = $value;

            return $values;
        }

        $child = $values[$segment] ?? [];
        $values[$segment] = $this->set(is_array($child) ? $child : [], $segments, $value);

        return $values;
    }
}
