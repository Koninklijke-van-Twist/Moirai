'use strict';

const QR_LEVELS_EPSON = {
    L: 48,
    M: 49,
    Q: 50,
    H: 51,
};

const QR_LEVELS_GOOJPRT = {
    L: 1,
    M: 2,
    Q: 3,
    H: 4,
};

function resolvePrinterProfile() {
    return String(process.env.MOIRAI_PRINTER_PROFILE || 'epson').toLowerCase();
}

function resolveQrModuleSize(options = {}) {
    return Math.max(1, Math.min(16, Number(
        options.moduleSize
        ?? process.env.MOIRAI_QR_MODULE_SIZE
        ?? 6
    )));
}

function resolveQrLevelKey(options = {}) {
    return String(options.level || process.env.MOIRAI_QR_LEVEL || 'M').toUpperCase();
}

function gsKCommand(fn, params) {
    const body = Buffer.from(params);
    const len = body.length + 2;
    const header = Buffer.from([0x1d, 0x28, 0x6b, len & 0xff, (len >> 8) & 0xff, 0x31, fn]);
    return Buffer.concat([header, body]);
}

function buildEpsonQrBuffer(content, options = {}) {
    const data = Buffer.from(String(content ?? ''), 'utf8');
    const moduleSize = resolveQrModuleSize(options);
    const level = QR_LEVELS_EPSON[resolveQrLevelKey(options)] ?? QR_LEVELS_EPSON.M;

    if (!data.length) {
        throw new Error('QR content is empty');
    }

    if (data.length > 7092) {
        throw new Error('QR content is too long');
    }

    const setModel = gsKCommand(0x41, [0x32, 0x00]);
    const setSize = gsKCommand(0x43, [moduleSize]);
    const setLevel = gsKCommand(0x45, [level]);

    const storeLen = data.length + 3;
    const store = Buffer.alloc(8 + data.length);
    store[0] = 0x1d;
    store[1] = 0x28;
    store[2] = 0x6b;
    store[3] = storeLen & 0xff;
    store[4] = (storeLen >> 8) & 0xff;
    store[5] = 0x31;
    store[6] = 0x50;
    store[7] = 0x30;
    data.copy(store, 8);

    const print = gsKCommand(0x51, [0x30]);

    return Buffer.concat([setModel, setSize, setLevel, store, print]);
}

function buildGoojprtQrBuffer(content, options = {}) {
    const data = Buffer.from(String(content ?? ''), 'utf8');
    const dotSize = Math.max(2, Math.min(24, resolveQrModuleSize(options)));
    const levelKey = resolveQrLevelKey(options);
    const level = QR_LEVELS_GOOJPRT[levelKey] ?? QR_LEVELS_GOOJPRT.M;

    if (!data.length) {
        throw new Error('QR content is empty');
    }

    if (data.length > 2953) {
        throw new Error('QR content is too long');
    }

    const setEncoding = gsKCommand(0x41, [0x00]);
    const setDotSize = gsKCommand(0x42, [dotSize]);
    const setVersion = gsKCommand(0x43, [0x00]);
    const setLevel = gsKCommand(0x45, [level]);

    const storeLen = data.length + 3;
    const store = Buffer.alloc(8 + data.length);
    store[0] = 0x1d;
    store[1] = 0x28;
    store[2] = 0x6b;
    store[3] = storeLen & 0xff;
    store[4] = (storeLen >> 8) & 0xff;
    store[5] = 0x31;
    store[6] = 0x50;
    store[7] = 0x31;
    data.copy(store, 8);

    const print = gsKCommand(0x51, [0x31]);

    return Buffer.concat([setEncoding, setDotSize, setVersion, setLevel, store, print]);
}

function buildNativeQrBuffer(content, options = {}) {
    if (resolvePrinterProfile() === 'epson') {
        return buildEpsonQrBuffer(content, options);
    }

    return buildGoojprtQrBuffer(content, options);
}

function appendNativeQr(printer, content, options = {}) {
    printer.print(buildNativeQrBuffer(content, options));
    return printer;
}

function useRasterQr() {
    const mode = String(process.env.MOIRAI_QR_MODE || 'epson').toLowerCase();
    return mode === 'raster' || mode === 'image';
}

function useNativeQr() {
    return !useRasterQr();
}

module.exports = {
    appendNativeQr,
    buildNativeQrBuffer,
    buildEpsonQrBuffer,
    buildGoojprtQrBuffer,
    useRasterQr,
    useNativeQr,
};
