import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bindAssetScanner, scanTarget } from '../../resources/js/asset-scanner.mjs';

const prefix = 'https://ams.example.test/sub/q/';
const token = 'a'.repeat(64);

test('only canonical AMS QR values navigate to the local result path', () => {
    assert.equal(scanTarget(prefix + token, prefix), '/sub/q/' + token);
    for (const value of [
        'https://evil.example/sub/q/' + token,
        'https://ams.example.test@evil.example/sub/q/' + token,
        prefix + token + '?next=https://evil.example',
        prefix + token + '\n',
        prefix + '123',
        null,
    ]) assert.equal(scanTarget(value, prefix), null);
});

function setup(getUserMedia) {
    const callbacks = new Map();
    const start = { addEventListener: (_, listener) => callbacks.set('start', listener), focus() {} };
    const stop = { hidden: true, addEventListener: (_, listener) => callbacks.set('stop', listener), focus() {} };
    const video = { hidden: true };
    const message = { textContent: '' };
    const elements = { video, canvas: { getContext: () => ({}) }, '[data-start]': start, '[data-stop]': stop, '[data-message]': message };
    const root = { querySelector: selector => elements[selector], dataset: { denied: 'manual', requesting: 'requesting', stopped: 'stopped' } };
    bindAssetScanner(root, { jsQR() {}, navigator: { mediaDevices: { getUserMedia } }, clearTimeout() {}, addEventListener() {}, document: { addEventListener() {} } });
    return { callbacks, start, stop, video, message };
}

test('camera permission denial returns controls to manual fallback', async () => {
    const { callbacks, start, stop, video, message } = setup(async () => {
        throw Object.assign(new Error('denied'), { name: 'NotAllowedError' });
    });
    await callbacks.get('start')();
    assert.equal(message.textContent, 'manual');
    assert.equal(start.disabled, false);
    assert.equal(stop.hidden, true);
    assert.equal(video.srcObject, null);
});

test('a camera granted after cancellation is released without starting video', async () => {
    let resolve, stopped = 0;
    const { callbacks, video } = setup(() => new Promise(done => { resolve = done; }));
    const pending = callbacks.get('start')();
    callbacks.get('stop')();
    resolve({ getTracks: () => [{ stop: () => stopped++ }] });
    await pending;
    assert.equal(stopped, 1);
    assert.equal(video.srcObject, null);
});
