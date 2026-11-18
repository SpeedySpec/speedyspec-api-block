# block-builder

A reference builder for the block system: the interface where someone adds, arranges and configures blocks. It is
written against the builder API in `block-presentation` and nothing else, so it works with any block package and
any host that mounts that API. It is one builder, not the builder; another can be written the same way.

It has no dependencies and no build step: plain ES modules.

## Parts

| Module | What it is |
|---|---|
| `src/api.js` | A client for the builder API. A refused edit is a `Refused` error carrying every reason. |
| `src/tree.js` | Reading a document: find a block, where it sits, and where to move it one step. |
| `src/forms.js` | A settings form from a block type's schema, so no block type needs builder code. |
| `src/store.js` | The state of one editing session; edits go to the server one at a time, in order. |
| `src/builder-element.js` | The interface, as a `<block-builder>` element: structure, inspector, live preview. |

The first four have no interface and can drive one of your own.

## Using the element

```html
<block-builder api="/api/blocks" document="home"></block-builder>
<script type="module">
    import { defineBlockBuilder } from '@speedyspec/block-builder/element';

    defineBlockBuilder();
</script>
```

To send something with every request, such as a token, set a client before the element is attached:

```js
import { createClient } from '@speedyspec/block-builder';

element.client = createClient({ baseUrl: '/api/blocks', headers: { 'X-CSRF-Token': token } });
```

The element decides nothing about what is valid. What may be added at a place comes from the catalogue, the form
from each type's schema, and every edit is accepted or refused by the server, which says why.

The preview is drawn in a frame that can run no script, whatever the document contains.

## Trying it

```bash
composer install --working-dir=demo
```

```bash
php -S 127.0.0.1:8765 demo/server.php
```

Then open http://127.0.0.1:8765. The demo keeps its documents in the system's temporary directory, lets anyone on
the machine edit, and is not for production.

## Tests

```bash
npm test
```

They cover the client, tree, forms and store. The element is checked by hand against the demo.

## Not included

Undo and redo, drag and drop, selecting a block by clicking it in the preview, editing bindings, a friendlier
control for values that differ by breakpoint (they are edited as text or JSON), and saving templates.
