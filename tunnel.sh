#!/usr/bin/env bash
set -e

echo "========================================================"
echo "  Cloudflare Tunnel Helper (macOS / MacPorts)"
echo "========================================================"
echo ""

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
echo "Menjalankan Cloudflare Tunnel..."
echo "Tekan Ctrl+C untuk menghentikan tunnel."
echo ""

if [ $# -eq 0 ]; then
    cloudflared tunnel
else
    cloudflared tunnel "$@"
fi
