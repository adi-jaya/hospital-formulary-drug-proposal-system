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

window.Alpine = Alpine;

Alpine.start();
