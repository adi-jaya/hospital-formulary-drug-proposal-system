# Sistem Usulan Obat Formularium RS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun aplikasi web mandiri Sistem Usulan Obat Formularium RS Aisyiyah Siti Fatimah Tulangan yang memungkinkan dokter mengajukan banyak usulan obat tanpa login dari HP/PC serta menyediakan halaman rekapitulasi real-time, Live CSV Feed Spreadsheet, dan unduhan `.xlsx` rapi bagi Farmasi.

**Architecture:** Aplikasi monolitik ringan berbasis Laravel 12, Blade, Alpine.js lokal, dan Tailwind CSS v4 dengan database utama SQLite mode `WAL` (`busy_timeout = 5000`) menggunakan Eloquent standar yang 100% kompatibel dengan MariaDB. Data dokter diambil dari REST API `portalsifast` melalui backend cache + snapshot JSON lokal + gabungan histori dokter manual, sedangkan saran *autocomplete* menggabungkan kamus referensi read-only dari CSV RS (~206 obat) dengan histori `proposal_items` (*self-learning*) tanpa menulis ke tabel master saat submit.

**Tech Stack:** PHP `^8.3`, Laravel `^12.0`, SQLite 3 (`WAL`), Blade + Alpine.js `^3.14` + Tailwind CSS `^4.0`, `openspout/openspout:^4.32`, Pest PHP, Laravel Pint.

**Spec:** `/Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/SPEC-FormulariumRS.md`

## Global Constraints

- Lokasi proyek mandiri: `/Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS` — **dilarang keras** memodifikasi file apa pun di dalam `PortalSifast` atau `Official-Website-RSUASF`.
- Konvensi penamaan: **100% Bahasa Inggris** untuk nama tabel (`proposals`, `proposal_items`, `reference_medicines`), kolom, model, controller, service, dan route; **100% Bahasa Indonesia** untuk label UI, placeholder, dan pesan validasi.
- Zona waktu & bahasa: `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`.
- Tanpa login, manajemen user, maupun PIN di Halaman Form (`/`) dan Halaman Rekapitulasi (`/recap`); tidak ada fitur edit/hapus baris di UI publik; tidak menampilkan badge `"Sudah Masuk"` pada saran Autocomplete.
- Portabilitas DB: Gunakan standar Laravel Migration & Eloquent/Query Builder yang kompatibel di SQLite **dan** MariaDB (hindari sintaks SQL khusus SQLite di query domain).
- Sebelum menandai selesai: `./vendor/bin/pest`, `./vendor/bin/pint --test`, dan `npm run build` wajib lulus 100%.

## Review Focus

1. **Sel CSV multi-baris (`"dr. Hud\ndr. Gagang"`) dan koma desimal (`"Nuzolam 0,25 Mg"`):** Berkas CSV asli RS memiliki *newline* di dalam tanda kutip; importer wajib memakai stream `fgetcsv()` (bukan `file()` / `explode("\n")`) agar seluruh ~206 obat terbaca utuh tanpa baris pecah. *(Diuji di Task 2).*
2. **Respons HTTP 200 tidak valid / kosong dari API `portalsifast` saat server maintenance:** Jika API mengembalikan HTML maintenance atau `data: []` dengan status `200`, `DoctorDirectoryService` tidak boleh menimpa `doctors_snapshot.json` yang sehat dengan array kosong, melainkan tetap jatuh ke snapshot lokal. *(Diuji di Task 3).*
3. **Duplikasi *case-insensitive* pada Autocomplete (`"diarvit 100 mg tab"` vs `"Diarvit 100 Mg Tab"`):** Ketika dokter mengetik ulang obat yang sudah ada dengan variasi huruf besar/kecil berbeda, saran Autocomplete wajib menggabungkannya menjadi satu entri bersih tanpa kembar. *(Diuji di Task 4).*
4. **Input strip tunggal `"-"` pada `Nama Principle` vs Sanitasi Formula Injection:** Ketikan `"-"` atau spasi kosong pada `principal_name` harus tersimpan sebagai `null` di DB (bukan berubah menjadi `'-'` di CSV/Excel), sementara input berbahaya seperti `=CMD()` atau `+123` pada nama obat/dokter tetap diprefiks `'` saat diekspor ke CSV/XLSX. *(Diuji di Task 2, Task 5, & Task 7).*
5. **Pemulihan baris *dynamic repeater* saat validasi gagal:** Jika dokter sudah mengisi beberapa baris obat lalu lupa memilih `Nama Dokter`, redirect validasi gagal wajib mengembalikan seluruh baris `old('items')` secara utuh di Alpine.js tanpa mereset form menjadi 1 baris kosong. *(Diuji di Task 5).*

---

### Task 1: Bootstrap Proyek Laravel 12, Konfigurasi SQLite WAL & Skema Eloquent

**Files:**
- Create: `/Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/` (Laravel 12 project root)
- Create: `FormulariumRS/config/formulary.php`
- Modify: `FormulariumRS/config/app.php`, `FormulariumRS/config/database.php`, `FormulariumRS/.env.example`, `FormulariumRS/.env`
- Create: `FormulariumRS/database/migrations/2026_11_03_000001_create_proposals_table.php`
- Create: `FormulariumRS/database/migrations/2026_11_03_000002_create_proposal_items_table.php`
- Create: `FormulariumRS/database/migrations/2026_11_03_000003_create_reference_medicines_table.php`
- Create: `FormulariumRS/app/Models/Proposal.php`
- Create: `FormulariumRS/app/Models/ProposalItem.php`
- Create: `FormulariumRS/app/Models/ReferenceMedicine.php`
- Create: `FormulariumRS/database/factories/ProposalFactory.php`
- Create: `FormulariumRS/database/factories/ProposalItemFactory.php`
- Create: `FormulariumRS/database/factories/ReferenceMedicineFactory.php`
- Create: `FormulariumRS/docs/superpowers/specs/2026-11-03-formularium-rs-spec.md`
- Create: `FormulariumRS/docs/superpowers/plans/2026-11-03-formularium-rs-plan.md`
- Test: `FormulariumRS/tests/Feature/DatabaseSchemaAndWalTest.php`

