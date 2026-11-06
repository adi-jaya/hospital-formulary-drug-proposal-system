# Sistem Usulan Obat Formularium RS
### RS Aisyiyah Siti Fatimah Tulangan • Farmasi

Aplikasi web mandiri (*standalone*) berbasis **Laravel 12**, **Blade**, **Alpine.js**, dan **Tailwind CSS v4** dengan database **SQLite WAL Mode** untuk pengumpulan usulan obat tahunan dari para DPJP (Dokter Penanggung Jawab Pelayanan) tanpa hambatan login (*zero-friction*), dilengkapi dashboard rekapitulasi real-time, integrasi Live Spreadsheet Google Sheets/Excel, dan ekspor file `.xlsx` siap cetak.

---

## Fitur Utama

- **Zero-Friction Doctor Proposal Form (`/`):**
  - Dokter dapat langsung mengajukan obat tanpa perlu membuat akun atau login PIN.
  - *Searchable Combobox* Dokter: Terhubung langsung ke REST API `portalsifast` dengan sistem fallback bertingkat (Cache 12 Jam $\rightarrow$ API $\rightarrow$ Snapshot JSON Lokal $\rightarrow$ Pengetikan Manual).
  - *Dynamic Medicine Repeater:* Mengusulkan 1 hingga 50 obat dalam satu pengiriman formulir.
  - *Self-Learning Autocomplete:* Rekomendasi nama obat & pabrikan menggabungkan 206 obat kamus referensi RS dengan riwayat usulan terbaru secara *case-insensitive*.
  - *Principle Auto-Fill:* Memilih obat dari saran otomatis mengisi nama principle/pabrik pasangannya tanpa mengunci pilihan dokter.
  - *Idempotency Lock & Atomic Transaction:* Transaksi master-detail dibungkus `DB::transaction()` dan dilindungi hash SHA-256 di cache selama 15 detik untuk mencegah duplikasi akibat klik ganda.
  - *Safe Form Recovery:* Validasi gagal mengembalikan seluruh baris obat tanpa terpotong berkat isolasi data JSON script carrier.

- **Dashboard Rekapitulasi Real-Time (`/recap`):**
  - Metrik statistik instan: Total Obat Diusulkan, Dokter Berpartisipasi, dan Total Pengiriman Formulir.
  - Filter multi-dimensi: Tahun Periode (chip cepat), Dokter, Principle (menggunakan komponen kustom `<x-custom-select>` dengan navigasi keyboard), Rentang Tanggal, dan Pencarian Bebas.
  - Paginasi 25 baris responsif dengan mempertahankan query string.

- **Integrasi Live Spreadsheet & Ekspor:**
  - **Google Sheets Live Sync:** Modal koneksi dengan tombol salin 1-klik rumus `=IMPORTDATA("https://domain-rs/exports/proposals.csv?token=...")`.
  - **Microsoft Excel Power Query:** Koneksi live via menu *Data $\rightarrow$ From Web*.
  - **Proteksi Token Statis:** Endpoint CSV feed diamankan dengan validasi token konstan waktu (`hash_equals`).
  - **Unduh Excel Berformat (`.xlsx`):** Dibuat menggunakan engine ringan `openspout/openspout` dengan streaming memori rendah, header warna hijau zamrud RS (`#065F46`), teks tebal putih, dan border terukur rapi.
  - **Anti-Formula Injection:** Sanitasi otomatis karakter berisiko (`=`, `+`, `-`, `@`) untuk mencegah eksekusi formula berbahaya di spreadsheet.

---

## Tech Stack & Arsitektur

| Lapisan | Teknologi | Keterangan |
| :--- | :--- | :--- |
| **Backend** | PHP `^8.3`, Laravel `^12.0` | Monolitik ringan, routing modern `bootstrap/app.php` |
| **Database** | SQLite 3 (`WAL` Mode) | `busy_timeout = 5000`, `synchronous = NORMAL`, `foreign_keys = true` |
| **Frontend** | Blade + Alpine.js `^3.14` | Alpine dibundel lokal melalui Vite (tanpa CDN eksternal) |
| **Styling** | Tailwind CSS `^4.0` | Menggunakan `@tailwindcss/vite` dengan palet hijau zamrud RS |
| **Excel Writer** | `openspout/openspout` `^4.32` | Streaming memory-efficient Excel XLSX export |
| **Testing** | Pest PHP `^3.0` | 25 Feature & Unit Tests (132 assertions, 100% pass) |

Dokumentasi arsitektur dan teknis mendalam tersedia di [docs/DOKUMENTASI_SISTEM.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/docs/DOKUMENTASI_SISTEM.md).

---

## Panduan Cepat Memulai (*Quick Start*)

### 1. Kebutuhan Sistem
- PHP `>= 8.3` (ekstensi: `pdo_sqlite`, `curl`, `mbstring`, `zip`, `xml`)
- Composer `>= 2.7`
- Node.js `>= 20.0` & NPM

### 2. Instalasi & Setup Lingkungan
```bash
# Clone atau buka direktori proyek
cd FormulariumRS

# Pasang dependensi
composer install
npm install

# Setup konfigurasi lingkungan
cp .env.example .env
php artisan key:generate

# Buat database SQLite dan jalankan migrasi + seeder awal
touch database/database.sqlite
php artisan migrate --seed

# Kompilasi aset Vite
npm run build
```

