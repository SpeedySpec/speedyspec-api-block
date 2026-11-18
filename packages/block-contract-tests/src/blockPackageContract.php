<?php

declare(strict_types=1);

namespace SpeedySpec\Block\ContractTests;

use Closure;
use RuntimeException;
use SpeedySpec\Block\App\AppServiceProvider;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockOutputRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTypeRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Contracts\SchemaValidatorInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Infra\Memory\MemoryServiceProvider;

/**
 * Registers the checks every block package must pass, so a block written once works under any host and builder.
 *
 * @param Closure $makePackage
 *   Builds a fresh BlockPackageInterface for each test.
 * @param string $composerJsonPath
 *   The path to the package's own composer.json.
 * @param list<Closure> $with
 *   Builders of other packages to load first, for parent block types the package under test does not define.
 *
 * @since 0.1.0
 */
function blockPackageContract(Closure $makePackage, string $composerJsonPath, array $with = []): void
{
    describe('BlockPackageInterface contract', function () use ($makePackage, $composerJsonPath, $with) {
        it('depends on nothing but the contracts package at runtime', function () use ($composerJsonPath) {
            expect(packageRuntimeRequirements($composerJsonPath))->toBe([]);
        });

        it('adds at least one block type', function () use ($makePackage, $with) {
            [, $types] = packageHost($makePackage, $with);

            expect($types)->not->toBe([]);
        });

        it('gives every block type catalogue details, an example and front-end output', function () use (
            $makePackage,
            $with,
        ) {
            [$container, $types] = packageHost($makePackage, $with);

            expect(packageShapeFindings($container, $types))->toBe([]);
        });

        it('resolves and draws every example in both modes without issues', function () use ($makePackage, $with) {
            [$container, $types] = packageHost($makePackage, $with);

            expect(packageExampleFindings($container, $types))->toBe([]);
        });

        it('escapes markup placed in a text setting', function () use ($makePackage, $with) {
            [$container, $types] = packageHost($makePackage, $with);

            expect(unescapedMarkupFindings($container, $types))->toBe([]);
        });

        it('keeps private settings out of front-end output', function () use ($makePackage, $with) {
            [$container, $types] = packageHost($makePackage, $with);

            expect(privateLeakFindings($container, $types))->toBe([]);
        });

        it('keeps admin-only values out of front-end output', function () use ($makePackage, $with) {
            [$container, $types] = packageHost($makePackage, $with);

            expect(adminLeakFindings($container, $types))->toBe([]);
        });
    });
}

/**
 * Lists the runtime requirements a block package declares beyond PHP, extensions and the contracts package.
 *
 * @return list<string>
 *   Disallowed requirement names; empty when the package is portable.
 *
 * @since 0.1.0
 */
function packageRuntimeRequirements(string $composerJsonPath): array
{
    $json = is_readable($composerJsonPath) ? file_get_contents($composerJsonPath) : false;
    $data = is_string($json) ? json_decode($json, true) : null;

    if (!is_array($data))
    {
        return [sprintf('unreadable composer.json at %s', $composerJsonPath)];
    }

    $disallowed = [];

    foreach (array_keys((array) ($data['require'] ?? [])) as $name)
    {
        $name = (string) $name;

        if ($name === 'php' || str_starts_with($name, 'ext-') || $name === 'speedyspec/block-domain')
        {
            continue;
        }

        $disallowed[] = $name;
    }

    return $disallowed;
}

/**
 * Builds a host with only the default implementations and loads the package into it, with no builder present.
 *
 * @param list<Closure> $with
 *
 * @return array{0: BlockServiceContainer, 1: list<BlockName>}
 *   The container, and the block types the package under test added.
 *
 * @since 0.1.0
 */
function packageHost(Closure $makePackage, array $with = []): array
{
    $container = new BlockServiceContainer();
    (new MemoryServiceProvider())->register($container);
    (new AppServiceProvider())->register($container);
    $loader = $container->get(BlockPackageLoaderInterface::class);

    foreach ($with as $makeOther)
    {
        $loader->load($makeOther());
    }

    $package = $makePackage();

    if (!$package instanceof BlockPackageInterface)
    {
        throw new RuntimeException('The package builder must return a BlockPackageInterface.');
    }

    $loader->load($package);

    return [$container, $loader->typesOf($package->name())];
}

