@extends('layouts.app')

@section('title', __('customers.timeline.title'))
@section('page-title', __('customers.timeline.title'))

@section('content')
    @php
        $unpaidInvoices = $customer->invoices->filter(fn($inv) =>
            !$inv->isCancelled() && (float)$inv->remaining_amount > 0
        );
        $totalOutstanding = $unpaidInvoices->sum('remaining_amount');
    @endphp

    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h2 class="h4 mb-2">{{ $customer->name }}</h2>
                    <div class="d-flex flex-wrap gap-3 text-muted small">
                        <span>
                            <i class="bi bi-telephone me-1"></i>
                            <a href="https://wa.me/{{ $customer->phone }}" target="_blank" class="text-success text-decoration-none">
                                {{ $customer->phone }}
                            </a>
                        </span>
                        <span><i class="bi bi-heart-pulse me-1"></i>{{ $customer->animal_type }}</span>
                        <span><i class="bi bi-clock-history me-1"></i>{{ __('customers.timeline.total_visits') }}: {{ $customer->invoices->count() }}</span>
                        @if ($totalOutstanding > 0)
                            <a href="#customer-balance" class="medical-record-balance-link">
                                <i class="bi bi-cash-coin me-1" aria-hidden="true"></i>
                                المتبقي {{ number_format($totalOutstanding, 2) }} ج
                            </a>
                        @endif
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('customers.create') }}?phone={{ urlencode($customer->phone) }}&name={{ urlencode($customer->name) }}" class="btn btn-primary">
                        <i class="bi bi-clipboard2-plus me-1"></i>{{ __('customers.timeline.new_visit') }}
                    </a>
                    {{-- زرار تعديل بيانات العميل --}}
                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-primary">
                        <i class="bi bi-pencil-square me-1"></i>تعديل البيانات
                    </a>
                    <a href="{{ route('customers.animals.index', $customer) }}" class="btn btn-outline-primary">
                        <i class="bi bi-list-ul me-1"></i>حيوانات العميل
                    </a>
                    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-right me-1"></i>{{ __('customers.timeline.back_to_list') }}
                    </a>
                    {{-- اظهر مسح السجل الكامل للمدير فقط. --}}
                    @can('delete', $customer)
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCustomerHistoryModal">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('customers.history_deletion.action') }}
                        </button>
                    @endcan
                </div>
            </div>
        </div>
    </div>

