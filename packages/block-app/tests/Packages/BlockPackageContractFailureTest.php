<?php

declare(strict_types=1);

use SpeedySpec\Block\Domain\Contracts\BlockBehaviourInterface;
use SpeedySpec\Block\Domain\Contracts\BlockOutputInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageRegistrarInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\ValueObjects\AdminContext;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\BlockView;
use SpeedySpec\Block\Domain\ValueObjects\Context;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\RenderInput;

use function SpeedySpec\Block\ContractTests\adminLeakFindings;
use function SpeedySpec\Block\ContractTests\packageExampleFindings;
use function SpeedySpec\Block\ContractTests\packageHost;
use function SpeedySpec\Block\ContractTests\packageRuntimeRequirements;
use function SpeedySpec\Block\ContractTests\packageShapeFindings;
use function SpeedySpec\Block\ContractTests\packageTitleFindings;
use function SpeedySpec\Block\ContractTests\privateLeakFindings;
use function SpeedySpec\Block\ContractTests\privateViewFindings;
use function SpeedySpec\Block\ContractTests\unescapedMarkupFindings;

if (!function_exists('badPackage'))
{
    /**
     * Builds a package that breaks every rule the shared suite checks, to prove the suite catches each one.
     */
    function badPackage(): BlockPackageInterface
    {
        return new class implements BlockPackageInterface {
            public function name(): string
            {
                return 'acme/bad-blocks';
            }

            public function title(): string
            {
                return 'Broken blocks';
            }

            public function description(): string
            {
                return '';
            }

            public function register(BlockPackageRegistrarInterface $registrar): void
            {
                $leaky = BlockName::fromString('acme/leaky');
                $registrar->type(type('acme/leaky', [
                    'settings' => ['type' => 'object', 'properties' => [
                        'text' => ['type' => 'string', 'default' => ''],
                        'secret' => ['type' => 'string', 'default' => '', 'x-private' => true],
                    ]],
                    'examples' => [['text' => 'hello', 'secret' => 's']],
                ]));
                $registrar->behaviour($leaky, new class implements BlockBehaviourInterface {
                    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
                    {
                        return new BlockView($settings, admin: new AdminContext(['hint' => 'editors-only-hint']));
                    }
                });
                $registrar->output($leaky, new class implements BlockOutputInterface {
                    public function render(RenderInput $input): string
                    {
                        return '<p>' . $input->view->values['text'] . $input->view->values['secret'] . 'editors-only-hint</p>';
                    }
                });

                $registrar->type(type('acme/bare', ['title' => ' ']));

                $registrar->type(type('acme/orphan', ['parents' => ['acme/leaky'], 'examples' => [[]]]));
                $registrar->output(BlockName::fromString('acme/orphan'), fixedOutput('x'));
            }
        };
    }
}

test('the suite reports runtime requirements beyond the contracts package', function () {
    expect(packageRuntimeRequirements(__DIR__ . '/../Fixtures/bad-package-composer.json'))
        ->toBe(['speedyspec/block-app', 'acme/some-builder'])
        ->and(packageRuntimeRequirements(__DIR__ . '/no-such-file.json'))->toHaveCount(1);
});

test('the suite reports a block type with no title, example or front-end output', function () {
    [$container, $types] = packageHost(fn () => badPackage());
    $findings = implode("\n", packageShapeFindings($container, $types));

    expect($findings)->toContain('acme/bare has no catalogue title.')
        ->toContain('acme/bare has no example.')
        ->toContain('acme/bare has no front-end html output.')
        ->not->toContain('acme/leaky');
});

test('the suite reports a setting drawn without escaping', function () {
    [$container, $types] = packageHost(fn () => badPackage());

    expect(unescapedMarkupFindings($container, $types))
        ->toContain('acme/leaky draws its "text" setting without escaping it in front output.');
});

test('the suite reports a private setting drawn for a visitor', function () {
    [$container, $types] = packageHost(fn () => badPackage());

    expect(privateLeakFindings($container, $types))->toBe(['acme/leaky draws its private "secret" setting in front-end output.']);
});

test('the suite reports an admin-only value drawn for a visitor', function () {
    [$container, $types] = packageHost(fn () => badPackage());

    expect(adminLeakFindings($container, $types))->toContain('acme/leaky draws an admin-only value in front-end output.');
});

test('the suite reports an example that raises an issue', function () {
    [$container, $types] = packageHost(fn () => badPackage());
    $findings = implode("\n", packageExampleFindings($container, $types));

    expect($findings)->toContain('acme/orphan example 0 (front): disallowed-child');
});

