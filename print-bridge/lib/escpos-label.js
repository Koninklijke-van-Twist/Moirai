'use strict';

const escpos = require('escpos');
const CollectDevice = require('./collect-device');
const { useRasterQr } = require('./escpos-qr');
const { buildRasterQrBuffer, buildEpsonQrBuffer } = require('./raw-escpos');

const PRINTER_WIDTH = 32;
const DETAIL_FONT = 'a';
const FEED_LINES = Number(process.env.MOIRAI_FEED_LINES || 0);
const LINE_SPACING = Number(process.env.MOIRAI_LINE_SPACING || 30);
const LABEL_TAIL_MM = Number(process.env.MOIRAI_LABEL_TAIL_MM || 0);
const DOTS_PER_MM = 8;
const PRINT_SPEED_ENABLED = process.env.MOIRAI_ENABLE_PRINT_SPEED === '1';

function buildLabelTailBuffer() {
    if (LABEL_TAIL_MM <= 0) {
        return Buffer.from([0x1d, 0x56, 0x01]);
    }

    const tailDots = Math.max(8, Math.min(255, Math.round(LABEL_TAIL_MM * DOTS_PER_MM)));
    return Buffer.from([0x1b, 0x4a, tailDots, 0x1d, 0x56, 0x01]);
}

function sanitizeText(text) {
    return String(text ?? '')
        .trim()
        .replace(/—/g, '-')
        .replace(/\u2013/g, '-')
        .replace(/[\u2018\u2019]/g, "'")
        .replace(/[\u201C\u201D]/g, '"')
        || '-';
}

function resetDetailStyle(printer) {
    return printer
        .style('normal')
        .font(DETAIL_FONT)
        .size(0, 0)
        .align('lt');
}

function applyPrintSpeed(printer) {
    if (!PRINT_SPEED_ENABLED) {
        return printer;
    }

    const speed = Math.max(1, Math.min(13, Number(process.env.MOIRAI_PRINT_SPEED || 1)));
    printer.print(Buffer.from([0x1d, 0x28, 0x4b, 0x02, 0x00, 0x32, speed]));
    return printer;
}

function applyLineSpacing(printer) {
    const spacing = Math.max(24, Math.min(120, LINE_SPACING));
    printer.print(Buffer.from([0x1b, 0x33, spacing]));
    return printer;
}

function padLine(label, value) {
    const labelText = sanitizeText(label) + ':';
    const val = sanitizeText(value);
    const gap = PRINTER_WIDTH - labelText.length - val.length;

    if (gap >= 1) {
        return labelText + ' '.repeat(gap) + val;
    }

    return labelText + ' ' + val;
}

function buildBuffer(setup) {
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
                setup(printer);
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

function buildHeaderBuffer(payload) {
    if (useRasterQr()) {
        return buildRasterHeaderBuffer(payload);
    }

    return buildNativeHeaderBuffer(payload);
}

function buildNativeHeaderBuffer(payload) {
    const title = String(payload.title || '').trim();
    const qrUrl = String(payload.qrUrl || '').trim();

    return buildBuffer((printer) => {
        printer.hardware('init');
        applyPrintSpeed(printer);
        applyLineSpacing(printer);
        printer
            .font('a')
            .align('ct')
            .style('b')
            .size(0, 0)
            .text(title || ' ')
            .style('normal')
            .feed(1);

        if (qrUrl) {
            printer.align('ct').print(buildEpsonQrBuffer(qrUrl)).feed(1);
        }
    });
}

function buildRasterHeaderBuffer(payload) {
    const title = String(payload.title || '').trim();
    const qrUrl = String(payload.qrUrl || '').trim();

    return buildRasterQrBuffer(qrUrl).then((raster) => buildBuffer((printer) => {
        printer.hardware('init');
        applyPrintSpeed(printer);
        applyLineSpacing(printer);
        printer
            .font('a')
            .align('ct')
            .style('b')
            .size(0, 0)
            .text(title || ' ')
            .style('normal')
            .feed(1);

        if (qrUrl) {
            printer.align('ct').print(raster).feed(1);
        }
    }));
}

function buildDetailsBuffer(payload) {
    const id = String(payload.id || '').trim();
    const lines = Array.isArray(payload.lines) ? payload.lines : [];

    return buildBuffer((printer) => {
        applyPrintSpeed(printer);
        applyLineSpacing(printer);
        resetDetailStyle(printer);

        if (id) {
            printer.style('b').text(id).style('normal').feed(1);
        }

        printer.drawLine();

        lines.forEach((line) => {
            const label = String(line.label || '').trim();
            if (!label) {
                return;
            }
            printer.text(padLine(label, line.value));
        });

        if (FEED_LINES > 0) {
            printer.feed(FEED_LINES);
        }
    });
}

function buildTailBuffer() {
    return buildLabelTailBuffer();
}

async function buildLabelBuffers(payload) {
    const lines = Array.isArray(payload.lines) ? payload.lines : [];
    const header = await buildHeaderBuffer(payload);
    const details = await buildDetailsBuffer(payload);
    const tail = buildTailBuffer();
    const detailLineCount = lines.length + (payload.id ? 1 : 0) + 1;

    return { header, details, tail, detailLineCount };
}

module.exports = {
    buildLabelBuffers,
    buildLabelTailBuffer,
    PRINTER_WIDTH,
};
