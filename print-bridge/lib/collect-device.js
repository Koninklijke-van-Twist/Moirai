'use strict';

class CollectDevice {
    constructor() {
        this.chunks = [];
    }

    open(callback) {
        this.chunks = [];
        callback(null);
    }

    write(data, callback) {
        this.chunks.push(Buffer.isBuffer(data) ? data : Buffer.from(data));
        callback(null);
    }

    close(callback) {
        callback(null);
    }

    getBuffer() {
        return Buffer.concat(this.chunks);
    }
}

module.exports = CollectDevice;
