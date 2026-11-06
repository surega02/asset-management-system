import { copyFile, mkdir } from 'node:fs/promises';

await mkdir('public/build', { recursive: true });
await Promise.all([
    copyFile('node_modules/jsqr/dist/jsQR.js', 'public/build/jsQR.js'),
    copyFile('node_modules/jsqr/LICENSE', 'public/build/jsQR.LICENSE'),
    copyFile('resources/js/asset-scanner.mjs', 'public/build/asset-scanner.mjs'),
    copyFile('resources/js/asset-label-print.mjs', 'public/build/asset-label-print.mjs'),
]);
