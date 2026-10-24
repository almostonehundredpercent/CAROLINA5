import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { spawn } from 'node:child_process';
import { createServer, request } from 'node:http';
import { gunzipSync } from 'node:zlib';
import { fileURLToPath } from 'node:url';

const projectRoot = fileURLToPath(new URL('../../', import.meta.url));
let phpServer;
let baseUrl;

async function unusedPort() {
    const listener = createServer();
    await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
    const { port } = listener.address();
    await new Promise((resolve, reject) => listener.close(error => error ? reject(error) : resolve()));
    return port;
}

function get(path, acceptEncoding) {
    return new Promise((resolve, reject) => {
        const url = new URL(path, baseUrl);
        const outgoing = request(url, { headers: acceptEncoding ? { 'Accept-Encoding': acceptEncoding } : {} }, response => {
            const chunks = [];
            response.on('data', chunk => chunks.push(chunk));
            response.on('end', () => resolve({
                status: response.statusCode,
                headers: response.headers,
                body: Buffer.concat(chunks),
            }));
        });
        outgoing.on('error', reject);
        outgoing.end();
    });
}

before(async () => {
    const port = await unusedPort();
    baseUrl = `http://127.0.0.1:${port}`;
    phpServer = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'docker/router.php'], {
        cwd: projectRoot,
        windowsHide: true,
        stdio: 'ignore',
    });

    for (let attempt = 0; attempt < 40; attempt++) {
        if (phpServer.exitCode !== null) throw new Error(`PHP server exited with code ${phpServer.exitCode}`);
        try {
            const response = await get('/css/site.css');
            if (response.status === 200) return;
        } catch {}
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    throw new Error('PHP test server did not start');
});

after(async () => {
    if (!phpServer || phpServer.exitCode !== null) return;
    phpServer.kill();
    await new Promise(resolve => phpServer.once('close', resolve));
});

test('static text assets use gzip without changing their contents or cache variation', async () => {
    const plain = await get('/css/admin.css');
    const compressed = await get('/css/admin.css', 'gzip');

    assert.equal(plain.status, 200);
    assert.equal(compressed.status, 200);
    assert.equal(compressed.headers['content-encoding'], 'gzip');
    assert.match(compressed.headers.vary || '', /Accept-Encoding/i);
    assert.ok(compressed.body.length < plain.body.length / 2, 'gzip should materially reduce the CSS transfer size');
    assert.deepEqual(gunzipSync(compressed.body), plain.body);
});
