<x-layout :title="'Rekapitulasi Usulan Obat Formularium'" :activeTab="'recap'">
    <div class="space-y-6" x-data="{ showSpreadsheetModal: false, copied: false }">

        <!-- Top Header & Action Controls -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <span class="w-2.5 h-6 bg-emerald-600 rounded-full inline-block"></span>
                    Rekapitulasi Usulan Obat Formularium
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Seluruh usulan obat dari para dokter tercatat secara real-time untuk ditinjau oleh Farmasi.
                </p>
            </div>

            <div class="flex items-center flex-wrap gap-2.5">
                <!-- Connect Live Spreadsheet Modal Button -->
                <button type="button" 
                        @click="showSpreadsheetModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-300 hover:border-emerald-500 hover:bg-emerald-50/50 text-slate-800 hover:text-emerald-900 text-xs font-semibold shadow-2xs transition-all">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Hubungkan Live Spreadsheet
                </button>

                <!-- Export to Excel XLSX Button -->
                <a href="{{ url('/exports/proposals.xlsx?' . http_build_query(request()->query())) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 active:bg-emerald-900 text-white text-xs font-semibold shadow-xs transition-all">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export ke Excel (.xlsx)
                </a>
            </div>
        </div>

        <!-- 3 Summary Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Obat Diusulkan</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($totalItems, 0, ',', '.') }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">Termasuk obat dari berbagai principle</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Dokter Berpartisipasi</p>
                        <p class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($totalDoctors, 0, ',', '.') }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">Dokter spesialis dan dokter umum aktif</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pengiriman Formulir</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($totalProposals, 0, ',', '.') }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">Sesi pengajuan formulir terdaftar</p>
            </div>
        </div>

        <!-- Filter Bar Card -->
        <div class="relative z-20 bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5">
            <form method="GET" action="{{ route('proposals.index') }}" class="space-y-4">
                @if (!empty($filters['period_year']))
                    <input type="hidden" name="period_year" value="{{ $filters['period_year'] }}">
                @endif

                <!-- Quick Year Filter Chips -->
                <div class="flex items-center flex-wrap gap-1.5 pb-2 border-b border-slate-100 text-xs">
                    <span class="font-semibold text-slate-500 mr-1.5">Tahun Periode:</span>
                    <a href="{{ route('proposals.index', array_merge(request()->except('period_year', 'page'), ['period_year' => ''])) }}"
                       class="px-3 py-1 rounded-full font-medium transition-colors {{ empty($filters['period_year']) ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Semua Tahun
                    </a>
                    @foreach ($availableYears as $year)
                        <a href="{{ route('proposals.index', array_merge(request()->except('period_year', 'page'), ['period_year' => $year])) }}"
                           class="px-3 py-1 rounded-full font-medium transition-colors {{ ($filters['period_year'] ?? null) == $year ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                            {{ $year }}
                        </a>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 relative z-20">
                    <!-- Dropdown Dokter (Custom Select) -->
                    <x-custom-select 
                        name="doctor"
                        label="Dokter"
                        :options="$availableDoctors"
                        :selected="$filters['doctor'] ?? ''"
                        placeholder="Semua Dokter"
                        search-placeholder="Cari nama dokter..." />

                    <!-- Dropdown Principle (Custom Select) -->
                    <x-custom-select 
                        name="principal"
                        label="Principle / Pabrik"
                        :options="$availablePrincipals"
                        :selected="$filters['principal'] ?? ''"
                        placeholder="Semua Principle"
                        search-placeholder="Cari principle / pabrik..." />

                    <!-- Dari Tanggal -->
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Dari Tanggal</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full h-[38px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden">
                    </div>

                    <!-- Sampai Tanggal -->
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Sampai Tanggal</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full h-[38px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden">
                    </div>
                </div>

                <!-- Baris Pencarian Kata Kunci & Tombol Filter -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 relative z-10">
                    <div class="w-full sm:flex-1 relative">
                        <input type="text" 
                               name="search" 
                               value="{{ $filters['search'] ?? '' }}" 
                               placeholder="Cari nama obat, dokter, atau principle..." 
                               class="w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                        <a href="{{ route('proposals.index') }}" 
                           class="px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition-colors">
                            Reset
                        </a>
                        <button type="submit" 
                                class="px-5 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold shadow-xs transition-colors">
                            Terapkan Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Data Table Card -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold text-slate-600 uppercase tracking-wider">
                        <tr>
                            <th scope="col" class="py-3 px-4 w-12 text-center">No</th>
                            <th scope="col" class="py-3 px-4">Tanggal Usulan</th>
                            <th scope="col" class="py-3 px-4">Periode</th>
                            <th scope="col" class="py-3 px-4">Nama Dokter</th>
                            <th scope="col" class="py-3 px-4">Nama Obat Diusulkan</th>
                            <th scope="col" class="py-3 px-4">Principle / Pabrik</th>
                            <th scope="col" class="py-3 px-4 min-w-[220px]">Alasan Klinis</th>
                            <th scope="col" class="py-3 px-4 min-w-[200px]">Catatan Pengusul</th>
                            <th scope="col" class="py-3 px-4">Waktu Input</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($items as $index => $item)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3 px-4 text-center text-slate-400 font-medium">
                                    {{ $items->firstItem() + $index }}
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-900 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($item->proposal_date)->translatedFormat('d M Y') }}
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/50">
                                        {{ $item->period_year }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-medium text-slate-800">
                                    {{ $item->doctor_name }}
                                </td>
                                <td class="py-3 px-4 font-semibold text-emerald-950">
                                    {{ $item->medicine_name }}
                                </td>
                                <td class="py-3 px-4">
                                    @if ($item->principal_name)
                                        <span class="inline-block px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700">
                                            {{ $item->principal_name }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-700 whitespace-pre-line leading-relaxed">
                                    @if ($item->clinical_justification)
                                        {{ $item->clinical_justification }}
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-600 whitespace-pre-line leading-relaxed">
                                    @if ($item->notes)
                                        {{ $item->notes }}
                                    @else
                                        <span class="text-slate-400 italic text-[11px]">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-400 whitespace-nowrap text-[11px]">
                                    {{ $item->created_at ? $item->created_at->translatedFormat('d/m/Y H:i') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-400">
                                    <svg class="mx-auto h-8 w-8 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <p class="font-medium text-slate-600">Tidak ada usulan obat yang cocok dengan filter saat ini.</p>
                                    <p class="text-xs text-slate-400 mt-1">Coba ubah kata kunci atau hapus beberapa filter pencarian.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($items->hasPages())
                <div class="p-4 border-t border-slate-100 bg-slate-50/50">
                    {{ $items->links() }}
                </div>
            @endif
        </div>

        <!-- Live Spreadsheet Modal (Alpine.js) -->
        <div x-show="showSpreadsheetModal"
             x-transition.opacity.duration.200ms
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
             style="display: none;">
            <div class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4"
                 @click.outside="showSpreadsheetModal = false">
                
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Hubungkan Live Spreadsheet
                    </h3>
                    <button type="button" @click="showSpreadsheetModal = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3 text-xs text-slate-600">
                    <p>
                        Sinkronisasikan seluruh data usulan obat ke <strong>Google Sheets</strong> atau <strong>Microsoft Excel</strong> secara otomatis tanpa perlu unduh file berulang kali.
                    </p>

                    <!-- Google Sheets Formula 1-Click Copy -->
                    <div class="bg-slate-50 rounded-xl p-3 border border-slate-200">
                        <label class="block font-semibold text-slate-700 mb-1">Rumus Google Sheets:</label>
                        <div class="flex items-center gap-2">
                            <input type="text" 
                                   readonly 
                                   value="{{ $importDataFormula }}" 
                                   id="formulaInput"
                                   class="w-full bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 font-mono text-[11px] text-slate-800 select-all">
                            <button type="button"
                                    @click="navigator.clipboard.writeText('{{ $importDataFormula }}'); copied = true; setTimeout(() => copied = false, 2500)"
                                    class="px-3 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white font-semibold text-xs whitespace-nowrap transition-colors">
                                <span x-show="!copied">Salin</span>
                                <span x-show="copied" class="text-emerald-200">Tersalin!</span>
                            </button>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1.5">
                            Tempelkan rumus di atas pada sel <code>A1</code> di lembar Google Sheets Anda.
                        </p>
                        <div class="mt-2.5 p-2.5 bg-amber-50 rounded-lg border border-amber-200/80 text-[11px] text-amber-900 flex items-start gap-2">
                            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span><strong>Tips Kolom Tanggal:</strong> Jika Google Sheets menampilkan angka seri (seperti <code>46340</code>), cukup blok <strong>Kolom B</strong> &rarr; buka menu <strong>Format &gt; Angka &gt; Tanggal</strong>.</span>
                        </div>
                    </div>

                    <!-- Excel Guide -->
                    <div class="p-3 bg-emerald-50/50 rounded-xl border border-emerald-100 text-slate-700 space-y-1">
                        <p class="font-semibold text-emerald-950">Cara Pakai di Microsoft Excel:</p>
                        <ol class="list-decimal list-inside space-y-0.5 text-[11px] text-slate-600">
                            <li>Buka Excel &rarr; tab <strong>Data</strong> &rarr; pilih <strong>Dari Web (From Web)</strong>.</li>
                            <li>Masukkan URL CSV Feed ber-token berikut:</li>
                        </ol>
                        <input type="text" readonly value="{{ $liveCsvUrl }}" class="w-full bg-white border border-emerald-200 rounded-lg px-2 py-1 font-mono text-[10px] text-slate-700 select-all mt-1">
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="button" 
                            @click="showSpreadsheetModal = false"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layout>
