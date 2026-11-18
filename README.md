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
