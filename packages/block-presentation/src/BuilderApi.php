<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Presentation;

use InvalidArgumentException;
use SpeedySpec\Block\Domain\BlockServiceContainer;
use SpeedySpec\Block\Domain\Contracts\BlockCatalogueInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPackageLoaderInterface;
use SpeedySpec\Block\Domain\Contracts\BlockPreviewInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateLibraryRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BlockTemplateRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\BreakpointRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\BuilderAccessInterface;
use SpeedySpec\Block\Domain\Contracts\ClientExportInterface;
use SpeedySpec\Block\Domain\Contracts\CollectionSourceRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\ConditionRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DecoratorRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DesignTokenRegistryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentEditorInterface;
use SpeedySpec\Block\Domain\Contracts\EditorInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentIncluderInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRendererInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentRepositoryInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentResolverInterface;
use SpeedySpec\Block\Domain\Contracts\DocumentSerializerInterface;
use SpeedySpec\Block\Domain\Contracts\StyleCollectorInterface;
use SpeedySpec\Block\Domain\Entities\Block;
use SpeedySpec\Block\Domain\Entities\BlockTree;
use SpeedySpec\Block\Domain\Entities\Document;
use SpeedySpec\Block\Domain\Exceptions\DuplicateBlockIdException;
use SpeedySpec\Block\Domain\Exceptions\InvalidBlockNameException;
use SpeedySpec\Block\Domain\Exceptions\InvalidSettingPathException;
use SpeedySpec\Block\Domain\ValueObjects\Binding;
use SpeedySpec\Block\Domain\ValueObjects\BlockName;
use SpeedySpec\Block\Domain\ValueObjects\BlockTemplate;
use SpeedySpec\Block\Domain\ValueObjects\BuilderAbility;
use SpeedySpec\Block\Domain\ValueObjects\EditRefusal;
use SpeedySpec\Block\Domain\ValueObjects\EditResult;
use SpeedySpec\Block\Domain\ValueObjects\Issue;
use SpeedySpec\Block\Domain\ValueObjects\Mode;
use SpeedySpec\Block\Domain\ValueObjects\Position;
use SpeedySpec\Block\Domain\ValueObjects\RenderRequest;
use SpeedySpec\Block\Domain\ValueObjects\SettingPath;
use Throwable;

/**
 * The builder contracts as a JSON API, so a builder written in JavaScript can use them.
 *
 * It knows no framework: a host turns its own request into an ApiRequest, calls handle(), and sends the
 * ApiResponse. It adds no behaviour of its own; every route is one of the contracts in `block-domain`, reached
 * only after BuilderAccessInterface allows it. Authentication, sessions and request forgery protection stay
 * with the host.
 *
 * @since 0.1.0
 */
class BuilderApi
{
    /**
     * @param BlockServiceContainer $container
     *   Where the contracts are bound; each is taken when a request first needs it.
     *
     * @since 0.1.0
     */
    public function __construct(private readonly BlockServiceContainer $container)
    {
    }

    /**
     * Answers one request; it never throws.
     *
     * @since 0.1.0
     */
    public function handle(ApiRequest $request): ApiResponse
    {
        try
        {
            return $this->route($request);
        }
        catch (ApiProblem $e)
        {
            return new ApiResponse($e->status, ['error' => $e->getMessage()]);
        }
        catch (
            InvalidArgumentException
            | InvalidBlockNameException
            | InvalidSettingPathException
            | DuplicateBlockIdException $e
        )
        {
            // These are the model refusing something the request named, and their messages are about that.
            return new ApiResponse(400, ['error' => $e->getMessage()]);
        }
        catch (Throwable)
        {
            // Anything else may describe the server; the host logs it, the person is told only that it failed.
            return new ApiResponse(500, ['error' => 'The request could not be completed.']);
        }
    }

