<div class="modal fade" id="revenueModal" tabindex="-1" aria-labelledby="revenueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            
            <!-- MODAL HEADER -->
            <div class="modal-header">
                <h5 class="modal-title" id="revenueModalLabel">
                    <i class="bi bi-cash-stack text-success me-2"></i>درج العيادة
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- MODAL BODY -->
            <div class="modal-body">
                
                <!-- SECTION A -->
                <div class="bg-success bg-opacity-10 rounded-4 p-4 text-center">
                    <div class="text-muted mb-1">الكاش المحصّل فعليًا</div>
                    <h2 class="display-6 fw-bold text-success mb-2">
                        {{ number_format($revenueSummary['actual_gross_revenue'] ?? 0, 2) }} ج
                    </h2>
                    <div class="small text-muted">
                        {{ $revenueSummary['invoice_count'] ?? 0 }} فاتورة | 
                        {{ $revenueSummary['customer_visits'] ?? 0 }} زيارة | 
                        {{ $revenueSummary['quick_sales'] ?? 0 }} بيع سريع
                    </div>
                    @if(($revenueSummary['cancelled_count'] ?? 0) > 0)
                        <div class="alert alert-secondary mt-3 mb-0 py-1 small">
                            يوجد {{ $revenueSummary['cancelled_count'] }} فاتورة ملغية
                        </div>
                    @endif
                </div>

                <!-- SECTION B -->
                @if(($revenueSummary['total_expenses'] ?? 0) > 0)
                    <hr class="my-3">
                    <div class="mb-3">
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-arrow-down-circle text-danger me-2"></i>المصاريف
                        </h6>
                        <ul class="list-group list-group-flush">
                            @foreach($revenueSummary['expenses_list'] ?? [] as $expense)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                    <span class="text-start">{{ $expense->title }}</span>
                                    <span class="text-danger text-end">( {{ number_format($expense->amount, 2) }} ج )</span>
                                </li>
                            @endforeach
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent fw-bold border-bottom-0">
                                <span class="text-start">إجمالي المصاريف</span>
                                <span class="text-danger text-end">( {{ number_format($revenueSummary['total_expenses'], 2) }} ج )</span>
                            </li>
                        </ul>
                    </div>
                @endif

                <!-- SECTION C -->
                @if(($revenueSummary['supplier_cash_out'] ?? 0) > 0)
                    <hr class="my-3">
                    <div class="mb-3">
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-arrow-down-circle text-warning me-2"></i>دفعات موردين من الدرج
                        </h6>
                        <ul class="list-group list-group-flush">
                            @foreach($revenueSummary['supplier_payments_list'] ?? [] as $payment)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                    <span class="text-start">
                                        @if($payment->purchaseOrder)
                                            <a href="{{ route('purchases.show', $payment->purchaseOrder->id) }}" 
                                               target="_blank" 
                                               class="text-decoration-none fw-semibold">
                                                {{ $payment->purchaseOrder->order_number }}
                                                <i class="bi bi-box-arrow-up-left small"></i>
                                            </a>
                                        @endif
                                        @if($payment->notes)
                                            <span class="text-muted ms-1">- {{ $payment->notes }}</span>
                                        @endif
                                    </span>
                                    <span class="text-warning text-end">( {{ number_format($payment->amount, 2) }} ج )</span>
                                </li>
                            @endforeach
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent fw-bold border-bottom-0">
                                <span class="text-start">إجمالي دفعات الموردين</span>
                                <span class="text-warning text-end">( {{ number_format($revenueSummary['supplier_cash_out'], 2) }} ج )</span>
                            </li>
                        </ul>
                    </div>
                @endif

                <!-- SECTION C2 (Cash Refunds) -->
                @if(($revenueSummary['cash_refunds_out'] ?? 0) > 0)
                    <hr class="my-3">
                    <div class="mb-3">
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-arrow-down-circle text-danger me-2"></i>كاش مرتجعات للعملاء
                        </h6>
                        <ul class="list-group list-group-flush">
                            @foreach($revenueSummary['cash_refunds_list'] ?? [] as $refund)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                    <span class="text-start">
                                        @if($refund->invoice)
                                            <a href="{{ route('invoices.show', $refund->invoice->id) }}"
                                               target="_blank"
                                               class="text-decoration-none fw-semibold">
                                                {{ $refund->invoice->invoice_number }}
                                                <i class="bi bi-box-arrow-up-left small"></i>
                                            </a>
                                        @endif
                                        @if($refund->reason)
                                            <span class="text-muted ms-1">- {{ $refund->reason }}</span>
                                        @endif
                                    </span>
                                    <span class="text-danger text-end">( {{ number_format($refund->cash_refunded, 2) }} ج )</span>
                                </li>
                            @endforeach
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent fw-bold border-bottom-0">
                                <span class="text-start">إجمالي كاش المرتجعات</span>
                                <span class="text-danger text-end">( {{ number_format($revenueSummary['cash_refunds_out'], 2) }} ج )</span>
                            </li>
                        </ul>
                    </div>
                @endif

                <!-- SECTION D -->
                <hr class="my-3">
                <div class="bg-primary bg-opacity-10 rounded-4 border border-primary border-opacity-25 p-4 text-center">
                    <h6 class="fw-bold text-primary mb-2">
                        <i class="bi bi-safe2 me-2"></i>صافي الدرج
                    </h6>
                    <div class="text-muted small mb-2" dir="ltr">
                        {{ number_format($revenueSummary['actual_gross_revenue'] ?? 0, 2) }}
                        @if(($revenueSummary['total_expenses'] ?? 0) > 0)
                            - {{ number_format($revenueSummary['total_expenses'], 2) }}
                        @endif
                        @if(($revenueSummary['supplier_cash_out'] ?? 0) > 0)
                            - {{ number_format($revenueSummary['supplier_cash_out'], 2) }}
                        @endif
                        @if(($revenueSummary['cash_refunds_out'] ?? 0) > 0)
                            - {{ number_format($revenueSummary['cash_refunds_out'], 2) }}
                        @endif
                    </div>
                    @php
                        $net = $revenueSummary['net_cash_in_drawer'] ?? 0;
                        $colorClass = $net > 0 ? 'text-success' : 'text-danger';
                    @endphp
                    <h2 class="display-5 fw-bold mb-0 {{ $colorClass }}">
                        {{ number_format($net, 2) }} ج
                    </h2>
                </div>

            </div>

            <!-- MODAL FOOTER -->
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">إغلاق</button>
            </div>

        </div>
    </div>
</div>