if (!function_exists('sneakyPackage'))
{
    /**
     * Builds a package whose defects are subtler than raw markup in a front-end text setting.
     */
    function sneakyPackage(): BlockPackageInterface
    {
        return new class implements BlockPackageInterface {
            public function name(): string
            {
                return 'acme/sneaky-blocks';
            }

            public function title(): string
            {
                return 'Broken blocks';
            }

            public function description(): string
            {
                return '';
            }

            public function register(BlockPackageRegistrarInterface $registrar): void
            {
                $name = BlockName::fromString('acme/sneaky');
                $registrar->type(type('acme/sneaky', [
                    'settings' => ['type' => 'object', 'properties' => [
                        'text' => ['type' => 'string', 'default' => ''],
                        'tip' => ['type' => 'string', 'default' => ''],
                        'link' => ['type' => 'string', 'default' => ''],
                        'note' => ['type' => ['string', 'null'], 'default' => null],
                        'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'default' => []],
                        'style' => ['type' => 'object', 'properties' => ['caption' => ['type' => 'string']], 'default' => []],
                        'pin' => ['type' => 'integer', 'default' => 0, 'x-private' => true],
                        'recipients' => [
                            'type' => 'array', 'items' => ['type' => 'string'], 'default' => [], 'x-private' => true,
                        ],
                    ]],
                    'examples' => [['text' => 'hello']],
                ]));
                $registrar->behaviour($name, new class implements BlockBehaviourInterface {
                    public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
                    {
                        return new BlockView($settings, admin: new AdminContext(['hint' => 'Tom & Jerry <admin>']));
                    }
                });
                $registrar->output($name, new class implements BlockOutputInterface {
                    public function render(RenderInput $input): string
                    {
                        $v = $input->view->values;
                        $e = $input->escaper;

                        return '<p title=\'' . strip_tags((string) $v['tip']) . '\'>' . $e->text((string) $v['text']) . '</p>'
                            . '<a href="' . $e->attribute((string) $v['link']) . '">x</a>'
                            . '<i>' . (string) $v['note'] . '</i>'
                            . '<b>' . implode(',', $v['tags']) . '</b>'
                            . '<u>' . (string) ($v['style']['caption'] ?? '') . '</u>'
                            . '<s>' . $v['pin'] . implode(',', $v['recipients']) . '</s>'
                            . '<em>' . $e->text('Tom & Jerry <admin>') . '</em>';
                    }
                });
                $registrar->output($name, new class implements BlockOutputInterface {
                    public function render(RenderInput $input): string
                    {
                        return '<p>' . $input->view->values['text'] . '</p>';
                    }
                }, Mode::Admin);
            }
        };
    }
}

test('the suite reports markup let through in the admin output only', function () {
    [$container, $types] = packageHost(fn () => sneakyPackage());

    expect(unescapedMarkupFindings($container, $types))
        ->toContain('acme/sneaky draws its "text" setting without escaping it in admin output.')
        ->not->toContain('acme/sneaky draws its "text" setting without escaping it in front output.');
});

test('the suite reports a quote that breaks out of an attribute', function () {
    [$container, $types] = packageHost(fn () => sneakyPackage());

    expect(unescapedMarkupFindings($container, $types))
        ->toContain('acme/sneaky lets a quote in its "tip" setting through unescaped in front output.');
});

test('the suite reports a setting written into a link without a scheme check', function () {
    [$container, $types] = packageHost(fn () => sneakyPackage());

    expect(unescapedMarkupFindings($container, $types))
        ->toContain('acme/sneaky writes its "link" setting into a link without checking the scheme in front output.');
});

test('the suite reports nullable, listed and nested text settings drawn without escaping', function () {
    [$container, $types] = packageHost(fn () => sneakyPackage());
    $findings = unescapedMarkupFindings($container, $types);

    expect($findings)->toContain('acme/sneaky draws its "note" setting without escaping it in front output.')
        ->toContain('acme/sneaky draws its "tags" setting without escaping it in front output.')
        ->toContain('acme/sneaky draws its "style.caption" setting without escaping it in front output.');
});

test('the suite reports private settings that are not text', function () {
    [$container, $types] = packageHost(fn () => sneakyPackage());

    expect(privateLeakFindings($container, $types))->toBe([
        'acme/sneaky draws its private "pin" setting in front-end output.',
        'acme/sneaky draws its private "recipients" setting in front-end output.',
    ]);
});

test('the suite reports an admin-only value drawn for a visitor even when it is escaped', function () {
    [$container, $types] = packageHost(fn () => sneakyPackage());

    expect(adminLeakFindings($container, $types))->toBe(['acme/sneaky draws an admin-only value in front-end output.']);
});

