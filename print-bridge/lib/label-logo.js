'use strict';

const fs = require('fs');
const path = require('path');
const sharp = require('sharp');
const { buildRasterPngBuffer } = require('./raster-image');

const LOGO_PATH = path.join(__dirname, '../assets/kvt-logo.svg');
const LOGO_MAX_HEIGHT = Number(process.env.MOIRAI_LABEL_LOGO_HEIGHT || 48);
const LOGO_MAX_WIDTH = Number(process.env.MOIRAI_LABEL_LOGO_WIDTH || 384);

let cachedLogoRaster = null;

function shouldPrintLogo(payload = {}) {
    if (payload.logo === false) {
        return false;
    }
    if (process.env.MOIRAI_LABEL_LOGO === '0') {
        return false;
    }
    return true;
}

async function buildLabelLogoBuffer() {
    if (cachedLogoRaster) {
        return cachedLogoRaster;
    }

    if (!fs.existsSync(LOGO_PATH)) {
        throw new Error(`Logo-bestand niet gevonden: ${LOGO_PATH}`);
    }

    const svg = fs.readFileSync(LOGO_PATH);
    const png = await sharp(svg)
        .resize({
            width: LOGO_MAX_WIDTH,
            height: LOGO_MAX_HEIGHT,
            fit: 'inside',
            background: { r: 255, g: 255, b: 255, alpha: 1 },
        })
        .flatten({ background: '#ffffff' })
        .png()
        .toBuffer();

    cachedLogoRaster = await buildRasterPngBuffer(png);
    return cachedLogoRaster;
}

function logLogoError(err) {
    const message = err && err.message ? err.message : String(err);
    console.error(`[moirai-print-bridge] Label logo skipped: ${message}`);
}

module.exports = {
    buildLabelLogoBuffer,
    shouldPrintLogo,
    logLogoError,
};
