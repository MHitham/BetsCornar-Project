<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class VerifyCloudflareLicense
{
    /**
     * المسارات المستثناة من التحقق من الترخيص
     */
    protected array $excludedPaths = [
        'license/*',
        'login',
        'logout',
    ];

    /**
     * معالجة الطلب الوارد
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ── التحقق مما إذا كان المسار مستثنى من فحص الترخيص ──
        if ($this->isExcludedPath($request)) {
            return $next($request);
        }

        // ── قراءة مفتاح الترخيص من ملف الإعدادات ──
        $licenseKey = config('app.license_key') ?: env('LICENSE_KEY');

        if (empty($licenseKey)) {
            return $this->licenseErrorResponse($this->getDeviceFingerprint());
        }

        // ── فحص الكاش أولاً قبل الاتصال بـ API ──
        $cachedResult = cache()->get('license_check_result');

        if ($cachedResult === 'active') {
            // الترخيص نشط ومخزّن في الكاش — السماح بالطلب
            return $next($request);
        }

        if ($cachedResult === 'blocked') {
            // الترخيص محظور ومخزّن في الكاش — عرض صفحة الخطأ
            return $this->licenseErrorResponse($this->getDeviceFingerprint());
        }

        // ── لا يوجد كاش — الاتصال بـ API الخاص بـ Cloudflare ──
        $deviceHash = $this->getDeviceFingerprint();
        $apiUrl = env('CLOUDFLARE_LICENSE_API_URL');

        try {
            $response = Http::timeout(5)->post($apiUrl, [
                'license_key' => $licenseKey,
                'device_hash' => $deviceHash,
            ]);

            // ── التحقق من حالة الاستجابة ──
            if ($response->successful() && $response->json('active') === true) {
                // الترخيص نشط — تخزين النتيجة في الكاش ليوم كامل
                cache()->put('license_check_result', 'active', now()->addDay());

                return $next($request);
            }

            // الترخيص غير نشط أو مرفوض — تخزين النتيجة في الكاش لساعة واحدة
            cache()->put('license_check_result', 'blocked', now()->addHour());

            return $this->licenseErrorResponse($deviceHash);

        } catch (\Throwable $e) {
            // ── فشل الاتصال بـ API (لا يوجد إنترنت أو انتهت المهلة) ──
            return $this->handleOfflineGracePeriod($deviceHash, $next, $request);
        }
    }

    /**
     * معالجة فترة السماح عند انقطاع الاتصال
     */
    protected function handleOfflineGracePeriod(string $deviceHash, Closure $next, Request $request): Response
    {
        // ── التحقق من وجود فترة السماح في الكاش ──
        $offlineExpiry = cache()->get('license_offline_expiry');

        if ($offlineExpiry !== null) {
            // فترة السماح موجودة — التحقق من انتهائها
            if (now()->lessThanOrEqualTo($offlineExpiry)) {
                // لا تزال فترة السماح سارية — السماح بالطلب
                return $next($request);
            }

            // انتهت فترة السماح — عرض صفحة الخطأ
            return $this->licenseErrorResponse($deviceHash);
        }

        // ── لا توجد فترة سماح مسجّلة — إنشاء فترة سماح لمدة 7 أيام ──
        $expiry = now()->addDays(7);
        cache()->put('license_offline_expiry', $expiry, $expiry);

        return $next($request);
    }

    /**
     * توليد بصمة الجهاز واستردادها من الملف إن وُجدت
     */
    protected function getDeviceFingerprint(): string
    {
        $deviceIdPath = storage_path('app/.device_id');

        // ── قراءة البصمة من الملف إن كانت موجودة مسبقاً ──
        if (file_exists($deviceIdPath)) {
            $stored = trim(file_get_contents($deviceIdPath));
            if (! empty($stored)) {
                return $stored;
            }
        }

        // ── توليد بصمة الجهاز لأول مرة ──
        $fingerprint = $this->generateDeviceFingerprint();

        // حفظ البصمة في الملف للاستخدام لاحقاً
        file_put_contents($deviceIdPath, $fingerprint);

        return $fingerprint;
    }

    /**
     * توليد بصمة فريدة للجهاز باستخدام معلومات النظام
     */
    protected function generateDeviceFingerprint(): string
    {
        // ── جمع معلومات النظام الأساسية ──
        $uname = php_uname();
        $hostname = gethostname() ?: '';

        // ── محاولة الحصول على UUID الجهاز (Windows فقط) ──
        $uuid = '';
        try {
            $output = [];
            exec('wmic csproduct get uuid 2>NUL', $output);
            // تجاهل السطر الأول (العنوان) وقراءة القيمة
            $uuid = isset($output[1]) ? trim($output[1]) : '';
        } catch (\Throwable $e) {
            $uuid = '';
        }

        // ── محاولة الحصول على معرّف المعالج (Windows فقط) ──
        $processorId = '';
        try {
            $output = [];
            exec('wmic cpu get processorid 2>NUL', $output);
            $processorId = isset($output[1]) ? trim($output[1]) : '';
        } catch (\Throwable $e) {
            $processorId = '';
        }

        // ── دمج جميع البيانات وتشفيرها بـ SHA256 ──
        $combined = implode('|', [$uname, $hostname, $uuid, $processorId]);

        return hash('sha256', $combined);
    }

    /**
     * التحقق مما إذا كان المسار الحالي مستثنى من فحص الترخيص
     */
    protected function isExcludedPath(Request $request): bool
    {
        foreach ($this->excludedPaths as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * إرجاع صفحة خطأ الترخيص بتصميم عربي RTL
     */
    protected function licenseErrorResponse(string $deviceHash): Response
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطأ في الترخيص — BetsCornar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Cairo', sans-serif;
            background: #0d1117;
            color: #e6edf3;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .license-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 16px;
            padding: 3rem 2.5rem;
            max-width: 540px;
            width: 100%;
            box-shadow: 0 16px 48px rgba(0,0,0,0.6);
        }
        .icon-wrapper {
            width: 72px;
            height: 72px;
            background: rgba(248,81,73,0.12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        .icon-wrapper svg {
            width: 36px;
            height: 36px;
            fill: #f85149;
        }
        h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #f85149;
            text-align: center;
            margin-bottom: 0.75rem;
            line-height: 1.6;
        }
        .subtitle {
            font-size: 0.95rem;
            color: #8b949e;
            text-align: center;
            margin-bottom: 2rem;
            line-height: 1.7;
        }
        .device-hash-box {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.75rem;
        }
        .device-hash-box label {
            font-size: 0.78rem;
            color: #8b949e;
            display: block;
            margin-bottom: 0.4rem;
        }
        .device-hash-box code {
            font-size: 0.75rem;
            color: #79c0ff;
            word-break: break-all;
            letter-spacing: 0.03em;
        }
        .form-label {
            font-size: 0.88rem;
            color: #c9d1d9;
            margin-bottom: 0.4rem;
            display: block;
        }
        .form-control {
            background: #0d1117;
            border: 1px solid #30363d;
            color: #e6edf3;
            border-radius: 8px;
            padding: 0.6rem 0.9rem;
            font-family: 'Cairo', sans-serif;
            font-size: 0.9rem;
            width: 100%;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: #388bfd;
            box-shadow: 0 0 0 3px rgba(56,139,253,0.15);
        }
        .form-control::placeholder { color: #484f58; }
        .btn-submit {
            margin-top: 1rem;
            width: 100%;
            background: #238636;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.65rem;
            font-family: 'Cairo', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover { background: #2ea043; }
        .footer-note {
            margin-top: 1.5rem;
            font-size: 0.78rem;
            color: #484f58;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="license-card">
        <div class="icon-wrapper">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10
                         10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
        </div>

        <h1>هذا البرنامج غير مرخص أو انتهت صلاحية الترخيص</h1>

        <p class="subtitle">
            لا يمكن تشغيل النظام بدون ترخيص ساري المفعول.<br>
            تواصل مع الدعم الفني للحصول على مفتاح ترخيص جديد.
        </p>

        <div class="device-hash-box">
            <label>🔑 معرّف الجهاز (Device ID) — أرسله لفريق الدعم:</label>
            <code>{$deviceHash}</code>
        </div>

        <div>
            <label class="form-label">أدخل مفتاح الترخيص الجديد:</label>
            <input
                type="text"
                class="form-control"
                placeholder="XXXX-XXXX-XXXX-XXXX"
                id="new-license-key"
                autocomplete="off"
                spellcheck="false"
            >
            <button class="btn-submit" onclick="submitLicense()">تفعيل الترخيص</button>
        </div>

        <p class="footer-note">BetsCornar &copy; جميع الحقوق محفوظة</p>
    </div>

    <script>
        function submitLicense() {
            const key = document.getElementById('new-license-key').value.trim();
            if (!key) {
                alert('يرجى إدخال مفتاح الترخيص أولاً.');
                return;
            }
            // للاستخدام المستقبلي — إرسال المفتاح إلى المسار المخصص
            alert('سيتم دعم هذه الميزة قريباً. تواصل مع فريق الدعم.');
        }
    </script>
</body>
</html>
HTML;

        return response($html, 403);
    }
}
