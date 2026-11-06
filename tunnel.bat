@echo off
setlocal
echo ========================================================
echo   Tunneling Laravel Herd Virtual Host
echo   Target: https://hospital-formulary-drug-proposal-system.test
echo ========================================================
echo.

:: 1. Hapus file public\hot jika tertinggal dari npm run dev sebelumnya
if exist "public\hot" del /f /q "public\hot"

:: 2. Pastikan aset frontend ter-compile untuk akses publik bebas CORS
echo Menyiapkan aset produksi (npm run build)...
call npm run build
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
