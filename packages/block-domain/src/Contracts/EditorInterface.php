<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\ValueObjects\EditorPage;
use SpeedySpec\Block\Domain\ValueObjects\EditorRequest;

/**
 * The editor a site uses to work on documents, bound by the host so it can be turned off or replaced.
 *
 * A host binds one or none. With none, documents can still be drawn and changed through the builder API, and admin
 * drawings carry no editing marks. A plugin that brings its own editor binds its own. What an editor must handle is
 * the builder API and the block definitions as data; no block package carries editor code.
 *
 * @since 0.1.0
 */
interface EditorInterface
{
    /**
     * Names the editor as lower-case `vendor/name`.
     *
     * @since 0.1.0
     */
    public function name(): string;

    /**
     * Names the editor for people, for a host's settings screen.
     *
     * @since 0.1.0
     */
    public function title(): string;

    /**
     * Gives what the host places on its screen to open one document in the editor.
     *
     * @since 0.1.0
     */
    public function open(EditorRequest $request): EditorPage;
}
