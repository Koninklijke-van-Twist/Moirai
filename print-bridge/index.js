'use strict';

const { startDiscovery } = require('./lib/printer');
const { startServer } = require('./lib/server');
const { buildLabelLogoBuffer, logLogoError } = require('./lib/label-logo');

buildLabelLogoBuffer()
    .then((buffer) => {
        console.log(`[moirai-print-bridge] Label logo ready (${buffer.length} bytes)`);
    })
    .catch((err) => {
        logLogoError(err);
    });

startDiscovery();
startServer();
