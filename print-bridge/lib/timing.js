'use strict';

function sleep(ms) {
    return new Promise((resolve) => {
        setTimeout(resolve, ms);
    });
}

function estimatePrintDurationMs(bufferLength) {
    const chunkSize = Number(process.env.MOIRAI_WRITE_CHUNK_BYTES || 512);
    const chunkDelayMs = Number(process.env.MOIRAI_WRITE_CHUNK_DELAY_MS || 0);
    const chunks = Math.ceil(bufferLength / chunkSize);
    return Math.min(30000, Math.max(2000, 1000 + chunks * chunkDelayMs + bufferLength * 2));
}

function estimateHeaderDrainMs(headerLength) {
  if (headerLength < 500) {
    return Math.min(3000, Math.max(400, 300 + Math.round(headerLength * 0.2)));
  }

  return Math.min(6000, Math.max(900, 600 + Math.round(headerLength * 0.35)));
}

function estimateDetailsDrainMs(detailsLength, lineCount = 0) {
    const perLineMs = Number(process.env.MOIRAI_DETAILS_LINE_MS || 120);
    return Math.min(8000, Math.max(600, 400 + Math.round(detailsLength * 0.4) + lineCount * perLineMs));
}

module.exports = {
    sleep,
    estimatePrintDurationMs,
    estimateHeaderDrainMs,
    estimateDetailsDrainMs,
};
