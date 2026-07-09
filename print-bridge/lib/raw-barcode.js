'use strict';

const ESC = 0x1b;
const GS = 0x1d;

const BARCODE_MODE_ON = Buffer.from([GS, 0x45, 0x43, 0x01]);
const BARCODE_MODE_OFF = Buffer.from([GS, 0x45, 0x43, 0x00]);

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

function barcodeSetup(options = {}) {
    const width = Math.max(1, Math.min(6, Number(options.width ?? 2)));
    const height = Math.max(1, Math.min(255, Number(options.height ?? 80)));
    const hri = Number(options.hri ?? 0);

    return Buffer.from([
        GS, 0x77, width,
        GS, 0x68, height,
        GS, 0x48, hri,
    ]);
}

function asciiData(text) {
    return Buffer.from(String(text), 'ascii');
}

function code128Data(text, withPrefix) {
    const escaped = String(text).replace(/\{/g, '{{');
    const raw = withPrefix ? `{B${escaped}` : escaped;
    return asciiData(raw);
}

function format1(type, data, options = {}) {
    const payload = Buffer.isBuffer(data) ? data : asciiData(data);
    const body = Buffer.concat([
        barcodeSetup(options),
        Buffer.from([GS, 0x6b, type]),
        payload,
        Buffer.from([0x00]),
    ]);

    if (!options.barcodeMode) {
        return body;
    }

    return Buffer.concat([BARCODE_MODE_ON, body, BARCODE_MODE_OFF]);
}

function format1NulAfterType(type, data, options = {}) {
    const payload = Buffer.isBuffer(data) ? data : asciiData(data);
    const body = Buffer.concat([
        barcodeSetup(options),
        Buffer.from([GS, 0x6b, type, 0x00]),
        payload,
        Buffer.from([0x00]),
    ]);

    if (!options.barcodeMode) {
        return body;
    }

    return Buffer.concat([BARCODE_MODE_ON, body, BARCODE_MODE_OFF]);
}

function format2(type, data, options = {}) {
    const payload = Buffer.isBuffer(data) ? data : asciiData(data);
    const command = Buffer.alloc(4 + payload.length);
    command[0] = GS;
    command[1] = 0x6b;
    command[2] = type;
    command[3] = payload.length;
    payload.copy(command, 4);

    const body = Buffer.concat([barcodeSetup(options), command]);

    if (!options.barcodeMode) {
        return body;
    }

    return Buffer.concat([BARCODE_MODE_ON, body, BARCODE_MODE_OFF]);
}

function section(label, body, extraText) {
    const parts = [
        alignCenter(),
        textLine(label),
        feedLines(1),
        body,
        feedLines(2),
    ];

    if (extraText) {
        parts.push(alignCenter(), textLine(extraText), feedLines(1));
    }

    parts.push(feedLines(1));
    return Buffer.concat(parts);
}

function toCode39Safe(text) {
    return String(text)
        .toUpperCase()
        .replace(/[^0-9A-Z $%+\-./]/g, '-');
}

function buildWorkingCode39Barcode(text, options = {}) {
    return format1(0x04, toCode39Safe(text), {
        barcodeMode: true,
        hri: 2,
        ...options,
    });
}

function buildWorkingCode128Barcode(text, options = {}) {
    return format1(0x08, code128Data(text, true), {
        barcodeMode: true,
        hri: 2,
        ...options,
    });
}

function buildBarcodeUrlTestBuffer(options = {}) {
    const url = String(
        options.url
        || 'https://sleutels.kvt.nl/moirai/#l/TEST123'
    ).trim();
    const code39Url = toCode39Safe(url);

    return Buffer.concat([
        initBuffer(),
        alignCenter(),
        textLine('Barcode URL test'),
        feedLines(1),

        section('1 C39 mode (safe)', buildWorkingCode39Barcode(url), `DATA ${code39Url}`),
        section('2 C128 mode URL', buildWorkingCode128Barcode(url)),
        section('3 C128 mode #l', buildWorkingCode128Barcode('https://sleutels.kvt.nl/moirai/#l/TEST123')),
        section('4 C128 mode #p', buildWorkingCode128Barcode('https://sleutels.kvt.nl/moirai/#p/359123456789012')),

        alignCenter(),
        textLine('Origineel:'),
        textLine(url),
        feedLines(3),
        cutPartial(),
    ]);
}

function buildRawBarcodeDiagnosticBuffer(options = {}) {
    const code39 = String(options.code39 || 'TEST123').toUpperCase();
    const code128 = String(options.code128 || 'TEST123');
    const ean13 = String(options.ean13 || '359123456789').replace(/\D/g, '').slice(0, 12);
    const ean8 = String(options.ean8 || '7351353').replace(/\D/g, '').slice(0, 7);
    const itf = String(options.itf || '1234567890').replace(/\D/g, '');
    const codabar = String(options.codabar || 'A40156A').toUpperCase();

    const sections = [
        section('1 F1 CODE39', format1(0x04, code39)),
        section('2 F2 CODE39', format2(0x45, code39)),
        section('3 F1 CODE39 mode', format1(0x04, code39, { barcodeMode: true })),
        section('4 F1 C128 {B', format1(0x08, code128Data(code128, true))),
        section('5 F2 C128 {B', format2(0x49, code128Data(code128, true))),
        section('6 F1 C128 raw', format1(0x08, code128)),
        section('7 F2 C128 raw', format2(0x49, code128)),
        section('8 F1 C128 NUL', format1NulAfterType(0x08, code128Data(code128, true))),
        section('9 F1 EAN13', format1(0x02, ean13)),
        section('10 F2 EAN13', format2(0x43, ean13)),
        section('11 F1 EAN8', format1(0x03, ean8)),
        section('12 F2 EAN8', format2(0x44, ean8)),
        section('13 F1 ITF', format1(0x05, itf.length % 2 === 0 ? itf : `${itf}0`)),
        section('14 F2 ITF', format2(0x46, itf.length % 2 === 0 ? itf : `${itf}0`)),
        section('15 F1 CODABAR', format1(0x06, codabar)),
        section('16 F2 CODABAR', format2(0x47, codabar)),
        section('17 F1 CODE93', format1(0x07, code128)),
        section('18 F2 CODE93', format2(0x48, code128)),
        section('19 F2 C128 HRI', format2(0x49, code128Data(code128, true), { hri: 2 })),
        section('20 F1 C39 wide', format1(0x04, code39, { width: 3, height: 100 })),
    ];

    return Buffer.concat([
        initBuffer(),
        alignCenter(),
        textLine('Barcode raw test'),
        feedLines(1),
        ...sections,
        cutPartial(),
    ]);
}

module.exports = {
    BARCODE_MODE_ON,
    BARCODE_MODE_OFF,
    barcodeSetup,
    format1,
    format1NulAfterType,
    format2,
    buildWorkingCode39Barcode,
    buildWorkingCode128Barcode,
    buildBarcodeUrlTestBuffer,
    buildRawBarcodeDiagnosticBuffer,
};
