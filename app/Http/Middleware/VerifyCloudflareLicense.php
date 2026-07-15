<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache; // 🚀 أضفنا الكاش هنا
use Symfony\Component\HttpFoundation\Response;

class VerifyCloudflareLicense
{
    // protected array $excludedPaths = [
    //     'license/*',
    //     'login',
    //     'logout',
    // ];

    public function handle(Request $request, Closure $next): Response
    {
        // // تجاوز الترخيص في بيئة التطوير المحلية
        // if (app()->environment('local')) {
        //     return $next($request);
        // }
        return $next($request);

        // if ($this->isExcludedPath($request)) {
        //     return $next($request);
        // }

        // if (! $this->checkMacLock()) {
        //     return $this->licenseErrorResponse('MAC_MISMATCH' , $this->getDeviceFingerprint());
        // }

        // $licenseKey = config('app.license_key') ?: env('LICENSE_KEY');

        // if (empty($licenseKey)) {
        //     return $this->licenseErrorResponse($this->getDeviceFingerprint());
        // }

        // $deviceHash = $this->getDeviceFingerprint();

        // // 🚀 1. بنعمل اسم فريد للكاش بناءً على السيريال والجهاز
        // $cacheKey = 'cf_license_valid_' . md5($licenseKey . '_' . $deviceHash);

        // // 🚀 2. لو الكاش موجود ولسه مخلصش صلاحيته، عدي الصفحة فوراً ومتحملش هم
        // if (Cache::get($cacheKey) === true) {
        //     return $next($request);
        // }

        // $apiUrl = env('CLOUDFLARE_LICENSE_API_URL');

        // try {
        //     $response = Http::timeout(5)->post($apiUrl, [
        //         'license_key' => $licenseKey,
        //         'device_hash' => $deviceHash,
        //     ]);

        //     if ($response->successful() && $response->json('active') === true) {
                
        //         // 🚀 3. الرخصة تمام؟ احفظ الحالة دي في الكاش لمدة ساعة (60 دقيقة)
        //         Cache::put($cacheKey, true, now()->addHour());

        //         return $next($request);
        //     }

        //     return $this->licenseErrorResponse($deviceHash);

        // } catch (\Throwable $e) {
        //     return $this->handleOfflineGracePeriod($deviceHash, $next, $request);
        // }
    }

}