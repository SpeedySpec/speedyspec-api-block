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
| `block-structure-domain` | The layout blocks' definitions: section, columns, column, grid and element. |
| `block-structure` | The standard way to draw the layout blocks. |
| `block-text-domain` | The text blocks' definitions: heading, paragraph, lists, quote, code, table, separator, spacer. |
| `block-text` | The standard way to draw the text blocks. |
| `block-media-domain` | The media blocks' definitions: image, gallery, video, audio, embed, icons, map. |
| `block-media` | The standard way to draw the media blocks, with its own icon set. |
| `block-actions-domain` | The actions blocks' definitions: buttons, call to action, testimonial, pricing, rating, social links. |
| `block-actions` | The standard way to draw the actions blocks, with one stylesheet. |
| `block-interactive-domain` | The interactive blocks' definitions: accordion, tabs, slider, countdown, progress, counter, modal. |
| `block-interactive` | The standard way to draw the interactive blocks, with a stylesheet and a small script. |
| `block-site-domain` | The site blocks' definitions: menu, table of contents, breadcrumbs, search, and the post list template. |
| `block-site` | The standard way to draw the site blocks, with one stylesheet and no script. |
| `block-presentation` | The builder contracts as a framework-free JSON API, behind an access rule the host supplies. |
| `block-infra-wp` | The WordPress adapter: posts as documents, WordPress's block types and binding sources. |
| `block-builder` | A reference builder in plain JavaScript, written against the builder API. See its README. |
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
$loader = $container->get(BlockPackageLoaderInterface::class);
$loader->load(new StructureTypesPackage());           // what the blocks are
$loader->load(StructurePackage::from($container));   // how they are drawn; a plugin may load its own instead
```

Their layout is written through the style collector, and they take the shared `core/style` settings when
`PresentationPackage` is loaded.

## Documents inside documents

`DocumentsPackage` (in `block-app`) adds `core/include`, a block that shows another stored document in its place:
a header on every page, a section reused across pages.

```php
$container->get(BlockPackageLoaderInterface::class)->load(DocumentsPackage::from($container));
```

- The included document is drawn by the ordinary renderer, in the same mode, with the including block's context.
- A document that includes itself, directly or through others, is drawn once and then stopped. Inclusions nest
  at most 8 deep, and one inclusion leads to at most 500 others.
- When the host has bound a `BuilderAccessInterface`, it is asked before a document is included (`view` for a
  visitor, `edit` in admin mode), so including a document is never a way to read it. A refused document is reported
  exactly as a missing one. A host that binds an access rule must allow visitors to view what public pages include.
- A block's output is only markup, so what an included document reports is kept by
  `DocumentIncluderInterface`: read `issues()` after drawing a page, then call `clear()`. Its assets reach the
  page's own result.

`BlockTemplateLibraryRegistryInterface` lists template libraries for a builder: the templates defined in code,
the ones people saved, and any a host adds by implementing `BlockTemplateLibraryInterface`, for example one that
fetches a collection from a service.

## Text

`block-text-domain` defines the blocks most pages are written with, in the Text section of the block list, and
`block-text` draws them. A plugin that wants its own markup loads its own outputs package instead of `block-text`,
and pages keep their settings.

| Block | What it is |
|---|---|
| `core/heading` | A heading, level 1 to 6, with formatted text and an optional anchor; without one its id comes from its text. |
| `core/paragraph` | Formatted text. |
| `core/list` | A bulleted or numbered list of `core/list-item` blocks, numbered from a chosen start. |
| `core/list-item` | One item, with formatted text; it may hold a `core/list` to nest. |
| `core/quote` | A quotation with an optional citation. |
| `core/code` | Code shown exactly as typed, with an optional language name. |
| `core/table` | Rows of formatted cells, an optional header row and a caption. |
| `core/separator` | A rule between parts of a page. |
| `core/spacer` | Empty space of a set height, which may differ by breakpoint. |

```php
$loader->load(new TextTypesPackage());           // what the blocks are
$loader->load(TextPackage::from($container));   // how they are drawn
```

Formatted text keeps only strong, em, s, code, sub, sup, mark, line breaks and links to http, https, mailto, tel,
same-site paths and anchors. It is cleaned when stored and again when drawn.

## Media

Files belong to the host. `MediaLibraryInterface` in `block-domain` finds an item by id, searches the library a page
at a time and accepts an upload; an item has an id, URL, kind (image, video, audio or file), type, title, alt text,
size where known, and the smaller sizes the host made. There are three implementations:

| Package | Library |
|---|---|
| `block-infra-memory` | `InMemoryMediaLibrary`, for tests; it forgets everything at the end of the request. |
| `block-infra-filesystem` | `FilesystemMediaLibrary`, files in one directory the host serves, with an index beside them. |
| `block-infra-wp` | `WpMediaLibrary`, over WordPress attachments, through a port so it is tested without WordPress. |

Uploads are checked by `MediaUploadPolicyInterface` before any library sees them. The default, in `block-app`,
decides a file's type from its first bytes, never from its name or declared type, and accepts only JPEG, PNG, GIF,
WebP, AVIF, MP4, WebM, MP3, Ogg, WAV and PDF, up to 20 MB. SVG and HTML are refused. Serve uploaded files with the
type the library recorded and `X-Content-Type-Options: nosniff`.

`block-media-domain` defines the Media section of the block list, and `block-media` draws it:

| Block | What it is |
|---|---|
| `core/image` | An image from the library, with alt text that may override the library's, a caption, a link and a size. |
| `core/gallery` | Several images in a grid of 1 to 6 columns, which may differ by breakpoint. |
| `core/video`, `core/audio` | A file from the library, with controls, looping and playing by itself (always muted); a video may have a poster. |
| `core/embed` | A YouTube or Vimeo video, framed sandboxed, with a plain link after it. |
| `core/icon` | One of the outputs package's icons, decorative or with a label. |
| `core/icon-list`, `core/icon-list-item` | A list with an icon before each item; items use the list's icon unless they set one. |
| `core/map` | An OpenStreetMap map around coordinates, or a link to search for an address. |

```php
$loader->load(MediaTypesPackage::from($container));   // needs a MediaLibraryInterface bound
$loader->load(MediaPackage::from($container));
```

Blocks store media ids, not URLs: the URL is looked up when the page is drawn, so moving files breaks nothing. A
block with nothing chosen yet draws nothing for visitors and a prompt for editors. A chosen file that has gone, or
is of the wrong kind, makes the block unavailable: visitors get nothing, editors get the placeholder and an
`unavailable-media` issue. A behaviour reports this by throwing `UnavailableBlockException`, which any block type
may use. A gallery leaves out images that have gone and is unavailable only when none are left.

## Actions

`block-actions-domain` defines the Actions section of the block list, and `block-actions` draws it:

| Block | What it is |
|---|---|
| `core/button`, `core/buttons` | A link that looks like a button, in one of four looks and three sizes; a row of them. |
| `core/call-to-action` | A heading, a few words and buttons. |
| `core/testimonial` | A quote with the person's name, role and, when the host has a media library, their photo. |
| `core/pricing-table`, `core/pricing-plan` | Plans side by side; each has a price, a period, a list of features and buttons. |
| `core/rating` | A score out of 1 to 10 stars, always said in words as well. |
| `core/social-links`, `core/social-link` | Links to profiles on a fixed list of services, drawn as the service's name. |

```php
$loader->load(ActionsTypesPackage::from($container));   // uses the media library when one is bound
$loader->load(ActionsPackage::from($container));
```

- **Buttons and social links without a usable link** draw nothing for visitors. Editors see them greyed out, with a note saying what they need. A link opening in a new tab always gets `rel="noopener noreferrer"`.
- **A social link must go to its own service.** An Instagram link must go to instagram.com; website, RSS and Mastodon take any https link, and email takes only `mailto:`. A link to another site is reported as an issue.
- **Logos.** Social links are drawn as the service's name, not its logo, since the logos are other companies' trademarks. A plugin that wants logos loads its own outputs package.
- **Styles.** The outputs share one stylesheet asset, `core/actions`, built on the design tokens' custom properties (such as `--color-primary`). It includes hover and visible-focus states.

## Interactive

`block-interactive-domain` defines the Interactive section of the block list, and `block-interactive` draws it:

| Block | What it is | With no script |
|---|---|---|
| `core/accordion`, `core/accordion-item` | Sections that open when clicked, optionally one at a time. | The browser's own `details` elements. |
| `core/tabs`, `core/tab` | Panels shown one at a time, chosen by their labels. | Every panel in order, under its label. |
| `core/slider`, `core/slide` | Slides in a row, with Previous, Next and, when set to move by itself, Pause. | A row that scrolls and snaps to each slide. |
| `core/countdown` | The time left until a date in a time zone, then closing words. | The date and time in words. |
| `core/progress` | A bar and a percentage. | The same. |
| `core/counter` | A number that counts up when it comes into view. | The final number. |
| `core/modal` | A button that opens a panel over the page. | The browser's own `popover`. |

```php
$loader->load(new InteractiveTypesPackage());   // takes a clock for countdowns, the system's by default
$loader->load(new InteractivePackage());
```

- **Assets.** The outputs declare two assets: an inline stylesheet, `core/interactive`, and a module script, `core/interactive-script`. The script is a file in the package, so the host's asset locator decides where it is served.
- **What the script does.** It only adds behaviour:
  - keyboard tabs, following the WAI-ARIA tabs pattern;
  - slider controls;
  - a ticking countdown;
  - a counting animation.
- **How it stays safe.** It writes no markup from the page's text, and each block is set up once.
- **Reduced motion.** For visitors who ask for less motion, the slider never moves by itself and the counter does not count.
- **Countdown times.** A countdown counts to one moment in UTC:
  - a local time skipped when summer time starts becomes the next valid moment;
  - a time repeated when it ends counts to the first of the two.

## Site

`block-site-domain` defines the Site section of the block list, and `block-site` draws it:

| Block | What it is |
|---|---|
| `core/navigation`, `core/navigation-link` | A menu of links, with submenus up to two levels deep; the link to the current page is marked. |
| `core/table-of-contents` | Links to the page's headings between two levels. |
| `core/breadcrumbs` | The path from the top of the site to this page, the page itself last and not linked. |
| `core/search` | A search form sent where the host's search lives. |

```php
$loader->load(SiteTypesPackage::from($container));   // uses the site map when one is bound
$loader->load(new SitePackage());
```

- **The site map.** Where a page sits in the host's site is the host's to say, through `SiteMapInterface`. It gives a page's address, its trail and where search goes. `InMemorySiteMap` (in `block-infra-memory`) takes lists, and `WpSiteMap` (in `block-infra-wp`) uses a post's permalink, its parent pages and WordPress's own search. Without a site map, breadcrumbs and search show nothing to visitors and no menu link is marked current.
- **The page step.** The site package sees the whole page before any block is drawn, and adds to the context:
  - `core/headings` and `core/heading-ids`: every heading, with an id unique on the page (`intro`, `intro-2`). The text package's heading uses that id, so a table of contents always links to the right heading.
  - `core/page-url`, `core/breadcrumbs` and `core/search`, from the site map.

  Headings inside a repeating block, and headings in included documents, are not listed.
- **The post list** is a template, `core/post-list`, not a block type. It is a `core/repeat` over the `core/recent-posts` collection the host registers. Inside it, a heading, a paragraph and a button are bound to each post's `title`, `excerpt` and `url`, and every part stays editable. `ArrayCollectionSource` (in `block-infra-memory`) is a simple collection to register.
- **No script.** Submenus open on hover and when a keyboard user moves into them. A closed submenu is moved out of view rather than removed, so Tab still reaches its links.

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
resolved, or that sits where its type is not allowed, is drawn as a placeholder instead of being left out.

## The builder API

`block-presentation` exposes the builder contracts as JSON so a builder written in JavaScript can use them. It
knows no framework: a host turns its request into an `ApiRequest`, calls `BuilderApi::handle()`, and sends the
`ApiResponse`.

```php
(new PresentationServiceProvider())->register($container);
$container->add(BuilderAccessInterface::class, fn () => new MyAccess($currentUser));

