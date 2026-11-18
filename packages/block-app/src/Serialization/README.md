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

- Keys appear in the order shown. Output uses `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
- `settings`, `extensions`, `bindings` and a binding's `arguments` are always JSON objects, even when empty (`{}`).
  `children` and `blocks` are always JSON arrays (`[]`).
- `fallback` is present only when the binding has one; a `null` fallback is written as `"fallback":null`.
- Arrays nested inside settings keep their natural form: a PHP list is a JSON array, an associative array a JSON
  object. An empty array nested inside settings is ambiguous and is written as `[]`, so an empty nested object
  `{}` comes back as `[]` after a load and save.

## Parsing

`parse()` needs no registry and keeps blocks of unregistered types unchanged. `format` must be `1`. `version`
defaults to 1; `settings`, `extensions`, `bindings` and `children` default to empty when absent. Every failure
(invalid JSON, wrong structure, invalid block name or binding path, duplicate ids, a binding without a string
`source`, non-object `settings`) throws `InvalidDocumentException`.

## Client export

`Mode::Admin` returns the same document. `Mode::Front` removes each top-level setting marked `x-private` in the
block type's settings schema, together with every binding that targets one. A block whose type is not registered
loses all of its settings and all of its bindings, because nothing says which are safe; its extensions and
children are kept, and children are cleaned by their own type.
