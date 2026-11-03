import './bootstrap';
import Alpine from 'alpinejs';

Alpine.data('proposalForm', () => {
    let initialData = { initialItems: [], initialDoctor: '' };
    const dataElement = document.getElementById('proposal-form-data');
    if (dataElement && dataElement.textContent) {
        try {
            initialData = JSON.parse(dataElement.textContent);
        } catch (e) {
            console.error('Failed to parse proposal initial data', e);
        }
    }

    const initialItems = initialData.initialItems && initialData.initialItems.length
        ? initialData.initialItems.map((it) => ({
            medicine_name: it.medicine_name || '',
            principal_name: it.principal_name || '',
            autoFilled: false,
        }))
        : [{ medicine_name: '', principal_name: '', autoFilled: false }];

    return {
        doctorInput: initialData.initialDoctor || '',
        doctorSuggestions: [],
        doctorIsOpen: false,
        doctorLoading: false,
        doctorHighlightedIndex: -1,
        doctorDebounceTimer: null,
        doctorAbortController: null,
        items: initialItems,
        submitting: false,

        handleDoctorInput(e) {
            clearTimeout(this.doctorDebounceTimer);
            if (this.doctorAbortController) {
                this.doctorAbortController.abort();
                this.doctorAbortController = null;
            }

            const val = e.target.value.trim();
            this.doctorHighlightedIndex = -1;

            if (val.length < 1) {
                this.doctorSuggestions = [];
                this.doctorIsOpen = false;
                this.doctorLoading = false;
                return;
            }

            this.doctorLoading = true;
            this.doctorIsOpen = true;

            this.doctorDebounceTimer = setTimeout(() => {
                if (this.doctorInput.trim().length < 1) {
                    this.doctorSuggestions = [];
                    this.doctorIsOpen = false;
                    this.doctorLoading = false;
                    return;
                }

                this.doctorAbortController = new AbortController();
                fetch(`/api/suggestions/doctors?q=${encodeURIComponent(val)}`, {
                    signal: this.doctorAbortController.signal,
                })
                    .then((res) => res.json())
                    .then((json) => {
                        if (this.doctorInput.trim().length < 1) {
                            this.doctorSuggestions = [];
                            this.doctorIsOpen = false;
                            this.doctorLoading = false;
                            return;
                        }
                        this.doctorSuggestions = json.data || [];
                        this.doctorLoading = false;
                        this.doctorIsOpen = true;
                    })
                    .catch((err) => {
                        if (err && err.name === 'AbortError') return;
                        this.doctorSuggestions = [];
                        this.doctorLoading = false;
                    });
            }, 200);
        },

        navigateDoctorDown() {
            if (!this.doctorIsOpen) return;
            const max = this.doctorSuggestions.length - 1;
            if (this.doctorHighlightedIndex < max) {
                this.doctorHighlightedIndex++;
            }
        },

        navigateDoctorUp() {
            if (!this.doctorIsOpen) return;
            if (this.doctorHighlightedIndex > 0) {
                this.doctorHighlightedIndex--;
            }
        },

        selectDoctorHighlighted() {
            if (
                this.doctorHighlightedIndex >= 0 &&
                this.doctorSuggestions[this.doctorHighlightedIndex]
            ) {
                this.selectDoctor(this.doctorSuggestions[this.doctorHighlightedIndex].name);
            } else if (this.doctorInput.trim() !== '') {
                this.doctorIsOpen = false;
            }
        },

        selectDoctor(name) {
            clearTimeout(this.doctorDebounceTimer);
            if (this.doctorAbortController) {
                this.doctorAbortController.abort();
                this.doctorAbortController = null;
            }
            this.doctorInput = name;
            this.doctorIsOpen = false;
            this.doctorLoading = false;
            this.doctorHighlightedIndex = -1;
            this.doctorSuggestions = [];
        },

        closeDoctorDropdown() {
            this.doctorIsOpen = false;
            this.doctorHighlightedIndex = -1;
        },

        addItem() {
            if (this.items.length < 50) {
                this.items.push({ medicine_name: '', principal_name: '', autoFilled: false });
            }
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },
    };
});

Alpine.data('medicineAutocomplete', (item) => ({
    isOpen: false,
    suggestions: [],
    highlightedIndex: -1,
    debounceTimer: null,
    abortController: null,

    handleInput(e) {
        clearTimeout(this.debounceTimer);
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }

        const val = e.target.value.trim();
        this.highlightedIndex = -1;
        if (val.length < 1) {
            this.suggestions = [];
            this.isOpen = false;
            return;
        }

        this.debounceTimer = setTimeout(() => {
            if (item.medicine_name.trim().length < 1) {
                this.suggestions = [];
                this.isOpen = false;
                return;
            }

            this.abortController = new AbortController();
            fetch(`/api/suggestions/medicines?q=${encodeURIComponent(val)}`, {
                signal: this.abortController.signal,
            })
                .then((res) => res.json())
                .then((json) => {
                    if (item.medicine_name.trim().length < 1) {
                        this.suggestions = [];
                        this.isOpen = false;
                        return;
                    }
                    this.suggestions = json.data || [];
                    this.isOpen = this.suggestions.length > 0;
                })
                .catch((err) => {
                    if (err && err.name === 'AbortError') return;
                    this.suggestions = [];
                    this.isOpen = false;
                });
        }, 180);
    },

    navigateDown() {
        if (!this.isOpen) return;
        if (this.highlightedIndex < this.suggestions.length - 1) {
            this.highlightedIndex++;
        }
    },

    navigateUp() {
        if (!this.isOpen) return;
        if (this.highlightedIndex > 0) {
            this.highlightedIndex--;
        }
    },

    selectHighlighted() {
        if (this.highlightedIndex >= 0 && this.suggestions[this.highlightedIndex]) {
            this.pick(this.suggestions[this.highlightedIndex]);
        }
    },

    pick(sug) {
        clearTimeout(this.debounceTimer);
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
        item.medicine_name = sug.medicine_name;
        if (sug.principal_name && (!item.principal_name || item.autoFilled)) {
            item.principal_name = sug.principal_name;
            item.autoFilled = true;
        }
        this.suggestions = [];
        this.isOpen = false;
    },
}));