    /**
     * @since 0.1.0
     */
    private function route(ApiRequest $request): ApiResponse
    {
        $method = strtoupper($request->method);
        $path = array_map(rawurldecode(...), array_values(array_filter(
            explode('/', $request->path),
            static fn (string $part): bool => $part !== '',
        )));
        $count = count($path);
        $root = $path[0] ?? '';

        return match (true)
        {
            $method === 'GET' && $path === ['catalogue'] => $this->catalogue($request),
            $method === 'GET' && $path === ['vocabulary'] => $this->vocabulary(),
            $method === 'GET' && $path === ['documents'] => $this->documents(),
            $method === 'POST' && $path === ['documents'] => $this->create($this->body($request)),
            $method === 'GET' && $root === 'documents' && $count === 2 => $this->read($path[1]),
            $method === 'DELETE' && $root === 'documents' && $count === 2 => $this->delete($path[1]),
            $method === 'POST' && $root === 'documents' && $count === 3 && $path[2] === 'operations'
                => $this->operate($path[1], $request),
            $method === 'POST' && $root === 'documents' && $count === 3 && $path[2] === 'preview'
                => $this->preview($path[1], $this->body($request)),
            $method === 'GET' && $root === 'documents' && $count === 3 && $path[2] === 'render'
                => $this->render($path[1], $request),
            $method === 'GET' && $path === ['templates'] => $this->templates(),
            $method === 'POST' && $path === ['templates'] => $this->saveTemplate($this->body($request)),
            $method === 'DELETE' && $root === 'templates' && $count === 3
                => $this->removeTemplate($path[1] . '/' . $path[2]),
            default => throw new ApiProblem(404, 'There is nothing at that address.'),
        };
    }

    private function catalogue(ApiRequest $request): ApiResponse
    {
        $this->need(BuilderAbility::Edit);
        $catalogue = $this->container->get(BlockCatalogueInterface::class);
        $entries = $catalogue->all();

        if (isset($request->query['document']))
        {
            $document = $this->document($request->query['document'], BuilderAbility::Edit);
            $parent = $request->query['parent'] ?? '';
            $index = (int) ($request->query['index'] ?? 0);
            $entries = $catalogue->placeableAt(
                $document,
                $parent === '' ? Position::root($index) : Position::inside($parent, $index),
            );
        }

        $loader = $this->container->get(BlockPackageLoaderInterface::class);
        $defining = array_flip(array_filter(array_map(static fn ($entry): ?string => $entry->package, $entries)));
        $packages = [];

        // In load order, so sections appear the way the host arranged its packages.
        foreach ($loader->loaded() as $name)
        {
            $details = $loader->details($name);

            if (isset($defining[$name]) && $details !== null)
            {
                $packages[] = ['name' => $name, ...$details];
            }
        }

        return new ApiResponse(200, [
            'packages' => $packages,
            'blocks' => array_map(static fn ($entry): array => $entry->toArray(), $entries),
        ]);
    }

    private function vocabulary(): ApiResponse
    {
        $this->need(BuilderAbility::Edit);
        $described = static fn ($entry): array => ['name' => $entry->name(), 'arguments' => $entry->argumentsSchema()];

        return new ApiResponse(200, [
            'breakpoints' => array_map(
                static fn ($breakpoint): array => ['name' => $breakpoint->name, 'minWidth' => $breakpoint->minWidth],
                $this->container->get(BreakpointRegistryInterface::class)->all(),
            ),
            'tokens' => (object) $this->container->get(DesignTokenRegistryInterface::class)->all(),
            'conditions' => array_map($described, $this->container->get(ConditionRegistryInterface::class)->all()),
            'collections' => array_map(
                $described,
                $this->container->get(CollectionSourceRegistryInterface::class)->all(),
            ),
            'decorators' => array_map(
                static fn ($decorator): array => [
                    'namespace' => $decorator->namespace,
                    'settings' => $decorator->settingsSchema,
                    'appliesTo' => $decorator->appliesTo,
                ],
                $this->container->get(DecoratorRegistryInterface::class)->all(),
            ),
        ]);
    }

