'use strict';

const { execFile } = require('child_process');

const PS = 'powershell.exe';
const PS_ARGS = ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command'];

function runPowerShell(script) {
    return new Promise((resolve, reject) => {
        execFile(PS, [...PS_ARGS, script], { windowsHide: true, maxBuffer: 4 * 1024 * 1024 }, (error, stdout, stderr) => {
            if (error) {
                reject(new Error(stderr || error.message));
                return;
            }
            resolve(String(stdout || '').trim());
        });
    });
}

async function listPrinters() {
    const output = await runPowerShell('Get-Printer | Select-Object Name, PortName, DriverName | ConvertTo-Json -Compress');
    if (!output) {
        return [];
    }

    try {
        const parsed = JSON.parse(output);
        const rows = Array.isArray(parsed) ? parsed : [parsed];
        return rows.map((row) => ({
            name: String(row.Name || ''),
            port: String(row.PortName || ''),
            driver: String(row.DriverName || ''),
        })).filter((row) => row.name);
    } catch (err) {
        return [];
    }
}

async function listPrinterNames() {
    const printers = await listPrinters();
    return printers.map((printer) => printer.name);
}

async function printRaw(printerName, buffer, options = {}) {
    const fs = require('fs');
    const os = require('os');
    const path = require('path');

    const tmpFile = path.join(os.tmpdir(), `moirai-print-${Date.now()}-${Math.random().toString(16).slice(2)}.bin`);
    await fs.promises.writeFile(tmpFile, buffer);

    const safePrinter = String(printerName).replace(/'/g, "''");
    const safeFile = tmpFile.replace(/'/g, "''");
    const chunkSize = Number(options.chunkSize || process.env.MOIRAI_WRITE_CHUNK_BYTES || 128);
    const chunkDelayMs = Number(options.chunkDelayMs || process.env.MOIRAI_WRITE_CHUNK_DELAY_MS || 60);
    const postPrintWaitMs = Number(options.postPrintWaitMs || process.env.MOIRAI_POST_PRINT_WAIT_MS || 0);

    const script = `
$ErrorActionPreference = 'Stop'
Add-Type @"
using System;
using System.Runtime.InteropServices;
using System.Threading;

public class RawPrinterHelper {
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    public class DOCINFOA {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
    }

    [DllImport("winspool.drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi)]
    public static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.drv", EntryPoint = "ClosePrinter", SetLastError = true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In] DOCINFOA di);

    [DllImport("winspool.drv", EntryPoint = "EndDocPrinter", SetLastError = true)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", EntryPoint = "StartPagePrinter", SetLastError = true)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", EntryPoint = "EndPagePrinter", SetLastError = true)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", EntryPoint = "WritePrinter", SetLastError = true)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    public static void SendBytesToPrinter(string printerName, byte[] bytes, int chunkSize, int chunkDelayMs, int postPrintWaitMs) {
        IntPtr hPrinter;
        if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero)) {
            throw new System.ComponentModel.Win32Exception(Marshal.GetLastWin32Error(), "OpenPrinter failed");
        }

        try {
            DOCINFOA di = new DOCINFOA();
            di.pDocName = "Moirai label";
            di.pDataType = "RAW";

            if (!StartDocPrinter(hPrinter, 1, di)) {
                throw new System.ComponentModel.Win32Exception(Marshal.GetLastWin32Error(), "StartDocPrinter failed");
            }

            try {
                if (!StartPagePrinter(hPrinter)) {
                    throw new System.ComponentModel.Win32Exception(Marshal.GetLastWin32Error(), "StartPagePrinter failed");
                }

                try {
                    if (chunkSize < 64) {
                        chunkSize = 64;
                    }

                    for (int offset = 0; offset < bytes.Length; offset += chunkSize) {
                        int length = Math.Min(chunkSize, bytes.Length - offset);
                        IntPtr unmanagedBytes = Marshal.AllocCoTaskMem(length);
                        try {
                            Marshal.Copy(bytes, offset, unmanagedBytes, length);
                            int written;
                            if (!WritePrinter(hPrinter, unmanagedBytes, length, out written) || written != length) {
                                throw new System.ComponentModel.Win32Exception(Marshal.GetLastWin32Error(), "WritePrinter failed");
                            }
                        } finally {
                            Marshal.FreeCoTaskMem(unmanagedBytes);
                        }

                        if (chunkDelayMs > 0 && offset + length < bytes.Length) {
                            Thread.Sleep(chunkDelayMs);
                        }
                    }
                } finally {
                    EndPagePrinter(hPrinter);
                }
            } finally {
                EndDocPrinter(hPrinter);
            }
        } finally {
            ClosePrinter(hPrinter);
        }

        if (postPrintWaitMs > 0) {
            Thread.Sleep(postPrintWaitMs);
        }
    }
}
"@

$printerName = '${safePrinter}'
$filePath = '${safeFile}'
$bytes = [System.IO.File]::ReadAllBytes($filePath)
[RawPrinterHelper]::SendBytesToPrinter($printerName, $bytes, ${chunkSize}, ${chunkDelayMs}, ${postPrintWaitMs})
Write-Output 'OK'
`;

    try {
        const result = await runPowerShell(script);
        if (result !== 'OK') {
            throw new Error(result || 'Windows RAW print failed');
        }
    } finally {
        await fs.promises.unlink(tmpFile).catch(() => {});
    }
}

module.exports = {
    listPrinters,
    listPrinterNames,
    printRaw,
};
