{{--
    Searchable Project Code Selector Component
    Uses a portal (teleported to <body>) so the dropdown escapes overflow:hidden containers.

    Props:
        $entryId    - unique identifier for this selector instance
        $namePrefix - form field name prefix (e.g. "entries[123]" or "project_rows[0]")
        $selectedProjectCodeId - currently selected project_code_id
        $selectedCategory      - currently selected project_category (RFQ/MKT/etc)
        $manualProjectName     - currently entered manual project code/name
        $projectName           - current project_name value
        $inputClass            - CSS classes for styling
        $disabled              - whether the selector is read-only
--}}
@props([
    'entryId',
    'namePrefix',
    'selectedProjectCodeId' => null,
    'selectedCategory' => null,
    'manualProjectName' => null,
    'projectName' => null,
    'inputClass' => 'w-full border-0 text-xs py-1 px-1 focus:ring-0 bg-transparent',
    'disabled' => false,
])

@php
    $specialCategories = ['RFQ', 'MKT', 'PUR', 'R&D', 'A.S.S', 'TDR'];
    $isSpecial = in_array($selectedCategory, $specialCategories);
    $jsId = preg_replace('/[^a-zA-Z0-9]/', '_', $entryId);
@endphp

<div x-data="projectCodeSelector_{{ $jsId }}()"
     x-init="init()"
     class="flex items-center gap-1"
     @click.away="closeDropdown()">

    {{-- Hidden inputs for form submission --}}
    <input type="hidden" name="{{ $namePrefix }}[project_code_id]" :value="selectedProjectCodeId">
    <input type="hidden" name="{{ $namePrefix }}[project_category]" :value="selectedCategory">
    <input type="hidden" name="{{ $namePrefix }}[project_name]" :value="projectName">

    {{-- Main search/display input --}}
    <div class="relative flex-1">
        @if(!$disabled)
            <input type="text"
                   x-ref="searchInput"
                   x-model="searchQuery"
                   @focus="openDropdown()"
                   @input.debounce.200ms="fetchResults()"
                   @keydown.escape="closeDropdown()"
                   @keydown.arrow-down.prevent="highlightNext()"
                   @keydown.arrow-up.prevent="highlightPrev()"
                   @keydown.enter.prevent="selectHighlighted()"
                   :placeholder="selectedDisplay || '-- Search Project Code --'"
                   class="{{ $inputClass }}"
                   autocomplete="off">

            {{-- Clear button --}}
            <button x-show="selectedProjectCodeId || selectedCategory"
                    @click.prevent="clearSelection()"
                    type="button"
                    class="absolute right-1 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 text-xs leading-none">
                &times;
            </button>
        @else
            <span class="px-1 text-xs" x-text="selectedDisplay || '-'"></span>
        @endif
    </div>

    {{-- Manual project entry field (shown when special category selected) --}}
    <template x-if="isSpecialCategory && !{{ $disabled ? 'true' : 'false' }}">
        <input type="text"
               name="{{ $namePrefix }}[manual_project_code_name]"
               x-model="manualProjectCodeName"
               @input="projectName = (selectedCategory || '') + (manualProjectCodeName ? ' - ' + manualProjectCodeName : '')"
               placeholder="Enter Project Code / Name"
               class="{{ $inputClass }} flex-1 border border-gray-300 rounded px-1">
    </template>
    <template x-if="isSpecialCategory && {{ $disabled ? 'true' : 'false' }}">
        <span class="text-xs text-gray-600" x-text="manualProjectCodeName || ''"></span>
    </template>
</div>

