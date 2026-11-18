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
| `src/store.js` | The state of one editing session; edits go to the server one at a time, in order. Keeps the history for undo and redo. |
| `src/drag.js` | Where a dragged block lands: the part of the target under the pointer, and the position to send. |
| `src/view.js` | What the interface shows: block types by category, where a new block goes, values by width. |
| `src/inline.js` | Typing on the page: tidying typed or pasted markup, what a key does, which links are safe. |
| `src/media.js` | Media settings: what to ask the library for, and changing a list of chosen ids. |
| `src/builder-element.js` | The interface, as a `<block-builder>` element. |
| `src/styles.js` | The interface's appearance, scoped to the element. |

The first eight have no interface and can drive one of your own.

## The interface

It is laid out the way the block editors people already know are:

- **The page is in the middle and is edited directly.** Click a block to select it; a toolbar appears on it to
  select its parent, move it, duplicate it, add a block beside it, or remove it.
- **Add block** opens a searchable panel of block types by category. It says where the block will go (inside or
  after the selected block) and dims the types the server does not allow there.
- **Outline** lists the page's blocks as a tree, marks the ones with problems, and selects on click.
- **Drag and drop.** A block can be dragged on the page or in the outline, and a block type can be dragged from
  the add-block panel. The edges of the block under the pointer mean before and after, its middle means inside
  when it can hold blocks; a line or a dashed frame shows which. Where siblings sit side by side, such as columns,
  before and after run across. A drop onto the block itself, into something inside it, or where it already is
  does nothing. Whether a block is allowed where it was dropped is the server's decision: a refused drop changes
  nothing and says why.
- **Undo and redo**, from the top bar or with the usual keys (Ctrl or Cmd with Z; Shift with it, or Ctrl+Y, to
  redo). Up to 50 steps are kept for the session. While the pointer is in a text field the keys are left to the
  field, which has its own undo.
- **Phone, Tablet, Full width** show the page at that width, so responsive styles can be checked.
- **Typing on the page.** Click the text of a heading, paragraph, list item, quote, code block or table cell and
  type. Enter starts a new block of the same kind after it (Shift+Enter breaks the line instead); Backspace in an
  empty block removes it; Escape or clicking away stops typing and keeps the text. Selecting words shows Bold,
  Italic, Code and Link; a link must be http, https, mailto, tel, a path on this site or an anchor, and a bare
  domain gets `https://`. Pasted text keeps only the formatting the block allows. Each stop is one ordinary
  settings change, so it can be undone.
- **Media.** A media setting shows the chosen file, with Replace and Remove; a gallery's images are listed in order
  with buttons to move or remove each. Choosing opens a picker in the inspector: search by name, upload from your
  computer, or pick from the library, newest first, a page at a time. Only files of the setting's kind are offered.
  An upload the server refuses is shown with its reason. A chosen file that has left the library says so.
- **Lists of text**, such as a plan's features, are edited in the inspector, one box per item, with buttons to add, move or remove items. Items can also be typed into on the page.
- **The page's stylesheets** are placed on the canvas, so blocks look as they will: links to stylesheets first, then inline ones, then each block's own rules. Scripts never are.
- **The inspector** on the right has three tabs. *Block* holds the block's own settings; a table
  has buttons to add or remove a row or column, and code gets a monospace box. *Style* holds the shared
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

Then open http://127.0.0.1:8765 to edit, and http://127.0.0.1:8765/view to see the page as a visitor does, with
its stylesheets and scripts. The demo keeps its documents in the system's temporary directory, lets anyone on the
machine edit, and is not for production.

`PackageFiles` (in `php/`) is how the demo serves package assets. Each loaded package's scripts and stylesheets are
served from `/packages/<name>/`, and nothing outside a package's own directory, nor any other kind of file, is
served. As the asset locator, it gives those files their addresses. A host without a build step can use it the same
way.

## Tests

```bash
npm test
```

They cover the client, tree, forms, store, view and drop-position logic. The element is checked by hand against the demo.

## Not included

Pasting blocks rather than text, editing bindings, inserting and saving templates, and
keyboard shortcuts.
