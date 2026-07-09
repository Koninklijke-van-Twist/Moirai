'use strict';

const escpos = require('escpos');
const { buildLabelBuffers } = require('./escpos-label');
const { buildBarcodeTestBuffer, buildQrTestBuffer, buildQrBarcodeTestBuffer } = require('./test-print');
const { buildRawDiagnosticBuffer } = require('./raw-escpos');
const { buildRawBarcodeDiagnosticBuffer, buildBarcodeUrlTestBuffer } = require('./raw-barcode');
const {
    findPrinterDevices,
    pickPrinterDevice,
    describeUsbDevice,
    sendRawBuffer,
    sendUsbLabelParts,
} = require('./direct-usb');
const { sleep, estimatePrintDurationMs } = require('./timing');

const SCAN_INTERVAL_MS = Number(process.env.MOIRAI_SCAN_INTERVAL_MS || 5000);
const PRINTER_NAME = String(process.env.MOIRAI_PRINTER_NAME || '').trim();
const PART_WAIT_MS = Number(process.env.MOIRAI_PART_WAIT_MS || 0);
const USB_POST_PRINT_WAIT_MS = Number(process.env.MOIRAI_POST_PRINT_WAIT_MS || 300);
const USE_USB_DIRECT_DEFAULT = process.platform !== 'win32';

function useUsbDirect() {
    if (process.env.MOIRAI_USE_USB_DIRECT === '1') {
        return true;
    }
    if (process.env.MOIRAI_USE_USB_DIRECT === '0') {
        return false;
    }
    return USE_USB_DIRECT_DEFAULT;
}

const PRINTER_NAME_PATTERNS = [
    /gooj/i,
    /pt[- ]?210/i,
    /pos[- ]?58/i,
    /thermal/i,
    /receipt/i,
    /58mm/i,
];

let winSpool = null;
let activeTarget = null;
let scanning = false;
let scanTimer = null;
let printQueue = Promise.resolve();

function log(message) {
    const stamp = new Date().toISOString();
    process.stdout.write(`[${stamp}] ${message}\n`);
}

function loadOptionalModules() {
    if (process.platform === 'win32') {
        try {
            winSpool = require('./win-spool');
        } catch (err) {
            log(`Windows spool helper unavailable: ${err.message}`);
        }
    }
}

function matchesPrinterName(name) {
    return PRINTER_NAME_PATTERNS.some((pattern) => pattern.test(name));
}

function isComPort(port) {
    return /^COM\d+/i.test(String(port || '').trim());
}

function discoverUsbPrinter() {
    if (!useUsbDirect()) {
        return null;
    }

    try {
        const devices = findPrinterDevices();
        const device = pickPrinterDevice(devices);
        if (!device) {
            return null;
        }

        return { kind: 'usb', device };
    } catch (err) {
        log(`USB discovery error: ${err.message}`);
        return null;
    }
}

function isRawDriver(driver) {
    return /generic|text only|raw/i.test(String(driver || ''));
}

async function discoverWindowsPrinter() {
    if (!winSpool) {
        return null;
    }

    const printers = await winSpool.listPrinters();
    const usable = printers.filter((printer) => !isComPort(printer.port));

    if (usable.length === 0) {
        return null;
    }

    if (PRINTER_NAME && !PRINTER_NAME.match(/^vid:/i)) {
        const exact = usable.find((printer) => printer.name === PRINTER_NAME);
        if (exact) {
            return { kind: 'winspool', name: exact.name };
        }
        log(`Configured printer not found: ${PRINTER_NAME}`);
    }

    const rawDriver = usable.find((printer) => isRawDriver(printer.driver));
    if (rawDriver) {
        return { kind: 'winspool', name: rawDriver.name };
    }

    const matched = usable.find((printer) => matchesPrinterName(printer.name));
    if (matched) {
        return { kind: 'winspool', name: matched.name };
    }

    return null;
}

async function discoverPrinter() {
    const usbTarget = discoverUsbPrinter();
    if (usbTarget) {
        return usbTarget;
    }

    if (process.platform === 'win32') {
        return discoverWindowsPrinter();
    }

    return discoverWindowsPrinter();
}

function describeTarget(target) {
    if (!target) {
        return null;
    }

    if (target.kind === 'winspool') {
        return target.name;
    }

    if (target.kind === 'usb' && target.device) {
        return describeUsbDevice(target.device);
    }

    return 'usb';
}

async function scanOnce() {
    if (scanning) {
        return;
    }

    scanning = true;
    try {
        const found = await discoverPrinter();
        const nextName = describeTarget(found);

        if (nextName && (!activeTarget || describeTarget(activeTarget) !== nextName)) {
            activeTarget = found;
            log(`Printer found: ${nextName}`);
        } else if (!nextName && activeTarget) {
            log('Printer disconnected');
            activeTarget = null;
        } else if (!nextName) {
            log('No printer found, scanning...');
        }
    } catch (err) {
        log(`Scan error: ${err.message}`);
        activeTarget = null;
    } finally {
        scanning = false;
    }
}

function startDiscovery() {
    loadOptionalModules();
    scanOnce();
    scanTimer = setInterval(() => {
        scanOnce();
    }, SCAN_INTERVAL_MS);
    if (typeof scanTimer.unref === 'function') {
        scanTimer.unref();
    }
}

function getStatus() {
    return {
        ok: true,
        connected: Boolean(activeTarget),
        printer: describeTarget(activeTarget),
        transport: activeTarget ? activeTarget.kind : null,
        platform: process.platform,
    };
}

