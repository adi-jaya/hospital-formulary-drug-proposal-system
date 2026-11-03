import './bootstrap';
import Alpine from 'alpinejs';

Alpine.data('proposalForm', () => {
    let initialData = { initialItems: [], initialDoctor: '', doctorsList: [] };
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
        doctors: initialData.doctorsList || [],
        items: initialItems,
        submitting: false,

        get filteredDoctors() {
            const q = this.doctorInput.trim().toLowerCase();
            if (!q) return this.doctors.slice(0, 20);
            return this.doctors
                .filter(
                    (d) =>
                        d.name.toLowerCase().includes(q) ||
                        (d.clinic && d.clinic.toLowerCase().includes(q))
                )
                .slice(0, 20);
        },

        selectDoctor(name) {
            this.doctorInput = name;
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

    handleInput(e) {
        const val = e.target.value.trim();
        this.highlightedIndex = -1;
        if (val.length < 1) {
            this.suggestions = [];
            this.isOpen = false;
            return;
        }
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            fetch(`/api/suggestions/medicines?q=${encodeURIComponent(val)}`)
                .then((res) => res.json())
                .then((json) => {
                    this.suggestions = json.data || [];
                    this.isOpen = this.suggestions.length > 0;
                })
                .catch(() => {
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
        item.medicine_name = sug.medicine_name;
        if (sug.principal_name && (!item.principal_name || item.autoFilled)) {
            item.principal_name = sug.principal_name;
            item.autoFilled = true;
        }
        this.isOpen = false;
    },
}));

Alpine.data('principalAutocomplete', (item) => ({
    isOpen: false,
    suggestions: [],
    highlightedIndex: -1,
    debounceTimer: null,

    handleInput(e) {
        item.autoFilled = false;
        const val = e.target.value.trim();
        this.highlightedIndex = -1;
        if (val.length < 1) {
            this.suggestions = [];
            this.isOpen = false;
            return;
        }
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            fetch(`/api/suggestions/principals?q=${encodeURIComponent(val)}`)
                .then((res) => res.json())
                .then((json) => {
                    this.suggestions = json.data || [];
                    this.isOpen = this.suggestions.length > 0;
                })
                .catch(() => {
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
        item.principal_name = name;
        item.autoFilled = false;
        this.isOpen = false;
    },
}));

window.Alpine = Alpine;

Alpine.start();
