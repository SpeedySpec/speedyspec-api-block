# speedyspec-api-block

A WordPress-independent block system built as Domain-Driven Design layer packages. Contracts and value objects live
in `block-domain`; implementations live in other packages so each can be replaced. Every package lives under
`packages/` and is linked to its siblings through Composer path repositories. To run a package's tests, change into
its directory and run `composer install && vendor/bin/pest --colors=never`.

## Packages

| Package | Purpose |
|---|---|
| `block-domain` | Every contract, value objects, entities and a per-contract service container. Requires only PHP. |
| `block-app` | Framework-free defaults: schema validation, registries, resolving, drawing, serialization, package loading. |
| `block-infra-memory` | In-memory repositories, record store and data source, for tests and storage-less hosts. |
| `block-infra-filesystem` | A record store that keeps documents and saved templates as files. |
| `block-infra-pdo` | A record store that keeps them in one database table (SQLite, MySQL, MariaDB, PostgreSQL). |
| `block-contract-tests` | Shared Pest suites that any replacement implementation or block package runs. |
| `block-structure` | The layout blocks: section, columns, column, grid and element. |
| `block-example` | A small block package; the model for writing one. See its README. |

## Storage

Documents and saved templates are stored through one small contract, `RecordStoreInterface`: named text records
grouped into collections, with `get`, `put`, `delete` and `keys`. The repositories that use it
(`StoredDocumentRepository`, `StoredBlockTemplateRepository`) live in `block-app` and turn documents and templates
into text and back, so a host that wants its own storage implements the record store and nothing else.

```php
$container = new BlockServiceContainer();
(new AppServiceProvider())->register($container);
(new StorageServiceProvider())->register($container);                  // repositories over a record store
(new FilesystemServiceProvider('/var/app/blocks'))->register($container); // or PdoServiceProvider, or your own
```

- `block-infra-filesystem` writes one file per record under a directory that must not be served to visitors.
- `block-infra-pdo` uses one table; call `PdoRecordStore::install()` once to create it.
- A replacement record store runs `recordStoreContract()` from `block-contract-tests` to prove it behaves the same.
- A host can still replace a repository outright by binding `DocumentRepositoryInterface` or
  `BlockTemplateRepositoryInterface` itself.

## Showing by rule and repeating

`FlowPackage` (in `block-app`) is an ordinary block package a host loads when it wants these:

```php
$container->get(BlockPackageLoaderInterface::class)->load(FlowPackage::from($container));
```

- **Visibility rules on any block.** The `core/visibility` decorator stores groups of rules; a block is shown when
  every rule of any one group holds. A rule names a condition from `ConditionRegistryInterface` and passes it
  arguments. There are no expressions. A rule that cannot be judged hides the block from visitors, and an editor
  sees the reason as an issue.
- **A repeating block.** `core/repeat` draws its children once per item of a collection from
  `CollectionSourceRegistryInterface`, up to a limit. Inside it, the `core/current-item` data source binds a setting
  to a field of the item, and the `core/current-item-is` condition shows a block only for matching items.

A host adds its own conditions (signed in, a role, a date range) and collections (posts, products, rows) by
implementing `ConditionInterface` and `CollectionSourceInterface` and adding them to the registries.

## How blocks look

One vocabulary for presentation, shared by every block package and builder:

- **Breakpoints** come from `BreakpointRegistryInterface` (`tablet` at 768px and `desktop` at 1024px by default;
  a host adds, removes or replaces them).
- **A responsive value** is one stored shape, read with `ResponsiveValue`: a single value, or a map of `base` and
  breakpoint names.
- **Design tokens** are named values in `DesignTokenRegistryInterface`. A setting stores `token:color.primary`,
  not the colour.
- **Style rules** are handed to `StyleCollectorInterface` while a page is drawn; the block gets a class and the
  host places `css()` in the page, then calls `clear()`. A `StyleRule` cannot be built from an unsafe value.
- **`PresentationPackage`** adds the `core/style` decorator to every block type: padding, margin, gap, width,
  maximum width, minimum height, text alignment, size, weight, line height, colour, background and corner radius,
  each responsive, each with a strict value format.

```php
$container->get(BlockPackageLoaderInterface::class)->load(PresentationPackage::from($container));
$result = $container->get(DocumentRendererInterface::class)->render($document, new RenderRequest());
$css = $container->get(StyleCollectorInterface::class)->css();
```

## Laying out a page

`block-structure` is a block package with the five blocks pages are arranged with, so block packages and builders
share them instead of each shipping its own container:

| Block | What it is |
|---|---|
| `core/section` | A container drawn as `div`, `section`, `article`, `header`, `footer`, `main`, `aside` or `nav`. |
| `core/columns` | Holds `core/column` blocks; stacked on narrow screens, in a row from a chosen breakpoint. |
| `core/column` | One column, with a share of the row from 1 to 12 that may differ by breakpoint. |
| `core/grid` | Equal columns, 1 to 12, that may differ by breakpoint. |
| `core/element` | One element from a fixed list (headings, paragraph, lists and so on) with text. |

```php
$container->get(BlockPackageLoaderInterface::class)->load(StructurePackage::from($container));
```

Their layout is written through the style collector, and they take the shared `core/style` settings when
`PresentationPackage` is loaded.

## For builders

A builder is the interface where someone drops, arranges and configures blocks. It is not part of this repository;
it is written against these contracts in `block-domain`, so any builder works with any block package and produces
documents any other builder can open.

| Contract | What a builder uses it for |
|---|---|
| `BlockCatalogueInterface` | Listing the block types to offer, and which of them may be placed at a position. |
| `PlacementRulesInterface` | Asking whether one block type may sit inside another, or at the document root. |
| `BlockTemplateRegistryInterface` | Listing ready-made arrangements that block packages and the host define. |
| `BlockTemplateRepositoryInterface` | Saving and listing the arrangements people save while building. |
| `DocumentEditorInterface` | Every change to a document: insert, paste, move, remove, duplicate, settings, bindings. |
| `BlockPreviewInterface` | Redrawing one block after a change, in admin or front mode. |
| `UnresolvedBlockOutputInterface` | What is drawn in admin mode for a block that cannot be resolved. |

The editor never changes the document it is given. Each operation answers with a new document, or with every reason
it was refused, so a builder can show them all at once. Saving the document is the builder's own step, through
`DocumentRepositoryInterface`.

Blocks are drawn in one of two modes. `front` is what a visitor sees. `admin` is what a builder shows: a block type
may register a separate admin output, the block carries its issues and private settings, and a block that cannot be
resolved is drawn as a placeholder instead of being left out. A block that sits where its type is not allowed is
not drawn in either mode; resolving reports it as an issue.
