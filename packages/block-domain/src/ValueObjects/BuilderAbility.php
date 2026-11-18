<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\ValueObjects;

/**
 * What a person may be allowed to do through a builder.
 *
 * @since 0.1.0
 */
enum BuilderAbility: string
{
    /**
     * See a document as a visitor would.
     */
    case View = 'view';

    /**
     * Open a document for editing, which shows its private settings, and change it.
     */
    case Edit = 'edit';

    /**
     * Save and remove the templates everyone shares.
     */
    case ManageTemplates = 'manage-templates';

    /**
     * Add files to the media library.
     */
    case UploadMedia = 'upload-media';
}