**Interfaces:**
- Produces:
  - `App\Models\Proposal` (`fillable: ['doctor_name', 'proposal_date', 'period_year']`, `items(): HasMany`)
  - `App\Models\ProposalItem` (`fillable: ['proposal_id', 'medicine_name', 'principal_name']`, `proposal(): BelongsTo`)
  - `App\Models\ReferenceMedicine` (`fillable: ['medicine_name', 'principal_name']`)
  - `config('formulary.doctor_api_url')`, `config('formulary.doctor_api_timeout')`, `config('formulary.doctor_cache_ttl_hours')`, `config('formulary.feed_token')`

- [ ] **Step 1: Scaffold Laravel 12, pasang dependency, dan salin dokumen Spec & Plan**

Jalankan dari `/Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan`:
```bash
composer create-project laravel/laravel:^12.0 FormulariumRS --prefer-dist --no-interaction
cd FormulariumRS
composer require openspout/openspout:^4.32
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
npm install alpinejs@^3.14
mkdir -p docs/superpowers/specs docs/superpowers/plans
cp ../SPEC-FormulariumRS.md docs/superpowers/specs/2026-11-03-formularium-rs-spec.md
cp ../PLAN-FormulariumRS.md docs/superpowers/plans/2026-11-03-formularium-rs-plan.md
git init && git add . && git commit -m "chore: scaffold Laravel 12 FormulariumRS base"
```
*(Jika `pestphp/pest` sudah terpasang bawaan template Laravel 12, lewati `composer require --dev` yang redundan dan pastikan `./vendor/bin/pest` siap dijalankan).*

- [ ] **Step 2: Tulis failing test untuk PRAGMA SQLite WAL, relasi Eloquent, dan cascade delete**

Buat `tests/Feature/DatabaseSchemaAndWalTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\ReferenceMedicine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('configures sqlite connection defaults for wal, busy_timeout 5000, and foreign keys', function (): void {
    expect(config('database.connections.sqlite.busy_timeout'))->toBe(5000)
        ->and(config('database.connections.sqlite.journal_mode'))->toBe('WAL')
        ->and(config('database.connections.sqlite.synchronous'))->toBe('NORMAL')
        ->and(config('database.connections.sqlite.foreign_key_constraints'))->toBeTrue()
        ->and(config('app.timezone'))->toBe('Asia/Jakarta');
});

it('persists proposals with items and cascades on delete', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);

    $proposal->items()->createMany([
        ['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell'],
        ['medicine_name' => 'Paracetamol Drop', 'principal_name' => null],
    ]);

    ReferenceMedicine::create([
        'medicine_name' => 'Diarvit 100 Mg Tab',
        'principal_name' => 'Novell',
    ]);

    expect($proposal->items)->toHaveCount(2)
        ->and($proposal->items->first()->proposal->is($proposal))->toBeTrue();

    $proposal->delete();

    expect(ProposalItem::count())->toBe(0)
        ->and(ReferenceMedicine::count())->toBe(1);
});
```

- [ ] **Step 3: Jalankan test untuk memverifikasi kegagalan awal**

Run: `./vendor/bin/pest tests/Feature/DatabaseSchemaAndWalTest.php`
Expected: FAIL (`Class "App\Models\Proposal" not found`).

- [ ] **Step 4: Implementasikan konfigurasi `formulary.php`, SQLite PRAGMA, migrasi, model, dan factory**

- Set `timezone => env('APP_TIMEZONE', 'Asia/Jakarta')` dan `locale => env('APP_LOCALE', 'id')` di `config/app.php` serta `.env` / `.env.example` (generate `FORMULARY_FEED_TOKEN` acak 32 karakter di `.env` lokal).
- Di `config/database.php` pada koneksi `sqlite`, set `'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true)`, `'busy_timeout' => 5000`, `'journal_mode' => 'WAL'`, `'synchronous' => 'NORMAL'`.
- Buat `config/formulary.php` dengan key `doctor_api_url`, `doctor_api_timeout` (`10`), `doctor_cache_ttl_hours` (`12`), dan `feed_token`.
- Buat ketiga migrasi (`proposals`, `proposal_items`, `reference_medicines`), ketiga model (`Proposal`, `ProposalItem`, `ReferenceMedicine`), dan factory-nya sesuai kontrak di bagian Spec §4.2. Jalankan `php artisan migrate` pada `database/database.sqlite` dan verifikasi `sqlite3 database/database.sqlite "PRAGMA journal_mode;"` mengembalikan `wal`.

- [ ] **Step 5: Jalankan test dan pastikan lulus**

Run: `./vendor/bin/pest tests/Feature/DatabaseSchemaAndWalTest.php`
Expected: PASS (2 passed).

- [ ] **Step 6: Commit**

```bash
git add .
git commit -m "feat: configure SQLite WAL and add proposal & reference schemas"
```

---

### Task 2: Normalisasi Teks, Sanitasi Spreadsheet & Importer CSV Referensi (`206` Obat)

**Files:**
- Create: `FormulariumRS/app/Support/TextNormalizer.php`
- Create: `FormulariumRS/app/Support/SpreadsheetSanitizer.php`
- Create: `FormulariumRS/app/Services/FormularyCsvImporter.php`
- Create: `FormulariumRS/app/Console/Commands/ImportFormularyCsvCommand.php`
- Create: `FormulariumRS/database/seeders/ReferenceMedicineSeeder.php`
- Create: `FormulariumRS/database/seeders/data/formulary_medicines.csv` (disalin dari `/Users/adijaya/Downloads/REKAP FORMULARIUM RS - daftar obat.csv`)
- Modify: `FormulariumRS/database/seeders/DatabaseSeeder.php`
- Test: `FormulariumRS/tests/Unit/CsvImportNormalizationTest.php`

