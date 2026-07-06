{{--
    Turns any <select class="searchable"> into a type-to-search combobox
    (Tom Select), while keeping the native <select> so GET-form submission and
    the selected value are unchanged. Drop this include once per page that has
    filter dropdowns, and add the `searchable` class to the selects you want.
--}}
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        /* Match the app's form-control sizing/typography. */
        .ts-wrapper.form-select { padding: 0; height: auto; background-image: none; }
        .ts-control { border-radius: 0.375rem; min-height: calc(1.5em + 0.75rem + 2px); }
        .ts-dropdown { font-size: 0.9rem; }
        .ts-dropdown .option.active { background-color: #e7f1ff; color: #1e293b; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('select.searchable').forEach(function (el) {
                if (el.tomselect) return; // guard against double-init
                new TomSelect(el, {
                    allowEmptyOption: true,   // keep the "All …" option selectable
                    maxOptions: null,         // no truncation (e.g. 40+ vendors)
                    plugins: el.hasAttribute('data-no-clear') ? [] : ['clear_button'],
                });
            });
        });
    </script>
@endpush
