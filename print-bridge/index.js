'use strict';

const { startDiscovery } = require('./lib/printer');
const { startServer } = require('./lib/server');

startDiscovery();
startServer();
