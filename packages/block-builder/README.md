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
| `src/view.js` | What the interface shows: block types by category, where a new block goes, values by width. |
| `src/builder-element.js` | The interface, as a `<block-builder>` element. |
| `src/styles.js` | The interface's appearance, scoped to the element. |

The first five have no interface and can drive one of your own.

## The interface

It is laid out the way the block editors people already know are:

- **The page is in the middle and is edited directly.** Click a block to select it; a toolbar appears on it to
  select its parent, move it, duplicate it, add a block beside it, or remove it.
- **Add block** opens a searchable panel of block types by category. It says where the block will go (inside or
  after the selected block) and dims the types the server does not allow there.
- **Outline** lists the page's blocks as a tree, marks the ones with problems, and selects on click.
- **Phone, Tablet, Full width** show the page at that width, so responsive styles can be checked.
- **The inspector** on the right has three tabs. *Block* holds the block's own settings. *Style* holds the shared
  style settings in sections, with a switch for which widths a value applies from, colour pickers that offer the
  site's colour tokens first, and alignment buttons. *Visibility* builds the rules for when a block is shown.
- **A refusal is shown beside the field that caused it;** anything else appears at the bottom of the page.
- **The trail** along the bottom shows where the selected block sits and selects any ancestor.

Selecting a block by clicking needs the page to name its blocks, which `block-presentation` does in an editor's
drawing. A block whose output does not write its attributes can still be selected from the outline.

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

The page is drawn in a frame that can run no script, whatever the document contains; the builder listens for
clicks from outside it.

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

They cover the client, tree, forms, store and view logic. The element is checked by hand against the demo.

## Not included

Undo and redo, drag and drop, typing directly on the page, editing bindings, inserting and saving templates, and
keyboard shortcuts.
