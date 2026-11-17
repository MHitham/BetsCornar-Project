// منع الإرسال المزدوج للنماذج التي تستخدم حماية idempotent.
document.addEventListener('submit', function (event) {
    const form = event.target;

    if (!form.matches('form[data-prevent-double]')) {
        return;
    }

    if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
    }

    form.dataset.submitting = '1';

    // تأجيل تعطيل الأزرار حتى لا نمنع اكتمال submit listeners الأخرى.
    setTimeout(function () {
        form.querySelectorAll('[type="submit"]').forEach(function (button) {
            button.dataset.old = button.innerHTML;
            button.disabled = true;
            button.innerHTML = 'جاري الحفظ...';
        });
    }, 0);
});

// إعادة ضبط حالة النموذج عند الرجوع للصفحة من سجل المتصفح.
window.addEventListener('pageshow', function () {
    document.querySelectorAll('form[data-prevent-double][data-submitting="1"]').forEach(function (form) {
        form.dataset.submitting = '0';
        form.querySelectorAll('[type="submit"]').forEach(function (button) {
            button.disabled = false;
            if (button.dataset.old !== undefined) {
                button.innerHTML = button.dataset.old;
                delete button.dataset.old;
            }
        });
    });
});
