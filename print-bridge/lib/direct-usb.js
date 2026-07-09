'use strict';

const { sleep } = require('./timing');

const IFACE_CLASS_PRINTER = 0x07;
const USB_VID = Number.parseInt(process.env.MOIRAI_USB_VID || '', 16) || null;
const USB_PID = Number.parseInt(process.env.MOIRAI_USB_PID || '', 16) || null;

function getUsbLib() {
    const usbPackage = require('usb');
    return usbPackage.usb || usbPackage;
}

function findPrinterDevices() {
    const usb = getUsbLib();
    return usb.getDeviceList().filter((device) => {
        try {
            return device.configDescriptor.interfaces.some((ifaceArr) =>
                ifaceArr.some((conf) => conf.bInterfaceClass === IFACE_CLASS_PRINTER)
            );
        } catch (err) {
            return false;
        }
    });
}

function describeUsbDevice(device) {
    const vid = device.deviceDescriptor.idVendor.toString(16).padStart(4, '0');
    const pid = device.deviceDescriptor.idProduct.toString(16).padStart(4, '0');
    return `usb:vid:${vid}pid:${pid}`;
}

function pickPrinterDevice(devices) {
    if (!devices.length) {
        return null;
    }

    if (USB_VID && USB_PID) {
        const exact = devices.find((device) =>
            device.deviceDescriptor.idVendor === USB_VID
            && device.deviceDescriptor.idProduct === USB_PID
        );
        if (exact) {
            return exact;
        }
    }

    return devices[0];
}

function openOutEndpoint(device) {
    return new Promise((resolve, reject) => {
        let endpoint = null;

        try {
            device.open();
        } catch (err) {
            reject(err);
            return;
        }

        const interfaces = device.interfaces || [];
        if (!interfaces.length) {
            reject(new Error('No USB interfaces found'));
            return;
        }

        let pending = interfaces.length;
        let failed = false;

        interfaces.forEach((iface) => {
            iface.setAltSetting(iface.altSetting, () => {
                if (failed) {
                    return;
                }

                try {
                    iface.claim();
                    iface.endpoints.forEach((ep) => {
                        if (ep.direction === 'out' && !endpoint) {
                            endpoint = ep;
                        }
                    });
                } catch (err) {
                    failed = true;
                    reject(err);
                    return;
                }

                pending -= 1;

                if (endpoint) {
                    resolve(endpoint);
                    return;
                }

                if (pending <= 0) {
                    reject(new Error('No USB OUT endpoint found'));
                }
            });
        });
    });
}

function transferChunk(endpoint, chunk) {
    return new Promise((resolve, reject) => {
        endpoint.transfer(chunk, (err) => {
            if (err) {
                reject(err);
                return;
            }
            resolve();
        });
    });
}

async function transferBuffer(endpoint, buffer, chunkSize) {
    for (let offset = 0; offset < buffer.length; offset += chunkSize) {
        const chunk = buffer.subarray(offset, offset + chunkSize);
        await transferChunk(endpoint, chunk);
    }
}

async function sendRawBuffer(device, buffer, options = {}) {
    const chunkSize = Number(options.chunkSize || process.env.MOIRAI_WRITE_CHUNK_BYTES || 512);
    const chunkDelayMs = Number(options.chunkDelayMs ?? process.env.MOIRAI_WRITE_CHUNK_DELAY_MS ?? 0);
    let endpoint = null;

    try {
        endpoint = await openOutEndpoint(device);

        for (let offset = 0; offset < buffer.length; offset += chunkSize) {
            const chunk = buffer.subarray(offset, offset + chunkSize);
            await transferChunk(endpoint, chunk);

            if (chunkDelayMs > 0 && offset + chunkSize < buffer.length) {
                await sleep(chunkDelayMs);
            }
        }

        if (options.postPrintWaitMs > 0) {
            await sleep(options.postPrintWaitMs);
        }
    } finally {
        try {
            device.close();
        } catch (err) {
            // ignore close errors
        }
    }
}

async function sendUsbLabelParts(device, header, details, tail, options = {}) {
    const { estimateHeaderDrainMs, estimateDetailsDrainMs } = require('./timing');
    const chunkSize = Number(options.chunkSize || process.env.MOIRAI_WRITE_CHUNK_BYTES || 512);
    const headerDrainMs = Number(options.headerDrainMs || estimateHeaderDrainMs(header.length));
    const detailsDrainMs = Number(
        options.detailsDrainMs
        || estimateDetailsDrainMs(details.length, options.detailLineCount || 0)
    );
    const tailWaitMs = Number(options.tailWaitMs || process.env.MOIRAI_POST_PRINT_WAIT_MS || 500);
    let endpoint = null;

    try {
        endpoint = await openOutEndpoint(device);
        await transferBuffer(endpoint, header, chunkSize);
        await sleep(headerDrainMs);
        await transferBuffer(endpoint, details, chunkSize);
        await sleep(detailsDrainMs);
        await transferBuffer(endpoint, tail, chunkSize);
        await sleep(tailWaitMs);
    } finally {
        try {
            device.close();
        } catch (err) {
            // ignore close errors
        }
    }
}

module.exports = {
    findPrinterDevices,
    pickPrinterDevice,
    describeUsbDevice,
    sendRawBuffer,
    sendUsbLabelParts,
};
