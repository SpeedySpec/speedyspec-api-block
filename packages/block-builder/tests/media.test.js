import assert from 'node:assert/strict';
import { test } from 'node:test';

import { accepts, addedMedia, mediaQuery, movedMedia, withMedia, withoutMediaAt } from '../src/media.js';

test('a media query leaves out what is empty', () => {
    assert.deepEqual(mediaQuery('', 'cat', 2), { search: 'cat', page: 2, perPage: 24 });
    assert.deepEqual(mediaQuery('image', '', 1), { kind: 'image', page: 1, perPage: 24 });
    assert.deepEqual(mediaQuery('video', '  dog ', 1), { kind: 'video', search: 'dog', page: 1, perPage: 24 });
});

test('an image is added to a list once', () => {
    const list = ['a', 'b'];

    assert.deepEqual(withMedia(list, 'c'), ['a', 'b', 'c']);
    assert.deepEqual(withMedia(list, 'a'), ['a', 'b']);
    assert.deepEqual(withMedia(undefined, 'a'), ['a']);
    assert.deepEqual(list, ['a', 'b']);
});

test('an image is taken out of a list by position', () => {
    assert.deepEqual(withoutMediaAt(['a', 'b', 'c'], 1), ['a', 'c']);
    assert.deepEqual(withoutMediaAt(['a'], 5), ['a']);
});

test('an image moves within a list, and moving past either end changes nothing', () => {
    assert.deepEqual(movedMedia(['a', 'b', 'c'], 0, 1), ['b', 'a', 'c']);
    assert.deepEqual(movedMedia(['a', 'b', 'c'], 2, 1), ['a', 'c', 'b']);
    assert.deepEqual(movedMedia(['a', 'b'], 0, -1), ['a', 'b']);
    assert.deepEqual(movedMedia(['a', 'b'], 1, 2), ['a', 'b']);
});

test('the file chooser offers only files of the kind wanted', () => {
    assert.equal(accepts('image'), 'image/*');
    assert.equal(accepts('video'), 'video/*');
    assert.equal(accepts('audio'), 'audio/*');
    assert.equal(accepts('file'), 'image/*,video/*,audio/*,application/pdf');
    assert.equal(accepts(''), 'image/*,video/*,audio/*,application/pdf');
});

test('an image is added to the settings as they are when the choice is sent, keeping everything else', () => {
    const latest = { media: ['a'], caption: 'Typed meanwhile' };

    assert.deepEqual(addedMedia(latest, 'media', 'b'), { media: ['a', 'b'], caption: 'Typed meanwhile' });
    assert.deepEqual(addedMedia(addedMedia({}, 'media', 'a'), 'media', 'b'), { media: ['a', 'b'] });
    assert.deepEqual(addedMedia({ media: 'not a list' }, 'media', 'a'), { media: ['a'] });
    assert.deepEqual(latest, { media: ['a'], caption: 'Typed meanwhile' });
});
