<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ===================================================================
 * وسيط التحقق من الترخيص - CheckLicense Middleware
 * ===================================================================
 *
 * يتحقق من حالة ترخيص التطبيق في كل طلب HTTP:
 *
 * 1. إذا مُفعل → يسمح بالمرور
 * 2. إذا في فترة التجربة → يسمح بالمرور
 * 3. إذا انتهت التجربة / تلاعب بالوقت / تلاعب بالبيانات:
 *    → يعرض نافذة ترخيص حظر (بدون Blade page)
 *    → المستخدم يدخل المفتاح أو يغلق التطبيق
 *
 * المسارات المستثناة: license/* و native-test (لتجنب الحلقة المغلقة)
 */
class CheckLicense
{
    /**
     * المسارات المستثناة من فحص الترخيص
     */
    protected array $excludedPaths = [
        'license/*',
        'native-test',
    ];

    /**
     * معالجة الطلب الوارد
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ===================================================================
        // تخطي المسارات المستثناة (مسار التفعيل + اختبار NativePHP)
        // ===================================================================
        foreach ($this->excludedPaths as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        // ===================================================================
        // التحقق من وجود جدول الترخيص (قد لا يكون موجوداً قبل أول migration)
        // ===================================================================
        try {
            if (!\Schema::hasTable('app_licenses')) {
                return $next($request);
            }
        } catch (\Throwable $e) {
            // إذا فشل الاتصال بقاعدة البيانات، نسمح بالمرور مؤقتاً
            return $next($request);
        }

        // ===================================================================
        // فحص حالة الترخيص
        // ===================================================================
        try {
            $result = \Illuminate\Support\Facades\Cache::remember('license_status', 300, function () {
                return LicenseService::checkLicense();
            });
        } catch (\Throwable $e) {
            // في حالة أي خطأ غير متوقع، نسجل الخطأ ونسمح بالمرور
            Log::error('CheckLicense: خطأ غير متوقع', ['error' => $e->getMessage()]);
            return $next($request);
        }

        // ===================================================================
        // اتخاذ القرار بناءً على حالة الترخيص
        // ===================================================================
        switch ($result['status']) {
            case 'activated':
                // مُفعل بالكامل → السماح بالمرور
                return $next($request);

            case 'trial':
                // في فترة التجربة → السماح بالمرور
                return $next($request);

            case 'expired':
                // انتهت الفترة التجريبية → عرض نافذة التفعيل
                return $this->blockWithPopup(
                    $result['machine_id'],
                    'expired',
                    $request
                );

            case 'time_tampered':
                // تم اكتشاف تلاعب بالوقت → قفل فوري
                return $this->blockWithPopup(
                    $result['machine_id'],
                    'time_tampered',
                    $request
                );

            case 'tampered':
                // تم اكتشاف تلاعب بالبيانات → قفل فوري
                return $this->blockWithPopup(
                    $result['machine_id'],
                    'tampered',
                    $request
                );

            default:
                return $next($request);
        }
    }

    /**
     * ===================================================================
     * عرض نافذة الترخيص المانعة - Block With Popup
     * ===================================================================
     *
     * يعرض صفحة HTML مضمنة (ليست Blade page) تعمل كنافذة حظر:
     * - تعرض رسالة الخطأ
     * - تعرض رقم الجهاز (قابل للنسخ)
     * - تحتوي على حقل إدخال مفتاح الترخيص
     * - إذا أغلق المستخدم النافذة أو ضغط إلغاء → يُغلق التطبيق فوراً
     * - إذا أدخل مفتاح صحيح → يُفعل ويعيد التوجيه
     * - إذا أدخل مفتاح خاطئ → يعرض خطأ ويعيد المحاولة
     *
     * ملاحظة: هذا HTML مضمن في الـ middleware وليس Blade template
     * لتلبية متطلبات "استخدم NativePHP dialog أو JavaScript prompt"
     *
     * @param string $machineId بصمة الجهاز
     * @param string $reason سبب القفل
     * @param Request $request الطلب الأصلي
     * @return Response
     */
    protected function blockWithPopup(string $machineId, string $reason, Request $request): Response
    {
        // تحديد رسالة الخطأ حسب السبب
        $title = match ($reason) {
            'expired'       => '⚠️ انتهت الفترة التجريبية',
            'time_tampered' => '🔒 تم قفل التطبيق',
            'tampered'      => '🔒 تم قفل التطبيق',
            default         => '🔒 الترخيص مطلوب',
        };

        $subtitle = match ($reason) {
            'expired'       => 'يرجى إدخال مفتاح الترخيص للاستمرار في استخدام التطبيق',
            'time_tampered' => 'تم اكتشاف تلاعب بتاريخ النظام - يرجى إدخال مفتاح الترخيص',
            'tampered'      => 'تم اكتشاف تلاعب ببيانات التطبيق - يرجى إدخال مفتاح الترخيص',
            default         => 'يرجى إدخال مفتاح الترخيص',
        };

        // توليد CSRF token لطلب التفعيل
        $csrfToken = csrf_token();

        // ===================================================================
        // HTML مضمن - نافذة الترخيص المانعة
        // ===================================================================
        $html = <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- تم إزالة الاسم الثابت من صفحة الترخيص -->
    <title>ترخيص مطلوب</title>
    <style>
        /* ===== Reset & Base ===== */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', 'Tahoma', 'Arial', sans-serif;
            background: linear-gradient(135deg, #0a0a1a 0%, #1a1a3e 50%, #0d0d2b 100%);
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        /* ===== Animated Background ===== */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 40%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 70% 60%, rgba(139, 92, 246, 0.06) 0%, transparent 50%);
            animation: bgFloat 15s ease-in-out infinite alternate;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes bgFloat {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-3%, -3%) rotate(3deg); }
        }

        /* ===== Main Card ===== */
        .license-card {
            position: relative;
            z-index: 1;
            background: rgba(22, 27, 58, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 24px;
            padding: 48px 40px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 32px 64px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset;
            animation: cardSlideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes cardSlideIn {
            from { opacity: 0; transform: translateY(30px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ===== Lock Icon ===== */
        .lock-icon {
            font-size: 56px;
            text-align: center;
            margin-bottom: 16px;
            animation: iconPulse 2s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }

        /* ===== Typography ===== */
        .title {
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            color: #fff;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            font-size: 14px;
            text-align: center;
            color: #94a3b8;
            margin-bottom: 32px;
            line-height: 1.6;
        }

        /* ===== Machine ID Box ===== */
        .machine-id-label {
            display: block;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .machine-id-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(99, 102, 241, 0.15);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 28px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .machine-id-box:hover {
            border-color: rgba(99, 102, 241, 0.4);
            background: rgba(15, 23, 42, 0.8);
        }

        .machine-id-text {
            flex: 1;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 13px;
            color: #818cf8;
            word-break: break-all;
            direction: ltr;
            text-align: left;
            user-select: all;
        }

        .copy-btn {
            background: rgba(99, 102, 241, 0.15);
            border: none;
            color: #818cf8;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .copy-btn:hover {
            background: rgba(99, 102, 241, 0.3);
        }

        .copy-btn.copied {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        /* ===== Input Field ===== */
        .input-label {
            display: block;
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .license-input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(15, 23, 42, 0.6);
            border: 2px solid rgba(99, 102, 241, 0.2);
            border-radius: 12px;
            color: #fff;
            font-size: 16px;
            font-family: 'Consolas', 'Courier New', monospace;
            direction: ltr;
            text-align: left;
            letter-spacing: 1px;
            outline: none;
            transition: all 0.3s ease;
        }

        .license-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .license-input::placeholder {
            color: #475569;
            letter-spacing: 0;
        }

        .license-input.error {
            border-color: #ef4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }

        .license-input.success {
            border-color: #22c55e;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.1);
        }

        /* ===== Message Box ===== */
        .message {
            margin-top: 12px;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            text-align: center;
            display: none;
            animation: msgFadeIn 0.3s ease;
        }

        @keyframes msgFadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.error {
            display: block;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .message.success {
            display: block;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }

        /* ===== Buttons ===== */
        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .btn-activate {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
        }

        .btn-activate:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        }

        .btn-activate:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-close {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-close:hover {
            background: rgba(239, 68, 68, 0.2);
        }

        /* ===== Spinner ===== */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-left: 8px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ===== Trial Info Footer ===== */
        .footer-info {
            text-align: center;
            margin-top: 24px;
            font-size: 11px;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="license-card">
        <!-- أيقونة القفل -->
        <div class="lock-icon">{$this->getLockEmoji($reason)}</div>

        <!-- العنوان والعنوان الفرعي -->
        <h1 class="title">{$title}</h1>
        <p class="subtitle">{$subtitle}</p>

        <!-- رقم الجهاز -->
        <span class="machine-id-label">رقم الجهاز (Machine ID)</span>
        <div class="machine-id-box" onclick="copyMachineId()" title="انقر للنسخ">
            <span class="machine-id-text" id="machineId">{$machineId}</span>
            <button class="copy-btn" id="copyBtn">نسخ</button>
        </div>

        <!-- حقل إدخال مفتاح الترخيص -->
        <label class="input-label" for="licenseKey">مفتاح الترخيص</label>
        <input
            type="text"
            class="license-input"
            id="licenseKey"
            placeholder="أدخل مفتاح الترخيص هنا..."
            maxlength="20"
            autocomplete="off"
            autofocus
        >

        <!-- رسالة الخطأ/النجاح -->
        <div class="message" id="message"></div>

        <!-- أزرار -->
        <div class="buttons">
            <button class="btn btn-activate" id="activateBtn" onclick="activateLicense()">
                تفعيل الترخيص
            </button>
            <button class="btn btn-close" onclick="closeApp()">
                إغلاق
            </button>
        </div>

        <!-- معلومات إضافية -->
        <div class="footer-info">
            أرسل رقم الجهاز أعلاه للحصول على مفتاح الترخيص
        </div>
    </div>

    <script>
        /**
         * ===================================================================
         * JavaScript - منطق نافذة الترخيص المانعة
         * ===================================================================
         *
         * هذا الكود يعمل داخل نافذة NativePHP/Electron
         * يتعامل مع: نسخ رقم الجهاز، التفعيل عبر AJAX، إغلاق التطبيق
         */

        const CSRF_TOKEN = '{$csrfToken}';
        const activateBtn = document.getElementById('activateBtn');
        const licenseInput = document.getElementById('licenseKey');
        const messageBox = document.getElementById('message');

        // ===================================================================
        // نسخ رقم الجهاز إلى الحافظة
        // ===================================================================
        function copyMachineId() {
            const machineId = document.getElementById('machineId').textContent;
            navigator.clipboard.writeText(machineId).then(() => {
                const copyBtn = document.getElementById('copyBtn');
                copyBtn.textContent = 'تم النسخ ✓';
                copyBtn.classList.add('copied');
                setTimeout(() => {
                    copyBtn.textContent = 'نسخ';
                    copyBtn.classList.remove('copied');
                }, 2000);
            }).catch(() => {
                // Fallback: حدد النص يدوياً
                const range = document.createRange();
                range.selectNode(document.getElementById('machineId'));
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
            });
        }

        // ===================================================================
        // تفعيل الترخيص عبر AJAX
        // ===================================================================
        async function activateLicense() {
            const key = licenseInput.value.trim().toUpperCase();

            if (!key) {
                showMessage('يرجى إدخال مفتاح الترخيص', 'error');
                licenseInput.classList.add('error');
                licenseInput.focus();
                return;
            }

            // إظهار حالة التحميل
            activateBtn.disabled = true;
            activateBtn.innerHTML = 'جاري التحقق...<span class="spinner"></span>';
            licenseInput.classList.remove('error', 'success');
            hideMessage();

            try {
                const response = await fetch('/license/activate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                    },
                    body: JSON.stringify({ key: key }),
                });

                const data = await response.json();

                if (data.success) {
                    // التفعيل ناجح → إعادة توجيه للتطبيق
                    showMessage('تم التفعيل بنجاح! جاري إعادة التوجيه...', 'success');
                    licenseInput.classList.add('success');
                    activateBtn.innerHTML = '✓ تم التفعيل';

                    setTimeout(() => {
                        window.location.href = '/';
                    }, 1500);
                } else {
                    // مفتاح خاطئ → عرض خطأ وإعادة المحاولة
                    showMessage('مفتاح الترخيص غير صحيح - يرجى المحاولة مرة أخرى', 'error');
                    licenseInput.classList.add('error');
                    licenseInput.select();
                    resetButton();
                }
            } catch (error) {
                showMessage('حدث خطأ في الاتصال - يرجى المحاولة مرة أخرى', 'error');
                resetButton();
            }
        }

        // ===================================================================
        // إغلاق التطبيق فوراً
        // ===================================================================
        function closeApp() {
            try {
                window.close();
            } catch (e) {
                // Fallback: في حال لم يعمل window.close() في بعض بيئات Electron
                document.body.innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100vh;color:#94a3b8;font-size:18px;text-align:center;padding:20px;">يرجى إغلاق التطبيق يدوياً</div>';
            }
        }

        // ===================================================================
        // دوال مساعدة للواجهة
        // ===================================================================
        function showMessage(text, type) {
            messageBox.textContent = text;
            messageBox.className = 'message ' + type;
        }

        function hideMessage() {
            messageBox.className = 'message';
            messageBox.textContent = '';
        }

        function resetButton() {
            activateBtn.disabled = false;
            activateBtn.innerHTML = 'تفعيل الترخيص';
        }

        // ===================================================================
        // أحداث لوحة المفاتيح
        // ===================================================================

        // Enter → محاولة التفعيل
        licenseInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                activateLicense();
            }
        });

        // مسح رسالة الخطأ عند الكتابة
        licenseInput.addEventListener('input', function() {
            this.classList.remove('error');
            hideMessage();
            // تحويل تلقائي لأحرف كبيرة
            this.value = this.value.toUpperCase();
        });

        // ===================================================================
        // حماية: منع Navigate back أو إعادة تحميل بدون تفعيل
        // ===================================================================
        window.addEventListener('beforeunload', function(e) {
            // لا نمنع الإغلاق لأن المستخدم يريد إغلاق التطبيق
        });

        // تركيز تلقائي على حقل الإدخال
        window.addEventListener('load', function() {
            licenseInput.focus();
        });
    </script>
</body>
</html>
HTML;

        return response($html, 403);
    }

    /**
     * الحصول على أيقونة القفل المناسبة حسب السبب
     */
    private function getLockEmoji(string $reason): string
    {
        return match ($reason) {
            'expired'       => '⏰',
            'time_tampered' => '🔒',
            'tampered'      => '🛡️',
            default         => '🔒',
        };
    }
}
