'use strict';

const escpos = require('escpos');
const CollectDevice = require('./collect-device');
const { appendCode128 } = require('./escpos-barcode');

const PRINTER_WIDTH = 32;

function buildBarcodeTestBuffer(options = {}) {
    const label = String(options.label || 'Barcode test').trim();
    const code = String(options.code || '359123456789012').trim();
    const type = String(options.type || 'CODE128').trim().toUpperCase();

    return new Promise((resolve, reject) => {
        const device = new CollectDevice();
        const printer = new escpos.Printer(device, {
            encoding: 'CP437',
            width: PRINTER_WIDTH,
        });

        device.open((openErr) => {
            if (openErr) {
                reject(openErr);
                return;
            }

            try {
                printer
                    .hardware('init')
                    .align('ct')
                    .style('b')
                    .text(label)
                    .style('normal')
                    .feed(1);

                if (type === 'CODE128') {
                    appendCode128(printer, code, {
                        width: 2,
                        height: 100,
                        position: 'OFF',
                        font: 'B',
                    });
                } else {
                    printer.barcode(code, type, {
                        width: 2,
                        height: 100,
                        position: 'OFF',
                        font: 'B',
                    });
                }

                printer
                    .feed(3)
                    .print(Buffer.from([0x1d, 0x56, 0x01]));
            } catch (err) {
                reject(err);
                return;
            }

            printer.close(() => {
                resolve(device.getBuffer());
            });
        });
    });
}

function buildQrTestBuffer(options = {}) {
    const label = String(options.label || 'QR test').trim();
    const content = String(options.content || 'https://example.com/#l/TEST123').trim();

    return buildRasterQrBuffer(content).then((raster) => new Promise((resolve, reject) => {
        const device = new CollectDevice();
        const printer = new escpos.Printer(device, {
            encoding: 'CP437',
            width: PRINTER_WIDTH,
        });

        device.open((openErr) => {
            if (openErr) {
                reject(openErr);
                return;
            }

            try {
                printer
                    .hardware('init')
                    .align('ct')
                    .style('b')
                    .text(label)
                    .style('normal')
                    .feed(1)
                    .align('ct')
                    .print(raster)
                    .feed(3)
                    .print(Buffer.from([0x1d, 0x56, 0x01]));
            } catch (err) {
                reject(err);
                return;
            }

            printer.close(() => {
                resolve(device.getBuffer());
            });
        });
    }));
}

function buildQrBarcodeTestBuffer(options = {}) {
    const label = String(options.label || 'QR test').trim();
    const content = String(options.content || 'https://example.com/#l/TEST123').trim();

    return buildRasterQrBuffer(content).then((raster) => new Promise((resolve, reject) => {
        const device = new CollectDevice();
        const printer = new escpos.Printer(device, {
            encoding: 'CP437',
            width: PRINTER_WIDTH,
        });

        device.open((openErr) => {
            if (openErr) {
                reject(openErr);
                return;
            }

            try {
                printer
                    .hardware('init')
                    .align('ct')
                    .style('b')
                    .text(label)
                    .style('normal')
                    .feed(1)
                    .align('ct')
                    .print(raster)
                    .feed(3)
                    .print(Buffer.from([0x1d, 0x56, 0x01]));
            } catch (err) {
                reject(err);
                return;
            }

            printer.close(() => {
                resolve(device.getBuffer());
            });
        });
    }));
}

module.exports = {
    buildBarcodeTestBuffer,
    buildQrTestBuffer,
    buildQrBarcodeTestBuffer,
};
