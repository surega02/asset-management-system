export function scanTarget(value, prefix) {
    if (typeof value !== 'string' || !value.startsWith(prefix)) return null;
    const token = value.slice(prefix.length);
    if (!/^[a-f0-9]{64}$/.test(token) || token.length !== 64) return null;
    return new URL(prefix).pathname + token;
}

export function bindAssetScanner(root, environment = globalThis) {
    const video = root.querySelector('video');
    const canvas = root.querySelector('canvas');
    const start = root.querySelector('[data-start]');
    const stop = root.querySelector('[data-stop]');
    const message = root.querySelector('[data-message]');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    let stream;
    let timer;
    let run = 0;

    function reset() {
        run++;
        environment.clearTimeout(timer);
        stream?.getTracks().forEach(track => track.stop());
        stream = null;
        video.srcObject = null;
        video.hidden = true;
        stop.hidden = true;
        start.disabled = false;
    }

    function sample() {
        if (!stream) return;
        try {
            if (video.videoWidth && video.videoHeight && video.readyState >= 2) {
                const scale = Math.min(1, 640 / Math.max(video.videoWidth, video.videoHeight));
                canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
                canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
                const result = environment.jsQR(pixels.data, pixels.width, pixels.height, { inversionAttempts: 'dontInvert' });
                const target = scanTarget(result?.data, root.dataset.prefix);
                if (target) {
                    reset();
                    environment.location.assign(target);
                    return;
                }
                if (result) message.textContent = root.dataset.invalid;
            }
            timer = environment.setTimeout(sample, 180);
        } catch {
            reset();
            message.textContent = root.dataset.unavailable;
        }
    }

    start.addEventListener('click', async () => {
        if (!environment.navigator.mediaDevices?.getUserMedia || typeof environment.jsQR !== 'function' || !context) {
            message.textContent = root.dataset.unavailable;
            return;
        }
        const attempt = ++run;
        start.disabled = true;
        stop.hidden = false;
        message.textContent = root.dataset.requesting;
        try {
            const granted = await environment.navigator.mediaDevices.getUserMedia({
                audio: false, video: { facingMode: { ideal: 'environment' }, width: { ideal: 640 }, height: { ideal: 480 } },
            });
            if (attempt !== run) {
                granted.getTracks().forEach(track => track.stop());
                return;
            }
            stream = granted;
            stream.getVideoTracks().forEach(track => track.addEventListener('ended', () => {
                if (attempt !== run) return;
                reset();
                message.textContent = root.dataset.unavailable;
            }, { once: true }));
            video.srcObject = stream;
            video.hidden = false;
            await video.play();
            if (attempt !== run) return;
            message.textContent = root.dataset.scanning;
            sample();
        } catch (error) {
            if (attempt !== run) return;
            reset();
            message.textContent = error?.name === 'NotAllowedError' || error?.name === 'SecurityError'
                ? root.dataset.denied : root.dataset.unavailable;
        }
    });

    stop.addEventListener('click', () => {
        reset();
        message.textContent = root.dataset.stopped;
        start.focus();
    });
    environment.addEventListener('pagehide', reset);
    environment.document.addEventListener('visibilitychange', () => {
        if (environment.document.hidden && start.disabled) {
            reset();
            message.textContent = root.dataset.stopped;
        }
    });
}

const scanner = globalThis.document?.querySelector('[data-asset-scanner]');
if (scanner) bindAssetScanner(scanner);
