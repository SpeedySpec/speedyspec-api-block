# Serialization

`JsonDocumentSerializer` stores a document as JSON. `ClientExport` makes the copy of a document that may leave the
server.

## JSON shape

```json
{"format": 1, "id": "doc-1", "kind": "page", "blocks": [
  {"id": "a", "type": "acme/box", "version": 1, "settings": {}, "extensions": {}, "bindings":
    {"label": {"source": "acme/values", "arguments": {"key": "x"}, "fallback": "y"}}, "children": []}
]}
```

- Keys appear in the order shown. Output uses `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION`, so `1.0` stays a float
  through a round trip.
- `settings`, `extensions`, `bindings` and a binding's `arguments` are always JSON objects, even when empty (`{}`).
  `children` and `blocks` are always JSON arrays (`[]`).
- `fallback` is present only when the binding has one; a `null` fallback is written as `"fallback":null`.
- Arrays nested inside settings keep their natural form: a PHP list is a JSON array, an associative array a JSON
  object. An empty array nested inside settings is ambiguous and is written as `[]`, so an empty nested object
  `{}` comes back as `[]` after a load and save.

## What a load and save does not keep

Only the top-level maps (`settings`, `extensions`, `bindings`, a binding's `arguments`) are told apart from lists
on the way out. Below them PHP arrays carry no object-or-list flag, so:

- A nested object whose keys are exactly `0` to `n-1` in order, in settings, binding arguments, a fallback or an
  extension value, re-encodes as a JSON array: `{"m": {"0": "a", "1": "b"}}` comes back as `{"m": ["a", "b"]}`.
- A fallback of `{}` comes back as `[]`.
- An integer beyond 64 bits is decoded as a float and written back as one, losing precision.
- Unknown top-level keys and unknown block keys are dropped on save; only the keys shown above are written.
- `serialize()` writes format 1 only and throws `InvalidDocumentException` for a document whose `formatVersion` is
  anything else, since `parse()` would refuse what it wrote.

## Parsing

`parse()` needs no registry and keeps blocks of unregistered types unchanged. `format` must be `1`. `version`
defaults to 1; `settings`, `extensions`, `bindings` and `children` default to empty when absent. Objects and lists
are told apart on the raw JSON, so a setting named `0` is an object key, not a list. Every failure
(invalid JSON, wrong structure, invalid block name or binding path, duplicate ids, a binding without a string
`source`, non-object `settings`) throws `InvalidDocumentException`.

## Client export

`Mode::Admin` returns the same document. `Mode::Front` removes each top-level setting marked `x-private` in the
block type's settings schema, together with every binding that targets one. A block whose type is not registered
loses all of its settings and all of its bindings, because nothing says which are safe; its extensions and
children are kept, and children are cleaned by their own type. A block whose `version` differs from its registered
type's version, older or newer, is treated the same way: its settings have not been migrated, so the current
schema cannot say which of them are private. The export does not migrate.

## Strictness

- `settings`, `extensions`, `bindings`, `children` and a binding's `arguments` may be absent (they default to
  empty); when present they must be the right kind: `null`, a scalar, or a list where an object is required (and
  an object where `children` or `blocks` need an array) is rejected.
- Each extension key must be a valid decorator namespace (lower-case `vendor/name`).
- A non-finite number (for example `1e400`) is rejected on parse; `serialize()` throws `InvalidDocumentException`
  for one placed in settings by code.
- JSON is read and written to a depth of 2048 levels. Each nested block costs two levels (object and `children`
  array) plus the levels of its own settings, so roughly 1000 nested blocks; beyond that `parse()` and `serialize()`
  throw `InvalidDocumentException`.
