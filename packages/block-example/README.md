# block-example

A small block package that shows how one is written and proves the shared block package suite. It adds three block
types: `example/notice`, `example/list` and `example/list-item`.

## What a block package is

A block package carries block types, their behaviour and their output. It carries nothing about any builder, so the
same package works under any host and in any builder.

```
src/
├── ExampleBlockPackage.php     The entry point: registers everything the package offers.
├── Notice/
│   ├── NoticeOutput.php        Draws a notice for a visitor.
│   └── NoticeAdminOutput.php   Draws a notice for an editor, with its private internal note.
└── Listing/
    ├── ListBehaviour.php       Logic with no appearance: counts the list's items.
    ├── ListOutput.php          Draws the list element around its items.
    └── ListItemOutput.php      Draws one item.
```

## The rules

- **Depend only on the contracts.** `composer.json` requires `php` and `speedyspec/block-domain`, nothing else.
  Everything else a package needs for its tests goes in `require-dev`.
- **One entry point.** A class implementing `BlockPackageInterface` registers types, behaviours, outputs,
  decorators, migrations, data sources and steps through the registrar it is handed.
- **Use your own vendor.** A package named `example/blocks` may only add block types named `example/...`.
- **Define block types with data.** A type is a name, a settings schema with defaults, child and parent rules,
  catalogue details and at least one example.
- **Keep logic out of output.** A behaviour turns settings into a view of plain values. An output draws the view.
- **Write every value through the escaper.** Use `$input->escaper->text()`, `->attribute()` and `->url()`, and
  apply `$input->escaper->attributes($input->attributes)` to the root element. Add classes and attributes through
  the attribute bag, never by building attribute strings.
- **Mark editor-only settings private.** A setting with `x-private: true` is not in the view a visitor's output
  receives. Draw it only from an output registered for `Mode::Admin`.
- **Admin output is optional.** A block type with no admin output is drawn in the admin with its front-end output.

## Running the shared suite

```bash
composer install && vendor/bin/pest --colors=never
```

`tests/PackageContractTest.php` runs `blockPackageContract()`, which checks that the package depends only on the
contracts, that every block type can be listed and drawn, that every example draws cleanly in both modes, that
markup in a text setting is escaped, and that private settings and admin-only values never reach a visitor.