**Interfaces:**
- Consumes: `App\Models\ReferenceMedicine`
- Produces:
  - `App\Support\TextNormalizer::collapseWhitespace(?string $value): string`
  - `App\Support\TextNormalizer::normalizePrincipal(?string $value): ?string`
  - `App\Support\SpreadsheetSanitizer::sanitizeCell(?string $value): string`
  - `App\Services\FormularyCsvImporter::importFromPath(string $csvPath): array{imported: int, skipped: int}`
  - Artisan command: `php artisan formulary:import-csv {path?}`

- [ ] **Step 1: Salin berkas CSV sumber ke direktori seeder proyek**

```bash
mkdir -p database/seeders/data
cp "/Users/adijaya/Downloads/REKAP FORMULARIUM RS - daftar obat.csv" database/seeders/data/formulary_medicines.csv
```

- [ ] **Step 2: Tulis failing test untuk normalisasi teks, sanitasi formula injection, dan import CSV multi-baris**

Buat `tests/Unit/CsvImportNormalizationTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\ReferenceMedicine;
use App\Services\FormularyCsvImporter;
use App\Support\SpreadsheetSanitizer;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('normalizes whitespace, dash-only principals, and known csv typo aliases', function (): void {
    expect(TextNormalizer::collapseWhitespace("  Diarvit   100 Mg \t Tab "))->toBe('Diarvit 100 Mg Tab')
        ->and(TextNormalizer::normalizePrincipal(' - '))->toBeNull()
        ->and(TextNormalizer::normalizePrincipal('   '))->toBeNull()
        ->and(TextNormalizer::normalizePrincipal('Novel'))->toBe('Novell')
        ->and(TextNormalizer::normalizePrincipal('generik novell'))->toBe('Novell Generik');
});

it('escapes spreadsheet formula injection prefixes without corrupting empty or normal values', function (): void {
    expect(SpreadsheetSanitizer::sanitizeCell('=HYPERLINK("http://evil")'))->toBe("'=HYPERLINK(\"http://evil\")")
        ->and(SpreadsheetSanitizer::sanitizeCell('+628123'))->toBe("'+628123")
        ->and(SpreadsheetSanitizer::sanitizeCell('-100'))->toBe("'-100")
        ->and(SpreadsheetSanitizer::sanitizeCell('@sum(1,2)'))->toBe("'@sum(1,2)")
        ->and(SpreadsheetSanitizer::sanitizeCell(null))->toBe('')
        ->and(SpreadsheetSanitizer::sanitizeCell('Diarvit 100 Mg Tab'))->toBe('Diarvit 100 Mg Tab');
});

it('imports the hospital formulary csv idempotently including multiline quoted rows and comma decimals', function (): void {
    $path = database_path('seeders/data/formulary_medicines.csv');

    $firstRun = app(FormularyCsvImporter::class)->importFromPath($path);
    $secondRun = app(FormularyCsvImporter::class)->importFromPath($path);

    expect($firstRun['imported'])->toBeGreaterThanOrEqual(205)
        ->and(ReferenceMedicine::count())->toBe($firstRun['imported'])
        ->and(ReferenceMedicine::count())->toBe($secondRun['imported'])
        ->and(ReferenceMedicine::where('medicine_name', 'Nuzolam 0,25 Mg')->value('principal_name'))->toBe('Nulab')
        ->and(ReferenceMedicine::where('medicine_name', 'Puspacal')->value('principal_name'))->toBe('Dipa')
        ->and(ReferenceMedicine::where('medicine_name', 'L-Vit D3 Drop')->value('principal_name'))->toBe('Lapi')
        ->and(ReferenceMedicine::where('medicine_name', 'Pyridostigmin 60 Mg Tab')->value('principal_name'))->toBe('Novell')
        ->and(ReferenceMedicine::where('medicine_name', 'Paracetamol Drop')->value('principal_name'))->toBeNull();
});
```

- [ ] **Step 3: Jalankan test untuk memverifikasi kegagalan awal**

Run: `./vendor/bin/pest tests/Unit/CsvImportNormalizationTest.php`
Expected: FAIL (`Class "App\Support\TextNormalizer" not found`).

- [ ] **Step 4: Implementasikan `TextNormalizer`, `SpreadsheetSanitizer`, `FormularyCsvImporter`, command, & seeder**

- `TextNormalizer::collapseWhitespace`: `trim` dan `preg_replace('/\s+/u', ' ', ...)`.
- `TextNormalizer::normalizePrincipal`: kembalikan `null` jika kosong atau `preg_match('/^-+$/', $clean)`; petakan alias case-insensitive (`'novel' => 'Novell'`, `'generik novell' => 'Novell Generik'`, `'yarindo generik' => 'Yarindo Generik'`, `'fahrenheit generik' => 'Fahrenheit Generik'`).
- `SpreadsheetSanitizer::sanitizeCell`: kembalikan `''` jika `null`/`''`; jika karakter pertama termasuk `['=', '+', '-', '@', "\t", "\r"]`, awali dengan `'`.
- `FormularyCsvImporter::importFromPath`: buka file menggunakan `fopen` + loop `fgetcsv($handle)` (agar sel ber-*newline* seperti `"dr. Hud\ndr. Gagang"` tidak pecah), lewati baris header (`NAMA OBAT`, `REKAP USULAN...`, atau kosong), deduplikasi *case-insensitive* pada `medicine_name` (jika entri duplikat di CSV memiliki `principal_name` sementara entri sebelumnya `null`, pertahankan `principal_name` yang terisi), lalu simpan ke `reference_medicines`.
- Daftarkan `ReferenceMedicineSeeder` di `DatabaseSeeder` dan buat command `formulary:import-csv {path?}` (default ke `database_path('seeders/data/formulary_medicines.csv')`).

- [ ] **Step 5: Jalankan test & verifikasi `php artisan db:seed`**

Run: `./vendor/bin/pest tests/Unit/CsvImportNormalizationTest.php && php artisan migrate:fresh --seed`
Expected: PASS dan `reference_medicines` terisi ~206 baris obat unik.

