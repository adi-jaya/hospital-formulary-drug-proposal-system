<x-layout :title="'Formulir Usulan Obat Formularium'" :activeTab="'form'">
    <!-- Safe JSON Data Carrier for Alpine Component (prevents HTML attribute quote truncation) -->
    <script id="proposal-form-data" type="application/json">
        {!! json_encode([
            'initialItems' => old('items', [['medicine_name' => '', 'principal_name' => '', 'clinical_justification' => '']]),
            'initialDoctor' => old('doctor_name', ''),
            'initialNotes' => old('notes', ''),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}
    </script>

    <div class="space-y-6" x-data="proposalForm">

        <!-- Flash Success Summary Notification -->
        @if (session('submitted_summary'))
            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-5 shadow-xs transition-all">
                <div class="flex items-start gap-4">
                    <div class="rounded-xl bg-emerald-600 text-white p-2.5 shrink-0 shadow-xs">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-base font-bold text-emerald-950">
                            Usulan Obat Berhasil Dikirimkan!
                        </h2>
                        <p class="mt-1 text-sm text-emerald-800">
                            Terima kasih, <strong>{{ session('submitted_summary')['doctor_name'] }}</strong>. Sebanyak 
                            <strong>{{ session('submitted_summary')['item_count'] }} usulan obat</strong> untuk tanggal 
                            <strong>{{ session('submitted_summary')['proposal_date'] }}</strong> telah tercatat secara real-time ke dalam Sistem Formularium RS.
                        </p>
                        <div class="mt-3 flex items-center gap-3">
                            <a href="{{ route('proposals.index') }}" class="inline-flex items-center text-xs font-semibold text-emerald-700 hover:text-emerald-900 underline gap-1">
                                Lihat Rekapitulasi Usulan &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Validation Errors Alert -->
        @if ($errors->any())
            <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 text-rose-900 text-sm shadow-xs">
                <div class="flex items-center gap-2 font-semibold">
                    <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Mohon periksa kembali formulir Anda:
                </div>
                <ul class="mt-2 list-disc list-inside space-y-1 text-xs text-rose-800 pl-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('proposals.store') }}" @submit="submitting = true" class="space-y-6">
            @csrf

            <!-- Card Header Form: Dokter & Tanggal -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6">
                <div class="border-b border-slate-100 pb-4 mb-5">
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <span class="w-2 h-5 bg-emerald-600 rounded-full inline-block"></span>
                        Data Pengusul & Tanggal Usulan
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Pilih nama dokter spesialis / umum dari direktori aktif rumah sakit atau ketik nama mandiri jika tidak terdaftar.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Searchable Doctor Combobox with Remote Debounce -->
                    <div class="relative" @click.outside="closeDoctorDropdown()">
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Nama Dokter <span class="text-rose-500">*</span>
                        </label>

                        <div class="relative">
                            <input type="text"
                                   x-model="doctorInput"
                                   @focus="if (doctorInput.trim() !== '' && doctorSuggestions.length > 0) doctorIsOpen = true"
                                   @input="handleDoctorInput($event)"
                                   @keydown.arrow-down.prevent="navigateDoctorDown()"
                                   @keydown.arrow-up.prevent="navigateDoctorUp()"
                                   @keydown.enter.prevent="selectDoctorHighlighted()"
                                   @keydown.escape="closeDoctorDropdown()"
                                   placeholder="Ketik nama dokter atau klinik untuk mencari..."
                                   autocomplete="off"
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-hidden transition-all @error('doctor_name') border-rose-500 bg-rose-50/30 @enderror">
                            
                            <input type="hidden" name="doctor_name" :value="doctorInput">

                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                                <svg x-show="!doctorLoading" class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <svg x-show="doctorLoading" class="w-4 h-4 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24" style="display: none;">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Doctor Dropdown Menu -->
                        <div x-show="doctorIsOpen && doctorInput.trim() !== '' && (doctorSuggestions.length > 0 || !doctorLoading)" 
                             x-transition.opacity.duration.150ms
                             class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-sm shadow-xl border border-slate-200 focus:outline-hidden"
                             style="display: none;">
                            <template x-for="(doc, dIdx) in doctorSuggestions" :key="doc.name">
                                <div @click="selectDoctor(doc.name)"
                                     :class="{ 'bg-emerald-50 text-emerald-900': doctorHighlightedIndex === dIdx }"
                                     class="cursor-pointer px-3.5 py-2 hover:bg-emerald-50 hover:text-emerald-900 transition-colors flex items-center justify-between gap-2 border-b border-slate-50 last:border-b-0">
                                    <span class="font-medium text-slate-800" x-text="doc.name"></span>
                                    <span x-show="doc.clinic" class="text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full" x-text="doc.clinic"></span>
                                </div>
                            </template>

                            <div x-show="doctorSuggestions.length === 0 && doctorInput.trim() !== '' && !doctorLoading" class="p-2">
                                <button type="button" 
                                        @click="selectDoctor(doctorInput)"
                                        class="w-full text-left px-3 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors">
                                    + Gunakan nama dokter: "<span x-text="doctorInput"></span>"
                                </button>
                            </div>
                        </div>

                        @error('doctor_name')
                            <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Tanggal Usulan -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Tanggal Usulan <span class="text-rose-500">*</span>
                        </label>
                        <input type="date"
                               name="proposal_date"
                               value="{{ old('proposal_date', now()->toDateString()) }}"
                               required
                               class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-hidden transition-all @error('proposal_date') border-rose-500 bg-rose-50/30 @enderror">
                        
                        @error('proposal_date')
                            <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Dynamic Medicine Repeater Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                            <span class="w-2 h-5 bg-emerald-600 rounded-full inline-block"></span>
                            Daftar Obat yang Diusulkan
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Ketik nama obat untuk mendapatkan saran otomatis dari referensi RS. Tekan tombol tambah untuk mengusulkan lebih dari satu obat.
                        </p>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">
                        Total: <span x-text="items.length"></span> obat
                    </span>
                </div>

                <div class="space-y-4">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/40 hover:bg-slate-50/90 transition-all space-y-3 relative group">
                            <div class="flex items-center justify-between">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-emerald-100 text-emerald-800 text-xs font-bold" x-text="index + 1"></span>

                                <button type="button"
                                        @click="removeItem(index)"
                                        :disabled="items.length <= 1"
                                        class="text-xs text-slate-400 hover:text-rose-600 disabled:opacity-30 disabled:pointer-events-none transition-colors flex items-center gap-1 font-medium">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Hapus
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Medicine Autocomplete -->
                                <div class="relative" 
                                     x-data="medicineAutocomplete(item)"
                                     @click.outside="isOpen = false">
                                    <label class="block text-xs font-medium text-slate-700 mb-1">
                                        Nama Obat & Bentuk Sediaan <span class="text-rose-500">*</span>
                                    </label>
                                    <input type="text"
                                           :name="`items[${index}][medicine_name]`"
                                           x-model="item.medicine_name"
                                           @input="handleInput($event)"
                                           @keydown.arrow-down.prevent="navigateDown()"
                                           @keydown.arrow-up.prevent="navigateUp()"
                                           @keydown.enter.prevent="selectHighlighted()"
                                           @keydown.escape="isOpen = false"
                                           placeholder="Contoh: Diarvit 100 Mg Tab"
                                           required
                                           autocomplete="off"
                                           class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-hidden transition-all">

                                    <!-- Medicine Dropdown Suggestions -->
                                    <div x-show="isOpen && suggestions.length > 0"
                                         x-transition.opacity.duration.150ms
                                         class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl bg-white py-1 text-sm shadow-xl border border-slate-200"
                                         style="display: none;">
                                        <template x-for="(sug, sIndex) in suggestions" :key="sIndex">
                                            <div @click="pick(sug)"
                                                 :class="{ 'bg-emerald-50 text-emerald-950 font-medium': highlightedIndex === sIndex }"
                                                 class="cursor-pointer px-3.5 py-2 hover:bg-emerald-50 hover:text-emerald-950 flex items-center justify-between text-xs border-b border-slate-50 last:border-b-0">
                                                <span class="font-medium text-slate-800" x-text="sug.medicine_name"></span>
                                                <span x-show="sug.principal_name" class="text-slate-400 text-[11px] italic" x-text="sug.principal_name"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- Principal Autocomplete -->
                                <div class="relative" 
                                     x-data="principalAutocomplete(item)"
                                     @click.outside="isOpen = false">
                                    <label class="block text-xs font-medium text-slate-700 mb-1">
                                        Nama Principle / Pabrik <span class="text-slate-400 text-[11px] font-normal">(Opsional)</span>
                                    </label>
                                    <input type="text"
                                           :name="`items[${index}][principal_name]`"
                                           x-model="item.principal_name"
                                           @input="handleInput($event)"
                                           @keydown.arrow-down.prevent="navigateDown()"
                                           @keydown.arrow-up.prevent="navigateUp()"
                                           @keydown.enter.prevent="selectHighlighted()"
                                           @keydown.escape="isOpen = false"
                                           placeholder="Contoh: Novell, Sanbe, Kalbe (atau kosongkan)"
                                           autocomplete="off"
                                           class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-hidden transition-all">

                                    <!-- Principal Dropdown Suggestions -->
                                    <div x-show="isOpen && suggestions.length > 0"
                                         x-transition.opacity.duration.150ms
                                         class="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-xl bg-white py-1 text-sm shadow-xl border border-slate-200"
                                         style="display: none;">
                                        <template x-for="(pName, pIndex) in suggestions" :key="pIndex">
                                            <div @click="pick(pName)"
                                                 :class="{ 'bg-emerald-50 text-emerald-950 font-medium': highlightedIndex === pIndex }"
                                                 class="cursor-pointer px-3.5 py-2 hover:bg-emerald-50 hover:text-emerald-950 text-xs border-b border-slate-50 last:border-b-0">
                                                <span x-text="pName"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Clinical Justification (Alasan Klinis) -->
                            <div class="pt-1">
                                <label class="block text-xs font-medium text-slate-700 mb-1">
                                    Alasan Klinis / Justifikasi Pengusulan <span class="text-slate-400 text-[11px] font-normal">(Opsional)</span>
                                </label>
                                <textarea :name="`items[${index}][clinical_justification]`"
                                          x-model="item.clinical_justification"
                                          rows="2"
                                          maxlength="2000"
                                          placeholder="Uraikan efikasi, keunggulan dibanding formularium lama, atau urgensi terapi..."
                                          class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-hidden transition-all resize-y"></textarea>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Catatan / Rekomendasi Tambahan Pengusul -->
                <div class="mt-6 pt-5 border-t border-slate-200">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                        Catatan / Rekomendasi Tambahan Pengusul <span class="text-slate-400 text-xs font-normal normal-case">(Opsional)</span>
                    </label>
                    <textarea name="notes"
                              x-model="notes"
                              rows="3"
                              maxlength="2000"
                              placeholder="Tuliskan catatan khusus terkait ketersediaan di distributor, alternatif substitusi, atau komparasi harga bila ada..."
                              class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-hidden transition-all resize-y @error('notes') border-rose-500 bg-rose-50/30 @enderror"></textarea>
                    @error('notes')
                        <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-4 pt-3 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100">
                    <button type="button"
                            @click="addItem()"
                            :disabled="items.length >= 50"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl border border-dashed border-emerald-600/60 bg-emerald-50/50 hover:bg-emerald-50 text-emerald-800 text-xs font-semibold transition-all">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah Baris Obat Lainnya
                    </button>

                    <button type="submit"
                            :disabled="submitting"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 active:bg-emerald-900 text-white font-semibold text-sm shadow-sm transition-all disabled:opacity-50 disabled:pointer-events-none">
                        <svg x-show="!submitting" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span x-text="submitting ? 'Mengirim Usulan...' : `Kirim Usulan Obat (${items.length} Obat)`"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-layout>
