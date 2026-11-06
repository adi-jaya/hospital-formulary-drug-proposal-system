#!/usr/bin/env bash
set -e

# Pindah ke direktori root proyek
cd "$(dirname "${BASH_SOURCE[0]}")"

echo "========================================================"
echo "  Cloudflare Tunnel Helper (macOS / MacPorts)"
echo "========================================================"
echo ""

# 0. Deteksi binary cloudflared
CLOUDFLARED_BIN="$(command -v cloudflared 2>/dev/null || true)"
if [ -z "$CLOUDFLARED_BIN" ]; then
    if [ -x "$HOME/.local/bin/cloudflared" ]; then
        CLOUDFLARED_BIN="$HOME/.local/bin/cloudflared"
    elif [ -x "/opt/local/bin/cloudflared" ]; then
        CLOUDFLARED_BIN="/opt/local/bin/cloudflared"
    else
        echo "Error: Binary 'cloudflared' tidak ditemukan di PATH."
        exit 1
    fi
fi

# 1. Hapus file public/hot jika tersisa dari npm run dev sebelumnya
if [ -f "public/hot" ]; then
    echo "Membersihkan public/hot..."
    rm -f "public/hot"
fi

# 2. Build aset produksi bebas CORS
echo "Menyiapkan aset produksi (npm run build)..."
npm run build
echo ""

# 3. Jalankan cloudflared tunnel
DEFAULT_URL="${TUNNEL_URL:-http://localhost:8000}"

echo "Menjalankan Cloudflare Tunnel..."
if [ $# -eq 0 ]; then
    echo "Target: $DEFAULT_URL"
    echo "Buka URL *.trycloudflare.com yang muncul di bawah ini."
    echo "Tekan Ctrl+C untuk menghentikan tunnel."
    echo ""
    "$CLOUDFLARED_BIN" tunnel --url "$DEFAULT_URL"
else
    echo "Parameter kustom: $@"
    echo "Tekan Ctrl+C untuk menghentikan tunnel."
    echo ""
    "$CLOUDFLARED_BIN" tunnel "$@"
fi
