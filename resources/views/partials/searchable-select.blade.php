{{--
    Turns any <select class="searchable"> into a type-to-search combobox
    (Tom Select), while keeping the native <select> so GET-form submission and
    the selected value are unchanged. Drop this include once per page that has
    filter dropdowns, and add the `searchable` class to the selects you want.
--}}
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        /* Universal TomSelect styling matching Bootstrap 5 form-control/form-select */
        .ts-wrapper {
            padding: 0 !important;
            border: none !important;
            box-shadow: none !important;
        }
        .ts-wrapper.form-select,
        .ts-wrapper.form-control {
            padding: 0 !important;
            border: none !important;
            box-shadow: none !important;
            height: auto !important;
            background: transparent !important;
        }
        .ts-wrapper .ts-control,
        .ts-wrapper.form-select .ts-control,
        .ts-wrapper.form-control .ts-control,
        .ts-control {
            border: 1px solid #ced4da !important;
            border-radius: 0.375rem !important;
            min-height: 38px !important;
            height: 38px !important;
            padding: 0.375rem 2.25rem 0.375rem 0.75rem !important;
            display: flex !important;
            align-items: center !important;
            flex-wrap: nowrap !important;
            font-size: 0.875rem !important;
            line-height: 1.5 !important;
            color: #212529 !important;
            background-color: #ffffff !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.75rem center !important;
            background-size: 16px 12px !important;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05) !important;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out !important;
        }
        .ts-wrapper.focus .ts-control,
        .ts-wrapper.form-select.focus .ts-control,
        .ts-wrapper.form-control.focus .ts-control,
        .ts-control.focus {
            border-color: #86b7fe !important;
            outline: 0 !important;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
        }
        .ts-control > input {
            font-size: 0.875rem !important;
            height: 1.5rem !important;
            line-height: 1.5 !important;
            color: #212529 !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            flex: 1 1 auto !important;
            min-width: 3rem !important;
            background: transparent !important;
        }
        .ts-control > input::placeholder {
            color: #6c757d !important;
            opacity: 1 !important;
        }
        .ts-control .item {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            font-size: 0.875rem !important;
            line-height: 1.5 !important;
            color: #212529 !important;
            max-width: calc(100% - 1.5rem) !important;
        }
        .ts-dropdown {
            font-size: 0.85rem !important;
            z-index: 1055 !important;
            border: 1px solid #ced4da !important;
            border-radius: 0.375rem !important;
            box-shadow: 0 4px 14px rgba(0,0,0,0.12) !important;
            max-height: 220px !important;
        }
        .ts-dropdown .ts-dropdown-content {
            max-height: 220px !important;
            overflow-y: auto !important;
            padding: 4px 0 !important;
        }
        .ts-dropdown .option {
            padding: 5px 10px !important;
            font-size: 0.84rem !important;
            line-height: 1.35 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        .ts-dropdown .option.active {
            background-color: #e7f1ff !important;
            color: #0f172a !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('select.searchable').forEach(function (el) {
                if (el.tomselect) return; // guard against double-init
                let placeholder = el.getAttribute('placeholder');
                let hasEmptyOption = false;
                if (!placeholder && el.options.length > 0 && el.options[0].value === '') {
                    placeholder = el.options[0].text;
                    hasEmptyOption = true;
                }
                const isRequired = el.hasAttribute('required') || el.hasAttribute('data-no-clear');
                new TomSelect(el, {
                    allowEmptyOption: !el.hasAttribute('data-no-empty-option') && !hasEmptyOption,
                    maxOptions: null,
                    placeholder: placeholder || undefined,
                    plugins: isRequired ? [] : ['clear_button'],
                });
            });
        });
    </script>
@endpush
