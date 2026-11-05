@echo off
setlocal
echo ========================================================
echo   Tunneling Laravel Herd Virtual Host
echo   Target: https://hospital-formulary-drug-proposal-system.test
echo ========================================================
echo.

set CLOUDFLARED_BIN="C:\Program Files (x86)\cloudflared\cloudflared.exe"
if not exist %CLOUDFLARED_BIN% (
    set CLOUDFLARED_BIN=cloudflared
)

echo Menghubungkan Cloudflare Tunnel...
echo Buka URL *.trycloudflare.com yang muncul di bawah ini.
echo Tekan Ctrl+C untuk menghentikan tunnel.
echo.

%CLOUDFLARED_BIN% tunnel --url https://hospital-formulary-drug-proposal-system.test --http-host-header hospital-formulary-drug-proposal-system.test --no-tls-verify

endlocal