async function sendBuffer(buffer) {
    if (activeTarget.kind === 'usb') {
        await sendRawBuffer(activeTarget.device, buffer, {
            chunkDelayMs: 0,
            postPrintWaitMs: USB_POST_PRINT_WAIT_MS,
        });
        return;
    }

    const waitMs = estimatePrintDurationMs(buffer.length);

    if (activeTarget.kind === 'winspool') {
        await winSpool.printRaw(activeTarget.name, buffer, { postPrintWaitMs: waitMs });
        return;
    }

    throw new Error('Unsupported printer target');
}

async function printLabel(payload) {
    if (!activeTarget) {
        await scanOnce();
        throw new Error('Printer not connected');
    }

    const { header, details, tail, detailLineCount } = await buildLabelBuffers(payload);

    if (activeTarget.kind === 'usb') {
        await sendUsbLabelParts(activeTarget.device, header, details, tail, { detailLineCount });
        return;
    }

    await sendBuffer(header);
    if (PART_WAIT_MS > 0) {
        await sleep(PART_WAIT_MS);
    }
    await sendBuffer(Buffer.concat([details, tail]));
}

async function printBarcodeTest(options) {
    if (!activeTarget) {
        await scanOnce();
        throw new Error('Printer not connected');
    }

    const buffer = await buildBarcodeTestBuffer(options);
    await sendBuffer(buffer);
}

async function printQrTest(options) {
    if (!activeTarget) {
        await scanOnce();
        throw new Error('Printer not connected');
    }

    const buffer = await buildQrTestBuffer(options);
    await sendBuffer(buffer);
}

async function printBarcodeUrlTest(options) {
    if (!activeTarget) {
        await scanOnce();
        throw new Error('Printer not connected');
    }

    const buffer = buildBarcodeUrlTestBuffer(options);
    await sendBuffer(buffer);
}

function queueBarcodeUrlTest(options) {
    printQueue = printQueue
        .then(() => printBarcodeUrlTest(options))
        .catch((err) => {
            log(`Barcode URL test error: ${err.message}`);
        });

    return printQueue;
}

async function printBarcodeRawTest(options) {
    if (!activeTarget) {
        await scanOnce();
        throw new Error('Printer not connected');
    }

    const buffer = buildRawBarcodeDiagnosticBuffer(options);
    await sendBuffer(buffer);
}

function queueBarcodeRawTest(options) {
    printQueue = printQueue
        .then(() => printBarcodeRawTest(options))
        .catch((err) => {
            log(`Barcode raw test error: ${err.message}`);
        });

    return printQueue;
}

async function printRawTest(options) {
    if (!activeTarget) {
        await scanOnce();
        throw new Error('Printer not connected');
    }

    const buffer = await buildRawDiagnosticBuffer(options);
    await sendBuffer(buffer);
}

function queueRawTest(options) {
    printQueue = printQueue
        .then(() => printRawTest(options))
        .catch((err) => {
            log(`Raw test error: ${err.message}`);
        });

    return printQueue;
}

async function printQrBarcodeTest(options) {
    if (!activeTarget) {
        await scanOnce();
        throw new Error('Printer not connected');
    }

    const buffer = await buildQrBarcodeTestBuffer(options);
    await sendBuffer(buffer);
}

function queueQrBarcodeTest(options) {
    printQueue = printQueue
        .then(() => printQrBarcodeTest(options))
        .catch((err) => {
            log(`QR+barcode test error: ${err.message}`);
        });

    return printQueue;
}

function queueQrTest(options) {
    printQueue = printQueue
        .then(() => printQrTest(options))
        .catch((err) => {
            log(`QR test error: ${err.message}`);
        });

    return printQueue;
}

function queueBarcodeTest(options) {
    printQueue = printQueue
        .then(() => printBarcodeTest(options))
        .catch((err) => {
            log(`Barcode test error: ${err.message}`);
        });

    return printQueue;
}

function ensurePrinterReady() {
    if (activeTarget) {
        return Promise.resolve(getStatus());
    }

    return scanOnce().then(() => {
        if (!activeTarget) {
            throw new Error('Printer not connected');
        }
        return getStatus();
    });
}

function queuePrint(payload) {
    printQueue = printQueue
        .then(() => printLabel(payload))
        .catch((err) => {
            log(`Print error: ${err.message}`);
        });

    return printQueue;
}

function queueUsbBarcodeTest() {
    const prev = process.env.MOIRAI_USE_USB_DIRECT;
    process.env.MOIRAI_USE_USB_DIRECT = '1';

    printQueue = printQueue
        .then(async () => {
            activeTarget = null;
            await scanOnce();
            if (!activeTarget || activeTarget.kind !== 'usb') {
                throw new Error('USB printer not available');
            }
            await printBarcodeTest({ label: 'USB direct barcode', code: '359123456789012', type: 'CODE128' });
        })
        .catch((err) => {
            log(`USB barcode test error: ${err.message}`);
        })
        .finally(() => {
            if (prev === undefined) {
                delete process.env.MOIRAI_USE_USB_DIRECT;
            } else {
                process.env.MOIRAI_USE_USB_DIRECT = prev;
            }
            activeTarget = null;
            return scanOnce();
        });

    return printQueue;
}

module.exports = {
    startDiscovery,
    getStatus,
    printLabel,
    queuePrint,
    queueBarcodeTest,
    queueQrTest,
    queueQrBarcodeTest,
    queueRawTest,
    queueBarcodeRawTest,
    queueBarcodeUrlTest,
    queueUsbBarcodeTest,
    ensurePrinterReady,
    scanOnce,
};
