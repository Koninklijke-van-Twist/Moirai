@echo off
setlocal

cd /d "%~dp0"

set "MOIRAI_USE_USB_DIRECT=1"

where node >nul 2>&1
if errorlevel 1 (
    echo Node.js is vereist. Installeer Node 18 of hoger en probeer opnieuw.
    exit /b 1
)

if not exist "node_modules\" (
    echo npm install...
    call npm install --omit=dev
    if errorlevel 1 exit /b 1
) else (
    call npm install --omit=dev
    if errorlevel 1 exit /b 1
)

echo Moirai print bridge op http://127.0.0.1:9173
echo Stop met Ctrl+C
echo.

node index.js

endlocal
