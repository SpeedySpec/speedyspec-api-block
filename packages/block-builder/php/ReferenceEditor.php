<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Builder;

use SpeedySpec\Block\Domain\Contracts\EditorInterface;
use SpeedySpec\Block\Domain\ValueObjects\EditorPage;
use SpeedySpec\Block\Domain\ValueObjects\EditorRequest;

/**
 * The reference editor: the `<block-builder>` element, opened on one document.
 *
 * It only says what to place; the host loads its script through the asset registry, so the host decides where the
 * package's files are served from.
 *
 * @since 0.1.0
 */
class ReferenceEditor implements EditorInterface
{
    public const string ASSET = 'speedyspec/block-builder';

    public const string PACKAGE = 'speedyspec/block-builder';

    public const string ENTRY = 'src/editor.js';

    public function name(): string
    {
        return 'speedyspec/block-builder';
    }

    public function title(): string
    {
        return 'Block builder';
    }

    public function open(EditorRequest $request): EditorPage
    {
        $headers = $request->values === []
            ? ''
            : sprintf(' headers="%s"', $this->escape((string) json_encode(
                $request->values,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            )));

        return new EditorPage([self::ASSET], sprintf(
            '<block-builder api="%s" document="%s"%s></block-builder>',
            $this->escape($request->apiUrl),
            $this->escape($request->documentId),
            $headers,
        ));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
