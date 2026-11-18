<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Serialization\JsonDocumentSerializer;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\InvalidDocumentException;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;

use function SpeedySpec\Block\ContractTests\documentSerializerContract;

describe('JsonDocumentSerializer', function () {
    documentSerializerContract(fn () => new JsonDocumentSerializer());
});

test('an unknown block type survives a load and save unchanged', function () {
    $json = '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"gone/away","version":3,'
        . '"settings":{"secret":"s","n":1},"extensions":{},"bindings":{},"children":[]}]}';
    $s = new JsonDocumentSerializer();
    expect($s->serialize($s->parse($json)))->toBe($json);
});

test('parse rejects documents it cannot trust', function (string $json) {
    expect(fn () => (new JsonDocumentSerializer())->parse($json))->toThrow(InvalidDocumentException::class);
})->with([
    '{', '[]', '{"format":2,"id":"d","kind":"page","blocks":[]}', '{"format":1,"kind":"page","blocks":[]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"type":"acme/x"}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"Bad Name"}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x"},{"id":"a","type":"acme/x"}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","settings":"nope"}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","settings":[1,2]}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","bindings":{"t":{"arguments":{}}}}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","bindings":{"bad path":{"source":"s"}}}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","children":[{"id":"a","type":"acme/x"}]}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","version":"1"}]}',
    '{"format":1,"id":"d","kind":"page","blocks":{"a":1}}',
    '{"format":1,"id":"d","kind":"page","blocks":["x"]}',
    '{"format":1,"id":"","kind":"page","blocks":[]}',
    '',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","settings":null}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","extensions":[]}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","extensions":null}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","extensions":{"acme/v":[1]}}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","extensions":{"Bad":{}}}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","bindings":[]}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","bindings":null}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","children":null}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","children":{}}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","bindings":{"t":{"source":"s","arguments":[1,2]}}}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","bindings":{"t":{"source":"s","arguments":null}}}]}',
    '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","settings":{"n":1e400}}]}',
]);

test('empty maps encode as objects and empty child lists as arrays', function () {
    $doc = new Document('d', 'page', new BlockTree(new Block('a', BlockName::fromString('acme/x'))));
    $json = (new JsonDocumentSerializer())->serialize($doc);

    expect($json)->toBe('{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","version":1,'
        . '"settings":{},"extensions":{},"bindings":{},"children":[]}]}');
});

test('empty documents encode blocks as an array', function () {
    expect((new JsonDocumentSerializer())->serialize(new Document('d', 'page', new BlockTree())))
        ->toBe('{"format":1,"id":"d","kind":"page","blocks":[]}');
});

test('bindings encode empty arguments as an object and a fallback only when set', function () {
    $b = block('a', 'acme/x')
        ->withBinding(SettingPath::fromString('p'), Binding::to('s'))
        ->withBinding(SettingPath::fromString('q'), Binding::to('s', ['k' => 1])->withFallback(null));
    $json = (new JsonDocumentSerializer())->serialize(new Document('d', 'page', new BlockTree($b)));

    expect($json)->toContain('"p":{"source":"s","arguments":{}}')
        ->and($json)->toContain('"q":{"source":"s","arguments":{"k":1},"fallback":null}');

    $copy = (new JsonDocumentSerializer())->parse($json)->tree->find('a');
    expect($copy->bindings['p']->hasFallback)->toBeFalse()
        ->and($copy->bindings['q']->hasFallback)->toBeTrue()
        ->and($copy->bindings['q']->fallback)->toBeNull();
});

test('unicode and slashes are not escaped', function () {
    $b = block('a', 'acme/x', ['url' => 'https://example.test/a/b', 'text' => 'héllo ✓']);
    $json = (new JsonDocumentSerializer())->serialize(new Document('d', 'page', new BlockTree($b)));

    expect($json)->toContain('"url":"https://example.test/a/b"')->and($json)->toContain('"text":"héllo ✓"');
});

test('nested lists stay arrays and nested maps stay objects', function () {
    $b = block('a', 'acme/x', ['list' => [1, 2], 'map' => ['k' => 'v']]);
    $s = new JsonDocumentSerializer();
    $json = $s->serialize(new Document('d', 'page', new BlockTree($b)));

    expect($json)->toContain('"list":[1,2]')->and($json)->toContain('"map":{"k":"v"}')
        ->and($s->parse($json)->tree->find('a')->settings)->toBe(['list' => [1, 2], 'map' => ['k' => 'v']]);
});

test('a deeply nested tree round-trips', function () {
    $node = block('n20', 'acme/x');

    for ($i = 19; $i >= 1; $i--)
    {
        $node = block('n' . $i, 'acme/x', [], $node);
    }

    $s = new JsonDocumentSerializer();
    $copy = $s->parse($s->serialize(new Document('d', 'page', new BlockTree($node))));

    expect($copy->tree->ids())->toHaveCount(20)->and($copy->tree->find('n20'))->not->toBeNull();
});

test('parse defaults a missing version and optional sections', function () {
    $doc = (new JsonDocumentSerializer())->parse('{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x"}]}');
    $a = $doc->tree->find('a');

    expect($a->typeVersion)->toBe(1)->and($a->settings)->toBe([])->and($a->extensions)->toBe([])
        ->and($a->bindings)->toBe([])->and($a->children)->toBe([]);
});

test('integer-like setting keys and binding paths round-trip', function () {
    $b = block('a', 'acme/x', [0 => 'x', 1 => 'y'])
        ->withBinding(SettingPath::fromString('5'), Binding::to('s', [0 => 'arg']));
    $s = new JsonDocumentSerializer();
    $json = $s->serialize(new Document('d', 'page', new BlockTree($b)));
    $copy = $s->parse($json)->tree->find('a');

    expect($json)->toContain('"settings":{"0":"x","1":"y"}')
        ->and($copy->settings)->toBe([0 => 'x', 1 => 'y'])
        ->and($copy->bindings['5']->arguments)->toBe([0 => 'arg'])
        ->and($s->serialize($s->parse($json)))->toBe($json);
});

test('nested settings mixing lists and objects round-trip', function () {
    $json = '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","version":1,'
        . '"settings":{"a":[{"k":[1,{"z":2}]},[],"s"],"b":{"1":[{"2":1}]}},"extensions":{},"bindings":{},"children":[]}]}';
    $s = new JsonDocumentSerializer();

    expect($s->serialize($s->parse($json)))->toBe($json);
});

test('floats keep their fraction through a round trip', function () {
    $json = '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","version":1,'
        . '"settings":{"n":1.0,"m":1.5},"extensions":{},"bindings":{},"children":[]}]}';
    $s = new JsonDocumentSerializer();

    expect($s->serialize($s->parse($json)))->toBe($json);
});

test('serialize wraps encoding failures in InvalidDocumentException', function () {
    $doc = new Document('d', 'page', new BlockTree(block('a', 'acme/x', ['n' => INF])));

    expect(fn () => (new JsonDocumentSerializer())->serialize($doc))->toThrow(InvalidDocumentException::class);
});

test('an extension with a valid namespace and a binding with a fallback object parse', function () {
    $json = '{"format":1,"id":"d","kind":"page","blocks":[{"id":"a","type":"acme/x","version":1,"settings":{},'
        . '"extensions":{"acme/vis":{"hide":true}},"bindings":{"t":{"source":"s","arguments":{},"fallback":{"k":1}}},'
        . '"children":[]}]}';
    $s = new JsonDocumentSerializer();

    expect($s->serialize($s->parse($json)))->toBe($json);
});
