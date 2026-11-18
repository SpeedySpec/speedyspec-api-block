<?php

declare(strict_types=1);

use SpeedySpec\Block\App\Media\MediaTypeDetector;

test('a file type is read from its first bytes', function (string $bytes, string $type) {
    expect((new MediaTypeDetector())->detect($bytes))->toBe($type);
})->with([
    'jpeg' => ["\xFF\xD8\xFF\xE0rest", 'image/jpeg'],
    'png' => ["\x89PNG\r\n\x1A\nrest", 'image/png'],
    'gif87' => ['GIF87a....', 'image/gif'],
    'gif89' => ['GIF89a....', 'image/gif'],
    'webp' => ['RIFF' . "\x00\x00\x00\x00" . 'WEBPVP8 ', 'image/webp'],
    'avif' => ["\x00\x00\x00\x1Cftypavif", 'image/avif'],
    'avif sequence' => ["\x00\x00\x00\x1Cftypavis", 'image/avif'],
    'mp4' => ["\x00\x00\x00\x18ftypisom", 'video/mp4'],
    'mp4 version 2' => ["\x00\x00\x00\x18ftypmp42", 'video/mp4'],
    'avif under a general brand' => ["\x00\x00\x00\x1Cftypmif1\x00\x00\x00\x00mif1avifmiaf", 'image/avif'],
    'webm' => ["\x1A\x45\xDF\xA3rest", 'video/webm'],
    'mp3 tag' => ['ID3' . "\x04\x00", 'audio/mpeg'],
    'mp3 frame' => ["\xFF\xFB\x90\x00", 'audio/mpeg'],
    'mp3 frame mpeg2' => ["\xFF\xE3\x18\x00", 'audio/mpeg'],
    'ogg' => ['OggS' . "\x00\x02", 'audio/ogg'],
    'wav' => ['RIFF' . "\x00\x00\x00\x00" . 'WAVEfmt ', 'audio/wav'],
    'pdf' => ['%PDF-1.7', 'application/pdf'],
    'image then markup' => ["\xFF\xD8\xFF" . '<script>', 'image/jpeg'],
]);

test('anything else has no type', function (string $bytes) {
    expect((new MediaTypeDetector())->detect($bytes))->toBeNull();
})->with([
    'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"></svg>'],
    'html' => ['<!DOCTYPE html><p>x'],
    'too short gif' => ['GIF8'],
    'empty' => [''],
    'ftyp with no brand' => ["\x00\x00\x00\x18ftyp"],
    'riff of another kind' => ['RIFF' . "\x00\x00\x00\x00" . 'AVI LIST'],
    'text' => ['hello'],
    'iphone photo' => ["\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic"],
    'heif without avif' => ["\x00\x00\x00\x18ftypmif1\x00\x00\x00\x00mif1heic"],
    'm4a audio' => ["\x00\x00\x00\x20ftypM4A \x00\x00\x00\x00"],
    'quicktime' => ["\x00\x00\x00\x14ftypqt  "],
    'made-up brand' => ['AAAAftypABCD<html>'],
]);