Alpine.data('principalAutocomplete', (item) => ({
    isOpen: false,
    suggestions: [],
    highlightedIndex: -1,
    debounceTimer: null,
    abortController: null,

    handleInput(e) {
        clearTimeout(this.debounceTimer);
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }

        item.autoFilled = false;
        const val = e.target.value.trim();
        this.highlightedIndex = -1;
        if (val.length < 1) {
            this.suggestions = [];
            this.isOpen = false;
            return;
        }

        this.debounceTimer = setTimeout(() => {
            if ((item.principal_name || '').trim().length < 1) {
                this.suggestions = [];
                this.isOpen = false;
                return;
            }

            this.abortController = new AbortController();
            fetch(`/api/suggestions/principals?q=${encodeURIComponent(val)}`, {
                signal: this.abortController.signal,
            })
                .then((res) => res.json())
                .then((json) => {
                    if ((item.principal_name || '').trim().length < 1) {
                        this.suggestions = [];
                        this.isOpen = false;
                        return;
                    }
                    this.suggestions = json.data || [];
                    this.isOpen = this.suggestions.length > 0;
                })
                .catch((err) => {
                    if (err && err.name === 'AbortError') return;
                    this.suggestions = [];
                    this.isOpen = false;
                });
        }, 180);
    },

    navigateDown() {
        if (!this.isOpen) return;
        if (this.highlightedIndex < this.suggestions.length - 1) {
            this.highlightedIndex++;
        }
    },

    navigateUp() {
        if (!this.isOpen) return;
        if (this.highlightedIndex > 0) {
            this.highlightedIndex--;
        }
    },

    selectHighlighted() {
        if (this.highlightedIndex >= 0 && this.suggestions[this.highlightedIndex]) {
            this.pick(this.suggestions[this.highlightedIndex]);
        }
    },

    pick(name) {
        clearTimeout(this.debounceTimer);
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
        item.principal_name = name;
        item.autoFilled = false;
        this.suggestions = [];
        this.isOpen = false;
    },
}));

Alpine.data('customSelect', (config) => ({
    isOpen: false,
    value: config.value || '',
    options: config.options || [],
    search: '',
    highlightedIndex: -1,

    init() {
        this.$watch('isOpen', (val) => {
            if (val) {
                this.search = '';
                this.highlightedIndex = -1;
                this.$nextTick(() => {
                    if (this.$refs.searchInput) {
                        this.$refs.searchInput.focus();
                    }
                });
            }
        });
    },

    get filteredOptions() {
        const q = this.search.trim().toLowerCase();
        if (!q) return this.options;
        return this.options.filter((opt) => {
            const label = typeof opt === 'string' ? opt : (opt.label || opt.value || '');
            return label.toLowerCase().includes(q);
        });
    },

    get selectedLabel() {
        if (!this.value) return config.placeholder || 'Pilih...';
        const found = this.options.find((opt) => {
            const val = typeof opt === 'string' ? opt : opt.value;
            return val === this.value;
        });
        if (!found) return this.value;
        return typeof found === 'string' ? found : found.label;
    },

    select(val) {
        this.value = val;
        this.isOpen = false;
        this.search = '';
        this.highlightedIndex = -1;
    },

    clear() {
        this.value = '';
        this.isOpen = false;
        this.search = '';
        this.highlightedIndex = -1;
    },

    navigateDown() {
        const list = this.filteredOptions;
        if (this.highlightedIndex < list.length - 1) {
            this.highlightedIndex++;
            this.scrollToHighlighted();
        }
    },

    navigateUp() {
        if (this.highlightedIndex > 0) {
            this.highlightedIndex--;
            this.scrollToHighlighted();
        }
    },

    selectHighlighted() {
        const list = this.filteredOptions;
        if (this.highlightedIndex >= 0 && list[this.highlightedIndex]) {
            const item = list[this.highlightedIndex];
            this.select(typeof item === 'string' ? item : item.value);
        }
    },

    scrollToHighlighted() {
        this.$nextTick(() => {
            const listEl = this.$refs.optionsList;
            if (!listEl) return;
            const highlightedEl = listEl.querySelector(`[data-index="${this.highlightedIndex}"]`);
            if (highlightedEl) {
                highlightedEl.scrollIntoView({ block: 'nearest' });
            }
        });
    },
}));

window.Alpine = Alpine;

Alpine.start();
