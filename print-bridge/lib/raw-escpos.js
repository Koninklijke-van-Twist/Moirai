'use strict';

const qr = require('qr-image');
const getPixels = require('get-pixels');
const Image = require('escpos/image');

const ESC = 0x1b;
const GS = 0x1d;

function textLine(text) {
    return Buffer.from(`${String(text)}\n`, 'ascii');
}

function initBuffer() {
    return Buffer.from([ESC, 0x40]);
}

function alignCenter() {
    return Buffer.from([ESC, 0x61, 0x01]);
}

function feedLines(count = 1) {
    return Buffer.from([ESC, 0x64, Math.max(0, Math.min(255, count))]);
}

function cutPartial() {
    return Buffer.from([GS, 0x56, 0x01]);
}

function gsK(fn, params) {
    const body = Buffer.from(params);
    const len = body.length + 2;
    return Buffer.concat([
        Buffer.from([GS, 0x28, 0x6b, len & 0xff, (len >> 8) & 0xff, 0x31, fn]),
        body,
    ]);
}

function gsKStore(data, storeM) {
    const payload = Buffer.from(data, 'utf8');
    const storeLen = payload.length + 3;
    const store = Buffer.alloc(8 + payload.length);
    store[0] = GS;
    store[1] = 0x28;
    store[2] = 0x6b;
    store[3] = storeLen & 0xff;
    store[4] = (storeLen >> 8) & 0xff;
    store[5] = 0x31;
    store[6] = 0x50;
    store[7] = storeM;
    payload.copy(store, 8);
    return store;
}

function buildEpsonQrBuffer(content) {
    const data = Buffer.from(String(content), 'utf8');
    return Buffer.concat([
        gsK(0x41, [0x32, 0x00]),
        gsK(0x43, [6]),
        gsK(0x45, [49]),
        gsKStore(data, 0x30),
        gsK(0x51, [0x30]),
    ]);
}

function buildGoojprtQrBuffer(content) {
    const data = Buffer.from(String(content), 'utf8');
    return Buffer.concat([
        gsK(0x41, [0x00]),
        gsK(0x42, [6]),
        gsK(0x43, [0x00]),
        gsK(0x45, [0x02]),
        gsKStore(data, 0x31),
        gsK(0x51, [0x31]),
    ]);
}

function buildBarcodeSetup() {
    return Buffer.from([
        GS, 0x77, 0x02,
        GS, 0x68, 0x50,
        GS, 0x48, 0x00,
    ]);
}

function buildCode39Buffer(text) {
    const payload = Buffer.from(String(text).toUpperCase(), 'ascii');
    return Buffer.concat([
        buildBarcodeSetup(),
        Buffer.from([GS, 0x6b, 0x04]),
        payload,
        Buffer.from([0x00]),
    ]);
}

function buildCode128Format1Buffer(text, withPrefix) {
    const raw = withPrefix ? `{B${String(text)}` : String(text);
    const payload = Buffer.from(raw, 'ascii');
    return Buffer.concat([
        buildBarcodeSetup(),
        Buffer.from([GS, 0x6b, 0x08]),
        payload,
        Buffer.from([0x00]),
    ]);
}

function buildCode128Format2Buffer(text, withPrefix) {
    const raw = withPrefix ? `{B${String(text)}` : String(text);
    const payload = Buffer.from(raw, 'ascii');
    const command = Buffer.alloc(4 + payload.length);
    command[0] = GS;
    command[1] = 0x6b;
    command[2] = 0x49;
    command[3] = payload.length;
    payload.copy(command, 4);
    return Buffer.concat([buildBarcodeSetup(), command]);
}

function buildRasterQrBuffer(content) {
    const png = qr.imageSync(String(content), { type: 'png', margin: 1 });

    return new Promise((resolve, reject) => {
        getPixels(png, 'image/png', (err, pixels) => {
            if (err) {
                reject(err);
                return;
            }

            const raster = new Image(pixels).toRaster();
            const width = Buffer.alloc(2);
            const height = Buffer.alloc(2);
            width.writeUInt16LE(raster.width, 0);
            height.writeUInt16LE(raster.height, 0);

            resolve(Buffer.concat([
                Buffer.from([GS, 0x76, 0x30, 0x00]),
                width,
                height,
                Buffer.from(raster.data),
            ]));
        });
    });
}

async function buildRawDiagnosticBuffer(options = {}) {
    const content = String(options.content || 'https://example.com/#l/TEST123').trim();
    const shortCode = String(options.shortCode || 'TEST123').trim();
    const rasterQr = await buildRasterQrBuffer(content);

    return Buffer.concat([
        initBuffer(),
        alignCenter(),
        textLine('RAW bytes test'),
        feedLines(1),

        textLine('1 Epson QR'),
        buildEpsonQrBuffer(content),
        feedLines(3),

        textLine('2 GOOJPRT QR'),
        buildGoojprtQrBuffer(content),
        feedLines(3),

        textLine('3 Raster QR'),
        rasterQr,
        feedLines(3),

        textLine('4 CODE39'),
        buildCode39Buffer(shortCode),
        feedLines(3),

        textLine('5 CODE128 f1'),
        buildCode128Format1Buffer(shortCode, false),
        feedLines(3),

        textLine('6 CODE128 f2'),
        buildCode128Format2Buffer(shortCode, false),
        feedLines(3),

        textLine('7 CODE128 f2 {B'),
        buildCode128Format2Buffer(shortCode, true),
        feedLines(4),
        cutPartial(),
    ]);
}

module.exports = {
    buildRawDiagnosticBuffer,
    buildEpsonQrBuffer,
    buildGoojprtQrBuffer,
    buildRasterQrBuffer,
    buildCode39Buffer,
    buildCode128Format1Buffer,
    buildCode128Format2Buffer,
};
