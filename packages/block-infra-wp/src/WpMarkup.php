<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Infra\WP;

use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use Throwable;

/**
 * Reads WordPress block markup into a block tree and writes a tree back as markup.
 *
 * It follows the grammar of WordPress's block comments without calling WordPress, so it works wherever the
 * markup is, including in tests. A block's attributes become its settings. The markup a block saved around its
 * inner blocks is kept, piece by piece, in a `wpContent` setting, because WordPress stores what a block looks
 * like in that markup and not in its attributes.
 *
 * WordPress blocks have no identity. A block read here is identified by where it sits (`wp-1-0`), and that id is
 * written into the block's `metadata` on the way out, so the next read finds the same block even if it moved.
 *
 * @since 0.1.0
 */
class WpMarkup
{
    public const string CONTENT = 'wpContent';

    public const string FREEFORM = 'core/freeform';

    private const string TOKEN = '/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?'
        . '(?P<name>[a-z][a-z0-9_-]*)\s+'
        . '(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?P<void>\/)?-->/s';

    /**
     * Reads markup; markup that is not well formed is read as far as it makes sense, never refused.
     *
     * @since 0.1.0
     */
    public function parse(string $markup): BlockTree
    {
        // Each frame is a block being read: its name, attributes, content pieces and children so far.
        $root = ['name' => null, 'attrs' => [], 'content' => [], 'children' => []];
        $stack = [$root];
        $offset = 0;

        while (preg_match(self::TOKEN, $markup, $found, PREG_OFFSET_CAPTURE, $offset) === 1)
        {
            $start = $found[0][1];
            $text = substr($markup, $offset, $start - $offset);
            $offset = $start + strlen($found[0][0]);
            $name = $this->name($found['namespace'][0] ?? '', $found['name'][0]);
            $top = count($stack) - 1;

            $this->text($stack[$top], $text);

            if ($name === null)
            {
                $this->text($stack[$top], $found[0][0]);
            }
            elseif (($found['closer'][0] ?? '') === '/')
            {
                if ($top > 0 && $stack[$top]['name'] === $name)
                {
                    $closed = array_pop($stack);
                    $this->child($stack[$top - 1], $closed);
                }
                else
                {
                    // A closer with nothing to close is kept as text rather than lost.
                    $this->text($stack[$top], $found[0][0]);
                }
            }
            else
            {
                $frame = [
                    'name' => $name,
                    'attrs' => $this->attributes($found['attrs'][0] ?? ''),
                    'content' => [],
                    'children' => [],
                ];

                if (($found['void'][0] ?? '') === '/')
                {
                    $this->child($stack[$top], $frame);
                }
                else
                {
                    $stack[] = $frame;
                }
            }
        }

        $this->text($stack[count($stack) - 1], substr($markup, $offset));

        // Blocks left open at the end are closed where the markup stops.
        while (count($stack) > 1)
        {
            $closed = array_pop($stack);
            $this->child($stack[count($stack) - 1], $closed);
        }

        // Text between blocks is written back with blank lines around it, so the space it was read with is
        // dropped; kept, it would grow by two lines on every save.
        foreach ($stack[0]['children'] as &$frame)
        {
            if ($frame['name'] === self::FREEFORM)
            {
                $frame['content'][0] = trim($frame['content'][0]);
            }
        }

        unset($frame);

        $used = [];
        $this->claimIds($stack[0]['children'], $used);

        return new BlockTree(...$this->blocks($stack[0]['children'], [], $used));
    }

    /**
     * Writes a tree as block markup, with every block's id in its metadata.
     *
     * @since 0.1.0
     */
    public function serialize(BlockTree $tree): string
    {
        return implode("\n\n", array_map($this->write(...), $tree->roots()));
    }

    /**
     * Adds text to a block being read; at the top level it becomes a freeform block unless it is only space.
     *
     * @param array<string, mixed> $frame
     *
     * @since 0.1.0
     */
    private function text(array &$frame, string $text): void
    {
        if ($text === '')
        {
            return;
        }

        if ($frame['name'] !== null)
        {
            $last = count($frame['content']) - 1;

            if ($last >= 0 && is_string($frame['content'][$last]))
            {
                $frame['content'][$last] .= $text;
            }
            else
            {
                $frame['content'][] = $text;
            }

            return;
        }

        $last = count($frame['children']) - 1;

        $open = $last >= 0
            && $frame['children'][$last]['name'] === self::FREEFORM
            && !empty($frame['children'][$last]['open']);

        if ($open)
        {
            $frame['children'][$last]['content'][0] .= $text;
        }
        elseif (trim($text) !== '')
        {
            $frame['children'][] = [
                'name' => self::FREEFORM,
                'attrs' => [],
                'content' => [$text],
                'children' => [],
                'open' => true,
            ];
        }
    }

    /**
     * Adds a finished block to its parent, leaving a gap in the parent's content where it sits.
     *
     * @param array<string, mixed> $parent
     * @param array<string, mixed> $child
     *
     * @since 0.1.0
     */
    private function child(array &$parent, array $child): void
    {
        $last = count($parent['children']) - 1;

        if ($last >= 0)
        {
            unset($parent['children'][$last]['open']);
        }

        $parent['children'][] = $child;

        if ($parent['name'] !== null)
        {
            $parent['content'][] = null;
        }
    }