$response = $container->get(BuilderApi::class)->handle(new ApiRequest($method, $path, $query, $rawBody));
http_response_code($response->status);
echo $response->json();
```

| Route | What it does | Needs |
|---|---|---|
| `GET /catalogue` | Block types; with `document`, `parent`, `index` only those that fit there. | edit |
| `GET /vocabulary` | Breakpoints, tokens, conditions, collections, decorators, data sources (`sources`), and whether the person may change site styles (`manageStyles`). | edit |
| `GET /documents` | Ids of the documents the person may edit. | edit |
| `POST /documents` | Creates an empty document from `id` and `kind`. | edit |
| `GET /documents/{id}` | The document as stored, with its issues. | edit |
| `DELETE /documents/{id}` | Removes it. | edit |
| `POST /documents/{id}/operations` | One editing operation, including `restore`, which puts back a whole earlier state and is what undo is built on; saves on success, `422` with every refusal otherwise. | edit |
| `POST /documents/{id}/preview` | Draws one block; `mode` is `admin` or `front`. With `styles`, the page's stylesheets to place. | edit, or view for front |
| `GET /documents/{id}/render` | Draws the page, with its collected rules (`css`) and its stylesheets to place (`styles`). | view, or edit for `mode=admin` |
| `GET /templates` | Template libraries and their templates, and whether the person may save and remove them (`manage`). | edit |
| `POST /templates` | Saves a block of a document as a template. | manage-templates and edit |
| `DELETE /templates/{vendor}/{name}` | Removes a saved template. | manage-templates |
| `PUT /tokens/{name}` | Sets a design token from `{value}`; answers every token. `422` with the reason for a name or value the site cannot use. | manage-styles |
| `DELETE /tokens/{name}` | Removes a design token; answers every token. | manage-styles |
| `PUT /breakpoints/{name}` | Sets a breakpoint from `{minWidth}`, a whole number of pixels; answers every breakpoint. | manage-styles |
| `DELETE /breakpoints/{name}` | Removes a breakpoint; answers every breakpoint. | manage-styles |
| `GET /media` | One page of the media library; `search`, `kind`, `page`, `perPage` (at most 100). | edit |
| `GET /media/{id}` | One media item. | edit |
| `POST /media?name=` | Uploads the request body as a file; `201` with the item, or `400`, `413` or `415` with the reason. | upload-media |

`BuilderAccessInterface` is the only permission check, and it is the host's to implement: the block system knows
nothing about users. Until a host binds one, every request is refused. Signing people in, sessions and request
forgery protection are also the host's. The media routes answer `501` until the host binds a media library, and
`ApiRequest` takes the request's headers as its fifth argument.

## Editor features

These are what a builder needs for the editing people expect beyond placing and configuring blocks. Each is either
an editing operation, so the server checks it like any other, or declared as data, so every builder offers the same.

- **Copy, cut and paste.** Blocks are copied as the text `{"type": "speedyspec/blocks", "format": 1, "blocks": [...]}`,
  with the blocks as the document export gives them, so they can be pasted into another document or another tab.
  Pasting is the `insertBlocks` operation, which gives every block a new id. Anything else on the clipboard is left
  to the browser. Removing several blocks at once is one operation, `{operation: "removeBlocks", ids}`, so it is one
  step to undo.
- **Templates.** `GET /templates` lists them; `insertTemplate` inserts one; `POST /templates` saves a block, with
  everything inside it, as a template everyone can use, without its private settings. Saved templates live in the
  `core/saved` library; the reference editor names them `saved/` and a slug of their title.
- **Bindings.** The vocabulary lists every registered data source with the arguments it takes and the value it gives
  (`DataSourceRegistryInterface::all()`), so a builder can offer them; `setBinding` and `clearBinding` change them.
- **Site styles.** Design tokens and breakpoints can be stored: `StorageServiceProvider` binds
  `StoredDesignTokenRegistry` and `StoredBreakpointRegistry` over the record store, starting from the defaults it is
  given until they are first changed. The token and breakpoint routes change them, for people with `manage-styles`.
  Styles set for a breakpoint that is later removed stop applying; a reference to a removed token falls back as an
  unset custom property does. Changing site styles is not a document edit, so it is not undone with the document.
- **Turning a block into another type.** A block type declares what it can become, as data:
  `transforms: [{to: "core/heading", settings: {text: "text"}}]` maps each setting of the new type to one of its own.
  The `transform` operation, `{operation: "transform", block, type}`, keeps the block's id, place and children,
  carries over the mapped settings and their bindings, and keeps the decorator settings that apply to the new type.
  A transform that would leave children where the new type cannot hold them is refused. The catalogue lists each
  type's `transforms`. Paragraph, heading and quote turn into one another.
- **Locks.** The `core/lock` extension, `{move, remove}`, keeps a block from being moved or removed, and the editor
  refuses with `locked`: removing a block also checks everything inside it. `restore`, which undo is built on, is
  never checked, so undoing the paste of a locked block works. Anyone who may edit may unlock, since a lock prevents
  accidents, not access. `DocumentsPackage` registers the lock as a decorator, so builders show it like any other.

## WordPress

Nothing above depends on WordPress. `block-infra-wp` is the one package that connects the two. It is
**experimental**: it reads and writes block markup faithfully, but does not yet draw every post as WordPress does.

- **Posts as documents.** `WpPostDocumentRepository` reads a post's block markup into a block tree and writes it
  back. `WpMarkup` follows WordPress's block comment grammar itself, without calling WordPress. A block's
  attributes are its settings; the markup it saved is kept in a `wpContent` setting.
- **Block identity.** WordPress blocks have none. A block is identified by where it sits (`wp-1-0`) and that id is
  written into the block's `metadata` when the post is saved, so it survives the block being moved.
- **Block types.** `WpBlockTypeBridge` describes each registered WordPress block type as a block type here, and
  `WpBlockOutput` draws it: static blocks from their saved markup, dynamic ones through WordPress.
- **Bindings.** Each WordPress block bindings source is offered as a data source under the same name, and
  bindings in a block's `metadata` are read and written.

```php
$provider = new WpServiceProvider();
$provider->register($container);
add_action('init', fn () => $provider->boot($container), 99);
```

Everything it needs from WordPress comes through four ports in `SpeedySpec\Block\Infra\WP\Ports`, so the adapter
is tested without WordPress. The four classes in `WordPress/` that call WordPress's own functions are thin and
have **not** been run against a live WordPress.

Known differences from WordPress when drawing:

- Static blocks and classic content are drawn from their saved markup and do not pass through WordPress's render
  pipeline, so layout classes, per-block styles, shortcodes, embeds and automatic paragraphs are missing.
- Dynamic blocks are handed to WordPress without their inner blocks or block context, so blocks that draw their
  own children, or read the current post from context, come out empty.
- WordPress's binding sources read the post from block context, which is not supplied yet.
- A block whose stored attributes do not fit its declared types, or that sits where `parent` or `allowed_blocks`
  forbid, is not drawn; WordPress is more forgiving.
- A WordPress block type whose name another package has already registered, such as `core/columns` when
  `block-structure` is loaded, is drawn by that package. `boot()` returns the names it skipped.
- An empty object nested inside a block's attributes is written back as an empty list.

