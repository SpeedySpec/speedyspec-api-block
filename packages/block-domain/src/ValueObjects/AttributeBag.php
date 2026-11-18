<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

use SpeedySpec\Block\Domain\Exceptions\InvalidAttributeException;

/**
 * The classes, attributes, styles and id a block's root element should carry.
 *
 * Decorators and steps change a block's output only through this bag, never by editing markup, so everything they
 * add is checked here and escaped once when the element is drawn.
 *
 * @since 0.1.0
 */
class AttributeBag
{
    /**
     * Keeps the constructor private so every entry passes through the checking methods.
     *
     * @param list<string> $classes
     *   Unique class names in insertion order.
     * @param array<string, string> $attributes
     *   Attribute name to raw value.
     * @param array<string, string> $styles
     *   Style property to value.
     *
     * @since 0.1.0
     */
    private function __construct(
        private readonly array $classes = [],
        private readonly array $attributes = [],
        private readonly array $styles = [],
        private readonly ?string $id = null,
    )
    {
    }

    /**
     * Starts a bag with nothing in it.
     *
     * @since 0.1.0
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * Adds class names, ignoring ones already present.
     *
     * @throws InvalidAttributeException
     *   When a class is empty or holds a character that could end the attribute.
     *
     * @since 0.1.0
     */
    public function withClass(string ...$classes): self
    {
        $all = $this->classes;

        foreach ($classes as $class)
        {
            if ($class === '' || preg_match('/[\s"\'<>=\x00-\x1F\x7F]/', $class) === 1)
            {
                throw new InvalidAttributeException(sprintf('"%s" is not a safe class name.', $class));
            }

            if (!in_array($class, $all, true))
            {
                $all[] = $class;
            }
        }

        return new self($all, $this->attributes, $this->styles, $this->id);
    }

    /**
     * Sets one attribute, replacing a value already stored under that name.
     *
     * @param string $value
     *   The raw value; it is escaped when the element is drawn.
     *
     * @throws InvalidAttributeException
     *   When the name is malformed, is managed by the bag itself, or is an event handler.
     *
     * @since 0.1.0
     */
    public function withAttribute(string $name, string $value): self
    {
        $lower = strtolower($name);

        if (
            preg_match('/^[a-zA-Z_][a-zA-Z0-9_.:-]*\z/', $name) !== 1
            || in_array($lower, ['class', 'id', 'style'], true)
            || str_starts_with($lower, 'on')
        )
        {
            throw new InvalidAttributeException(sprintf('"%s" is not an attribute a block may set.', $name));
        }

        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return new self($this->classes, $attributes, $this->styles, $this->id);
    }

    /**
     * Sets one inline style declaration, replacing a value already stored for that property.
     *
     * @throws InvalidAttributeException
     *   When the property is malformed or the value could add a declaration, leave the attribute or run script.
     *
     * @since 0.1.0
     */
    public function withStyle(string $property, string $value): self
    {
        if (preg_match('/^-{0,2}[a-zA-Z][a-zA-Z0-9-]*\z/', $property) !== 1)
        {
            throw new InvalidAttributeException(sprintf('"%s" is not a safe style property.', $property));
        }

        if (
            $value === ''
            || preg_match('/[;{}<>\x00-\x1F\x7F]/', $value) === 1
            || preg_match('/expression\(|javascript:|@import/i', $value) === 1
        )
        {
            throw new InvalidAttributeException(
                sprintf('The value given for "%s" is not a safe style value.', $property)
            );
        }

        $styles = $this->styles;
        $styles[$property] = $value;

        return new self($this->classes, $this->attributes, $styles, $this->id);
    }

    /**
     * Sets the element id, replacing one already stored.
     *
     * @throws InvalidAttributeException
     *   When the id is empty or holds whitespace or a control character.
     *
     * @since 0.1.0
     */
    public function withId(string $id): self
    {
        if ($id === '' || preg_match('/[\s\x00-\x1F\x7F]/', $id) === 1)
        {
            throw new InvalidAttributeException(sprintf('"%s" is not a safe element id.', $id));
        }

        return new self($this->classes, $this->attributes, $this->styles, $id);
    }

    /**
     * Combines two bags, letting the other bag win wherever both set the same thing.
     *
     * @since 0.1.0
     */
    public function merge(self $other): self
    {
        $classes = $this->classes;

        foreach ($other->classes as $class)
        {
            if (!in_array($class, $classes, true))
            {
                $classes[] = $class;
            }
        }

        $attributes = $this->attributes;

        foreach ($other->attributes as $name => $value)
        {
            $attributes[$name] = $value;
        }

        $styles = $this->styles;

        foreach ($other->styles as $property => $value)
        {
            $styles[$property] = $value;
        }

        return new self($classes, $attributes, $styles, $other->id ?? $this->id);
    }

    /**
     * Lists the class names in the order they were added.
     *
     * @return list<string>
     *   Unique class names.
     *
     * @since 0.1.0
     */
    public function classes(): array
    {
        return $this->classes;
    }

    /**
     * Lists the attributes other than class, style and id.
     *
     * @return array<string, string>
     *   Attribute name to raw value, in insertion order.
     *
     * @since 0.1.0
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * Lists the inline style declarations.
     *
     * @return array<string, string>
     *   Style property to value, in insertion order.
     *
     * @since 0.1.0
     */
    public function styles(): array
    {
        return $this->styles;
    }

    /**
     * Gives the element id, or null when none was set.
     *
     * @since 0.1.0
     */
    public function id(): ?string
    {
        return $this->id;
    }

    /**
     * Tells whether drawing this bag would add nothing to an element.
     *
     * @since 0.1.0
     */
    public function isEmpty(): bool
    {
        return $this->classes === [] && $this->attributes === [] && $this->styles === [] && $this->id === null;
    }
}