    private function name(string $namespace, string $name): ?string
    {
        $full = ($namespace === '' ? 'core/' : $namespace) . $name;

        try
        {
            return BlockName::fromString($full)->toString();
        }
        catch (Throwable)
        {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function attributes(string $json): array
    {
        $attributes = $json === '' ? [] : json_decode($json, true);

        return is_array($attributes) && ($attributes === [] || !array_is_list($attributes)) ? $attributes : [];
    }

    /**
     * Lets each block keep the id written in its metadata, the first time that id appears.
     *
     * Done for the whole tree before any positional id is given out, so a positional id never takes one that a
     * later block has stored.
     *
     * @param list<array<string, mixed>> $frames
     * @param array<string, true> $used
     *
     * @since 0.1.0
     */
    private function claimIds(array &$frames, array &$used): void
    {
        foreach ($frames as &$frame)
        {
            $stored = $frame['attrs']['metadata']['blockId'] ?? null;

            if (is_string($stored) && $stored !== '' && !isset($used[$stored]))
            {
                $used[$stored] = true;
                $frame['id'] = $stored;
            }

            $this->claimIds($frame['children'], $used);
        }
    }

    /**
     * @param list<array<string, mixed>> $frames
     * @param list<int> $path
     * @param array<string, true> $used
     *
     * @return list<Block>
     *
     * @since 0.1.0
     */
    private function blocks(array $frames, array $path, array &$used): array
    {
        $blocks = [];

        foreach ($frames as $index => $frame)
        {
            $here = [...$path, $index];
            $id = $frame['id'] ?? null;

            if ($id === null)
            {
                $id = 'wp-' . implode('-', $here);

                for ($n = 2; isset($used[$id]); $n++)
                {
                    $id = 'wp-' . implode('-', $here) . '-' . $n;
                }

                $used[$id] = true;
            }

            $settings = $frame['attrs'];
            $metadata = is_array($settings['metadata'] ?? null) ? $settings['metadata'] : [];
            $bindings = [];

            foreach (is_array($metadata['bindings'] ?? null) ? $metadata['bindings'] : [] as $attribute => $binding)
            {
                // The saved markup is drawn as it is, so it is never taken from a data source.
                if ((string) $attribute === self::CONTENT)
                {
                    continue;
                }

                if (is_array($binding) && is_string($binding['source'] ?? null) && $binding['source'] !== '')
                {
                    $bindings[(string) $attribute] = Binding::to(
                        $binding['source'],
                        is_array($binding['args'] ?? null) ? $binding['args'] : [],
                    );
                }
            }

            unset($metadata['blockId'], $metadata['bindings'], $settings['metadata']);

            if ($metadata !== [])
            {
                $settings['metadata'] = $metadata;
            }

            $settings[self::CONTENT] = $frame['content'];

            try
            {
                $blocks[] = new Block(
                    $id,
                    BlockName::fromString($frame['name']),
                    1,
                    $settings,
                    [],
                    $bindings,
                    $this->blocks($frame['children'], $here, $used),
                );
            }
            catch (Throwable)
            {
                // A block the model refuses, such as one with a binding path it cannot hold, is left out
                // rather than stopping the whole post from opening.
            }
        }

        return $blocks;
    }

    private function write(Block $block): string
    {
        $content = is_array($block->settings[self::CONTENT] ?? null) ? $block->settings[self::CONTENT] : [];
        $inner = $this->inner($content, array_map($this->write(...), $block->children));

        if ($block->type->toString() === self::FREEFORM)
        {
            return $inner;
        }

        $name = $block->type->toString();
        $name = str_starts_with($name, 'core/') ? substr($name, 5) : $name;
        $attributes = $block->settings;
        unset($attributes[self::CONTENT]);
        $metadata = is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [];
        unset($attributes['metadata']);

        foreach ($block->bindings as $attribute => $binding)
        {
            $metadata['bindings'][$attribute] = ['source' => $binding->source, 'args' => (object) $binding->arguments];
        }

        $metadata['blockId'] = $block->id;
        $attributes['metadata'] = $metadata;
        $json = $this->encode($attributes);

        if ($inner === '' && $block->children === [])
        {
            return sprintf('<!-- wp:%s %s /-->', $name, $json);
        }

        return sprintf('<!-- wp:%s %s -->%s<!-- /wp:%s -->', $name, $json, $inner, $name);
    }

    /**
     * Puts drawn children back into the gaps of a block's saved content.
     *
     * Children beyond the gaps, which is what a block added through the editor is, go just before the last
     * piece of content, which is where a block's closing markup is.
     *
     * @param list<string|null> $content
     * @param list<string> $children
     *
     * @since 0.1.0
     */
    private function inner(array $content, array $children): string
    {
        $pieces = [];

        foreach ($content as $piece)
        {
            if (is_string($piece))
            {
                $pieces[] = $piece;
            }
            elseif ($children !== [])
            {
                $pieces[] = array_shift($children);
            }
        }

        if ($children !== [])
        {
            $last = $pieces !== [] && is_string(end($content)) ? array_pop($pieces) : '';
            $pieces = [...$pieces, ...$children, $last];
        }

        return implode('', $pieces);
    }

    /**
     * Encodes attributes the way WordPress does, so they cannot end the comment they are written in.
     *
     * @param array<string, mixed> $attributes
     *
     * @since 0.1.0
     */
    private function encode(array $attributes): string
    {
        $json = (string) json_encode((object) $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Escapes are rewritten pair by pair: a backslash followed by a quote must be told apart from an escaped
        // backslash that happens to end a string, or the quote that closes the string is rewritten instead.
        $json = (string) preg_replace_callback(
            '/\\\\(.)/s',
            static fn (array $found): string => match ($found[1])
            {
                '"' => '\\u0022',
                '\\' => '\\u005c',
                default => $found[0],
            },
            $json,
        );

        return str_replace(['--', '<', '>', '&'], ['\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026'], $json);
    }
}
