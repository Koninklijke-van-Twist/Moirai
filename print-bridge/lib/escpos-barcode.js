'use strict';

function escapeCode128Data(text) {
    return String(text ?? '').replace(/\{/g, '{{');
}

function useCode128Prefix() {
    return String(process.env.MOIRAI_CODE128_PREFIX ?? '1') !== '0';
}

function code128Payload(text) {
    const code = escapeCode128Data(text);
    if (!useCode128Prefix() || code.startsWith('{')) {
        return code;
    }

    return `{B${code}`;
}

function resolveCode128Format() {
    const profile = String(process.env.MOIRAI_PRINTER_PROFILE || 'goojprt').toLowerCase();
    const configured = String(process.env.MOIRAI_CODE128_FORMAT || '').trim();

    if (configured) {
        return configured;
    }

    return profile === 'goojprt' ? '2' : '1';
}

function buildBarcodeSetup(options = {}) {
    const height = Math.max(1, Math.min(255, Number(options.height || 80)));
    const width = Math.max(1, Math.min(6, Number(options.width || 2)));
    const hri = options.position === 'BLW' ? 2 : 0;

    return Buffer.from([
        0x1d, 0x77, width,
        0x1d, 0x68, height,
        0x1d, 0x48, hri,
    ]);
}

function buildCode128Buffer(text, options = {}) {
    const payload = Buffer.from(code128Payload(text), 'ascii');

    if (payload.length < 1 || payload.length > 255) {
        throw new Error('CODE128 payload length out of range');
    }

    const setup = buildBarcodeSetup(options);

    if (resolveCode128Format() === '2') {
        const command = Buffer.alloc(4 + payload.length);
        command[0] = 0x1d;
        command[1] = 0x6b;
        command[2] = 0x49;
        command[3] = payload.length;
        payload.copy(command, 4);
        return Buffer.concat([setup, command]);
    }

    const command = Buffer.concat([
        Buffer.from([0x1d, 0x6b, 0x08]),
        payload,
        Buffer.from([0x00]),
    ]);

    return Buffer.concat([setup, command]);
}

function buildCode39Buffer(text, options = {}) {
    const payload = Buffer.from(String(text ?? '').toUpperCase(), 'ascii');
    const setup = buildBarcodeSetup(options);

    if (!payload.length || payload.length > 255) {
        throw new Error('CODE39 payload length out of range');
    }

    const command = Buffer.concat([
        Buffer.from([0x1d, 0x6b, 0x04]),
        payload,
        Buffer.from([0x00]),
    ]);

    return Buffer.concat([setup, command]);
}

function appendCode128(printer, text, options = {}) {
    printer.print(buildCode128Buffer(text, options));
    return printer;
}

function appendCode39(printer, text, options = {}) {
    printer.print(buildCode39Buffer(text, options));
    return printer;
}

module.exports = {
    appendCode128,
    appendCode39,
    buildCode128Buffer,
    buildCode39Buffer,
    code128Payload,
};