/**
 * Builds a document holding one block of the type, placed inside the parents its type requires.
 *
 * @param array<string, mixed> $settings
 *
 * @throws RuntimeException
 *   When a required parent type is not registered or the nesting is deeper than five levels.
 *
 * @since 0.1.0
 */
function packageDocument(BlockServiceContainer $container, BlockType $type, array $settings): Document
{
    $types = $container->get(BlockTypeRegistryInterface::class);
    $block = new Block('subject', $type->name, $type->version, $settings);
    $current = $type;

    for ($level = 1; $level <= 5; $level++)
    {
        $parents = $current->parents->toArray();

        if ($parents === true)
        {
            return new Document('contract', 'page', new BlockTree($block));
        }

        $parentName = BlockName::fromString($parents[0]);
        $parent = $types->get($parentName);

        if ($parent === null)
        {
            throw new RuntimeException(sprintf(
                'Block type "%s" must sit inside "%s", which is not registered; pass its package in $with.',
                $current->name->toString(),
                $parentName->toString(),
            ));
        }

        $block = new Block(
            'parent' . $level,
            $parent->name,
            $parent->version,
            $parent->examples[0] ?? [],
            [],
            [],
            [$block],
        );
        $current = $parent;
    }

    throw new RuntimeException(
        sprintf('Block type "%s" needs more than five levels of parents.', $type->name->toString())
    );
}

/**
 * Checks that every block type can be listed, demonstrated and drawn.
 *
 * @param list<BlockName> $names
 *
 * @return list<string>
 *   One sentence per defect.
 *
 * @since 0.1.0
 */
function packageShapeFindings(BlockServiceContainer $container, array $names): array
{
    $types = $container->get(BlockTypeRegistryInterface::class);
    $outputs = $container->get(BlockOutputRegistryInterface::class);
    $findings = [];

    foreach ($names as $name)
    {
        $type = $types->get($name);
        $label = $name->toString();

        if ($type === null)
        {
            $findings[] = sprintf('%s is not registered.', $label);

            continue;
        }

        if (trim($type->catalogue->title) === '')
        {
            $findings[] = sprintf('%s has no catalogue title.', $label);
        }

        if ($type->examples === [])
        {
            $findings[] = sprintf('%s has no example.', $label);
        }

        if (!$outputs->has($name, Mode::Front))
        {
            $findings[] = sprintf('%s has no front-end html output.', $label);
        }
    }

    return $findings;
}

/**
 * Resolves and draws every example of every block type in both modes.
 *
 * @param list<BlockName> $names
 *
 * @return list<string>
 *   One sentence per example that raised an issue.
 *
 * @since 0.1.0
 */
function packageExampleFindings(BlockServiceContainer $container, array $names): array
{
    $types = $container->get(BlockTypeRegistryInterface::class);
    $renderer = $container->get(DocumentRendererInterface::class);
    $findings = [];

    foreach ($names as $name)
    {
        $type = $types->get($name);

        foreach ($type?->examples ?? [] as $index => $example)
        {
            foreach (Mode::cases() as $mode)
            {
                $result = $renderer->render(packageDocument($container, $type, $example), new RenderRequest($mode));

                foreach ($result->issues as $issue)
                {
                    $findings[] = sprintf(
                        '%s example %d (%s): %s on block "%s": %s',
                        $name->toString(),
                        $index,
                        $mode->value,
                        $issue->kind->value,
                        $issue->blockId,
                        $issue->message,
                    );
                }
            }
        }
    }

    return $findings;
}

/**
 * Places markup in each free-text setting and checks it does not come out as markup.
 *
 * @param list<BlockName> $names
 *
 * @return list<string>
 *   One sentence per setting whose value reached the output unescaped.
 *
 * @since 0.1.0
 */
function unescapedMarkupFindings(BlockServiceContainer $container, array $names): array
{
    $markup = '<script>alert("x")</script>';
    $findings = [];

    foreach (packageStringSettings($container, $names, false) as [$type, $setting])
    {
        $output = packageFrontOutput($container, $type, $setting, $markup);

        if ($output !== null && str_contains($output, '<script>alert('))
        {
            $findings[] = sprintf('%s draws its "%s" setting without escaping it.', $type->name->toString(), $setting);
        }
    }

    return $findings;
}

/**
 * Places a marker in each private text setting and checks it never reaches a visitor.
 *
 * @param list<BlockName> $names
 *
 * @return list<string>
 *   One sentence per private setting found in front-end output.
 *
 * @since 0.1.0
 */
