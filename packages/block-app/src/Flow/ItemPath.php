<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Flow;

/**
 * Reads a field of the current item by a dotted path, the one syntax bindings and conditions share.
 *
 * @since 0.1.0
 */
class ItemPath
{
    /**
     * Follows a dotted path such as `address.city` through nested maps and lists.
     *
     * @param string $path
     *   An empty path names the item itself.
     *
     * @return array{0: bool, 1: mixed}
     *   Whether the path led to a value, and the value; telling "absent" from a stored null needs both.
     *
     * @since 0.1.0
     */
    public static function read(mixed $item, string $path): array
    {
        if ($path === '')
        {
            return [true, $item];
        }

        foreach (explode('.', $path) as $segment)
        {
            if (!is_array($item) || !array_key_exists($segment, $item))
            {
                return [false, null];
            }

            $item = $item[$segment];
        }

        return [true, $item];
    }
}
