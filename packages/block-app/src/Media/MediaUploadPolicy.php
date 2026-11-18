<?php

declare(strict_types=1);

namespace SpeedySpec\Block\App\Media;

use SpeedySpec\Block\Domain\Exceptions\MediaUploadRefusedException;
use SpeedySpec\Block\Domain\ValueObjects\MediaUpload;

/**
 * Decides whether an uploaded file may go into the media library.
 *
 * Its type is the one {@link MediaTypeDetector} finds in the content; what the file is called or declared as is
 * not consulted, so a page cannot be made to serve markup under an image's name.
 *
 * @since 0.1.0
 */
class MediaUploadPolicy
{
    public const int DEFAULT_MAX_BYTES = 20 * 1024 * 1024;

    /**
     * @param int $maxBytes
     *   The largest file the host accepts.
     *
     * @since 0.1.0
     */
    public function __construct(
        private readonly MediaTypeDetector $detector,
        private readonly int $maxBytes = self::DEFAULT_MAX_BYTES,
    )
    {
    }

    /**
     * Checks one upload and describes it with its real type.
     *
     * @throws MediaUploadRefusedException
     *   When the file is empty, unnamed, too large or of a kind not accepted.
     *
     * @since 0.1.0
     */
    public function check(string $name, string $bytes): MediaUpload
    {
        if ($bytes === '')
        {
            throw new MediaUploadRefusedException(400, 'The file is empty.');
        }

        if (trim($name) === '')
        {
            throw new MediaUploadRefusedException(400, 'Give the file a name.');
        }

        if (strlen($bytes) > $this->maxBytes)
        {
            throw new MediaUploadRefusedException(413, sprintf(
                'The file is larger than the limit of %s MB.',
                round($this->maxBytes / (1024 * 1024), 1),
            ));
        }

        $type = $this->detector->detect($bytes);

        if ($type === null)
        {
            throw new MediaUploadRefusedException(415, 'Only images, video, audio and PDF files can be uploaded.');
        }

        return new MediaUpload($name, $type, $bytes);
    }
}