function privateLeakFindings(BlockServiceContainer $container, array $names): array
{
    $findings = [];

    foreach (packageStringSettings($container, $names, true) as [$type, $setting])
    {
        $marker = 'private-marker-' . bin2hex(random_bytes(6));
        $output = packageFrontOutput($container, $type, $setting, $marker);

        if ($output !== null && str_contains($output, $marker))
        {
            $findings[] = sprintf(
                '%s draws its private "%s" setting in front-end output.',
                $type->name->toString(),
                $setting,
            );
        }
    }

    return $findings;
}

/**
 * Checks that values a block offers only to editors are absent from what a visitor receives.
 *
 * A value that is also part of the block's front-end view is public and is not counted.
 *
 * @param list<BlockName> $names
 *
 * @return list<string>
 *   One sentence per admin-only value found in front-end output.
 *
 * @since 0.1.0
 */
function adminLeakFindings(BlockServiceContainer $container, array $names): array
{
    $types = $container->get(BlockTypeRegistryInterface::class);
    $resolver = $container->get(DocumentResolverInterface::class);
    $renderer = $container->get(DocumentRendererInterface::class);
    $findings = [];

    foreach ($names as $name)
    {
        $type = $types->get($name);

        if ($type === null || $type->examples === [])
        {
            continue;
        }

        $document = packageDocument($container, $type, $type->examples[0]);
        $admin = $resolver->resolve($document, Mode::Admin)->find('subject');
        $front = $renderer->render($document, new RenderRequest(Mode::Front));
        $public = packageStrings($front->resolved->find('subject')?->view?->values ?? []);

        foreach (packageStrings($admin?->admin?->values ?? []) as $value)
        {
            if (strlen($value) >= 8 && !in_array($value, $public, true) && str_contains($front->output, $value))
            {
                $findings[] = sprintf('%s draws an admin-only value in front-end output.', $name->toString());
            }
        }
    }

    return $findings;
}

/**
 * Lists the top-level free-text settings of the given block types.
 *
 * @param list<BlockName> $names
 * @param bool $private
 *   True to list only private settings, false to list every free-text setting.
 *
 * @return list<array{0: BlockType, 1: string}>
 *   Pairs of block type and setting name.
 *
 * @since 0.1.0
 */
function packageStringSettings(BlockServiceContainer $container, array $names, bool $private): array
{
    $types = $container->get(BlockTypeRegistryInterface::class);
    $validator = $container->get(SchemaValidatorInterface::class);
    $pairs = [];

    foreach ($names as $name)
    {
        $type = $types->get($name);

        if ($type === null || $type->examples === [])
        {
            continue;
        }

        $privateNames = $validator->privateProperties($type->settingsSchema);

        foreach ((array) ($type->settingsSchema['properties'] ?? []) as $setting => $schema)
        {
            $setting = (string) $setting;

            if (
                !is_array($schema)
                || ($schema['type'] ?? null) !== 'string'
                || isset($schema['enum'])
                || array_key_exists('const', $schema)
                || isset($schema['pattern'])
                || ($private && !in_array($setting, $privateNames, true))
            )
            {
                continue;
            }

            $pairs[] = [$type, $setting];
        }
    }

    return $pairs;
}

/**
 * Draws the type's first example for a visitor with one setting replaced.
 *
 * @return string|null
 *   Null when the replaced value no longer fits the settings schema, so the case does not apply.
 *
 * @since 0.1.0
 */
function packageFrontOutput(BlockServiceContainer $container, BlockType $type, string $setting, string $value): ?string
{
    $validator = $container->get(SchemaValidatorInterface::class);
    $settings = $type->examples[0];
    $settings[$setting] = $value;

    $complete = $validator->withDefaults($settings, $type->settingsSchema);

    if (!$validator->validate($complete, $type->settingsSchema)->isValid())
    {
        return null;
    }

    $document = packageDocument($container, $type, $settings);

    return $container->get(DocumentRendererInterface::class)->render($document, new RenderRequest(Mode::Front))->output;
}

/**
 * Collects every string found anywhere inside a value.
 *
 * @return list<string>
 *
 * @since 0.1.0
 */
function packageStrings(mixed $value): array
{
    if (is_string($value))
    {
        return [$value];
    }

    if (!is_array($value))
    {
        return [];
    }

    $strings = [];

    foreach ($value as $item)
    {
        $strings = [...$strings, ...packageStrings($item)];
    }

    return $strings;
}
