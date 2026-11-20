@props(['invoice'])
<input type="hidden" name="payment_invoice_id" value="{{ $invoice->id }}">
<input type="datetime-local" name="paid_at" class="form-control"
       value="{{ old('payment_invoice_id') == $invoice->id ? old('paid_at', now()->timezone('Africa/Cairo')->format('Y-m-d\TH:i')) : now()->timezone('Africa/Cairo')->format('Y-m-d\TH:i') }}">
<div class="form-text">بتوقيت القاهرة — يُملأ تلقائيًا ويمكنك تعديله.</div>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const errors = @json($errors->getBag('payment')->messages());
    const failedInvoice = @json(old('payment_invoice_id'));
    function cairoNow() {
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Africa/Cairo', year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
        }).formatToParts(new Date());
        const values = Object.fromEntries(parts.map(p => [p.type, p.value]));
        return `${values.year}-${values.month}-${values.day}T${values.hour}:${values.minute}`;
    }
    document.querySelectorAll('form[data-payment-form]').forEach(form => {
        const amount = form.elements.amount;
        const date = form.elements.paid_at;
        function showError(field, message) {
            const input = form.elements[field];
            if (!input) return;
            let error = form.querySelector(`[data-payment-error="${field}"]`);
            if (!error) {
                error = document.createElement('div');
                error.className = 'text-danger small mt-1';
                error.dataset.paymentError = field;
                error.setAttribute('role', 'alert');
                error.id = `payment-${form.elements.payment_invoice_id.value}-${field}-error`;
                (input.closest('.input-group') || input).after(error);
                input.setAttribute('aria-describedby', error.id);
            }
            error.textContent = message;
            input.classList.toggle('is-invalid', Boolean(message));
            input.setAttribute('aria-invalid', message ? 'true' : 'false');
        }
        function amountError() {
            if (!amount.value.trim()) return 'المبلغ مطلوب.';
            const value = Number(amount.value);
            if (!Number.isFinite(value) || value <= 0) return 'المبلغ يجب أن يكون أكبر من صفر.';
            if (value > Number(amount.max)) return `المبلغ أكبر من المتبقي (${Number(amount.max).toFixed(2)} ج)`;
            if (amount.validity.stepMismatch) return 'اكتب المبلغ بمنزلتين عشريتين بحد أقصى.';
            return '';
        }
        let dateEdited = String(failedInvoice) === form.elements.payment_invoice_id.value;
        date.addEventListener('input', () => { dateEdited = true; showError('paid_at', ''); });
        amount.addEventListener('input', () => showError('amount', amountError()));
        form.closest('.modal')?.addEventListener('show.bs.modal', () => {
            if (!dateEdited) date.value = cairoNow();
        });
        form.addEventListener('submit', event => {
            const message = amountError();
            showError('amount', message);
            if (!dateEdited) date.value = cairoNow();
            const invalidDate = !date.value || !date.validity.valid;
            showError('paid_at', invalidDate ? 'تاريخ الدفع غير صالح.' : '');
            if (message || invalidDate) {
                event.preventDefault();
                event.stopImmediatePropagation();
                (message ? amount : date).focus();
            }
        }, true);
        if (String(failedInvoice) === form.elements.payment_invoice_id.value) {
            Object.entries(errors).forEach(([field, messages]) => showError(field, messages[0]));
            amount.value = @json(old('amount', ''));
            if (form.elements.notes) form.elements.notes.value = @json(old('notes', ''));
            const modal = form.closest('.modal');
            if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
});
</script>
@endpush
@endonce
