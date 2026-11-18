<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Schema;

use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Exceptions\UnsupportedSchemaException;
use SpeedySpec\Block\Domain\ValueObjects\ValidationError;
use SpeedySpec\Block\Domain\ValueObjects\ValidationResult;

/**
 * Validates values against the small JSON Schema subset block settings use, with no external engine.
 *
 * Unsupported keywords are rejected by assertSupported() at registration; validate() ignores them so a
 * schema that slipped through can never make a render throw. `enum` and `const` compare strictly (`===`).
 *
 * @since 0.1.0
 */
class SubsetSchemaValidator implements SchemaValidatorInterface
{
    private const array KEYWORDS = [
        'type', 'enum', 'const', 'default', 'required', 'properties', 'additionalProperties', 'items',
        'minLength', 'maxLength', 'pattern', 'minimum', 'maximum', 'minItems', 'maxItems',
        'title', 'description', 'x-private', 'x-control', 'x-group',
    ];

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function assertSupported(array $schema, bool $allowPrivate = false): void
    {
        $this->assertSchemaSupported($schema, '', false, $allowPrivate);
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function validate(mixed $value, array $schema): ValidationResult
    {
        return ValidationResult::invalid(...$this->check($value, $schema, ''));
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function withDefaults(array $value, array $schema): array
    {
        $properties = $schema['properties'] ?? null;

        if (!is_array($properties))
        {
            return $value;
        }

        foreach ($value as $name => $present)
        {
            $child = $properties[$name] ?? null;

            if (is_array($present) && is_array($child) && is_array($child['properties'] ?? null))
            {
                $value[$name] = $this->withDefaults($present, $child);
            }
        }

        foreach ($properties as $name => $child)
        {
            if (!array_key_exists($name, $value) && is_array($child) && array_key_exists('default', $child))
            {
                $value[$name] = $child['default'];
            }
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function privateProperties(array $schema): array
    {
        $names = [];

        foreach (is_array($schema['properties'] ?? null) ? $schema['properties'] : [] as $name => $child)
        {
            if (is_array($child) && ($child['x-private'] ?? false) === true)
            {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * {@inheritDoc}
     *
     * @since 0.1.0
     */
    public function subschema(array $schema, array $segments): ?array
    {
        foreach ($segments as $segment)
        {
            $properties = $schema['properties'] ?? null;

            if (!is_array($properties) || !isset($properties[$segment]) || !is_array($properties[$segment]))
            {
                return null;
            }

            $schema = $properties[$segment];
        }

        return $schema;
    }

    /**
     * Walks one schema node, recursing into the keywords that hold sub-schemas.
     *
     * @param array<array-key, mixed> $schema
     *   The node to check.
     * @param string $path
     *   Dotted path of the node, empty for the root.
     * @param bool $privateAllowed
     *   Whether `x-private` may appear on this node.
     * @param bool $allowPrivate
     *   Whether the caller enabled `x-private` at all; only the root's direct properties may carry it.
     *
     * @throws UnsupportedSchemaException
     *
     * @since 0.1.0
     */
    private function assertSchemaSupported(array $schema, string $path, bool $privateAllowed, bool $allowPrivate): void
    {
        foreach ($schema as $keyword => $argument)
        {
            $keyword = (string) $keyword;
            $at = $this->join($path, $keyword);

            if (!in_array($keyword, self::KEYWORDS, true) || ($keyword === 'x-private' && !$privateAllowed))
            {
                throw new UnsupportedSchemaException(sprintf('Unsupported schema keyword at "%s".', $at));
            }

            if (!$this->argumentIsValid($keyword, $argument))
            {
                throw new UnsupportedSchemaException(
                    sprintf('Unsupported schema keyword at "%s": invalid argument.', $at)
                );
            }

            if ($keyword === 'properties')
            {
                $this->assertSubschemas($argument, $at, $path === '' && $allowPrivate, $allowPrivate);
            }
            elseif ($keyword === 'items' || ($keyword === 'additionalProperties' && is_array($argument)))
            {
                $this->assertSubschemas([$keyword => $argument], $path, false, $allowPrivate);
            }
        }
    }

    /**
     * Tells whether a keyword's argument has the shape validate() relies on, so nothing is silently ignored.
     *
     * @since 0.1.0
     */
    private function argumentIsValid(string $keyword, mixed $argument): bool
    {
        $isNumber = is_int($argument) || is_float($argument);

        return match ($keyword)
        {
            'type' => $this->isValidType($argument),
            'required' => is_array($argument) && array_is_list($argument)
                && array_all($argument, static fn ($name) => is_string($name)),
            'enum' => is_array($argument) && array_is_list($argument),
            'properties' => is_array($argument),
            'items' => is_array($argument),
            'additionalProperties' => is_bool($argument) || is_array($argument),
            'minLength', 'maxLength', 'minItems', 'maxItems' => is_int($argument) && $argument >= 0,
            'minimum', 'maximum' => $isNumber,
            'pattern' => $this->patternCompiles($argument),
            'title', 'description', 'x-control', 'x-group' => is_string($argument),
            'x-private' => is_bool($argument),
            default => true,
        };
    }

    /**
     * Tells whether a `type` argument is a known type name or a non-empty list of them.
     *
     * @since 0.1.0
     */
    private function isValidType(mixed $type): bool
    {
        $known = ['string', 'integer', 'number', 'boolean', 'array', 'object', 'null'];

        if (is_string($type))
        {
            return in_array($type, $known, true);
        }

        return is_array($type) && $type !== [] && array_is_list($type)
            && array_all($type, static fn ($name) => is_string($name) && in_array($name, $known, true));
    }

    /**
     * Checks every schema in a map, rejecting entries that are not schemas.
     *
     * @param mixed $schemas
     *   The `properties` map, or a single-entry map holding an `items` or `additionalProperties` schema.
     * @param string $path
     *   Dotted path of the map, or of the owner of a single-entry map.
     * @param bool $privateAllowed
     *   Whether the entries may carry `x-private`.
     * @param bool $allowPrivate
     *   Whether the caller enabled `x-private` at all.
     *
     * @throws UnsupportedSchemaException
     *
     * @since 0.1.0
     */
    private function assertSubschemas(
        mixed $schemas,
        string $path,
        bool $privateAllowed,
        bool $allowPrivate,
    ): void
    {
        if (!is_array($schemas))
        {
            throw new UnsupportedSchemaException(sprintf('Unsupported schema keyword at "%s": expected a map.', $path));
        }

        foreach ($schemas as $name => $child)
        {
            $at = $this->join($path, (string) $name);

            if (!is_array($child))
            {
                throw new UnsupportedSchemaException(
                    sprintf('Unsupported schema keyword at "%s": expected a schema.', $at)
                );
            }

            $this->assertSchemaSupported($child, $at, $privateAllowed, $allowPrivate);
        }
    }

    /**
     * Collects the failures of a value against one schema node.
     *
     * @param mixed $value
     *   The value to check.
     * @param array<array-key, mixed> $schema
     *   The schema node.
     * @param string $path
     *   Dotted path of the value, empty for the root.
     *
     * @return list<ValidationError>
     *
     * @since 0.1.0
     */
    private function check(mixed $value, array $schema, string $path): array
    {
        if (array_key_exists('type', $schema) && !$this->matchesType($value, $schema['type']))
        {
            return [new ValidationError($path, 'type', sprintf('Expected %s.', $this->describeType($schema['type'])))];
        }

        $errors = [];

        if (is_array($schema['enum'] ?? null) && !in_array($value, $schema['enum'], true))
        {
            $errors[] = new ValidationError($path, 'enum', 'Value is not one of the allowed values.');
        }

        if (array_key_exists('const', $schema) && $value !== $schema['const'])
        {
            $errors[] = new ValidationError($path, 'const', 'Value does not equal the required constant.');
        }

        if (is_string($value))
        {
            array_push($errors, ...$this->checkString($value, $schema, $path));
        }

        if (is_int($value) || is_float($value))
        {
            array_push($errors, ...$this->checkNumber($value, $schema, $path));
        }

        if (is_array($value))
        {
            if ($value === [] || array_is_list($value))
            {
                array_push($errors, ...$this->checkList($value, $schema, $path));
            }

            if ($value === [] || !array_is_list($value))
            {
                array_push($errors, ...$this->checkObject($value, $schema, $path));
            }
        }

        return $errors;
    }

    /**
     * Checks the string-only keywords.
     *
     * @param array<array-key, mixed> $schema
     *
     * @return list<ValidationError>
     *
     * @since 0.1.0
     */
    private function checkString(string $value, array $schema, string $path): array
    {
        $errors = [];
        $length = $this->length($value);

        if (is_int($schema['minLength'] ?? null) && $length < $schema['minLength'])
        {
            $errors[] = new ValidationError(
                $path,
                'minLength',
                sprintf('Must be at least %d characters.', $schema['minLength']),
            );
        }

        if (is_int($schema['maxLength'] ?? null) && $length > $schema['maxLength'])
        {
            $errors[] = new ValidationError(
                $path,
                'maxLength',
                sprintf('Must be at most %d characters.', $schema['maxLength']),
            );
        }

        if (is_string($schema['pattern'] ?? null) && !$this->matchesPattern($value, $schema['pattern']))
        {
            $errors[] = new ValidationError($path, 'pattern', 'Does not match the required pattern.');
        }

        return $errors;
    }

    /**
     * Checks the numeric-only keywords.
     *
     * @param array<array-key, mixed> $schema
     *
     * @return list<ValidationError>
     *
     * @since 0.1.0
     */
    private function checkNumber(int|float $value, array $schema, string $path): array
    {
        $errors = [];

        if ((is_int($schema['minimum'] ?? null) || is_float($schema['minimum'] ?? null)) && $value < $schema['minimum'])
        {
            $errors[] = new ValidationError($path, 'minimum', sprintf('Must be at least %s.', $schema['minimum']));
        }

        if ((is_int($schema['maximum'] ?? null) || is_float($schema['maximum'] ?? null)) && $value > $schema['maximum'])
        {
            $errors[] = new ValidationError($path, 'maximum', sprintf('Must be at most %s.', $schema['maximum']));
        }

        return $errors;
    }

    /**
     * Checks the list keywords: item count and the `items` schema.
     *
     * @param array<array-key, mixed> $value
     * @param array<array-key, mixed> $schema
     *
     * @return list<ValidationError>
     *
     * @since 0.1.0
     */
    private function checkList(array $value, array $schema, string $path): array
    {
        $errors = [];

        if (is_int($schema['minItems'] ?? null) && count($value) < $schema['minItems'])
        {
            $errors[] = new ValidationError(
                $path,
                'minItems',
                sprintf('Must have at least %d items.', $schema['minItems']),
            );
        }

        if (is_int($schema['maxItems'] ?? null) && count($value) > $schema['maxItems'])
        {
            $errors[] = new ValidationError(
                $path,
                'maxItems',
                sprintf('Must have at most %d items.', $schema['maxItems']),
            );
        }

        if (is_array($schema['items'] ?? null))
        {
            foreach ($value as $index => $item)
            {
                array_push($errors, ...$this->check($item, $schema['items'], $this->join($path, (string) $index)));
            }
        }

        return $errors;
    }

    /**
     * Checks the object keywords: `required`, `properties` and `additionalProperties`.
     *
     * @param array<array-key, mixed> $value
     * @param array<array-key, mixed> $schema
     *
     * @return list<ValidationError>
     *
     * @since 0.1.0
     */
    private function checkObject(array $value, array $schema, string $path): array
    {
        $errors = [];
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

        foreach (is_array($schema['required'] ?? null) ? $schema['required'] : [] as $name)
        {
            if (is_string($name) && !array_key_exists($name, $value))
            {
                $errors[] = new ValidationError($this->join($path, $name), 'required', 'Property is required.');
            }
        }

        foreach ($value as $name => $item)
        {
            $at = $this->join($path, (string) $name);

            if (array_key_exists($name, $properties))
            {
                if (is_array($properties[$name]))
                {
                    array_push($errors, ...$this->check($item, $properties[$name], $at));
                }

                continue;
            }

            $additional = $schema['additionalProperties'] ?? true;

            if ($additional === false)
            {
                $errors[] = new ValidationError($at, 'additionalProperties', 'Property is not allowed.');
            }
            elseif (is_array($additional))
            {
                array_push($errors, ...$this->check($item, $additional, $at));
            }
        }

        return $errors;
    }

    /**
     * Tells whether a value matches a `type` keyword argument, which may be a single type or a list.
     *
     * @since 0.1.0
     */
    private function matchesType(mixed $value, mixed $type): bool
    {
        foreach (is_array($type) ? $type : [$type] as $candidate)
        {
            $matches = match ($candidate)
            {
                'string' => is_string($value),
                'integer' => is_int($value),
                'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                'null' => $value === null,
                'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && ($value === [] || !array_is_list($value)),
                default => false,
            };

            if ($matches)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Renders a `type` keyword argument for an error message.
     *
     * @since 0.1.0
     */
    private function describeType(mixed $type): string
    {
        $names = array_filter(is_array($type) ? $type : [$type], 'is_string');

        return $names === [] ? 'a valid value' : implode(' or ', $names);
    }

    /**
     * Matches a value against a delimiter-less PCRE body, unanchored like JSON Schema but with `$` bound to
     * the true end of the string so a trailing newline cannot slip through.
     *
     * @since 0.1.0
     */
    private function matchesPattern(string $value, string $pattern): bool
    {
        return $this->match($pattern, $value) === 1;
    }

    /**
     * Tells whether a `pattern` argument is a string PCRE accepts.
     *
     * @since 0.1.0
     */
    private function patternCompiles(mixed $pattern): bool
    {
        return is_string($pattern) && $this->match($pattern, '') !== false;
    }

    /**
     * Runs a pattern, turning PCRE compile warnings into a false result so callers never see a warning.
     *
     * @return int|false
     *   1 or 0 for a match result, false when the pattern does not compile.
     *
     * @since 0.1.0
     */
    private function match(string $pattern, string $value): int|false
    {
        set_error_handler(static fn (): bool => true);

        try
        {
            return preg_match($this->compile($pattern), $value);
        }
        finally
        {
            restore_error_handler();
        }
    }

    /**
     * Wraps a pattern body in a delimiter no pattern can contain (a control byte) with the `u` and `D` flags.
     *
     * @since 0.1.0
     */
    private function compile(string $pattern): string
    {
        return "\x01" . $pattern . "\x01uD";
    }

    /**
     * Counts characters rather than bytes, falling back to bytes for invalid UTF-8.
     *
     * @since 0.1.0
     */
    private function length(string $value): int
    {
        $count = preg_match_all('/./su', $value);

        return $count === false ? strlen($value) : $count;
    }

    /**
     * Appends a segment to a dotted path.
     *
     * @since 0.1.0
     */
    private function join(string $path, string $segment): string
    {
        return $path === '' ? $segment : $path . '.' . $segment;
    }
}
