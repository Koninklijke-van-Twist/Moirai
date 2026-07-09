'use strict';

const http = require('http');
const { getStatus, queuePrint, queueBarcodeTest, queueQrTest, queueQrBarcodeTest, queueRawTest, queueBarcodeRawTest, queueBarcodeUrlTest, queueUsbBarcodeTest, ensurePrinterReady } = require('./printer');

const HOST = process.env.MOIRAI_BRIDGE_HOST || '127.0.0.1';
const PORT = Number(process.env.MOIRAI_BRIDGE_PORT || 9173);
const MAX_BODY_BYTES = 64 * 1024;

function sendJson(res, statusCode, payload) {
    const body = JSON.stringify(payload);
    res.writeHead(statusCode, {
        'Content-Type': 'application/json; charset=utf-8',
        'Content-Length': Buffer.byteLength(body),
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type',
    });
    res.end(body);
}

function readJsonBody(req) {
    return new Promise((resolve, reject) => {
        let size = 0;
        const chunks = [];

        req.on('data', (chunk) => {
            size += chunk.length;
            if (size > MAX_BODY_BYTES) {
                reject(new Error('Request body too large'));
                req.destroy();
                return;
            }
            chunks.push(chunk);
        });

        req.on('end', () => {
            if (chunks.length === 0) {
                resolve({});
                return;
            }

            try {
                resolve(JSON.parse(Buffer.concat(chunks).toString('utf8')));
            } catch (err) {
                reject(new Error('Invalid JSON body'));
            }
        });

        req.on('error', reject);
    });
}

function startServer() {
    const server = http.createServer(async (req, res) => {
        if (req.method === 'OPTIONS') {
            sendJson(res, 204, {});
            return;
        }

        const url = new URL(req.url || '/', `http://${HOST}:${PORT}`);

        if (req.method === 'GET' && url.pathname === '/health') {
            sendJson(res, 200, getStatus());
            return;
        }

        if (req.method === 'POST' && url.pathname === '/print') {
            try {
                const payload = await readJsonBody(req);
                const status = await ensurePrinterReady();

                sendJson(res, 200, {
                    ok: true,
                    queued: true,
                    printer: status.printer,
                    transport: status.transport,
                });

                queuePrint(payload);
            } catch (err) {
                sendJson(res, 503, {
                    ok: false,
                    error: err.message || 'Print failed',
                    ...getStatus(),
                });
            }
            return;
        }

        if (req.method === 'POST' && url.pathname === '/test/barcode') {
            try {
                const payload = await readJsonBody(req);
                const status = await ensurePrinterReady();

                sendJson(res, 200, {
                    ok: true,
                    queued: true,
                    test: 'barcode',
                    transport: status.transport,
                    printer: status.printer,
                });

                queueBarcodeTest(payload);
            } catch (err) {
                sendJson(res, 503, {
                    ok: false,
                    error: err.message || 'Barcode test failed',
                    ...getStatus(),
                });
            }
            return;
        }

        if (req.method === 'POST' && url.pathname === '/test/qr') {
            try {
                const payload = await readJsonBody(req);
                const status = await ensurePrinterReady();

                sendJson(res, 200, {
                    ok: true,
                    queued: true,
                    test: 'qr',
                    transport: status.transport,
                    printer: status.printer,
                });

                queueQrTest(payload);
            } catch (err) {
                sendJson(res, 503, {
                    ok: false,
                    error: err.message || 'QR test failed',
                    ...getStatus(),
                });
            }
            return;
        }

        if (req.method === 'POST' && url.pathname === '/test/qr-barcode') {
            try {
                const payload = await readJsonBody(req);
                const status = await ensurePrinterReady();

                sendJson(res, 200, {
                    ok: true,
                    queued: true,
                    test: 'qr-barcode',
                    transport: status.transport,
                    printer: status.printer,
                });

                queueQrBarcodeTest(payload);
            } catch (err) {
                sendJson(res, 503, {
                    ok: false,
                    error: err.message || 'QR+barcode test failed',
                    ...getStatus(),
                });
            }
            return;
        }

        if (req.method === 'POST' && url.pathname === '/test/raw') {
            try {
                const payload = await readJsonBody(req);
                const status = await ensurePrinterReady();

                sendJson(res, 200, {
                    ok: true,
                    queued: true,
                    test: 'raw',
                    transport: status.transport,
                    printer: status.printer,
                });

                queueRawTest(payload);
            } catch (err) {
                sendJson(res, 503, {
                    ok: false,
                    error: err.message || 'Raw test failed',
                    ...getStatus(),
                });
            }
            return;
        }

        if (req.method === 'POST' && url.pathname === '/test/barcode-raw') {
            try {
                const payload = await readJsonBody(req);
                const status = await ensurePrinterReady();

                sendJson(res, 200, {
                    ok: true,
                    queued: true,
                    test: 'barcode-raw',
                    transport: status.transport,
                    printer: status.printer,
                });

                queueBarcodeRawTest(payload);
            } catch (err) {
                sendJson(res, 503, {
                    ok: false,
                    error: err.message || 'Barcode raw test failed',
                    ...getStatus(),
                });
            }
            return;
        }

        if (req.method === 'POST' && url.pathname === '/test/barcode-url') {
            try {
                const payload = await readJsonBody(req);
                const status = await ensurePrinterReady();

                sendJson(res, 200, {
                    ok: true,
                    queued: true,
                    test: 'barcode-url',
                    transport: status.transport,
                    printer: status.printer,
                });

                queueBarcodeUrlTest(payload);
            } catch (err) {
                sendJson(res, 503, {
                    ok: false,
                    error: err.message || 'Barcode URL test failed',
                    ...getStatus(),
                });
            }
            return;
        }

        if (req.method === 'POST' && url.pathname === '/test/usb') {
            try {
                sendJson(res, 200, {
                    ok: true,
                    queued: true,
                    test: 'usb-barcode',
                    hint: 'Requires WinUSB via Zadig. See print-bridge/setup-usb-direct.ps1',
                });
                queueUsbBarcodeTest();
            } catch (err) {
                sendJson(res, 503, {
                    ok: false,
                    error: err.message || 'USB test failed',
                    ...getStatus(),
                });
            }
            return;
        }

        sendJson(res, 404, { ok: false, error: 'Not found' });
    });

    server.listen(PORT, HOST, () => {
        process.stdout.write(`Moirai print bridge listening on http://${HOST}:${PORT}\n`);
    });

    return server;
}

module.exports = {
    startServer,
};