- [ ] **Step 6: Commit**

```bash
git add .
git commit -m "feat: add CSV formulary importer, text normalizer, and spreadsheet sanitizer"
```

---

### Task 3: Layanan Tangguh Direktori Dokter (`DoctorDirectoryService`)

**Files:**
- Create: `FormulariumRS/app/Services/DoctorDirectoryService.php`
- Test: `FormulariumRS/tests/Feature/DoctorDirectoryServiceTest.php`

**Interfaces:**
- Consumes: `config('formulary.*')`, `App\Models\Proposal`, `App\Support\TextNormalizer`
- Produces:
  - `App\Services\DoctorDirectoryService::listDoctors(): array` — mengembalikan `list<array{name: string, clinic: ?string}>` terurut alfabetis berdasarkan `name`, hasil gabungan API/snapshot dan `proposals.doctor_name` tanpa duplikat *case-insensitive*.

- [ ] **Step 1: Tulis failing test untuk skenario sukses API, kegagalan jaringan/500, HTTP 200 payload rusak, & gabungan dokter manual**

Buat `tests/Feature/DoctorDirectoryServiceTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Services\DoctorDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Storage::fake('local');
});

it('fetches active doctors from portalsifast api, caches them, and writes a local snapshot', function (): void {
    Http::fake([
        '*' => Http::response([
            'success' => true,
            'data' => [
                [
                    'kdDokter' => '378.24.02.2022',
                    'namaDokter' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
                    'isAktif' => true,
                    'poli' => [['kdPoli' => 'URO', 'namaPoli' => 'Klinik Spesialis Urologi']],
                ],
                [
                    'kdDokter' => '999',
                    'namaDokter' => 'dr. Nonaktif',
                    'isAktif' => false,
                    'poli' => [],
                ],
            ],
        ], 200),
    ]);

    $doctors = app(DoctorDirectoryService::class)->listDoctors();

    expect($doctors)->toHaveCount(1)
        ->and($doctors[0])->toBe([
            'name' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
            'clinic' => 'Klinik Spesialis Urologi',
        ])
        ->and(Storage::disk('local')->exists('doctors_snapshot.json'))->toBeTrue();
});

it('falls back to local snapshot without overwriting it when api returns 500 or invalid 200 html/empty payload', function (): void {
    Storage::disk('local')->put('doctors_snapshot.json', json_encode([
        ['name' => 'dr. Delfia Rahmat Gozali Sp. A', 'clinic' => 'Klinik Spesialis Anak'],
    ], JSON_THROW_ON_ERROR));

    Http::fake([
        '*' => Http::sequence()
            ->push('<html>Maintenance</html>', 200)
            ->push(['success' => true, 'data' => []], 200)
            ->push([], 500),
    ]);

    $doctors = app(DoctorDirectoryService::class)->listDoctors();

    expect($doctors)->toHaveCount(1)
        ->and($doctors[0]['name'])->toBe('dr. Delfia Rahmat Gozali Sp. A');

    $snapshotAfter = json_decode((string) Storage::disk('local')->get('doctors_snapshot.json'), true);
    expect($snapshotAfter)->toHaveCount(1);
});

it('merges manual non-polyclinic doctors from proposals without case-insensitive duplicates', function (): void {
    Storage::disk('local')->put('doctors_snapshot.json', json_encode([
        ['name' => 'dr. Delfia Rahmat Gozali Sp. A', 'clinic' => 'Klinik Spesialis Anak'],
    ], JSON_THROW_ON_ERROR));
    Http::fake(['*' => Http::response([], 500)]);

    Proposal::create([
        'doctor_name' => 'dr. delfia rahmat gozali sp. a',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);
    Proposal::create([
        'doctor_name' => 'dr. Budi Santoso Sp.An',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);

    $doctors = app(DoctorDirectoryService::class)->listDoctors();

    expect($doctors)->toHaveCount(2)
        ->and(array_column($doctors, 'name'))->toBe([
            'dr. Budi Santoso Sp.An',
            'dr. Delfia Rahmat Gozali Sp. A',
        ]);
});
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan awal**

Run: `./vendor/bin/pest tests/Feature/DoctorDirectoryServiceTest.php`
Expected: FAIL (`Class "App\Services\DoctorDirectoryService" not found`).

- [ ] **Step 3: Implementasikan `DoctorDirectoryService`**

- Gunakan `Storage::disk('local')` untuk membaca/menulis `doctors_snapshot.json`.
- Panggil `Http::timeout((int) config('formulary.doctor_api_timeout', 10))->retry(2, 200, throw: false)->acceptJson()->get(...)` di dalam `Throwable` try/catch.
- Hanya anggap respons API valid jika `$response->successful()`, `data_get($json, 'success') === true`, dan hasil pemetaan dokter aktif (`isAktif === true` & `namaDokter !== ''`) tidak kosong. Simpan ke cache `formulary.doctors.api_list` selama `doctor_cache_ttl_hours` jam + tulis `doctors_snapshot.json`.
- Jika API gagal atau payload kosong/HTML, baca `doctors_snapshot.json` dan simpan ke cache fallback selama 15 menit agar request berikutnya tetap cepat.
- Gabungkan dengan `Proposal::query()->select('doctor_name')->distinct()->pluck('doctor_name')` secara *case-insensitive* (prioritaskan entri resmi dari API jika ejaan huruf besar/kecil berbeda).

- [ ] **Step 4: Jalankan test dan pastikan lulus**

Run: `./vendor/bin/pest tests/Feature/DoctorDirectoryServiceTest.php`
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
git add .
git commit -m "feat: add resilient DoctorDirectoryService with cache, snapshot fallback, and manual union"
```

---

### Task 4: Layanan Self-Learning Autocomplete & Endpoint Saran JSON

**Files:**
- Create: `FormulariumRS/app/Services/AutocompleteService.php`
- Create: `FormulariumRS/app/Http/Controllers/SuggestionController.php`
- Modify: `FormulariumRS/routes/web.php`
- Test: `FormulariumRS/tests/Feature/AutocompleteSuggestionTest.php`