if (!function_exists('typesOnlyPackage'))
{
    /**
     * Builds a definitions package: two block types, one with a private setting, and no outputs.
     */
    function typesOnlyPackage(): BlockPackageInterface
    {
        return package('acme/tab-types', function (BlockPackageRegistrarInterface $r) {
            $r->type(type('acme/tabs', ['children' => true, 'examples' => [[]], 'title' => 'Tabs']));
            $r->type(type('acme/tab', ['examples' => [['label' => 'One']], 'title' => 'Tab', 'settings' => [
                'type' => 'object',
                'properties' => [
                    'label' => ['type' => 'string', 'default' => ''],
                    'secret' => ['type' => 'string', 'default' => '', 'x-private' => true],
                ],
            ]]));
        }, 'Tabs');
    }
}

if (!function_exists('tabOutputsPackage'))
{
    /**
     * Builds an outputs package for the tab types, drawing only the ones it is given.
     */
    function tabOutputsPackage(array $names): BlockPackageInterface
    {
        return package('acme/tab-html', function (BlockPackageRegistrarInterface $r) use ($names) {
            foreach ($names as $name)
            {
                $r->output(BlockName::fromString($name), new class implements BlockOutputInterface {
                    public function render(RenderInput $input): string
                    {
                        return '<div>' . $input->escaper->text((string) ($input->view->values['label'] ?? '')) . '</div>';
                    }
                });
            }
        }, 'Tabs, drawn');
    }
}

test('a definitions package passes the definitions checks and fails the complete ones', function () {
    [$container, $types] = packageHost(fn () => typesOnlyPackage());

    expect(packageShapeFindings($container, $types, false))->toBe([])
        ->and(packageExampleFindings($container, $types, false))->toBe([])
        ->and(privateViewFindings($container, $types))->toBe([])
        ->and(packageShapeFindings($container, $types))->toBe([
            'acme/tabs has no front-end html output.',
            'acme/tab has no front-end html output.',
        ]);
});

test('the definitions checks report a private setting left in the front view', function () {
    [$container, $types] = packageHost(fn () => package('acme/leaky', function (BlockPackageRegistrarInterface $r) {
        $r->type(type('acme/leaky', ['examples' => [[]], 'settings' => [
            'type' => 'object',
            'properties' => ['secret' => ['type' => 'string', 'default' => 'x', 'x-private' => true]],
        ]]));
        $r->behaviour(BlockName::fromString('acme/leaky'), new class implements BlockBehaviourInterface {
            public function view(Block $block, BlockType $type, array $settings, Context $context, Mode $mode): BlockView
            {
                return new BlockView($settings);
            }
        });
    }));

    expect(privateViewFindings($container, $types))->toBe(['acme/leaky keeps its private "secret" setting in its front view.']);
});

test('an outputs package is checked against the types it draws, and missing one is named', function () {
    [$container, $own, $drawn] = packageHost(fn () => tabOutputsPackage(['acme/tabs']), [fn () => typesOnlyPackage()]);
    [$complete, , $all] = packageHost(fn () => tabOutputsPackage(['acme/tabs', 'acme/tab']), [fn () => typesOnlyPackage()]);

    expect($own)->toBe([])
        ->and(packageShapeFindings($container, $drawn))->toBe(['acme/tab has no front-end html output.'])
        ->and(packageShapeFindings($complete, $all))->toBe([])
        ->and(packageExampleFindings($complete, $all))->toBe([])
        ->and(unescapedMarkupFindings($complete, $all))->toBe([])
        ->and(privateLeakFindings($complete, $all))->toBe([]);
});

test('a package may depend on definitions packages, but not on implementations', function () {
    $file = function (array $require): string {
        $path = sys_get_temp_dir() . '/block-suite-' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($path, json_encode(['require' => $require]));

        return $path;
    };
    $domain = $file(['php' => '>=8.4', 'speedyspec/block-domain' => '^0.1', 'speedyspec/block-structure-domain' => '^0.1']);
    $app = $file(['speedyspec/block-domain' => '^0.1', 'speedyspec/block-app' => '^0.1', 'acme/domain' => '*']);

    expect(packageRuntimeRequirements($domain))->toBe([])
        ->and(packageRuntimeRequirements($app))->toBe(['speedyspec/block-app', 'acme/domain']);

    unlink($domain);
    unlink($app);
});

test('a package must have a title', function () {
    expect(packageTitleFindings(package('acme/x', fn () => null, ' ')))->toBe(['acme/x has no title.'])
        ->and(packageTitleFindings(package('acme/x', fn () => null, 'X')))->toBe([]);
});

