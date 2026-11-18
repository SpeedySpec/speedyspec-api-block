<?php

declare(strict_types=1);

namespace SpeedySpec\Block\Domain\Contracts;

use SpeedySpec\Block\Domain\Exceptions\MediaUploadRefusedException;
use SpeedySpec\Block\Domain\ValueObjects\MediaUpload;

/**
 * Decides whether an uploaded file may go into the media library, before any library sees it.
 *
 * A contract of its own so that whatever takes uploads, such as the builder API, applies the host's rules without
 * depending on how they are written, and so a host can change the rules, such as the size limit.
 *
 * @since 0.1.0
 */
interface MediaUploadPolicyInterface
{
    /**
     * Checks one upload and describes it with the type its content shows.
     *
     * @param string $name
     *   The name the file was uploaded with.
     * @param string $bytes
     *   The file.
     *
     * @throws MediaUploadRefusedException
     *   When the file breaks the rules.
     *
     * @since 0.1.0
     */
    public function check(string $name, string $bytes): MediaUpload;
}
