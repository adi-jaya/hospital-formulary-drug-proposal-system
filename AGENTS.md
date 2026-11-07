# AGENTS.md — Technical Guidelines & Operational Rules for AI Agents

Dokumen ini adalah acuan kerja teknis utama (*ground truth*) bagi seluruh AI coding assistant yang bekerja pada repositori **FormulariumRS** (Sistem Usulan Obat Formularium Rumah Sakit Aisyiyah Siti Fatimah Tulangan).

---

## 1. Overview Stack & Arsitektur Eksisting

### 1.1 Core Technology Stack
- **Runtime & Bahasa**: PHP `^8.2` / `^8.3` (wajib menggunakan `declare(strict_types=1);` pada seluruh berkas PHP).
- **Backend Framework**: Laravel `^12.0` (arsitektur modern berbasis `bootstrap/app.php`, tanpa file `app/Http/Kernel.php` warisan).
- **Database Engine**: SQLite 3 terkonfigurasi untuk konkurensi produksi di `config/database.php`:
  - `journal_mode = WAL` (*Write-Ahead Logging*).
  - `busy_timeout = 5000` (toleransi antrean transaksi hingga 5 detik).
  - `synchronous = NORMAL`.
  - `foreign_key_constraints = true`.
  - `transaction_mode = DEFERRED`.
- **Frontend Stack**:
  - **Blade Components**: Server-side rendering cepat, layout via `<x-layout>`, komponen reusable di `resources/views/components/` (misal: `<x-custom-select>`).
  - **Tailwind CSS `^4.0`**: Terintegrasi via `@tailwindcss/vite` di `vite.config.js` dan `@import "tailwindcss";` di `resources/css/app.css`.
  - **Alpine.js `^3.17`**: Dibundel secara lokal via Vite di `resources/js/app.js` (bebas ketergantungan CDN eksternal).
  - **Data Hydration Form**: Data formulir awal dioper melalui `<script id="...-data" type="application/json">` untuk mencegah karakter kutip (`'` atau `"`) merusak parser HTML.
- **Spreadsheet / Export Engine**:
  - `openspout/openspout: ^4.32` untuk ekspor XLSX dengan streaming memori konstan (< 10 MB) dan styling hijau zamrud RS.
  - Native PHP streaming `fputcsv()` untuk live CSV feed.
- **Testing & Tooling**: Pest PHP `^3.7`, Laravel Pint `^1.24`, Laravel Pail `^1.2`.

### 1.2 Struktur Layering Aktual (No Repository Pattern)
> **PENTING**: Repositori ini **TIDAK MENGGUNAKAN** Generic Repository Pattern (`*RepositoryInterface`, `*Repository`). Jangan memperkenalkan lapisan abstraksi repositori buatan.

Aplikasi menggunakan arsitektur modular fungsional:
1. **HTTP Controllers (`app/Http/Controllers/`)**:
   - Berfungsi sebagai orchestrator HTTP.
   - Menerima `FormRequest` atau `Request`, memanggil Service atau Query Object, dan mengembalikan `View`, `RedirectResponse`, `JsonResponse`, atau `StreamedResponse`.
   - Dependency Injection dilakukan langsung via controller method atau constructor.
2. **Form Requests (`app/Http/Requests/`)**:
   - Menangani validasi dan sanitasi awal data input.
   - Menggunakan hook `prepareForValidation(): void` untuk menormalisasi string (membuang spasi ganda, mereduksi dash `-` menjadi `null`) sebelum aturan validasi dievaluasi.
   - Mendefinisikan pesan error berbahasa Indonesia di method `messages(): array`.
3. **Domain Services (`app/Services/`)**:
   - Mengenkapsulasi logika bisnis mandiri, proses multi-langkah, dan integrasi eksternal.
   - Contoh:
     - `DoctorDirectoryService`: Multi-tier fallback (Cache 12 jam -> REST API Portal Sifast -> Snapshot `storage/app/doctors_snapshot.json` -> Union dokter usulan manual).
     - `AutocompleteService`: Menggabungkan kamus referensi + usulan dokter historis secara *case-insensitive*.
     - `ProposalExportService`: Streaming generator file XLSX ber-style via OpenSpout.
     - `FormularyCsvImporter`: Parser CSV RFC-4180 multi-line dan sinkronisasi referensi obat.