### 3. Konfigurasi Penting `.env`
Pastikan variabel berikut terisi di file `.env`:
```dotenv
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=sqlite
DB_FOREIGN_KEYS=true

FORMULARY_DOCTOR_API_URL="https://portalsifast.rsaisyiyahsitifatimah.com/api/dokter"
FORMULARY_DOCTOR_API_TIMEOUT=10
FORMULARY_DOCTOR_CACHE_TTL_HOURS=12

# Token 32 karakter untuk mengamankan Live CSV Feed Spreadsheet
FORMULARY_FEED_TOKEN="ganti_dengan_token_rahasia_32_karakter"
```

### 4. Menjalankan Server Pengembangan Lokal
```bash
composer run dev
```
Akses di browser:
- Formulir Usulan Dokter: `http://localhost:8000/`
- Rekapitulasi & Live Spreadsheet: `http://localhost:8000/recap`

### 5. Membagikan Akses Publik via Cloudflare Tunnel
Untuk keperluan demo, pengujian oleh dokter di smartphone, atau akses dari jaringan luar:
- **Windows (Laravel Herd)**: Jalankan `.\tunnel.bat`
- **macOS (MacPorts)**: Jalankan `./tunnel.sh`

Panduan lengkap mengenai arsitektur isolasi aset Vite dan konfigurasi tunnel dapat dibaca di [docs/PANDUAN_TUNNEL.md](docs/PANDUAN_TUNNEL.md).

---

## Perintah Artisan yang Tersedia

```bash
# Mengimpor atau memperbarui kamus CSV referensi obat & pabrikan
php artisan formulary:import-csv database/seeders/data/formulary_medicines.csv

# Mengisi database dengan data simulasi realistis (gabungan dokter API & data obat)
php artisan db:seed --class=FormulariumDataSeeder

# Menjalankan seluruh pengujian otomatis
./vendor/bin/pest

# Memeriksa dan merapikan standar format kode PHP
./vendor/bin/pint --test
```

---

## Struktur Direktori Utama

```text
FormulariumRS/
├── app/
│   ├── Console/Commands/
│   │   └── ImportFormularyCsvCommand.php     # CLI importer CSV kamus obat
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ProposalController.php        # Form usulan & penyimpanan atomik
│   │   │   ├── ProposalRecapController.php   # Halaman rekapitulasi & metrik
│   │   │   ├── ProposalExportController.php  # Stream CSV ber-token & XLSX download
│   │   │   └── SuggestionController.php      # JSON suggestions dokter, obat, principle
│   │   └── Requests/
│   │       └── StoreProposalRequest.php      # Validasi & normalisasi teks
│   ├── Models/
│   │   ├── Proposal.php                      # Model master sesi usulan
│   │   ├── ProposalItem.php                  # Model rincian obat yang diusulkan
│   │   └── ReferenceMedicine.php             # Kamus obat referensi RS
│   ├── Queries/
│   │   └── ProposalItemRecapQuery.php        # Query builder filter rekapitulasi
│   ├── Services/
│   │   ├── DoctorDirectoryService.php        # Integrasi tangguh API, Cache & Snapshot dokter
│   │   ├── AutocompleteService.php           # Logika hibrida saran obat & principle
│   │   ├── FormularyCsvImporter.php          # Parser CSV RFC-4180 multi-baris
│   │   └── ProposalExportService.php         # Penulis spreadsheet XLSX OpenSpout
│   └── Support/
│       ├── TextNormalizer.php                # Normalisasi spasi, alias principle & null strip
│       └── SpreadsheetSanitizer.php          # Sanitasi CSV formula injection
├── config/
│   ├── database.php                          # Konfigurasi SQLite WAL & timeout 5000
│   └── formulary.php                         # Pengaturan API dokter, cache TTL & feed token
├── database/
│   ├── migrations/                           # Migrasi proposals, items, & reference_medicines
│   └── seeders/
│       ├── DatabaseSeeder.php                # Seeder utama
│       ├── ReferenceMedicineSeeder.php       # Seeder kamus 206 obat
│       └── FormulariumDataSeeder.php         # Seeder integrasi dokter API & riwayat usulan
├── docs/
│   ├── DOKUMENTASI_SISTEM.md                 # Dokumentasi teknis & arsitektur lengkap
│   └── superpowers/                          # Spec dan implementation plan
├── resources/
│   ├── css/app.css                           # Tema Tailwind CSS v4 palet hijau zamrud RS
│   ├── js/app.js                             # Alpine.js debounced autocomplete & repeater
│   └── views/
│       ├── components/                       # Layout shell & Custom Select Blade components
│       └── proposals/
│           ├── create.blade.php              # View formulir usulan obat
│           └── index.blade.php               # View rekapitulasi & modal spreadsheet
└── tests/
    ├── Feature/                              # Pengujian integrasi submission, export, filter
    └── Unit/                                 # Pengujian normalisasi teks & parser CSV
```

---

## Lisensi & Kepemilikan
Aplikasi ini dikembangkan khusus untuk **RS Aisyiyah Siti Fatimah Tulangan** di bawah pengelolaan Farmasi.
Hak Cipta &copy; 2026 RS Aisyiyah Siti Fatimah Tulangan. Seluruh hak cipta dilindungi.
