<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Building\PlacementRules;
use SpeedySpec\Block\App\Registries\BlockTypeRegistry;
use SpeedySpec\Block\App\Schema\SubsetSchemaValidator;
use SpeedySpec\Block\Domain\Contracts\PlacementRulesInterface;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockType;
use SpeedySpec\Block\Domain\ValueObjects\IssueKind;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\PlacementDecision;
use SpeedySpec\Block\Domain\ValueObjects\PlacementRefusal;

if (!function_exists('rules'))
{
    function rules(BlockType ...$types): PlacementRules
    {
        $registry = new BlockTypeRegistry(new SubsetSchemaValidator());

        foreach ($types as $type)
        {
            $registry->add($type);
        }

        return new PlacementRules($registry);
    }
}

if (!function_exists('formTypes'))
{
    function formTypes(): array
    {
        return [
            type('acme/form', ['children' => ['acme/field']]),
            type('acme/field', ['parents' => ['acme/form']]),
            type('acme/box', ['children' => true]),
            type('acme/text'),
        ];
    }
}

test('a parent decides which children it takes and a child which parents it accepts', function () {
    $rules = rules(...formTypes());
    $n = fn (string $name) => BlockName::fromString($name);

    expect($rules->decide($n('acme/field'), $n('acme/form'))->allowed)->toBeTrue()
        ->and($rules->decide($n('acme/text'), $n('acme/form'))->reason)->toBe(PlacementRefusal::ParentRefusesChild)
        ->and($rules->decide($n('acme/field'), $n('acme/box'))->reason)->toBe(PlacementRefusal::ChildRefusesParent)
        ->and($rules->decide($n('acme/field'), null)->reason)->toBe(PlacementRefusal::ChildRefusesRoot)
        ->and($rules->decide($n('acme/text'), null)->allowed)->toBeTrue()
        ->and($rules->decide($n('acme/text'), $n('acme/text'))->reason)->toBe(PlacementRefusal::ParentRefusesChild);
});

test('a refusal carries a message and an approval carries none', function () {
    $rules = rules(...formTypes());
    $refused = $rules->decide(BlockName::fromString('acme/text'), BlockName::fromString('acme/form'));
    $allowed = $rules->decide(BlockName::fromString('acme/text'), null);

    expect($refused->allowed)->toBeFalse()->and($refused->message)->toContain('acme/text')
        ->and($allowed->reason)->toBeNull()->and($allowed->message)->toBeNull();
});

test('an unregistered type is not a placement problem', function () {
    $rules = rules(type('acme/box', ['children' => true]), type('acme/form', ['children' => ['acme/field']]));
    $n = fn (string $name) => BlockName::fromString($name);

    expect($rules->decide($n('gone/away'), $n('acme/box'))->allowed)->toBeTrue()
        ->and($rules->decide($n('gone/away'), null)->allowed)->toBeTrue()
        ->and($rules->decide($n('acme/box'), $n('gone/away'))->allowed)->toBeTrue()
        ->and($rules->decide($n('gone/away'), $n('acme/form'))->reason)->toBe(PlacementRefusal::ParentRefusesChild);
});

test('allowedChildren lists what may go inside a parent or at the root, in registration order', function () {
    $rules = rules(...formTypes());
    $names = fn (array $list) => array_map(fn (BlockName $n) => $n->toString(), $list);

    expect($names($rules->allowedChildren(BlockName::fromString('acme/form'))))->toBe(['acme/field'])
        ->and($names($rules->allowedChildren(BlockName::fromString('acme/box'))))->toBe(['acme/form', 'acme/box', 'acme/text'])
        ->and($names($rules->allowedChildren(null)))->toBe(['acme/form', 'acme/box', 'acme/text'])
        ->and($names($rules->allowedChildren(BlockName::fromString('acme/text'))))->toBe([]);
});

test('the resolver takes its placement decisions from the placement contract', function () {
    [$resolver] = resolver(function ($c) {
        types($c)->add(type('acme/box', ['children' => true]));
        $c->add(PlacementRulesInterface::class, fn () => new class implements PlacementRulesInterface {
            public function decide(BlockName $child, ?BlockName $parent): PlacementDecision
            {
                return $parent === null
                    ? PlacementDecision::allowed()
                    : PlacementDecision::refused(PlacementRefusal::ParentRefusesChild, 'no children today');
            }

            public function allowedChildren(?BlockName $parent): array
            {
                return [];
            }
        });
    });
    $result = $resolver->resolve(doc(block('a', 'acme/box', [], block('b', 'acme/box'))), Mode::Front);

    expect($result->find('b'))->toBeNull()->and(idsAndKinds($result->issues))->toBe([['b', IssueKind::DisallowedChild]]);
});