4. **Query Objects (`app/Queries/`)**:
   - Mengenkapsulasi query kompleks, multi-filter, dan join antar tabel.
   - Menggunakan static method (misal: `ProposalItemRecapQuery::build(array $filters = []): Builder<ProposalItem>`) yang mengembalikan instans `Builder` Eloquent murni sehingga fleksibel untuk dipaginasi, dihitung agregatnya (`clone $query`), atau diekspor bertahap (`chunk()`).
5. **Support Utilities (`app/Support/`)**:
   - Pure helper statis tanpa dependensi database.
   - `TextNormalizer`: Normalisasi spasi Unicode (`\s+/u`), penanganan alias principle pabrikan, dan normalisasi dash ke `null`.
   - `SpreadsheetSanitizer`: Pencegahan *CSV / Spreadsheet Formula Injection* (mengawali prefix dangerous `=`, `+`, `-`, `@`, `\t`, `\r` dengan `'`).
6. **Eloquent Models (`app/Models/`)**:
   - Lean models yang memuat `$fillable`, `casts(): array` (gaya baru Laravel 11/12), dan method relasi bertipe (`HasMany`, `BelongsTo`).
   - Query CRUD sederhana dan mutasi transaksional langsung dieksekusi via Eloquent di Controller atau Service.

---

## 2. Konvensi Koding Asli Proyek (Coding Conventions)

### 2.1 Deklarasi Tipe Ketat & Standar Penamaan
- **Strict Types**: Wajib mencantumkan `declare(strict_types=1);` tepat setelah tag pembuka `<?php` di setiap berkas PHP tanpa kecuali.
- **Bahasa & Istilah Basis Data**:
  - Tabel database: Bahasa Inggris baku, bentuk jamak snake_case (`proposals`, `proposal_items`, `reference_medicines`).
  - Kolom database: Bahasa Inggris baku snake_case (`doctor_name`, `proposal_date`, `period_year`, `medicine_name`, `principal_name`).
  - Model & Class PHP: PascalCase Bahasa Inggris (`Proposal`, `ProposalItem`, `ReferenceMedicine`).
  - Relasi Model: camelCase (`items()`, `proposal()`).
  - Variabel & Method: camelCase (`$baseDoctors`, `suggestMedicines()`, `normalizePrincipal()`).
  - Routes: Resourceful dot-notation (`proposals.create`, `proposals.store`, `proposals.index`, `suggestions.doctors`).

### 2.2 Response API & Endpoint Autocomplete
- Endpoint saran/autocomplete (`/api/suggestions/*`) mengembalikan JSON dengan wrapper seragam:
  ```json
  {
    "data": [ ... ]
  }
  ```
- Contoh implementasi di Controller:
  ```php
  public function doctors(Request $request, DoctorDirectoryService $service): JsonResponse
  {
      $query = trim((string) $request->query('q', ''));
      if ($query === '') {
          return response()->json(['data' => []]);
      }
      return response()->json(['data' => $service->listDoctors($query)]);
  }
  ```

### 2.3 Validasi & Sanitasi Data
- Gunakan Form Request khusus untuk mutasi data (`StoreProposalRequest`).
- Selalu sanitasi input di `prepareForValidation()`:
  ```php
  protected function prepareForValidation(): void
  {
      $this->merge([
          'doctor_name' => TextNormalizer::collapseWhitespace((string) $this->input('doctor_name')),
          // normalisasi item array...
      ]);
  }
  ```
- Aturan validasi wajib menggunakan array notation, bukan pipe-delimited string:
  ```php
  'doctor_name' => ['required', 'string', 'max:255'],
  'proposal_date' => ['required', 'date'],
  'items' => ['required', 'array', 'min:1', 'max:50'],
  'items.*.medicine_name' => ['required', 'string', 'max:255'],
  'items.*.principal_name' => ['nullable', 'string', 'max:255'],
  ```

### 2.4 Gaya Penulisan Eloquent & Query Builder
- **Model Casts**: Definisikan menggunakan method `protected function casts(): array`, bukan property `protected $casts = [...]`.
- **Eksplisit Table Scope**: Saat melakukan join, selalu sertakan prefix tabel pada kolom (`proposals.id`, `proposal_items.proposal_id`) untuk mencegah ambiguitas SQL.
- **Pencarian Aman**: Selalu escape karakter wildcard SQL pada klausa `LIKE` dengan `addcslashes($term, '%_')`:
  ```php
  $term = addcslashes(trim((string) $filters['search']), '%_');
  $query->where('medicine_name', 'like', "%{$term}%");
  ```
