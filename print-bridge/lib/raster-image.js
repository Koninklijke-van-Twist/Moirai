'use strict';

const getPixels = require('get-pixels');
const Image = require('escpos/image');

const GS = 0x1d;

function buildRasterPngBuffer(pngBuffer) {
    return new Promise((resolve, reject) => {
        getPixels(pngBuffer, 'image/png', (err, pixels) => {
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

module.exports = {
    buildRasterPngBuffer,
};
