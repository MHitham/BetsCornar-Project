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

    // تحقق من صحة النموذج أولًا — إذا لم يكن صالحًا، اتركه يظهر رسائل الخطأ ولا تعطّله.
    if (!form.checkValidity()) {
        return;
    }

    form.dataset.submitting = '1';

    // تأجيل تعطيل الأزرار حتى لا نمنع اكتمال submit listeners الأخرى.
    setTimeout(function () {
        // معالجة أزرار submit وأزرار بدون type وحقول input من نوع submit.
        form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach(function (el) {
            if (el.tagName === 'INPUT') {
                el.dataset.old = el.value;
                el.disabled = true;
                el.value = 'جاري الحفظ...';
            } else {
                el.dataset.old = el.innerHTML;
                el.disabled = true;
                el.innerHTML = 'جاري الحفظ...';
            }
        });
    }, 0);
});

// إعادة ضبط حالة النموذج عند الرجوع للصفحة من سجل المتصفح (bfcache).
window.addEventListener('pageshow', function (event) {
    // event.persisted = true يعني الصفحة جاية من bfcache.
    document.querySelectorAll('form[data-prevent-double][data-submitting="1"]').forEach(function (form) {
        form.dataset.submitting = '0';
        form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach(function (el) {
            el.disabled = false;
            if (el.tagName === 'INPUT') {
                if (el.dataset.old !== undefined) {
                    el.value = el.dataset.old;
                    delete el.dataset.old;
                }
            } else {
                if (el.dataset.old !== undefined) {
                    el.innerHTML = el.dataset.old;
                    delete el.dataset.old;
                }
            }
        });
    });
});