    private function documents(): ApiResponse
    {
        $this->need(BuilderAbility::Edit);
        $access = $this->container->get(BuilderAccessInterface::class);

        return new ApiResponse(200, ['documents' => array_values(array_filter(
            $this->container->get(DocumentRepositoryInterface::class)->ids(),
            static fn (string $id): bool => $access->allows(BuilderAbility::Edit, $id),
        ))]);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @since 0.1.0
     */
    private function create(array $body): ApiResponse
    {
        $id = $this->text($body, 'id');
        $kind = $this->text($body, 'kind');
        $this->need(BuilderAbility::Edit, $id);
        $documents = $this->container->get(DocumentRepositoryInterface::class);

        if ($documents->find($id) !== null)
        {
            throw new ApiProblem(409, sprintf('A document "%s" already exists.', $id));
        }

        $document = new Document($id, $kind, new BlockTree());
        $documents->save($document);

        return new ApiResponse(201, ['document' => $this->exported($document)]);
    }

    private function read(string $id): ApiResponse
    {
        $document = $this->document($id, BuilderAbility::Edit);

        return new ApiResponse(200, [
            'document' => $this->exported($document),
            'issues' => $this->issues($this->resolvedIssues($document)),
        ]);
    }

    private function delete(string $id): ApiResponse
    {
        $this->document($id, BuilderAbility::Edit);
        $this->container->get(DocumentRepositoryInterface::class)->remove($id);

        return new ApiResponse(204);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @since 0.1.0
     */
    private function operate(string $id, ApiRequest $request): ApiResponse
    {
        $document = $this->document($id, BuilderAbility::Edit);
        $result = $this->edit($document, $this->body($request), $request);

        if (!$result->isAccepted())
        {
            return new ApiResponse(422, ['refusals' => array_map(
                static fn (EditRefusal $refusal): array => [
                    'kind' => $refusal->kind->value,
                    'message' => $refusal->message,
                    'block' => $refusal->blockId,
                    'path' => $refusal->path,
                ],
                $result->refusals,
            )]);
        }

        $this->container->get(DocumentRepositoryInterface::class)->save($result->document);

        return new ApiResponse(200, [
            'document' => $this->exported($result->document),
            'affected' => $result->affectedIds,
            'issues' => $this->issues($this->resolvedIssues($result->document)),
        ]);
    }

    /**
     * Runs the editing operation a request names.
     *
     * @param array<string, mixed> $body
     *
     * @since 0.1.0
     */
    private function edit(Document $document, array $body, ApiRequest $request): EditResult
    {
        $editor = $this->container->get(DocumentEditorInterface::class);
        $block = fn (): string => $this->text($body, 'block');
        $path = fn (): SettingPath => SettingPath::fromString($this->text($body, 'path'));

        return match ($body['operation'] ?? null)
        {
            'insert' => $editor->insert(
                $document,
                BlockName::fromString($this->text($body, 'type')),
                $this->position($body),
                $this->map($body, 'settings'),
            ),
            'insertBlocks' => $editor->insertBlocks($document, $this->blocks($request), $this->position($body)),
            'insertTemplate' => $editor->insertTemplate(
                $document,
                $this->template($this->text($body, 'template')),
                $this->position($body),
            ),
            'move' => $editor->move($document, $block(), $this->position($body)),
            'remove' => $editor->remove($document, $block()),
            'duplicate' => $editor->duplicate($document, $block()),
            'updateSettings' => $editor->updateSettings($document, $block(), $this->map($body, 'settings', true)),
            'updateExtension' => $editor->updateExtension(
                $document,
                $block(),
                $this->text($body, 'namespace'),
                $this->map($body, 'settings', true),
            ),
            'setBinding' => $editor->setBinding($document, $block(), $path(), $this->binding($body)),
            'clearBinding' => $editor->clearBinding($document, $block(), $path()),
            'restore' => $editor->restore($document, $this->blocks($request)),
            default => throw new ApiProblem(400, 'The request names no editing operation this API knows.'),
        };
    }

    /**
     * @param array<string, mixed> $body
     *
     * @since 0.1.0
     */
    private function preview(string $id, array $body): ApiResponse
    {
        $mode = $this->mode($body['mode'] ?? 'admin');
        $document = $this->document($id, $mode === Mode::Admin ? BuilderAbility::Edit : BuilderAbility::View);
        $this->startPage();
        $preview = $this->container->get(BlockPreviewInterface::class)
            ->preview($document, $this->text($body, 'block'), $this->renderRequest($mode));

        if ($preview === null)
        {
            throw new ApiProblem(404, 'The document has no such block.');
        }

        return new ApiResponse(200, [
            'block' => $preview->blockId,
            'output' => $preview->output,
            'issues' => $this->issues([...$preview->issues, ...$this->includer()->issues()]),
            'assets' => $preview->assets,
            'css' => $this->container->get(StyleCollectorInterface::class)->css(),
        ]);
    }

    private function render(string $id, ApiRequest $request): ApiResponse
    {
        $mode = $this->mode($request->query['mode'] ?? 'front');
        $document = $this->document($id, $mode === Mode::Admin ? BuilderAbility::Edit : BuilderAbility::View);
        $this->startPage();
        $result = $this->container->get(DocumentRendererInterface::class)
            ->render($document, $this->renderRequest($mode));

        return new ApiResponse(200, [
            'output' => $result->output,
            'issues' => $this->issues([...$result->issues, ...$this->includer()->issues()]),
            'assets' => $result->assets,
            'css' => $this->container->get(StyleCollectorInterface::class)->css(),
        ]);
    }

    /**
     * Builds a drawing request, marked for editing when it is an admin drawing and the site has an editor.
     *
     * @since 0.1.0
     */
    private function renderRequest(Mode $mode): RenderRequest
    {
        return new RenderRequest($mode, 'html', $this->container->has(EditorInterface::class));
    }

    private function templates(): ApiResponse
    {
        $this->need(BuilderAbility::Edit);

        return new ApiResponse(200, ['libraries' => array_map(
            fn ($library): array => [
                'name' => $library->name(),
                'title' => $library->title(),
                'templates' => array_map($this->templateData(...), $library->all()),
            ],
            $this->container->get(BlockTemplateLibraryRegistryInterface::class)->all(),
        )]);
    }

    /**
     * Saves one block of a document, with everything inside it, as a template everyone can use.
     *
     * @param array<string, mixed> $body
     *
     * @since 0.1.0
     */
    private function saveTemplate(array $body): ApiResponse
    {
        $this->need(BuilderAbility::ManageTemplates);
        $block = $this->document($this->text($body, 'document'), BuilderAbility::Edit)
            ->tree->find($this->text($body, 'block'));

        if ($block === null)
        {
            throw new ApiProblem(404, 'The document has no such block.');
        }

        // A template is offered to everyone who may edit anything, so it must not carry the block's private settings.
        $shared = $this->container->get(ClientExportInterface::class)
            ->export(new Document('template', 'template', new BlockTree($block)), Mode::Front);

        $template = new BlockTemplate(
            $this->text($body, 'name'),
            $this->text($body, 'title'),
            $shared->tree,
            is_string($body['description'] ?? null) ? $body['description'] : '',
            is_string($body['category'] ?? null) ? $body['category'] : '',
        );
        $this->container->get(BlockTemplateRepositoryInterface::class)->save($template);

        return new ApiResponse(201, ['template' => $this->templateData($template)]);
    }

    private function removeTemplate(string $name): ApiResponse
    {
        $this->need(BuilderAbility::ManageTemplates);
        $this->container->get(BlockTemplateRepositoryInterface::class)->remove($name);

        return new ApiResponse(204);
    }

    /**
     * Refuses the request unless the person has the ability.
     *
     * @throws ApiProblem
     *
     * @since 0.1.0
     */
    private function need(BuilderAbility $ability, ?string $documentId = null): void
    {
        if (!$this->container->get(BuilderAccessInterface::class)->allows($ability, $documentId))
        {
            throw new ApiProblem(403, 'You are not allowed to do that.');
        }
    }

    /**
     * Loads a document the person may use; access is checked first, so a refusal does not reveal what exists.
     *
     * @throws ApiProblem
     *
     * @since 0.1.0
     */
    private function document(string $id, BuilderAbility $ability): Document
    {
        $this->need($ability, $id);

        return $this->container->get(DocumentRepositoryInterface::class)->find($id)
            ?? throw new ApiProblem(404, sprintf('There is no document "%s".', $id));
    }

    /**
     * Finds a template in any library.
     *
     * @throws ApiProblem
     *
     * @since 0.1.0
     */
    private function template(string $name): BlockTemplate
    {
        foreach ($this->container->get(BlockTemplateLibraryRegistryInterface::class)->all() as $library)
        {
            $template = $library->find($name);

            if ($template !== null)
            {
                return $template;
            }
        }

        throw new ApiProblem(404, sprintf('There is no template "%s".', $name));
    }

    /**
     * Empties what is collected while drawing, so one page's styles and issues never appear in the next.
     *
     * @since 0.1.0
     */
    private function startPage(): void
    {
        $this->container->get(StyleCollectorInterface::class)->clear();
        $this->includer()->clear();
    }

    private function includer(): DocumentIncluderInterface
    {
        return $this->container->get(DocumentIncluderInterface::class);
    }

    /**
     * Writes a document the way it is stored, complete with private settings, for someone allowed to edit it.
     *
     * @since 0.1.0
     */
    private function exported(Document $document): mixed
    {
        return json_decode($this->container->get(DocumentSerializerInterface::class)->serialize(
            $this->container->get(ClientExportInterface::class)->export($document, Mode::Admin),
        ));
    }

    /**
     * @return array<string, mixed>
     *
     * @since 0.1.0
     */
    private function templateData(BlockTemplate $template): array
    {
        $document = $this->exported(new Document($template->name, 'template', $template->tree));

        return [
            'name' => $template->name,
            'title' => $template->title,
            'description' => $template->description,
            'category' => $template->category,
            'blocks' => $document->blocks ?? [],
        ];
    }

    /**
     * @return list<Issue>
     *
     * @since 0.1.0
     */
    private function resolvedIssues(Document $document): array
    {
        return $this->container->get(DocumentResolverInterface::class)->resolve($document, Mode::Admin)->issues;
    }

    /**
     * @param list<Issue> $issues
     *
     * @return list<array<string, mixed>>
     *
     * @since 0.1.0
     */
    private function issues(array $issues): array
    {
        return array_map(static fn (Issue $issue): array => [
            'block' => $issue->blockId,
            'kind' => $issue->kind->value,
            'message' => $issue->message,
            'path' => $issue->path,
        ], $issues);
    }

    /**
     * Reads the request body as a JSON object.
     *
     * @return array<string, mixed>
     *
     * @throws ApiProblem
     *
     * @since 0.1.0
     */
    private function body(ApiRequest $request): array
    {
        $body = json_decode($request->body === '' ? '{}' : $request->body, true, 64);

        if (!is_array($body) || ($body !== [] && array_is_list($body)))
        {
            throw new ApiProblem(400, 'The request body must be a JSON object.');
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws ApiProblem
     *
     * @since 0.1.0
     */
    private function text(array $body, string $key): string
    {
        $value = $body[$key] ?? null;

        if (!is_string($value) || $value === '')
        {
            throw new ApiProblem(400, sprintf('The request needs a "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     *
     * @throws ApiProblem
     *
     * @since 0.1.0
     */
    private function map(array $body, string $key, bool $required = false): array
    {
        // A missing map must not be read as an empty one where that would replace what is stored with nothing.
        if ($required && !is_array($body[$key] ?? null))
        {
            throw new ApiProblem(400, sprintf('The request needs "%s" as an object.', $key));
        }

        $value = $body[$key] ?? [];

        if (!is_array($value) || ($value !== [] && array_is_list($value)))
        {
            throw new ApiProblem(400, sprintf('"%s" must be an object.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws ApiProblem
     *
     * @since 0.1.0
     */
    private function position(array $body): Position
    {
        $position = $body['position'] ?? null;

        if (!is_array($position) || !is_int($position['index'] ?? null))
        {
            throw new ApiProblem(400, 'The request needs a "position" with a whole-number "index".');
        }

        $parent = $position['parent'] ?? null;

        if ($parent !== null && (!is_string($parent) || $parent === ''))
        {
            throw new ApiProblem(400, 'A position\'s "parent" must be a block id or null.');
        }

        return $parent === null ? Position::root($position['index']) : Position::inside($parent, $position['index']);
    }

    /**
     * Reads pasted blocks, which arrive in the same form documents are stored in.
     *
     * @return list<Block>
     *
     * @throws ApiProblem
     *
     * @since 0.1.0
     */
    private function blocks(ApiRequest $request): array
    {
        // Read as objects rather than maps: a block's empty settings must stay `{}`, which a PHP array cannot say.
        $blocks = json_decode($request->body, false, 64)->blocks ?? null;

        if (!is_array($blocks))
        {
            throw new ApiProblem(400, '"blocks" must be a list of blocks.');
        }

        return $this->container->get(DocumentSerializerInterface::class)->parse((string) json_encode([
            'format' => 1,
            'id' => 'paste',
            'kind' => 'paste',
            'blocks' => $blocks,
        ]))->tree->roots();
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws ApiProblem
     *
     * @since 0.1.0
     */
    private function binding(array $body): Binding
    {
        $data = $this->map($body, 'binding');
        $binding = Binding::to($this->text($data, 'source'), $this->map($data, 'arguments'));

        return array_key_exists('fallback', $data) ? $binding->withFallback($data['fallback']) : $binding;
    }

    /**
     * @throws ApiProblem
     *
     * @since 0.1.0
     */
    private function mode(mixed $name): Mode
    {
        return (is_string($name) ? Mode::tryFrom($name) : null)
            ?? throw new ApiProblem(400, 'The mode must be "front" or "admin".');
    }
}