<script>
function projectCodeSelector_{{ $jsId }}() {
    return {
        searchQuery: '',
        showDropdown: false,
        results: [],
        loading: false,
        highlightedIndex: -1,
        selectedProjectCodeId: @json($selectedProjectCodeId),
        selectedCategory: @json($selectedCategory),
        manualProjectCodeName: @json($manualProjectName ?? ''),
        projectName: @json($projectName ?? ''),
        selectedDisplay: '',
        isSpecialCategory: @json($isSpecial),
        specialCategories: @json($specialCategories),
        _portalEl: null,

        init() {
            this.updateDisplay();
        },

        _getPortalContent() {
            const cats = this.specialCategories;
            const results = this.results;

            let html = `<div style="max-height:220px;overflow-y:auto;background:#fff;border:1px solid #d1d5db;border-radius:0.5rem;box-shadow:0 10px 15px -3px rgba(0,0,0,.1);font-size:12px;">`;
            // Manual Entry header
            html += `<div style="padding:4px 8px;font-size:9px;font-weight:600;color:#6b7280;text-transform:uppercase;background:#f9fafb;border-bottom:1px solid #e5e7eb;">Manual Entry</div>`;
            cats.forEach((cat, i) => {
                html += `<div data-action="category" data-cat="${cat}" style="padding:5px 8px;cursor:pointer;white-space:nowrap;" onmouseenter="this.style.background='#eef2ff'" onmouseleave="this.style.background='transparent'"><span style="font-weight:600;color:#4f46e5;">${cat}</span> <span style="color:#9ca3af;font-size:9px;">— manual entry</span></div>`;
            });
            // Project Codes header
            html += `<div style="padding:4px 8px;font-size:9px;font-weight:600;color:#6b7280;text-transform:uppercase;background:#f9fafb;border-top:1px solid #e5e7eb;">Project Codes</div>`;
            if (results.length > 0) {
                results.forEach((item) => {
                    html += `<div data-action="project" data-id="${item.id}" data-code="${this._esc(item.code)}" data-name="${this._esc(item.name || '')}" style="padding:5px 8px;cursor:pointer;white-space:nowrap;" onmouseenter="this.style.background='#eef2ff'" onmouseleave="this.style.background='transparent'"><span style="font-weight:500;">${this._esc(item.code)}</span>${item.name ? ' <span style="color:#9ca3af;">- ' + this._esc(item.name) + '</span>' : ''}</div>`;
                });
            } else if (this.searchQuery.length > 0 && !this.loading) {
                html += `<div style="padding:8px;text-align:center;color:#9ca3af;">No project codes found</div>`;
            } else if (this.loading) {
                html += `<div style="padding:8px;text-align:center;color:#9ca3af;">Loading...</div>`;
            }
            html += `</div>`;
            return html;
        },

        _esc(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        },

        openDropdown() {
            this.showDropdown = true;
            this.fetchResults();
            this._renderPortal();
        },

        closeDropdown() {
            this.showDropdown = false;
            if (this._portalEl) this._portalEl.style.display = 'none';
        },

        _renderPortal() {
            if (!this._portalEl) {
                const el = document.createElement('div');
                el.style.cssText = 'position:fixed;z-index:99999;';
                document.body.appendChild(el);
                this._portalEl = el;

                // Close when clicking outside
                document.addEventListener('mousedown', (e) => {
                    if (this._portalEl && !this._portalEl.contains(e.target) && !this.$el.contains(e.target)) {
                        this.closeDropdown();
                    }
                });

                // Reposition on scroll/resize
                const reposition = () => {
                    if (this.showDropdown) this._positionPortal();
                };
                window.addEventListener('scroll', reposition, true);
                window.addEventListener('resize', reposition, true);
            }

            this._positionPortal();
            this._portalEl.innerHTML = this._getPortalContent();
            this._portalEl.style.display = 'block';

            // Bind click handlers
            this._portalEl.querySelectorAll('[data-action="category"]').forEach(el => {
                el.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    this.selectCategory(el.dataset.cat);
                });
            });
            this._portalEl.querySelectorAll('[data-action="project"]').forEach(el => {
                el.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    this.selectProject({ id: parseInt(el.dataset.id), code: el.dataset.code, name: el.dataset.name });
                });
            });
        },

        _positionPortal() {
            if (!this._portalEl || !this.$refs.searchInput) return;
            const rect = this.$refs.searchInput.getBoundingClientRect();
            const w = Math.max(rect.width, 220);
            this._portalEl.style.top = (rect.bottom + 2) + 'px';
            this._portalEl.style.left = rect.left + 'px';
            this._portalEl.style.width = w + 'px';
        },

        updateDisplay() {
            if (this.selectedCategory && this.specialCategories.includes(this.selectedCategory)) {
                this.selectedDisplay = this.selectedCategory;
                this.isSpecialCategory = true;
                this.searchQuery = '';
            } else if (this.selectedProjectCodeId) {
                fetch('{{ route("api.project-codes.search") }}?q=')
                    .then(r => r.json())
                    .then(data => {
                        const found = data.find(p => p.id == this.selectedProjectCodeId);
                        if (found) {
                            this.selectedDisplay = found.code + (found.name ? ' - ' + found.name : '');
                            this.searchQuery = '';
                        }
                    });
                this.isSpecialCategory = false;
            } else {
                this.selectedDisplay = '';
                this.isSpecialCategory = false;
            }
        },

        fetchResults() {
            this.loading = true;
            const q = this.searchQuery || '';
            fetch('{{ route("api.project-codes.search") }}?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    this.results = data;
                    this.loading = false;
                    if (this.showDropdown) this._renderPortal();
                })
                .catch(() => { this.loading = false; });
        },

        selectProject(item) {
            this.selectedProjectCodeId = item.id;
            this.selectedCategory = null;
            this.manualProjectCodeName = '';
            this.projectName = item.name || item.code;
            this.selectedDisplay = item.code + (item.name ? ' - ' + item.name : '');
            this.isSpecialCategory = false;
            this.searchQuery = '';
            this.closeDropdown();
            this.dispatchChange();
        },

        selectCategory(cat) {
            this.selectedProjectCodeId = null;
            this.selectedCategory = cat;
            this.isSpecialCategory = true;
            this.selectedDisplay = cat;
            this.projectName = cat + (this.manualProjectCodeName ? ' - ' + this.manualProjectCodeName : '');
            this.searchQuery = '';
            this.closeDropdown();
            this.dispatchChange();
        },

        clearSelection() {
            this.selectedProjectCodeId = null;
            this.selectedCategory = null;
            this.manualProjectCodeName = '';
            this.projectName = '';
            this.selectedDisplay = '';
            this.isSpecialCategory = false;
            this.searchQuery = '';
            this.dispatchChange();
        },

        highlightNext() {
            const total = 6 + this.results.length;
            this.highlightedIndex = (this.highlightedIndex + 1) % total;
        },

        highlightPrev() {
            const total = 6 + this.results.length;
            this.highlightedIndex = (this.highlightedIndex - 1 + total) % total;
        },

        selectHighlighted() {
            if (this.highlightedIndex < 6) {
                this.selectCategory(this.specialCategories[this.highlightedIndex]);
            } else {
                const idx = this.highlightedIndex - 6;
                if (this.results[idx]) {
                    this.selectProject(this.results[idx]);
                }
            }
        },

        dispatchChange() {
            this.$el.dispatchEvent(new CustomEvent('project-changed', {
                bubbles: true,
                detail: {
                    projectCodeId: this.selectedProjectCodeId,
                    projectCategory: this.selectedCategory,
                    projectName: this.projectName,
                    manualProjectCodeName: this.manualProjectCodeName,
                }
            }));
        }
    };
}
</script>