**Interfaces:**
- Consumes: `App\Models\ReferenceMedicine`, `App\Models\ProposalItem`, `App\Support\TextNormalizer`
- Produces:
  - `App\Services\AutocompleteService::suggestMedicines(string $query = '', int $limit = 15): array` — mengembalikan `list<array{medicine_name: string, principal_name: ?string}>`
  - `App\Services\AutocompleteService::suggestPrincipals(string $query = '', int $limit = 15): array` — mengembalikan `list<string>`
  - Routes: `GET /api/suggestions/medicines?q=` (`suggestions.medicines`) dan `GET /api/suggestions/principals?q=` (`suggestions.principals`) mengembalikan `{"data": [...]}`.

- [ ] **Step 1: Tulis failing test untuk union CSV + `proposal_items`, auto-fill pasangan principle, deduplikasi case-insensitive, & tanpa kebocoran null/badge**

Buat `tests/Feature/AutocompleteSuggestionTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Models\ReferenceMedicine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns paired principal_name from reference_medicines and newly submitted proposal_items without duplicates or status badges', function (): void {
    ReferenceMedicine::create([
        'medicine_name' => 'Diarvit 100 Mg Tab',
        'principal_name' => 'Novell',
    ]);

    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi Sp.An',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);
    $proposal->items()->createMany([
        ['medicine_name' => 'diarvit 100 mg tab', 'principal_name' => 'Novell'],
        ['medicine_name' => 'Precedex 200 Mcg Inj', 'principal_name' => 'Pfizer'],
    ]);

    $medResponse = $this->getJson(route('suggestions.medicines', ['q' => 'diarvit']))->assertOk();
    expect($medResponse->json('data'))->toHaveCount(1)
        ->and($medResponse->json('data.0'))->toBe([
            'medicine_name' => 'Diarvit 100 Mg Tab',
            'principal_name' => 'Novell',
        ])
        ->and($medResponse->json('data.0'))->not->toHaveKey('status');

    $newMedResponse = $this->getJson(route('suggestions.medicines', ['q' => 'precedex']))->assertOk();
    expect($newMedResponse->json('data.0'))->toBe([
        'medicine_name' => 'Precedex 200 Mcg Inj',
        'principal_name' => 'Pfizer',
    ]);
});

it('returns distinct non-empty principals matching query with prefix priority', function (): void {
    ReferenceMedicine::create(['medicine_name' => 'Obat A', 'principal_name' => 'Sanbe']);
    ReferenceMedicine::create(['medicine_name' => 'Obat B', 'principal_name' => null]);

    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi Sp.An',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);
    $proposal->items()->createMany([
        ['medicine_name' => 'Obat C', 'principal_name' => 'sanbe'],
        ['medicine_name' => 'Obat D', 'principal_name' => 'Kalbe Farma'],
        ['medicine_name' => 'Obat E', 'principal_name' => null],
    ]);

    $response = $this->getJson(route('suggestions.principals', ['q' => 'sa']))->assertOk();

    expect($response->json('data'))->toBe(['Sanbe']);
});
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan awal**

Run: `./vendor/bin/pest tests/Feature/AutocompleteSuggestionTest.php`
Expected: FAIL (`Route [suggestions.medicines] not defined`).

- [ ] **Step 3: Implementasikan `AutocompleteService`, `SuggestionController`, dan rute `/api/suggestions/*`**

- Di `AutocompleteService`: ambil kandidat yang cocok menggunakan `where('medicine_name', 'like', '%'.$escaped.'%')` pada `ReferenceMedicine` dan `ProposalItem` (escape karakter wildcard `%` dan `_`), gabungkan di koleksi PHP dengan kunci `mb_strtolower($medicineName)`:
  - Jika obat sudah ada di `ReferenceMedicine`, utamakan kapitalisasi baku dari `ReferenceMedicine` (dan lengkapi `principal_name` dari `ProposalItem` terbaru jika di referensi `null`).
  - Urutkan hasil: kecocokan awalan (`str_starts_with(mb_strtolower(...), $qLower)`) lebih dulu, lalu alfabetis, batasi `$limit`.
- Terapkan pola serupa pada `suggestPrincipals` dengan mengabaikan `null` dan string kosong.

- [ ] **Step 4: Jalankan test dan pastikan lulus**

Run: `./vendor/bin/pest tests/Feature/AutocompleteSuggestionTest.php`
Expected: PASS (2 passed).

- [ ] **Step 5: Commit**

```bash
git add .
git commit -m "feat: add hybrid self-learning AutocompleteService and suggestion endpoints"
```

---

### Task 5: Halaman Formulir Usulan Obat, Dynamic Repeater Alpine.js & Anti Double-Submit

**Files:**
- Create: `FormulariumRS/app/Http/Requests/StoreProposalRequest.php`
- Create: `FormulariumRS/app/Http/Controllers/ProposalController.php`
- Create: `FormulariumRS/resources/css/app.css`
- Create: `FormulariumRS/resources/js/app.js`
- Create: `FormulariumRS/resources/views/components/layout.blade.php`
- Create: `FormulariumRS/resources/views/proposals/create.blade.php`
- Modify: `FormulariumRS/routes/web.php`
- Test: `FormulariumRS/tests/Feature/ProposalSubmissionTest.php`

**Interfaces:**
- Consumes: `DoctorDirectoryService::listDoctors()`, `SuggestionController` endpoints, `TextNormalizer`, `Proposal`, `ProposalItem`
- Produces:
  - Routes: `GET /` (`proposals.create`) dan `POST /proposals` (`proposals.store`, middleware `throttle:30,1`)
  - Shared Blade shell `<x-layout :title="..." :activeTab="'form'|'recap'">` untuk digunakan juga oleh Task 6.

- [ ] **Step 1: Tulis failing test untuk render form, transaksi master-detail, normalisasi `principal_name` kosong/`"-"`, pemulihan `old('items')`, dan idempotency double-submit**

Buat `tests/Feature/ProposalSubmissionTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Http::fake([
        '*' => Http::response([
            'success' => true,
            'data' => [[
                'kdDokter' => '378',
                'namaDokter' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
                'isAktif' => true,
                'poli' => [['kdPoli' => 'URO', 'namaPoli' => 'Klinik Spesialis Urologi']],
            ]],
        ], 200),
    ]);
});

it('renders the proposal form with injected doctor list and today default date', function (): void {
    $this->get(route('proposals.create'))
        ->assertOk()
        ->assertSee('Formulir Usulan Obat Formularium')
        ->assertSee('dr. Ahmad Nadhir Sp. U. M. Ked. Klin')
        ->assertSee(now()->toDateString());
});

it('stores proposal header and multiple items atomically, normalizing spaces and dash-only principals', function (): void {
    $payload = [
        'doctor_name' => '  dr. Budi   Santoso Sp.An ',
        'proposal_date' => '2026-11-15',
        'items' => [
            ['medicine_name' => '  Diarvit   100 Mg Tab ', 'principal_name' => '  Novel  '],
            ['medicine_name' => 'Paracetamol   Drop', 'principal_name' => ' - '],
            ['medicine_name' => 'Ketamin Inj', 'principal_name' => ''],
        ],
    ];

    $this->post(route('proposals.store'), $payload)
        ->assertRedirect(route('proposals.create'))
        ->assertSessionHas('submitted_summary');

    $proposal = Proposal::with('items')->sole();

    expect($proposal->doctor_name)->toBe('dr. Budi Santoso Sp.An')
        ->and($proposal->proposal_date->toDateString())->toBe('2026-11-15')
        ->and($proposal->period_year)->toBe(2026)
        ->and($proposal->items)->toHaveCount(3)
        ->and($proposal->items[0]->medicine_name)->toBe('Diarvit 100 Mg Tab')
        ->and($proposal->items[0]->principal_name)->toBe('Novell')
        ->and($proposal->items[1]->principal_name)->toBeNull()
        ->and($proposal->items[2]->principal_name)->toBeNull();
});

it('prevents duplicate double-submit of identical payload within 15 seconds', function (): void {
    $payload = [
        'doctor_name' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
        'proposal_date' => '2026-11-03',
        'items' => [
            ['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell'],
        ],
    ];

    $this->post(route('proposals.store'), $payload)->assertRedirect(route('proposals.create'));
    $this->post(route('proposals.store'), $payload)->assertRedirect(route('proposals.create'));

    expect(Proposal::count())->toBe(1)
        ->and(ProposalItem::count())->toBe(1);
});

it('preserves submitted repeater rows in session old input when validation fails', function (): void {
    $response = $this->from(route('proposals.create'))->post(route('proposals.store'), [
        'doctor_name' => '',
        'proposal_date' => '2026-11-03',
        'items' => [
            ['medicine_name' => 'Obat Satu 10 Mg', 'principal_name' => 'Sanbe'],
            ['medicine_name' => 'Obat Dua 20 Mg', 'principal_name' => 'Kalbe'],
        ],
    ]);

    $response->assertRedirect(route('proposals.create'))
        ->assertSessionHasErrors(['doctor_name']);

    $this->followRedirects($response)
        ->assertSee('Obat Satu 10 Mg')
        ->assertSee('Obat Dua 20 Mg');
});
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan awal**

Run: `./vendor/bin/pest tests/Feature/ProposalSubmissionTest.php`
Expected: FAIL (`Route [proposals.create] not defined`).

- [ ] **Step 3: Implementasikan `StoreProposalRequest`, `ProposalController`, Layout, Tailwind v4, & Alpine Repeater**

- `StoreProposalRequest`: normalisasi input di `prepareForValidation()` menggunakan `TextNormalizer::collapseWhitespace` dan `TextNormalizer::normalizePrincipal`; validasi `doctor_name` (`required|string|max:255`), `proposal_date` (`required|date`), `items` (`required|array|min:1|max:50`), `items.*.medicine_name` (`required|string|max:255`), `items.*.principal_name` (`nullable|string|max:255`) dengan pesan Bahasa Indonesia yang jelas.
- `ProposalController@store`: hitung SHA-256 fingerprint dari payload ternormalisasi, cek `Cache::add("formulary:idempotency:{$hash}", true, now()->addSeconds(15))`; jika `true`, simpan `Proposal` + `items()->createMany(...)` dalam `DB::transaction()`. Simpan ringkasan (`doctor_name`, `item_count`, `proposal_date`) ke flash session `submitted_summary` dan redirect ke `proposals.create`.
- `resources/css/app.css` & `components/layout.blade.php`: desain klinis bersih bernuansa hijau zamrud RS Aisyiyah Siti Fatimah (`#065F46` / `#059669` di latar terang `#F8FAFC`), header dengan identitas RS dan tab navigasi responsif (`Formulir Usulan` | `Rekapitulasi Usulan` — untuk sementara link rekap mengarah ke `url('/recap')` hingga Task 6 mendaftarkan route bernama `proposals.index`, atau daftarkan placeholder route `proposals.index` yang diimplementasikan penuh di Task 6).
- `resources/js/app.js` & `proposals/create.blade.php`: bundel `Alpine` lokal (`import Alpine from 'alpinejs'`), buat komponen *Searchable Doctor Combobox* (filter nama & klinik, dukung tombol `+ Gunakan nama dokter: "{ketikan}"`), *Dynamic Item Repeater* (inisialisasi dari `@json(old('items', [['medicine_name' => '', 'principal_name' => '']]))`), *Autocomplete* obat & principle dengan *debounce* 180ms + navigasi keyboard panah/Enter/Escape + *auto-fill* `principal_name` saat obat dipilih (jika `principal_name` kosong atau ditandai `autoFilled: true`), serta *disable button* saat submit.

- [ ] **Step 4: Jalankan test dan pastikan `npm run build` sukses**

Run: `./vendor/bin/pest tests/Feature/ProposalSubmissionTest.php && npm run build`
Expected: PASS (4 passed) dan build aset Vite sukses tanpa error.

- [ ] **Step 5: Commit**

```bash
git add .
git commit -m "feat: implement doctor combobox, dynamic medicine repeater form, and atomic submission"
```

---

### Task 6: Halaman Rekapitulasi Usulan, Filter Multi-Dimensi & Modal Live Spreadsheet

**Files:**
- Create: `FormulariumRS/app/Queries/ProposalItemRecapQuery.php`
- Create: `FormulariumRS/app/Http/Controllers/ProposalRecapController.php`
- Create: `FormulariumRS/resources/views/proposals/index.blade.php`
- Modify: `FormulariumRS/routes/web.php`
- Test: `FormulariumRS/tests/Feature/ProposalRecapFilterTest.php`

**Interfaces:**
- Consumes: `App\Models\Proposal`, `App\Models\ProposalItem`, `config('formulary.feed_token')`
- Produces:
  - `App\Queries\ProposalItemRecapQuery::build(array $filters): \Illuminate\Database\Eloquent\Builder` — melakukan `join('proposals', 'proposals.id', '=', 'proposal_items.proposal_id')`, memilih kolom gabungan (`proposal_items.*`, `proposals.doctor_name`, `proposals.proposal_date`, `proposals.period_year`), menerapkan filter `period_year`, `doctor`, `principal`, `date_from`, `date_to`, dan `search`, serta mengurutkan `proposals.proposal_date DESC, proposals.id DESC, proposal_items.id ASC`.
  - Route: `GET /recap` (`proposals.index`).

- [ ] **Step 1: Tulis failing test untuk tampilan tabel gabungan, metrik ringkas, seluruh filter, dan rumus `=IMPORTDATA`**

Buat `tests/Feature/ProposalRecapFilterTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('displays flattened proposal items and filters by period_year, doctor, principal, date range, and search keyword', function (): void {
    config(['formulary.feed_token' => 'secret-feed-token-2026']);

    $p2025 = Proposal::create([
        'doctor_name' => 'dr. Lama Sp.PD',
        'proposal_date' => '2025-11-10',
        'period_year' => 2025,
    ]);
    $p2025->items()->create(['medicine_name' => 'Obat Arsip 2025', 'principal_name' => 'Kalbe']);

    $p2026a = Proposal::create([
        'doctor_name' => 'dr. Delfia Rahmat Gozali Sp. A',
        'proposal_date' => '2026-11-02',
        'period_year' => 2026,
    ]);
    $p2026a->items()->create(['medicine_name' => 'Anazinc Sirup', 'principal_name' => 'Guardian']);

    $p2026b = Proposal::create([
        'doctor_name' => 'dr. Budi Sp.An',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);
    $p2026b->items()->create(['medicine_name' => 'Norevell 250 Ml', 'principal_name' => 'Novell']);

    $this->get(route('proposals.index'))
        ->assertOk()
        ->assertSee('Obat Arsip 2025')
        ->assertSee('Anazinc Sirup')
        ->assertSee('Norevell 250 Ml')
        ->assertSee('=IMPORTDATA(', false)
        ->assertSee('secret-feed-token-2026', false);

    $this->get(route('proposals.index', ['period_year' => 2026]))
        ->assertOk()
        ->assertSee('Anazinc Sirup')
        ->assertSee('Norevell 250 Ml')
        ->assertDontSee('Obat Arsip 2025');

    $this->get(route('proposals.index', ['doctor' => 'dr. Budi Sp.An']))
        ->assertOk()
        ->assertSee('Norevell 250 Ml')
        ->assertDontSee('Anazinc Sirup');

    $this->get(route('proposals.index', ['principal' => 'Guardian']))
        ->assertOk()
        ->assertSee('Anazinc Sirup')
        ->assertDontSee('Norevell 250 Ml');

    $this->get(route('proposals.index', ['search' => 'Norevell']))
        ->assertOk()
        ->assertSee('Norevell 250 Ml')
        ->assertDontSee('Anazinc Sirup');
});
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan awal**

Run: `./vendor/bin/pest tests/Feature/ProposalRecapFilterTest.php`
Expected: FAIL (`Class "App\Http\Controllers\ProposalRecapController" not found` atau teks belum ada).

- [ ] **Step 3: Implementasikan `ProposalItemRecapQuery`, `ProposalRecapController`, dan `resources/views/proposals/index.blade.php`**

- `ProposalItemRecapQuery::build(array $filters)`: bangun query join standar yang 100% kompatibel dengan SQLite & MariaDB.
- `ProposalRecapController@index`: hitung 3 metrik ringkasan dari klon query terfilter (`total_items`, `total_doctors` via `distinct('proposals.doctor_name')`, `total_proposals` via `distinct('proposals.id')`), sediakan daftar opsi dropdown filter (`availableYears`, `availableDoctors`, `availablePrincipals`), paginasi 25 baris dengan `withQueryString()`, dan kirimkan URL Live CSV Feed (beserta variasi tahun terpilih) ke view.
- `resources/views/proposals/index.blade.php`: tampilkan kartu metrik, bar filter responsif + chip tahun cepat + tombol `Reset`, tombol **Hubungkan Live Spreadsheet** (membuka modal Alpine dengan tombol *1-Click Copy* rumus `=IMPORTDATA(...)` dan panduan Excel *Data → From Web*), tombol **Export ke Excel (.xlsx)** yang meneruskan `request()->query()`, serta tabel responsif tanpa tombol edit/hapus.

- [ ] **Step 4: Jalankan test dan pastikan lulus**

Run: `./vendor/bin/pest tests/Feature/ProposalRecapFilterTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add .
git commit -m "feat: add proposal recapitulation page, filters, and live spreadsheet connection modal"
```

---

### Task 7: Endpoint Live CSV Feed Ber-Token & Export Excel `.xlsx` Rapi (`OpenSpout`)

**Files:**
- Create: `FormulariumRS/app/Services/ProposalExportService.php`
- Create: `FormulariumRS/app/Http/Controllers/ProposalExportController.php`
- Modify: `FormulariumRS/routes/web.php`
- Test: `FormulariumRS/tests/Feature/ProposalExportTest.php`

**Interfaces:**
- Consumes: `App\Queries\ProposalItemRecapQuery`, `App\Support\SpreadsheetSanitizer`, `config('formulary.feed_token')`, `OpenSpout\Writer\XLSX\Writer`
- Produces:
  - Routes:
    - `GET /exports/proposals.csv` (`exports.proposals.csv`)
    - `GET /exports/proposals.xlsx` (`exports.proposals.xlsx`)

- [ ] **Step 1: Tulis failing test untuk proteksi token CSV (`403` vs `200`), sanitasi formula injection di CSV, filter `period_year`, & pembacaan isi `.xlsx` nyata**

Buat `tests/Feature/ProposalExportTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('rejects live csv requests when feed token is missing, empty in config, or invalid', function (): void {
    config(['formulary.feed_token' => '']);
    $this->get(route('exports.proposals.csv', ['token' => 'anything']))->assertForbidden();

    config(['formulary.feed_token' => 'valid-secret-token']);
    $this->get(route('exports.proposals.csv'))->assertForbidden();
    $this->get(route('exports.proposals.csv', ['token' => 'wrong-token']))->assertForbidden();
});

it('streams sanitized utf-8 csv for valid token and respects optional period_year filter', function (): void {
    config(['formulary.feed_token' => 'valid-secret-token']);

    $p2025 = Proposal::create([
        'doctor_name' => 'dr. Lama',
        'proposal_date' => '2025-11-01',
        'period_year' => 2025,
    ]);
    $p2025->items()->create(['medicine_name' => 'Obat 2025', 'principal_name' => 'Sanbe']);

    $p2026 = Proposal::create([
        'doctor_name' => 'dr. Delfia Sp.A',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);
    $p2026->items()->create(['medicine_name' => '=CMD("calc")', 'principal_name' => null]);

    $response = $this->get(route('exports.proposals.csv', [
        'token' => 'valid-secret-token',
        'period_year' => 2026,
    ]))->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $csvContent = $response->streamedContent();
    expect($csvContent)
        ->toContain('No,Tanggal,"Tahun Periode","Nama Dokter","Nama Obat","Nama Principle","Waktu Input"')
        ->toContain("'=CMD(\"\"calc\"\")")
        ->not->toContain('Obat 2025');
});

it('exports a valid styled xlsx file matching active filters', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
        'proposal_date' => '2026-11-03',
        'period_year' => 2026,
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Diarvit 100 Mg Tab',
        'principal_name' => 'Novell',
    ]);

    $response = $this->get(route('exports.proposals.xlsx', ['period_year' => 2026]))->assertOk();

    expect($response->headers->get('Content-Type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $tmpFile = tempnam(sys_get_temp_dir(), 'formulary_test_') . '.xlsx';
    file_put_contents($tmpFile, $response->streamedContent());

    $reader = new Reader();
    $reader->open($tmpFile);

    $allText = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->toArray() as $cellValue) {
                $allText[] = (string) $cellValue;
            }
        }
    }
    $reader->close();
    @unlink($tmpFile);

    expect($allText)->toContain('REKAPITULASI USULAN OBAT FORMULARIUM RS AISYIYAH SITI FATIMAH TULANGAN')
        ->and($allText)->toContain('dr. Ahmad Nadhir Sp. U. M. Ked. Klin')
        ->and($allText)->toContain('Diarvit 100 Mg Tab')
        ->and($allText)->toContain('Novell');
});
```

- [ ] **Step 2: Jalankan test untuk memverifikasi kegagalan awal**

Run: `./vendor/bin/pest tests/Feature/ProposalExportTest.php`
Expected: FAIL (`Route [exports.proposals.csv] not defined`).

- [ ] **Step 3: Implementasikan `ProposalExportService` & `ProposalExportController`**

- `ProposalExportController@csv`: validasi `is_string($configuredToken) && $configuredToken !== '' && hash_equals($configuredToken, (string) $request->query('token', ''))`; jika gagal `abort(403)`. Gunakan `response()->stream(...)` dengan `fputcsv`, sanitasi setiap sel menggunakan `SpreadsheetSanitizer::sanitizeCell()`, dan set header `Cache-Control: no-store, no-cache, must-revalidate`.
- `ProposalExportController@xlsx`: buat file `.xlsx` menggunakan `OpenSpout\Writer\XLSX\Writer` + `Options`:
  - Judul laporan tebal ukuran 13 (`REKAPITULASI USULAN OBAT FORMULARIUM RS AISYIYAH SITI FATIMAH TULANGAN`) & subjudul waktu cetak/filter.
  - Header kolom (`No`, `Tanggal`, `Tahun Periode`, `Nama Dokter`, `Nama Obat`, `Nama Principle`) menggunakan `Style` bold, warna teks putih `Color::WHITE`, warna latar hijau zamrud `Color::rgb(6, 95, 70)`, dan `Border` tipis abu-abu.
  - Baris data ber-border tipis (`-` jika `principal_name` kosong, disanitasi dengan `SpreadsheetSanitizer::sanitizeCell()`), serta set lebar kolom eksplisit (`$options->setColumnWidth(...)`) proporsional dengan panjang maksimum tiap kolom (dibatasi min 8 – max 48).
  - Kembalikan sebagai `response()->streamDownload(...)` lalu hapus file temporer setelah terkirim.

- [ ] **Step 4: Jalankan seluruh suite pengujian, Pint, dan Vite build produksi**

Run: `./vendor/bin/pest && ./vendor/bin/pint --test && npm run build`
Expected: 100% PASS tanpa pelanggaran style maupun error kompilasi aset.

- [ ] **Step 5: Commit**

```bash
git add .
git commit -m "feat: add token-protected live CSV feed and styled OpenSpout XLSX export"
```
