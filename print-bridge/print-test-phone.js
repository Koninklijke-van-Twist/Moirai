'use strict';

const http = require('http');

const HOST = process.env.MOIRAI_BRIDGE_HOST || '127.0.0.1';
const PORT = Number(process.env.MOIRAI_BRIDGE_PORT || 9173);
const BASE_URL = process.env.MOIRAI_TEST_BASE_URL || 'http://localhost/Moirai/web/index.php';

const payload = {
    logo: true,
    title: 'iPhone 15 Pro',
    id: '359123456789012',
    qrUrl: `${BASE_URL}?t=p&d=359123456789012`,
    lines: [
        { label: 'Scherm', value: '6.1"' },
        { label: 'Opslag', value: '256 GB' },
        { label: 'Aanschaf', value: '9 jun 2026' },
        { label: 'OS', value: 'iOS 18.5' },
    ],
};

const body = JSON.stringify(payload);

const req = http.request({
    hostname: HOST,
    port: PORT,
    path: '/print',
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(body),
    },
}, (res) => {
    let data = '';
    res.on('data', (chunk) => {
        data += chunk;
    });
    res.on('end', () => {
        console.log(res.statusCode, data);
        process.exit(res.statusCode === 200 ? 0 : 1);
    });
});

req.on('error', (err) => {
    console.error(err.message);
    process.exit(1);
});

req.write(body);
req.end();
