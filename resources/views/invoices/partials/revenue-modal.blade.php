@php
    $periodType = $revenueSummary['period_type'] ?? 'day';
    $periodTitles = [
        'day' => 'إيرادات اليوم',
        'month' => 'إيرادات الشهر',
        'all' => 'إجمالي الإيرادات',
    ];
    $periodTitle = $periodTitles[$periodType] ?? 'الإيرادات';
    $periodLabels = [
        'day' => 'التاريخ',
        'month' => 'الشهر',
        'all' => 'النطاق',
    ];
@endphp

<div class="modal fade" id="revenueModal" tabindex="-1" aria-labelledby="revenueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="revenueModalLabel">
                    <i class="bi bi-cash-stack text-success me-2"></i>{{ $periodTitle }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="text-muted small mb-3">
                    {{ $periodLabels[$periodType] ?? 'الفترة' }}:
                    <strong class="text-dark">{{ $revenueSummary['label'] }}</strong>
                </div>

                <div class="revenue-hero text-center p-4 rounded-4 mb-4">
                    <div class="text-muted small mb-1">إجمالي الإيرادات (فواتير مؤكدة)</div>
                    <div class="display-6 fw-bold text-success mb-0">
                        {{ number_format($revenueSummary['gross_revenue'], 2) }}
                        <span class="fs-6 fw-semibold">{{ __('messages.currency') }}</span>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-6 col-sm-4">
                        <div class="revenue-stat-card text-center p-3 rounded-3 h-100">
                            <div class="text-muted small">عدد الفواتير</div>
                            <div class="fs-4 fw-bold text-primary">{{ $revenueSummary['invoice_count'] }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-4">
                        <div class="revenue-stat-card text-center p-3 rounded-3 h-100">
                            <div class="text-muted small">زيارات عملاء</div>
                            <div class="fs-4 fw-bold text-primary">{{ $revenueSummary['customer_visits'] }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="revenue-stat-card text-center p-3 rounded-3 h-100">
                            <div class="text-muted small">بيع سريع</div>
                            <div class="fs-4 fw-bold text-success">{{ $revenueSummary['quick_sales'] }}</div>
                        </div>
                    </div>
                    @if (($revenueSummary['cancelled_count'] ?? 0) > 0)
                        <div class="col-12">
                            <div class="alert alert-secondary py-2 mb-0 small">
                                <i class="bi bi-x-circle me-1"></i>
                                فواتير ملغية في نفس الفترة:
                                <strong>{{ $revenueSummary['cancelled_count'] }}</strong>
                                (لا تُحسب ضمن الإيرادات)
                            </div>
                        </div>
                    @endif
                </div>

                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    الإيرادات = مجموع إجمالي الفواتير المؤكدة فقط، بعد خصم المرتجعات المسجلة على الفاتورة.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary w-100 w-sm-auto" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>