- **Transaksi DB & Idempotency**:
  - Mutasi yang melibatkan master-detail wajib dibungkus `DB::transaction(function () { ... });`.
  - Gunakan fingerprint payload SHA-256 dan `Cache::add("formulary:idempotency:{$hash}", true, now()->addSeconds(15))` untuk mencegah duplikasi data akibat double-click atau retry jaringan lambat.
- **Format Tanggal Spreadsheet**: Seluruh ekspor spreadsheet (CSV live feed dan XLSX) menggunakan format tanggal lokal `d/m/Y` agar dikenali dengan benar oleh Google Sheets dan Excel regional Indonesia.

---

## 3. Batasan Operasional AI (Strict Rules)

Setiap AI agent yang berinteraksi dengan codebase ini **WAJIB** mematuhi batasan mutlak berikut:

1. **Konsistensi Penuh Terhadap Pola Eksisting**:
   - Wajib menjaga konsistensi dengan pola koding yang sudah ada saat membuat fitur baru atau memperbaiki bug.
   - Selalu ikuti pola layering yang ada (Controller -> Service -> Query Object / Model).
   - Jangan menambahkan arsitektur asing seperti Repository-Interface pattern, CQRS terpisah, Event/Listener yang berlebihan, atau DTO library eksternal tanpa instruksi eksplisit.
   - Pertahankan penggunaan Tailwind CSS v4 dan Alpine.js lokal; jangan memasukkan library CSS/JS lain (Bootstrap, jQuery, CDN script eksternal).
2. **Dilarang Refactor Arsitektur Berjalan Tanpa Instruksi**:
   - Dilarang keras mengubah atau me-refactor arsitektur utama yang sudah berjalan tanpa instruksi eksplisit dari pengguna.
   - Dilarang mengubah struktur rute web dan API yang sudah digunakan oleh front-end dan live feed spreadsheet (`proposals.*`, `suggestions.*`, `exports.*`).
   - Dilarang mengubah skema SQLite WAL atau memindahkan database ke provider lain tanpa instruksi eksplisit.
   - Sifat formulir dokter (`GET /` dan `POST /proposals`) adalah **zero-friction** (tanpa login/auth/PIN). Jangan pasang authentication guard pada form ini.
3. **Integritas Basis Data & Proteksi Migrasi**:
   - **DILARANG MERUSAK SKEMA LAMA**: Jangan pernah membuat file migrasi baru yang me-drop atau me-rename tabel/kolom yang sedang berjalan (`proposals`, `proposal_items`, `reference_medicines`) tanpa instruksi eksplisit.
   - Jika perlu menambahkan kolom baru, gunakan migrasi terpisah yang bersifat aditif (`nullable` atau memiliki `default`).
   - Jangan pernah menjalankan `migrate:fresh` di lingkungan selain pengujian lokal/testing.
4. **Keamanan & Sanitasi Feed**:
   - Live CSV feed (`/exports/proposals.csv`) wajib mempertahankan validasi `FORMULARY_FEED_TOKEN` menggunakan fungsi waktu-konstan `hash_equals()`.
   - Setiap nilai teks yang dikeluarkan ke CSV atau XLSX wajib melalui `SpreadsheetSanitizer::sanitizeCell()` untuk mencegah CSV Formula Injection.

---

## 4. Perintah Operasional Utama

Gunakan perintah-perintah berikut untuk operasional pengembangan, migrasi, build, dan pengujian:

### 4.1 Menjalankan Server & Environment
```bash
# Menjalankan seluruh stack pengembangan sekaligus (server, queue, logs, vite)
composer dev

# ATAU jalankan terminal terpisah:
php artisan serve
npm run dev
```

### 4.2 Manajemen Database & Migrasi
```bash
# Menjalankan migrasi database
php artisan migrate

# Reset database & seed awal (HANYA untuk dev awal lokal)
php artisan migrate:fresh --seed

# Menjalankan seeder gabungan data riil (API Dokter Portal Sifast + CSV Obat Referensi)
php artisan db:seed --class=FormulariumDataSeeder

# Impor manual kamus referensi obat dari CSV
php artisan formulary:import-csv database/seeders/data/formulary_medicines.csv
```

### 4.3 Kompilasi Aset Frontend
```bash
# Build aset untuk produksi (Tailwind v4 & Alpine.js via Vite)
npm run build

# Menjalankan dev server Vite dengan HMR
npm run dev
```

### 4.4 Pengujian (Testing) & Standarisasi Kode
```bash
# Menjalankan seluruh suite Pest PHP (28 tests, 159 assertions)
./vendor/bin/pest

# Menjalankan test via artisan/composer
composer test
# atau:
php artisan test

# Menjalankan uji satu file test tertentu
./vendor/bin/pest tests/Feature/ProposalSubmissionTest.php

# Memeriksa kepatuhan standarisasi kode PHP (Laravel Pint dry-run)
./vendor/bin/pint --test

# Memperbaiki formatting kode secara otomatis
./vendor/bin/pint
```

