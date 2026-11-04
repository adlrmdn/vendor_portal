{{--
    Turns any <select class="searchable"> into a type-to-search combobox
    (Tom Select), while keeping the native <select> so GET-form submission and
    the selected value are unchanged. Drop this include once per page that has
    filter dropdowns, and add the `searchable` class to the selects you want.
--}}
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        /* Universal TomSelect styling matching Apple Design System */
        .ts-wrapper,
        .ts-wrapper.form-select,
        .ts-wrapper.form-control,
        .ts-wrapper.apple-select {
            padding: 0 !important;
            border: none !important;
            box-shadow: none !important;
            height: auto !important;
            min-height: 0 !important;
            background: transparent !important;
            overflow: visible !important;
            position: relative !important;
        }

        .ts-wrapper .ts-control,
        .ts-wrapper.form-select .ts-control,
        .ts-wrapper.form-control .ts-control,
        .ts-wrapper.apple-select .ts-control,
        .ts-control {
            border: 1px solid #d2d2d7 !important;
            border-radius: 8px !important;
            min-height: 38px !important;
            height: 38px !important;
            padding: 0.375rem 2.25rem 0.375rem 0.75rem !important;
            display: flex !important;
            align-items: center !important;
            flex-wrap: nowrap !important;
            font-size: 0.86rem !important;
            line-height: 1.5 !important;
            color: #1d1d1f !important;
            background-color: #fafafc !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2386868b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m4 6 4 4 4-4'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.75rem center !important;
            background-size: 14px 10px !important;
            box-shadow: none !important;
            transition: all 0.15s ease !important;
        }

        .ts-wrapper.focus .ts-control,
        .ts-wrapper.form-select.focus .ts-control,
        .ts-wrapper.form-control.focus .ts-control,
        .ts-wrapper.apple-select.focus .ts-control,
        .ts-control.focus {
            background-color: #ffffff !important;
            border-color: #0071e3 !important;
            outline: 0 !important;
            box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.14) !important;
        }

        .ts-control > input {
            font-size: 0.86rem !important;
            height: 1.5rem !important;
            line-height: 1.5 !important;
            color: #1d1d1f !important;
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
            color: #86868b !important;
            opacity: 1 !important;
        }

        .ts-control .item {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            font-size: 0.86rem !important;
            line-height: 1.5 !important;
            color: #1d1d1f !important;
            font-weight: 500 !important;
            max-width: calc(100% - 1.5rem) !important;
        }

        .ts-dropdown {
            font-size: 0.85rem !important;
            z-index: 1060 !important;
            border: 1px solid #d2d2d7 !important;
            border-radius: 10px !important;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.14), 0 2px 6px rgba(0, 0, 0, 0.06) !important;
            background: #ffffff !important;
            max-height: 240px !important;
            margin-top: 4px !important;
            overflow: hidden !important;
        }

        .ts-dropdown .ts-dropdown-content {
            max-height: 240px !important;
            overflow-y: auto !important;
            padding: 5px !important;
            scrollbar-width: thin !important;
            scrollbar-color: rgba(0, 0, 0, 0.2) transparent !important;
        }

        .ts-dropdown .ts-dropdown-content::-webkit-scrollbar {
            width: 6px !important;
        }
        .ts-dropdown .ts-dropdown-content::-webkit-scrollbar-track {
            background: transparent !important;
        }
        .ts-dropdown .ts-dropdown-content::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2) !important;
            border-radius: 9999px !important;
        }

        .ts-dropdown .option {
            padding: 7px 11px !important;
            font-size: 0.84rem !important;
            line-height: 1.35 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            border-radius: 6px !important;
            color: #1d1d1f !important;
            margin-bottom: 2px !important;
            transition: background-color 0.1s ease, color 0.1s ease !important;
        }

        .ts-dropdown .option.active,
        .ts-dropdown .option:hover {
            background-color: #0071e3 !important;
            color: #ffffff !important;
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
