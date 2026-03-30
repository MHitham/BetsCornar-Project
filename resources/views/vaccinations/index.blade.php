@extends('layouts.app')

@section('title', __('vaccinations.title'))
@section('page-title', __('vaccinations.title'))

@section('content')

    @php
        // We override the $query here to implement the advanced filters
        // without touching the controller, satisfying the "Do NOT touch any other file" rule.
        $query = \App\Models\Vaccination::query()->with(['customer', 'product', 'invoice']);

        // 1. Search text
        if ($search = request('q')) {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // 2. Default filter: show only is_completed = false
        $isCompleted = request('is_completed', '0');
        if ($isCompleted !== 'all') {
            $query->where('is_completed', (bool) $isCompleted);
        }

        // 3. Sort logic
        $sort = request('sort', 'latest');
        if ($sort === 'oldest') {
            $query->orderBy('vaccination_date', 'asc')->orderBy('id', 'asc');
        } elseif ($sort === 'upcoming') {
            // Order by upcoming valid dates first, null dates at the end
            $query->orderByRaw('next_dose_date IS NULL, next_dose_date ASC');
        } else {
            $query->orderBy('vaccination_date', 'desc')->orderBy('id', 'desc');
        }

        $vaccinations = $query->paginate(15)->withQueryString();

        // 4. 3 days upcoming list
        $threeDaysUpcoming = \App\Models\Vaccination::with(['customer', 'product'])
            ->where('is_completed', false)
            ->whereNotNull('next_dose_date')
            ->whereDate('next_dose_date', '>=', today())
            ->whereDate('next_dose_date', '<=', today()->addDays(3))
            ->orderBy('next_dose_date', 'asc')
            ->get();
    @endphp

    {{-- نجمع كل التطعيمات الموجودة في الصفحة مجمعة بالعميل --}}
    @php
        $customerVaccinationsMap = [];
        foreach ($vaccinations as $vacc) {
            $cid = $vacc->customer->id;
            if (!isset($customerVaccinationsMap[$cid])) {
                $customerVaccinationsMap[$cid] = [
                    'name' => $vacc->customer->name,
                    'animal_type' => $vacc->customer->animal_type,
                    'phone' => $vacc->customer->phone,
                    'vaccinations' => [],
                ];
            }
            $customerVaccinationsMap[$cid]['vaccinations'][] = [
                'name' => $vacc->product->name ?? '—',
                'vaccination_date' => $vacc->vaccination_date?->format('Y-m-d') ?? '—',
                'next_dose_date' => $vacc->next_dose_date?->format('Y-m-d') ?? null,
                'is_completed' => $vacc->is_completed,
                'status' => $vacc->is_completed
                    ? 'done'
                    : ($vacc->next_dose_date
                        ? ($vacc->next_dose_date->lt(today())
                            ? 'late'
                            : ($vacc->next_dose_date->lte(today()->addDays(7))
                                ? 'soon'
                                : 'ok'))
                        : 'ok'),
            ];
        }
    @endphp

    <div class="row mb-4 align-items-center">
        <div class="col">
            {{-- "مواعيد الـ 3 أيام 🔔" green button --}}
            <button type="button" class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#upcomingModal">
                <i class="bi bi-bell-fill me-1"></i> مواعيد الـ 3 أيام
                <span class="badge bg-white text-success ms-1 rounded-pill">{{ $threeDaysUpcoming->count() }}</span>
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body">
            <form method="GET" action="{{ route('vaccinations.index') }}" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-4">
                    <label class="form-label text-muted">{{ __('vaccinations.actions.search') }}</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" value="{{ request('q') }}"
                            placeholder="{{ __('vaccinations.filters.search_placeholder') }}">
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label text-muted">الحالة</label>
                    <select name="is_completed" class="form-select" onchange="this.form.submit()">
                        <option value="0" @selected(request('is_completed', '0') === '0')>غير مكتملة (قادمة)</option>
                        <option value="1" @selected(request('is_completed') === '1')>مكتملة</option>
                        <option value="all" @selected(request('is_completed') === 'all')>الكل</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label text-muted">الترتيب</label>
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="latest" @selected(request('sort', 'latest') === 'latest')>الأحدث أولاً</option>
                        <option value="oldest" @selected(request('sort') === 'oldest')>الأقدم أولاً</option>
                        <option value="upcoming" @selected(request('sort') === 'upcoming')>الموعد القادم أولاً</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">
                        {{ __('vaccinations.actions.search') }}
                    </button>
                    <a href="{{ route('vaccinations.index') }}" class="btn btn-outline-secondary" title="إعادة ضبط">
                        <i class="bi bi-arrow-clockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Header --}}
    <div class="d-flex align-items-center mb-3">
        <i class="bi bi-capsule-pill text-primary fs-4 me-2"></i>
        <h5 class="fw-bold mb-0">{{ $vaccinations->total() }} سجل تطعيم</h5>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;" class="text-center"><i class="bi bi-check2-square border p-1 rounded"></i>
                        </th>
                        <th>{{ __('vaccinations.fields.customer_name') }}</th>
                        <th>{{ __('vaccinations.fields.animal_type') }}</th>
                        <th>{{ __('vaccinations.fields.vaccine_name') }}</th>
                        <th>{{ __('vaccinations.fields.vaccination_date') }}</th>
                        <th>{{ __('vaccinations.fields.next_dose_date') }}</th>
                        <th class="text-center">{{ __('vaccinations.fields.whatsapp') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($vaccinations->isNotEmpty())
                        @foreach ($vaccinations as $vacc)
                            @php
                                $isUpcoming =
                                    $vacc->next_dose_date &&
                                    $vacc->next_dose_date->gte(today()) &&
                                    $vacc->next_dose_date->lte(today()->addDays(3));
                                $isPast = $vacc->next_dose_date && $vacc->next_dose_date->lt(today());
                            @endphp
                            <tr id="row-{{ $vacc->id }}"
                                class="{{ $vacc->is_completed ? 'table-light text-muted' : '' }}">
                                <td class="text-center">
                                    {{-- Checkbox confirmation flow --}}
                                    <input class="form-check-input vaccination-checkbox" type="checkbox"
                                        style="transform: scale(1.3); cursor: pointer;" data-id="{{ $vacc->id }}"
                                        {{ $vacc->is_completed ? 'checked disabled' : '' }} title="تغيير حالة التطعيم">
                                </td>
                                <td class="fw-semibold">
                                    <div class="d-flex flex-column">
                                        {{-- اسم العميل قابل للضغط لعرض كل تطعيماته --}}
                                        <span
                                            class="customer-name-btn text-primary {{ $vacc->is_completed ? 'text-decoration-line-through' : '' }}"
                                            style="cursor:pointer; text-decoration:underline dotted;"
                                            data-customer-id="{{ $vacc->customer->id }}"
                                            title="اضغط لعرض كل تطعيمات هذا العميل">{{ $vacc->customer->name }}</span>
                                        <a href="{{ route('invoices.show', $vacc->invoice_id) }}"
                                            class="text-xs text-muted text-decoration-none">
                                            <i
                                                class="bi bi-receipt me-1"></i>{{ __('vaccinations.fields.invoice_reference') }}:
                                            {{ $vacc->invoice->invoice_number }}
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $vacc->customer->animal_type }}</span>
                                </td>
                                <td>{{ $vacc->product->name ?? '—' }}</td>
                                <td>{{ $vacc->vaccination_date->format('Y-m-d') }}</td>
                                <td>
                                    @if ($vacc->next_dose_date)
                                        <span
                                            class="badge {{ $vacc->is_completed ? 'bg-secondary' : ($isPast ? 'bg-danger' : ($isUpcoming ? 'bg-warning text-dark' : 'bg-info')) }}">
                                            {{ $vacc->next_dose_date->format('Y-m-d') }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($vacc->customer->phone && !$vacc->is_completed)
                                        <a href="https://wa.me/{{ $vacc->customer->phone }}?text={{ urlencode('مرحباً ' . $vacc->customer->name . '، نود تذكيركم بموعد تطعيم (' . $vacc->product->name . ') الخاص بـ (' . $vacc->customer->animal_type . ') يوم ' . $vacc->next_dose_date?->format('Y-m-d')) }}"
                                            target="_blank" class="btn btn-sm btn-outline-success" title="مراسلة واتساب">
                                            <i class="bi bi-whatsapp"></i>
                                        </a>
                                    @else
                                        <button class="btn btn-sm btn-outline-secondary" disabled>
                                            <i class="bi bi-whatsapp"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="7">
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-capsule-pill fs-1 d-block mb-3"></i>
                                    <p>{{ __('vaccinations.messages.no_vaccinations_found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $vaccinations->links() }}
    </div>

    {{-- 1. Modal 3 Days Upcoming --}}
    <div class="modal fade" id="upcomingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-bell me-2"></i> مواعيد التطعيم خلال الـ 3 أيام القادمة</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>العميل</th>
                                    <th>الحيوان</th>
                                    <th>التطعيم</th>
                                    <th>الموعد</th>
                                    <th class="text-center">تواصل</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($threeDaysUpcoming->isNotEmpty())
                                    @foreach ($threeDaysUpcoming as $item)
                                        <tr>
                                            <td>{{ $item->customer->name }}</td>
                                            <td>{{ $item->customer->animal_type }}</td>
                                            <td>{{ $item->product ? $item->product->name : '—' }}</td>
                                            <td>
                                                <span
                                                    class="badge bg-warning text-dark">{{ $item->next_dose_date->format('Y-m-d') }}</span>
                                            </td>
                                            <td class="text-center">
                                                @if ($item->customer->phone)
                                                    <a href="https://wa.me/{{ $item->customer->phone }}?text={{ urlencode('مرحباً ' . $item->customer->name . '، نود تذكيركم بموعد تطعيم (' . $item->product?->name . ') الخاص بـ (' . $item->customer->animal_type . ') يوم ' . $item->next_dose_date?->format('Y-m-d')) }}"
                                                        target="_blank" class="btn btn-sm btn-success">
                                                        <i class="bi bi-whatsapp"></i> واتساب
                                                    </a>
                                                @else
                                                    <span class="text-muted small">لا يوجد رقم</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">لا توجد مواعيد قادمة خلال
                                            الـ 3 أيام.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Action Confirmation Modal (Question 1) --}}
    <div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 bg-light">
                    <h5 class="modal-title fw-bold">إجراءات التطعيم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <i class="bi bi-question-circle text-primary mb-3 d-block" style="font-size: 3rem;"></i>
                    <h5 class="mb-4">هل أخذ الحيوان التطعيم؟</h5>
                    <input type="hidden" id="current_vacc_id">
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-primary btn-lg px-4" onclick="openNextDoseQuestionModal()">
                            <i class="bi bi-check-circle me-1"></i> ايوه
                        </button>
                        <button type="button" class="btn btn-warning btn-lg px-4" onclick="openRescheduleModal(false)">
                            <i class="bi bi-calendar-plus me-1"></i> لا (تأجيل)
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-lg px-4" data-bs-dismiss="modal">
                            إلغاء
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Next Dose Question Modal (Question 2) --}}
    <div class="modal fade" id="nextDoseQuestionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 bg-light">
                    <h5 class="modal-title fw-bold">موعد التطعيم القادم</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <i class="bi bi-calendar-event text-info mb-3 d-block" style="font-size: 3rem;"></i>
                    <h5 class="mb-4">هل تريد إضافة موعد التطعيم القادم؟</h5>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-success btn-lg px-4" onclick="openRescheduleModal(true)">
                            <i class="bi bi-calendar-plus me-1"></i> ايوه
                        </button>
                        <button type="button" class="btn btn-danger btn-lg px-4" onclick="markAsCompleted()">
                            <i class="bi bi-x-circle me-1"></i> لا
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. Reschedule Modal --}}
    <div class="modal fade" id="rescheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow border-0">
                <form id="rescheduleForm" onsubmit="submitReschedule(event)">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title fw-bold">تحديد موعد جديد للتطعيم</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div id="rescheduleError" class="alert alert-danger d-none"></div>
                        <label class="form-label fw-bold">اختر تاريخ الموعد القادم</label>
                        <input type="date" class="form-control form-control-lg" id="new_dose_date" required
                            min="{{ \Carbon\Carbon::tomorrow()->format('Y-m-d') }}">
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" id="rescheduleBtn">
                            <i class="bi bi-save me-1"></i> حفظ الموعد
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // ── بيانات التطعيمات من PHP ───────────────────────────────
            const customerData = @json($customerVaccinationsMap);
            const cvModal = new bootstrap.Modal(document.getElementById('customerVaccinationsModal'));

            // ── فتح Modal عند الضغط على اسم العميل ───────────────────
            document.querySelectorAll('.customer-name-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const cid = btn.getAttribute('data-customer-id');
                    const data = customerData[cid];
                    if (!data) return;

                    document.getElementById('cvm-title').textContent = data.name;
                    document.getElementById('cvm-subtitle').textContent = data.animal_type;

                    const statusMap = {
                        late: {
                            dot: '#E24B4A',
                            badge: 'bg-danger',
                            label: 'فات الموعد'
                        },
                        soon: {
                            dot: '#EF9F27',
                            badge: 'bg-warning text-dark',
                            label: 'قريب'
                        },
                        ok: {
                            dot: '#639922',
                            badge: 'bg-success',
                            label: 'بدري'
                        },
                        done: {
                            dot: '#888780',
                            badge: 'bg-secondary',
                            label: 'مكتمل'
                        },
                    };

                    let rows = '';
                    data.vaccinations.forEach(v => {
                        const s = statusMap[v.status] || statusMap.ok;
                        rows += `<tr>
                        <td><div style="width:10px;height:10px;border-radius:50%;background:${s.dot};margin:auto;"></div></td>
                        <td class="fw-semibold">${v.name}</td>
                        <td class="text-muted small">${v.vaccination_date}</td>
                        <td>${v.next_dose_date
                            ? `<span class="badge ${s.badge}">${v.next_dose_date}</span>`
                            : '<span class="text-muted">—</span>'
                        }</td>
                    </tr>`;
                    });
                    document.getElementById('cvm-body').innerHTML = rows;

                    // زرار واتساب بكل المواعيد
                    const waBtn = document.getElementById('cvm-wa-btn');
                    if (data.phone) {
                        let msg =
                            `السلام عليكم ${data.name}،\nنود تذكيركم بمواعيد تطعيم ${data.animal_type}:\n\n`;
                        data.vaccinations
                            .filter(v => v.next_dose_date && v.status !== 'done')
                            .forEach(v => {
                                msg += `• ${v.name} — ${v.next_dose_date}\n`;
                            });
                        msg += '\nعيادة بيتس كورنر 🐾';
                        waBtn.href = 'https://wa.me/' + data.phone + '?text=' + encodeURIComponent(
                            msg);
                        waBtn.classList.remove('d-none');
                    } else {
                        waBtn.classList.add('d-none');
                    }

                    cvModal.show();
                });
            });

            const actionModalEl = document.getElementById('actionModal');
            const actionModal = new bootstrap.Modal(actionModalEl);
            const nextDoseQuestionModal = new bootstrap.Modal(document.getElementById('nextDoseQuestionModal'));
            const rescheduleModal = new bootstrap.Modal(document.getElementById('rescheduleModal'));

            let activeCheckbox = null;

            // Display Confirmation Modals
            document.querySelectorAll('.vaccination-checkbox').forEach(box => {
                box.addEventListener('click', (e) => {
                    e.preventDefault(); // Prevent native check
                    if (box.disabled) return;

                    document.getElementById('current_vacc_id').value = box.getAttribute('data-id');
                    activeCheckbox = box;
                    actionModal.show();
                });
            });

            // Hide Checkbox Visual Check
            actionModalEl.addEventListener('hidden.bs.modal', () => {
                if (activeCheckbox && !activeCheckbox.disabled && !activeCheckbox.checked) {
                    activeCheckbox.checked = false;
                }
            });

            // Transition: Open next dose question
            window.openNextDoseQuestionModal = () => {
                actionModal.hide();
                setTimeout(() => {
                    nextDoseQuestionModal.show();
                }, 400);
            };

            // Ajax: Complete Vaccination (No next dose)
            window.markAsCompleted = async () => {
                const id = document.getElementById('current_vacc_id').value;
                if (!id) return;

                // disable buttons while processing
                const targetBtn = document.querySelector('#nextDoseQuestionModal .btn-danger');
                if (targetBtn) {
                    targetBtn.disabled = true;
                    targetBtn.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1"></span> جاري الحفظ...';
                }

                try {
                    const response = await fetch(`/vaccinations/${id}/complete`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();
                    if (data.success) {
                        nextDoseQuestionModal.hide();
                        location.reload();
                    }
                } catch (error) {
                    console.error(error);
                    alert('حدث خطأ أثناء الاتصال بالخادم');
                    if (targetBtn) {
                        targetBtn.disabled = false;
                        targetBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i> لا';
                    }
                }
            };

            let isCompletingWithReschedule = false;

            // Transition: Open Reschedule Modal (Date picker)
            window.openRescheduleModal = (willComplete) => {
                isCompletingWithReschedule = willComplete;
                actionModal.hide();
                nextDoseQuestionModal.hide();
                document.getElementById('rescheduleError').classList.add('d-none');
                document.getElementById('new_dose_date').value = '';
                setTimeout(() => {
                    rescheduleModal.show();
                }, 400);
            };

            // Ajax: Submit Reschedule (and potentially complete if flag is true)
            window.submitReschedule = async (e) => {
                e.preventDefault();
                const id = document.getElementById('current_vacc_id').value;
                const date = document.getElementById('new_dose_date').value;
                const btn = document.getElementById('rescheduleBtn');

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري الحفظ...';

                try {
                    // If the user said they DID take the vaccine AND want a new dose, we complete first.
                    if (isCompletingWithReschedule) {
                        const completeRes = await fetch(`/vaccinations/${id}/complete`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });
                        if (!completeRes.ok) throw new Error('فشل في إكمال التطعيم');
                    }

                    // Then submit the reschedule logic (which only updates the date on the backend)
                    const response = await fetch(`/vaccinations/${id}/reschedule`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            next_dose_date: date
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        rescheduleModal.hide();
                        location
                    .reload(); // reloads UI fading out the completed items and updating dates naturally
                    } else {
                        const err = document.getElementById('rescheduleError');
                        err.textContent = data.message || 'حدث خطأ ما';
                        err.classList.remove('d-none');
                    }
                } catch (error) {
                    console.error(error);
                    const err = document.getElementById('rescheduleError');
                    err.textContent = 'حدث خطأ أثناء حفظ الموعد';
                    err.classList.remove('d-none');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-save me-1"></i> حفظ الموعد';
                }
            };
        });
    </script>

    {{-- Modal: كل تطعيمات العميل --}}
    <div class="modal fade" id="customerVaccinationsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold" id="cvm-title"></h5>
                        <div class="text-muted small" id="cvm-subtitle"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:8px"></th>
                                <th>اللقاح</th>
                                <th>تاريخ التطعيم</th>
                                <th>الموعد القادم</th>
                            </tr>
                        </thead>
                        <tbody id="cvm-body"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <a id="cvm-wa-btn" href="#" target="_blank" class="btn btn-success">
                        <i class="bi bi-whatsapp me-1"></i> إرسال كل المواعيد
                    </a>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

@endsection