@if(filled($customer->notes))
    <div class="card border-0 shadow-sm">
        <div class="card-body bg-light rounded-3">
            <div class="d-flex align-items-start gap-3">
                <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10"
                     style="width: 44px; height: 44px;">
                    <i class="bi bi-sticky-fill text-warning fs-5"></i>
                </div>

                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h6 class="mb-0 fw-bold text-dark">ملاحظات العميل</h6>
                    </div>

                    <div class="text-muted lh-lg notes-box">
                        {!! nl2br(e($customer->notes)) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
    
    {{-- ملف المالك مجمّع حسب الحيوان، مع مجموعة مستقلة للسجلات القديمة بلا حيوان. --}}
    @foreach ($customer->animals as $animal)
        <section class="card medical-record-pet" data-pet-name="{{ $animal->name }}" aria-labelledby="medical-pet-{{ $animal->id }}">
            <header class="medical-record-pet-header">
                <h2 id="medical-pet-{{ $animal->id }}">{{ $animal->name }}</h2>
                @if (filled($animal->species) || filled($animal->breed) || filled($animal->age))
                    <div class="medical-record-pet-meta">
                        @if (filled($animal->species))
                            <span class="medical-record-species">{{ $animal->species }}</span>
                        @endif
                        @if (filled($animal->breed))
                            <span>{{ $animal->breed }}</span>
                        @endif
                        @if (filled($animal->age))
                            <span>{{ $animal->age }}</span>
                        @endif
                    </div>
                @endif
            </header>
            <div class="card-body">
                <h3 class="h6 fw-bold mb-3">الفواتير</h3>
                @forelse ($animal->invoices as $invoice)
                    <article class="medical-record-invoice {{ $invoice->isCancelled() ? 'bg-light' : '' }}">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <a href="{{ route('invoices.show', $invoice) }}" class="fw-bold"><bdi>{{ $invoice->invoice_number }}</bdi></a>
                                @if ($invoice->isCancelled())
                                    <span class="badge bg-danger">ملغية</span>
                                @else
                                    <span class="badge bg-success">مؤكدة</span>
                                @endif
                                <time class="medical-record-date" datetime="{{ $invoice->created_at->toAtomString() }}" dir="ltr">{{ displayDateTime($invoice->created_at) }}</time>
                            </div>
                            <strong>{{ number_format($invoice->total, 2) }} ج.م</strong>
                        </div>
                        <dl class="medical-record-details">
                            <div>
                                <dt>التشخيص</dt>
                                <dd>{{ $invoice->diagnosis ?: '—' }}</dd>
                            </div>
                            @if (filled($invoice->treatment))
                                <div>
                                    <dt>العلاج</dt>
                                    <dd>{!! nl2br(e($invoice->treatment)) !!}</dd>
                                </div>
                            @endif
                        </dl>
                    </article>
                @empty
                    <p class="text-muted">لا توجد فواتير مرتبطة بهذا الحيوان.</p>
                @endforelse

                <h3 class="h6 fw-bold mt-4">التطعيمات</h3>
                @forelse ($animal->vaccinations as $vaccination)
                    <div class="d-flex flex-wrap gap-3 border-bottom py-2">
                        <span class="fw-semibold">{{ $vaccination->product?->name ?? '—' }}</span>
                        <span>{{ $vaccination->vaccination_date?->format('Y-m-d') ?? '—' }}</span>
                        <span>الجرعة التالية: {{ $vaccination->next_dose_date?->format('Y-m-d') ?? '—' }}</span>
                        @if($vaccination->is_completed)<span class="badge bg-secondary">مكتمل</span>@else<span class="badge bg-warning text-dark">غير مكتمل</span>@endif
                    </div>
                @empty
                    <p class="text-muted mb-0">لا توجد تطعيمات مرتبطة بهذا الحيوان.</p>
                @endforelse
            </div>
        </section>
    @endforeach

    @if ($orphanInvoices->isNotEmpty() || $orphanVaccinations->isNotEmpty())
        <details id="medical-record-archive" data-pet-name="orphan">
            <summary class="btn btn-dark d-inline-flex align-items-center gap-2" style="background: #102f3e; border-color: #102f3e; color: #fff;">
                <i class="bi bi-archive" aria-hidden="true"></i>
                <span>أرشيف</span>
                <span class="badge bg-light text-dark">{{ $orphanInvoices->count() + $orphanVaccinations->count() }}</span>
                <i class="bi bi-chevron-down small" aria-hidden="true"></i>
            </summary>
            <div class="card card-body mt-3">
                @foreach($orphanInvoices as $invoice)
                    <div class="border rounded p-3 mb-3">
                        <a href="{{ route('invoices.show', $invoice) }}" class="fw-semibold">{{ $invoice->invoice_number }}</a>
                        <span class="text-muted ms-2">{{ displayDateTime($invoice->created_at) }}</span>
                        @if(filled($invoice->animal_name))
                            <span class="badge bg-light text-dark border ms-2">{{ $invoice->animal_name }}</span>
                        @endif
                        <div class="mt-2"><strong>التشخيص:</strong> {{ $invoice->diagnosis ?: '—' }}</div>
                        @if(filled($invoice->treatment))<div class="mt-2"><strong>العلاج:</strong> {!! nl2br(e($invoice->treatment)) !!}</div>@endif
                    </div>
                @endforeach
                @foreach($orphanVaccinations as $vaccination)
                    <div class="border-bottom py-2">{{ $vaccination->product?->name ?? '—' }} — {{ $vaccination->vaccination_date?->format('Y-m-d') ?? '—' }}</div>
                @endforeach
            </div>
        </details>
    @endif

    @if($totalOutstanding > 0)
    <aside id="customer-balance" class="card medical-record-balance" aria-labelledby="customer-balance-title">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 id="customer-balance-title" class="h6 fw-bold mb-0">
                <i class="bi bi-cash-coin me-2" aria-hidden="true"></i>
                متبقي على العميل
            </h2>
            <span class="fw-bold">{{ number_format($totalOutstanding, 2) }} ج</span>
        </div>
        <div class="table-responsive p-0">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>تاريخ الفاتورة</th>
                        <th>إجمالي الفاتورة</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th scope="col"><span class="visually-hidden">إجراءات</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($unpaidInvoices as $unpaidInv)
                    <tr>
                        <td>
                            <a href="{{ route('invoices.show', $unpaidInv) }}" class="fw-semibold text-decoration-none">
                                {{ $unpaidInv->invoice_number }}
                            </a>
                        </td>
                        <td>{{ $unpaidInv->created_at->format('Y/m/d') }}</td>
                        <td>{{ number_format($unpaidInv->total, 2) }} ج</td>
                        <td class="text-success">{{ number_format($unpaidInv->amount_paid, 2) }} ج</td>
                        <td class="text-danger fw-bold">{{ number_format($unpaidInv->remaining_amount, 2) }} ج</td>
                        <td>
                            <button class="btn btn-sm btn-success"
                                    data-bs-toggle="modal"
                                    data-bs-target="#payModal{{ $unpaidInv->id }}">
                                <i class="bi bi-cash-coin me-1"></i> تسجيل دفعة
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </aside>

    
    @foreach($unpaidInvoices as $unpaidInv)
    <div class="modal fade" id="payModal{{ $unpaidInv->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تسجيل دفعة — {{ $unpaidInv->invoice_number }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('invoice.payments.store', $unpaidInv) }}" method="POST" data-prevent-double data-payment-form novalidate>
                    @csrf
                    <x-idempotency-token />
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            المتبقي على هذه الفاتورة:
                            <strong>{{ number_format($unpaidInv->remaining_amount, 1) }} ج</strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">المبلغ المدفوع <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control"
                                   min="0.01" step="0.01"
                                   max="{{ $unpaidInv->remaining_amount }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">تاريخ الدفع</label>
                            <x-payment-date :invoice="$unpaidInv" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-1"></i> تسجيل الدفعة
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endforeach
    @endif

    @foreach($customer->invoices as $invoice)
    <div class="modal fade" id="paymentsModal{{ $invoice->id }}" tabindex="-1" aria-labelledby="paymentsModalLabel{{ $invoice->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentsModalLabel{{ $invoice->id }}">
                        <i class="bi bi-clock-history me-2 text-primary"></i>
                        سجل دفعات الفاتورة #{{ $invoice->invoice_number }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    
                    @if($invoice->payments->isEmpty())
                        <p class="text-muted text-center py-3">لا توجد دفعات مسجلة</p>
                    @else
                        @php $totalPaid = $invoice->payments->sum('amount'); @endphp
                        <table class="table table-sm align-middle mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th>التاريخ</th>
                                    <th>المبلغ</th>
                                    <th>ملاحظات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->payments as $payment)
                                    <tr>
                                        <td class="font-monospace small">{{ displayDateTime($payment->paid_at) }}</td>
                                        <td class="fw-semibold text-success">{{ number_format($payment->amount, 2) }} ج.م</td>
                                        <td class="text-muted">{{ $payment->notes ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>إجمالي المدفوع</td>
                                    <td class="text-success">{{ number_format($totalPaid, 2) }} ج.م</td>
                                    <td>-</td>
                                </tr>
                                @php $rem = $invoice->total - $totalPaid; @endphp
                                <tr>
                                    <td>المتبقي</td>
                                    <td class="{{ $rem > 0 ? 'text-danger' : 'text-success' }}">
                                        {{ number_format($rem, 2) }} ج.م
                                    </td>
                                    <td>-</td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif

                    
                    @php $remaining = $invoice->total - $invoice->payments->sum('amount'); @endphp
                    @if($remaining > 0 && $invoice->status !== 'cancelled')
                        <hr>
                        <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle me-1 text-success"></i>تسجيل دفعة جديدة</h6>
                        {{-- حماية تسجيل دفعة العميل من الإرسال المكرر. --}}
                        <form action="{{ route('invoice.payments.store', $invoice) }}" method="POST" data-prevent-double data-payment-form novalidate>
                            @csrf
                            <x-idempotency-token />
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">المبلغ المدفوع <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" name="amount" class="form-control"
                                               min="0.01" step="0.01" max="{{ $remaining }}"
                                               placeholder="المبلغ المدفوع" required>
                                        <span class="input-group-text">ج.م</span>
                                    </div>
                                    <div class="form-text text-muted">الحد الأقصى: {{ number_format($remaining, 2) }} ج.م</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">ملاحظات</label>
                                    <input type="text" name="notes" class="form-control" placeholder="ملاحظات (اختياري)">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">تاريخ الدفع</label>
                                    <x-payment-date :invoice="$invoice" />
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-lg me-1"></i>تسجيل دفعة
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach

    {{-- اعرض أثر الحذف واطلب كتابة الهاتف قبل تفعيل زر التأكيد. --}}
    @can('delete', $customer)
        <div class="modal fade" id="deleteCustomerHistoryModal" tabindex="-1" aria-labelledby="deleteCustomerHistoryTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" action="{{ route('customers.destroy', $customer) }}" method="POST"
                      data-prevent-double data-expected-phone="{{ $customer->phone }}"
                      data-has-error="{{ $errors->customerDeletion->has('phone_confirmation') ? '1' : '0' }}">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="deleteCustomerHistoryTitle">{{ __('customers.history_deletion.action') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('customers.actions.cancel') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p>{{ __('customers.history_deletion.warning') }}</p>
                        <ul>
                            <li>{{ __('customers.history_deletion.invoice_count', ['count' => $customer->invoices_count]) }}</li>
                            <li>{{ __('customers.history_deletion.animal_count', ['count' => $customer->animals_count]) }}</li>
                            <li>{{ __('customers.history_deletion.vaccination_count', ['count' => $customer->vaccinations_count]) }}</li>
                        </ul>
                        <label for="delete-customer-phone" class="form-label">{{ __('customers.history_deletion.phone_label', ['phone' => $customer->phone]) }}</label>
                        <input id="delete-customer-phone" name="phone_confirmation" type="tel" class="form-control @error('phone_confirmation', 'customerDeletion') is-invalid @enderror"
                               dir="ltr" inputmode="tel" autocomplete="off" required>
                        @error('phone_confirmation', 'customerDeletion')
                            <div class="invalid-feedback" role="alert">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('customers.actions.cancel') }}</button>
                        <button type="submit" class="btn btn-danger" disabled>{{ __('customers.history_deletion.confirm') }}</button>
                    </div>
                </form>
            </div>
        </div>
        @push('scripts')
            <script>
                // فعّل التأكيد عند التطابق وأعد فتح النافذة إذا رفض الخادم الرقم.
                (() => {
                    const modal = document.getElementById('deleteCustomerHistoryModal');
                    const form = modal.querySelector('form');
                    const phone = form.elements.phone_confirmation;
                    const confirmButton = form.querySelector('button[type="submit"]');
                    const update = () => { confirmButton.disabled = phone.value.trim() !== form.dataset.expectedPhone; };
                    phone.addEventListener('input', update);
                    modal.addEventListener('shown.bs.modal', () => phone.focus());
                    update();
                    if (form.dataset.hasError === '1') bootstrap.Modal.getOrCreateInstance(modal).show();
                })();
            </script>
        @endpush
    @endcan
@endsection
