@props([
    'name',
    'options' => [],
    'selected' => '',
    'placeholder' => 'Pilih...',
    'searchPlaceholder' => 'Cari...',
    'label' => null,
])

@php
    $normalizedOptions = collect($options)->map(function ($opt) {
        if (is_array($opt)) {
            return [
                'value' => (string) ($opt['value'] ?? ''),
                'label' => (string) ($opt['label'] ?? ($opt['value'] ?? '')),
            ];
        }
        return [
            'value' => (string) $opt,
            'label' => (string) $opt,
        ];
    })->values()->all();

    $selectedVal = (string) $selected;
    $selectedOption = collect($normalizedOptions)->firstWhere('value', $selectedVal);
    $initialLabel = $selectedOption ? $selectedOption['label'] : ($selectedVal !== '' ? $selectedVal : $placeholder);
@endphp

<div class="relative" 
     x-data="customSelect({
         name: @js($name),
         value: @js($selectedVal),
         placeholder: @js($placeholder),
         options: @js($normalizedOptions)
     })" 
     @click.outside="isOpen = false">
    
    @if ($label)
        <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">
            {{ $label }}
        </label>
    @endif

    <!-- Hidden Input for Form Submission -->
    <input type="hidden" name="{{ $name }}" value="{{ $selectedVal }}" :value="value">

    <!-- Trigger Button -->
    <div class="relative">
        <button type="button"
                @click="isOpen = !isOpen"
                @keydown.arrow-down.prevent="isOpen = true; $nextTick(() => $refs.searchInput?.focus())"
                @keydown.arrow-up.prevent="isOpen = true; $nextTick(() => $refs.searchInput?.focus())"
                @keydown.escape="isOpen = false"
                class="w-full flex items-center justify-between rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-left text-slate-800 hover:border-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 focus:outline-hidden transition-all shadow-2xs">
            <span class="truncate block pr-2" 
                  :class="{ 'text-slate-400 font-normal': !value, 'text-slate-900 font-medium': value }"
                  x-text="selectedLabel">
                {{ $initialLabel }}
            </span>

            <div class="flex items-center gap-1 shrink-0 text-slate-400">
                <!-- Clear Button if value is set -->
                <button type="button" 
                        x-show="value" 
                        @click.stop="clear()" 
                        title="Hapus pilihan"
                        class="p-0.5 rounded-full hover:bg-slate-100 hover:text-slate-600 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Chevron Icon -->
                <svg class="w-4 h-4 transition-transform duration-200" 
                     :class="{ 'rotate-180 text-emerald-600': isOpen }" 
                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </button>
    </div>

    <!-- Dropdown Menu -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1 scale-98"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-1 scale-98"
         class="absolute z-30 mt-1.5 w-full min-w-[200px] rounded-xl bg-white border border-slate-200 shadow-xl overflow-hidden focus:outline-hidden"
         style="display: none;">
        
        <!-- Search Input inside Dropdown -->
        <div class="p-2 border-b border-slate-100 bg-slate-50/50">
            <div class="relative">
                <input type="text"
                       x-ref="searchInput"
                       x-model="search"
                       @keydown.arrow-down.prevent="navigateDown()"
                       @keydown.arrow-up.prevent="navigateUp()"
                       @keydown.enter.prevent="selectHighlighted()"
                       @keydown.escape="isOpen = false"
                       placeholder="{{ $searchPlaceholder }}"
                       class="w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-500 focus:outline-hidden">
                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Options List -->
        <div x-ref="optionsList" class="max-h-56 overflow-y-auto py-1 text-xs">
            <!-- Reset / All Option -->
            <div @click="clear()"
                 class="px-3 py-2 cursor-pointer flex items-center justify-between transition-colors"
                 :class="{ 'bg-emerald-50 text-emerald-900 font-semibold': !value, 'text-slate-600 hover:bg-slate-50': value }">
                <span>{{ $placeholder }}</span>
                <span x-show="!value" class="text-emerald-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
            </div>

            <!-- Filtered Options Template -->
            <template x-for="(opt, idx) in filteredOptions" :key="opt.value">
                <div @click="select(opt.value)"
                     :data-index="idx"
                     class="px-3 py-2 cursor-pointer flex items-center justify-between transition-colors border-t border-slate-50"
                     :class="{
                         'bg-emerald-50 text-emerald-950 font-semibold': value === opt.value,
                         'bg-slate-100': highlightedIndex === idx && value !== opt.value,
                         'text-slate-800 hover:bg-slate-50': value !== opt.value && highlightedIndex !== idx
                     }">
                    <span class="truncate" x-text="opt.label"></span>
                    <span x-show="value === opt.value" class="text-emerald-600 shrink-0 ml-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                </div>
            </template>

            <!-- Empty Search Results -->
            <div x-show="filteredOptions.length === 0" class="px-3 py-4 text-center text-xs text-slate-400">
                Tidak ada opsi yang cocok
            </div>
        </div>
    </div>
</div>
