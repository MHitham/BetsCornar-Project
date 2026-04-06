@extends('layouts.app')

@section('title', __('customers.title'))
@section('page-title', __('customers.title'))

@section('content')

    {{-- ØªÙ… Ø§Ù„Ø¥Ø¶Ø§ÙØ©: Ø­ÙØ¸ Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„Ù†ØªØ§Ø¦Ø¬ Ø§Ù„Ø­Ø§Ù„ÙŠØ© Ù„Ø§Ø³ØªØ®Ø¯Ø§Ù…Ù‡ ÙÙŠ Ø§Ù„ØªØ­Ø¯ÙŠØ¯ Ø¹Ø¨Ø± ÙƒÙ„ Ø§Ù„ØµÙØ­Ø§Øª --}}
    @php
        $customersTotal = $customers->total();
    @endphp

    {{-- Search Bar --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('customers.index') }}" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">{{ __('customers.actions.search') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" value="{{ $q }}"
                            placeholder="{{ __('customers.filters.search_placeholder') }}">
                    </div>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-search me-1"></i>{{ __('customers.actions.search') }}
                    </button>
                    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-people-fill text-primary fs-5"></i>
            <span class="fw-bold fs-6">{{ $customers->total() }} عميل</span>
        </div>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus-fill me-1"></i>{{ __('customers.actions.add') }}
        </a>
    </div>

    {{-- ØªÙ… Ø§Ù„Ø¥Ø¶Ø§ÙØ©: Ø´Ø±ÙŠØ· Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡Ø§Øª Ø§Ù„Ø¬Ù…Ø§Ø¹ÙŠØ© Ù„Ù†Ø³Ø® Ø§Ù„Ø£Ø±Ù‚Ø§Ù… ÙˆØªØµØ¯ÙŠØ± Excel --}}
    <div id="bulk-actions-toolbar" class="card mb-3 d-none">
        <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="fw-semibold text-primary">
                <span id="selected-count">0</span>
                {{ __('customers.bulk.selected_count') }}
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-success" id="copy-selected-phones">
                    <i class="bi bi-clipboard me-1"></i>{{ __('customers.bulk.copy_phones') }}
                </button>
                <button type="button" class="btn btn-success" id="export-selected-excel">
                    <i class="bi bi-file-earmark-excel me-1"></i>{{ __('customers.bulk.export_excel') }}
                </button>
                <button type="button" class="btn btn-outline-secondary" id="clear-selection">
                    <i class="bi bi-x-circle me-1"></i>{{ __('customers.bulk.clear_selection') }}
                </button>
            </div>
        </div>
    </div>

    {{-- ØªÙ… Ø§Ù„Ø¥Ø¶Ø§ÙØ©: Ø¨Ø§Ù†Ø± Ø§Ù„ØªØ­Ø¯ÙŠØ¯ Ø¹Ø¨Ø± Ø¬Ù…ÙŠØ¹ Ø§Ù„ØµÙØ­Ø§Øª --}}
    <div id="selection-banner"
        class="alert alert-info d-none d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
        <span id="selection-banner-text"></span>
        <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" id="select-all-results">
            {{ __('customers.bulk.select_all_results', ['count' => $customersTotal]) }}
        </button>
    </div>

    {{-- Table --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        {{-- ØªÙ… Ø§Ù„Ø¥Ø¶Ø§ÙØ©: Ù…Ø±Ø¨Ø¹ ØªØ­Ø¯ÙŠØ¯ Ø¬Ù…ÙŠØ¹ Ø¹Ù†Ø§ØµØ± Ø§Ù„ØµÙØ­Ø© Ø§Ù„Ø­Ø§Ù„ÙŠØ© --}}
                        <th class="text-center" style="width: 56px;">
                            <input type="checkbox" class="form-check-input" id="select-page-customers"
                                title="{{ __('customers.bulk.select_page') }}">
                        </th>
                        <th>#</th>
                        <th>{{ __('customers.fields.name') }}</th>
                        <th>{{ __('customers.fields.phone') }}</th>
                        <th>{{ __('customers.fields.animal_type') }}</th>
                        <th>{{ __('customers.fields.last_vaccination') }}</th>
                        <th class="text-center">{{ __('messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($customers->isNotEmpty())
                        @foreach ($customers as $customer)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input customer-export-checkbox"
                                        value="{{ $customer->id }}" data-phone="{{ $customer->phone }}">
                                </td>
                                <td class="text-muted small">{{ $customer->id }}</td>
                                <td class="fw-semibold">{{ $customer->name }}</td>
                                <td>
                                    <a href="https://wa.me/{{ $customer->phone }}" target="_blank"
                                        class="text-success text-decoration-none" title="ÙˆØ§ØªØ³Ø§Ø¨">
                                        <i class="bi bi-whatsapp me-1"></i>{{ $customer->phone }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        {{ $customer->animal_type }}
                                    </span>
                                </td>
                                <td>
                                    {{-- تم الإضافة: الاعتماد على العلاقة المباشرة latestVaccination بدل تجميع vaccinations --}}
                                    @php $lastVacc = $customer->latestVaccination; @endphp
                                    @if ($lastVacc)
                                        <span class="badge bg-primary text-white">
                                            {{ $lastVacc->vaccination_date->format('Y-m-d') }}
                                        </span>
                                    @else
                                        <span class="text-muted small"></span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('customers.create') }}?phone={{ urlencode($customer->phone) }}&name={{ urlencode($customer->name) }}"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-clipboard2-plus me-1"></i>زياره جديدة
                                    </a>
                                    {{-- تم الإضافة: زر السجل الطبي للعميل --}}
                                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-clock-history me-1"></i>{{ __('customers.timeline.title') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="bi bi-people text-muted"></i>
                                    <p>{{ __('customers.messages.no_customers') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $customers->links() }}
    </div>

    {{-- ØªÙ… Ø§Ù„Ø¥Ø¶Ø§ÙØ©: Ù†Ù…ÙˆØ°Ø¬ Ù…Ø®ÙÙŠ Ù„Ø¥Ø±Ø³Ø§Ù„ Ø¹Ù†Ø§ØµØ± Ø§Ù„ØªØµØ¯ÙŠØ± Ø¥Ù„Ù‰ Ù…Ø³Ø§Ø± Excel --}}
    <form method="POST" action="{{ route('customers.export-excel') }}" id="customers-export-form" class="d-none">
        @csrf
        <div id="customers-export-inputs"></div>
    </form>

    {{-- ØªÙ… Ø§Ù„Ø¥Ø¶Ø§ÙØ©: Ù…Ù†Ø·Ù‚ Ø§Ù„ØªØ­Ø¯ÙŠØ¯ Ø§Ù„Ø¬Ù…Ø§Ø¹ÙŠ ÙˆÙ†Ø³Ø® Ø§Ù„Ø£Ø±Ù‚Ø§Ù… ÙˆØ§Ù„ØªØµØ¯ÙŠØ± Ø¨Ø§Ø³ØªØ®Ø¯Ø§Ù… JavaScript ÙÙ‚Ø· --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const selectPageCheckbox = document.getElementById('select-page-customers');
            const rowCheckboxes = Array.from(document.querySelectorAll('.customer-export-checkbox'));
            const bulkToolbar = document.getElementById('bulk-actions-toolbar');
            const selectionBanner = document.getElementById('selection-banner');
            const selectionBannerText = document.getElementById('selection-banner-text');
            const selectAllResultsButton = document.getElementById('select-all-results');
            const selectedCount = document.getElementById('selected-count');
            const copyPhonesButton = document.getElementById('copy-selected-phones');
            const exportExcelButton = document.getElementById('export-selected-excel');
            const clearSelectionButton = document.getElementById('clear-selection');
            const exportForm = document.getElementById('customers-export-form');
            const exportInputs = document.getElementById('customers-export-inputs');
            const totalResults = {{ $customersTotal }};
            const currentSearch = @json($q);
            let selectAllAcrossPages = false;

            function selectedIds() {
                return rowCheckboxes.filter((checkbox) => checkbox.checked).map((checkbox) => checkbox.value);
            }

            function selectedPhones() {
                return [...new Set(
                    rowCheckboxes
                        .filter((checkbox) => checkbox.checked)
                        .map((checkbox) => (checkbox.dataset.phone || '').trim())
                        .filter(Boolean)
                )];
            }

            function syncPageCheckbox() {
                const checkedCount = selectedIds().length;
                const pageCount = rowCheckboxes.length;

                selectPageCheckbox.checked = checkedCount > 0 && checkedCount === pageCount;
                selectPageCheckbox.indeterminate = checkedCount > 0 && checkedCount < pageCount;
            }

            function renderBanner() {
                const checkedCount = selectedIds().length;
                const pageCount = rowCheckboxes.length;

                if (selectAllAcrossPages) {
                    selectionBanner.classList.remove('d-none');
                    selectionBannerText.textContent =
                        `{{ __('customers.bulk.all_results_selected_prefix') }} ${totalResults} {{ __('customers.bulk.all_results_selected_suffix') }}`;
                    selectAllResultsButton.classList.add('d-none');
                    return;
                }

                if (pageCount > 0 && checkedCount === pageCount && totalResults > pageCount) {
                    selectionBanner.classList.remove('d-none');
                    selectionBannerText.textContent =
                        `{{ __('customers.bulk.page_selected_prefix') }} ${checkedCount} {{ __('customers.bulk.page_selected_suffix') }}`;
                    selectAllResultsButton.classList.remove('d-none');
                    return;
                }

                selectionBanner.classList.add('d-none');
            }

            function renderToolbar() {
                const count = selectAllAcrossPages ? totalResults : selectedIds().length;
                selectedCount.textContent = count;
                bulkToolbar.classList.toggle('d-none', count === 0);
                renderBanner();
                syncPageCheckbox();
            }

            function resetSelection() {
                selectAllAcrossPages = false;
                rowCheckboxes.forEach((checkbox) => {
                    checkbox.checked = false;
                });
                renderToolbar();
            }

            function appendHiddenInput(name, value) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                exportInputs.appendChild(input);
            }

            async function copySelectedPhones() {
                if (!selectAllAcrossPages && selectedIds().length === 0) {
                    window.alert(@json(__('customers.bulk.no_selection')));
                    return;
                }

                let phones = [];

                if (selectAllAcrossPages) {
                    const url = new URL(@json(route('customers.export-phones')));
                    url.searchParams.set('select_all_ids', '1');

                    if (currentSearch) {
                        url.searchParams.set('q', currentSearch);
                    }

                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Failed to fetch phones');
                    }

                    const data = await response.json();
                    phones = Array.isArray(data.phones) ? data.phones : [];
                } else {
                    phones = selectedPhones();
                }

                const uniquePhones = [...new Set(phones.map((phone) => String(phone).trim()).filter(Boolean))];

                if (uniquePhones.length === 0) {
                    window.alert(@json(__('customers.bulk.no_selection')));
                    return;
                }

                await navigator.clipboard.writeText(uniquePhones.join('\n'));
                window.alert(@json(__('customers.bulk.copy_success')));
            }

            function exportSelectedExcel() {
                if (!selectAllAcrossPages && selectedIds().length === 0) {
                    window.alert(@json(__('customers.bulk.no_selection')));
                    return;
                }

                exportInputs.innerHTML = '';

                if (selectAllAcrossPages) {
                    appendHiddenInput('select_all_ids', '1');

                    if (currentSearch) {
                        appendHiddenInput('q', currentSearch);
                    }
                } else {
                    selectedIds().forEach((id) => appendHiddenInput('ids[]', id));
                }

                exportForm.submit();
            }

            selectPageCheckbox.addEventListener('change', () => {
                selectAllAcrossPages = false;
                rowCheckboxes.forEach((checkbox) => {
                    checkbox.checked = selectPageCheckbox.checked;
                });
                renderToolbar();
            });

            rowCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    if (!checkbox.checked) {
                        selectAllAcrossPages = false;
                    }
                    renderToolbar();
                });
            });

            selectAllResultsButton.addEventListener('click', () => {
                rowCheckboxes.forEach((checkbox) => {
                    checkbox.checked = true;
                });
                selectAllAcrossPages = true;
                renderToolbar();
            });

            clearSelectionButton.addEventListener('click', resetSelection);
            exportExcelButton.addEventListener('click', exportSelectedExcel);
            copyPhonesButton.addEventListener('click', async () => {
                try {
                    await copySelectedPhones();
                } catch (error) {
                    window.alert(@json(__('customers.bulk.copy_failed')));
                }
            });

            renderToolbar();
        });
    </script>

@endsection