---

## 5. Prosedur Release & Git Tagging (Operational Guidelines for Agents)

Ketika pengguna menginstruksikan AI Agent untuk membuat **tag** atau **release**, agent **WAJIB** mengikuti alur kerja baku berikut secara disiplin:

### 5.1 Verifikasi Kualitas Pra-Rilis (*Quality Gate*)
Sebelum membuat tag apa pun, agent dilarang langsung mengeksekusi `git tag`. Agent wajib memastikan seluruh tahap verifikasi berikut terpenuhi:
1. **Working Tree Bersih**: Jalankan `git status` dan pastikan tidak ada uncommitted changes atau untracked files yang tertinggal. Jika ada perubahan yang sah, commit terlebih dahulu dengan pesan deskriptif.
2. **Suite Test 100% Pass**: Jalankan `./vendor/bin/pest`. Seluruh pengujian wajib berstatus PASS tanpa error atau peringatan kegagalan.
3. **Standarisasi Kode Bersih**: Jalankan `./vendor/bin/pint --test` untuk memastikan formatting kode PHP mematuhi standar repositori.
4. **Verifikasi Build Aset**: Jalankan `npm run build` untuk memvalidasi bahwa seluruh komponen Blade, Vite, Tailwind CSS v4, dan Alpine.js dapat terkompilasi ke `public/build` tanpa error.
5. **Sinkronisasi Branch**: Pastikan berada di branch `main` dan telah tersinkronisasi dengan upstream (`origin/main`).

### 5.2 Penentuan Versi Berdasarkan Semantic Versioning (SemVer)
- Periksa tag terakhir yang ada dengan `git tag -l --sort=-v:refname` atau `git describe --tags --abbrev=0`.
- Terapkan format `v<MAJOR>.<MINOR>.<PATCH>`:
  - **`v1.0.0`**: Rilis perdana sistem usulan (baseline produksi).
  - **`PATCH` (misal: `v1.0.1`)**: Perbaikan bug, perbaikan CSS/Blade, sanitasi spreadsheet, atau pembaruan dokumentasi tanpa perubahan fitur.
  - **`MINOR` (misal: `v1.1.0`)**: Penambahan fitur baru yang backward-compatible (misal: penambahan filter baru di rekapitulasi, tipe ekspor baru, integrasi API baru).
  - **`MAJOR` (misal: `v2.0.0`)**: Perubahan arsitektur mendasar, breaking changes skema database, atau perombakan alur formulir yang mengubah kontrak data lama.

### 5.3 Pembuatan Annotated Git Tag
Agent **WAJIB** menggunakan annotated tag (bendera `-a`), bukan lightweight tag:
```bash
# Contoh pembuatan annotated tag
git tag -a v1.0.0 -m "Release v1.0.0: Sistem Usulan Obat Formularium RS Aisyiyah Siti Fatimah Tulangan"

# Verifikasi metadata tag
git show v1.0.0

# Push tag ke remote GitHub
git push origin v1.0.0
```
> **PENTING**: Dilarang menggunakan force push (`--force`) pada tag yang sudah dipublikasikan ke remote.

### 5.4 Pembuatan GitHub Release & Penyusunan Catatan Rilis (*Release Notes*)
1. Jika GitHub CLI (`gh`) tersedia dan terautentikasi:
   ```bash
   gh release create v1.0.0 \
     --title "v1.0.0 — Rilis Awal Sistem Usulan Obat Formularium RS" \
     --generate-notes
   ```
2. Jika pembuatan release dilakukan via antarmuka web GitHub, Agent wajib menyajikan teks **Catatan Rilis (*Release Notes*)** yang terstruktur rapi untuk disalin pengguna, dengan struktur:
   - **Ringkasan Rilis**: Tujuan dan konteks rilis ini.
   - **Fitur Baru (Added)**: Fitur yang baru diimplementasikan beserta dampaknya bagi pengguna/DPJP/Farmasi.
   - **Perbaikan & Peningkatan (Changed / Fixed)**: Perbaikan bug, performa, atau penyesuaian UI.
   - **Dokumentasi & Operasional (Docs)**: Pembaruan panduan atau file referensi teknis.
   - **Status Pengujian**: Ringkasan jumlah test passing pada versi tersebut.
