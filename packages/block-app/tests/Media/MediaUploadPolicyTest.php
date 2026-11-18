<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Media\MediaTypeDetector;
use SpeedySpec\Block\App\Media\MediaUploadPolicy;
use SpeedySpec\Block\Domain\Exceptions\MediaUploadRefusedException;

const PNG = "\x89PNG\r\n\x1A\n";
const REFUSED_KIND = 'Only images, video, audio and PDF files can be uploaded.';

function refusal(callable $check): MediaUploadRefusedException
{
    try
    {
        $check();
    }
    catch (MediaUploadRefusedException $e)
    {
        return $e;
    }

    throw new RuntimeException('The upload was accepted.');
}

test('an upload takes the type found in its content, whatever it is called', function () {
    $upload = (new MediaUploadPolicy(new MediaTypeDetector()))->check('notes.txt', PNG . 'data');

    expect($upload->mimeType)->toBe('image/png')->and($upload->name)->toBe('notes.txt')
        ->and($upload->bytes)->toBe(PNG . 'data');
});

test('an upload is refused with a status and a reason', function (
    string $name,
    string $bytes,
    int $status,
    string $message,
) {
    $e = refusal(fn () => (new MediaUploadPolicy(new MediaTypeDetector(), 16))->check($name, $bytes));

    expect($e->status)->toBe($status)->and($e->getMessage())->toBe($message);
})->with([
    'empty' => ['a.png', '', 400, 'The file is empty.'],
    'no name' => ['', PNG, 400, 'Give the file a name.'],
    'only spaces' => ['  ', PNG, 400, 'Give the file a name.'],
    'html called a jpeg' => ['photo.jpg', '<!DOCTYPE html>', 415, REFUSED_KIND],
    'svg' => ['logo.svg', '<svg></svg>', 415, REFUSED_KIND],
]);

test('the size limit is the host\'s, and a file of exactly the limit is accepted', function () {
    $policy = new MediaUploadPolicy(new MediaTypeDetector(), 16);
    $e = refusal(fn () => $policy->check('a.png', str_pad(PNG, 17, 'x')));

    expect($policy->check('a.png', str_pad(PNG, 16, 'x'))->mimeType)->toBe('image/png')
        ->and($e->status)->toBe(413);
});

test('the default limit is 20 MB and the message names the limit', function () {
    $policy = new MediaUploadPolicy(new MediaTypeDetector());
    $e = refusal(fn () => $policy->check('a.png', str_pad(PNG, 20 * 1024 * 1024 + 1, 'x')));
    $small = refusal(fn () => (new MediaUploadPolicy(new MediaTypeDetector(), 5 * 1024 * 1024))
        ->check('a.png', str_pad(PNG, 5 * 1024 * 1024 + 1, 'x')));

    expect($e->getMessage())->toBe('The file is larger than the limit of 20 MB.')
        ->and($small->getMessage())->toBe('The file is larger than the limit of 5 MB.');
});
