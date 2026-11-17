<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PreventDuplicateSubmission
{
    public function handle(Request $request, Closure $next): Response
    {
        // رفض الطلبات التي لا تحمل رمز إرسال صالحًا.
        $token = $request->input('_submit_token');
        $userId = $request->user()?->getAuthIdentifier();

        if (! is_string($token) || trim($token) === '' || $userId === null) {
            return back()->with('error', 'انتهت صلاحية الصفحة، حدّث الصفحة وحاول مرة أخرى.');
        }

        $key = 'idem:'.$userId.':'.$token;

        // تسجيل الرمز ذريًا عبر مخزن Cache الافتراضي قبل تنفيذ العملية.
        if (! Cache::add($key, 'processing', 300)) {
            for ($attempt = 0; $attempt < 16; $attempt++) {
                usleep(500000);
                $cached = Cache::get($key);

                if (is_string($cached) && $cached !== 'processing') {
                    return redirect($cached)->with('info', 'تم حفظ الطلب بالفعل.');
                }
            }

            return back()->with('info', 'الطلب قيد التنفيذ، من فضلك لا تضغط مرة أخرى.');
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            // تحرير الرمز عند فشل العملية حتى يمكن تصحيح الطلب وإعادة المحاولة.
            Cache::forget($key);
            throw $exception;
        }

        // لا نحجز الرمز إذا كانت الاستجابة فشلًا أو لم تُرجع تحويلًا ناجحًا.
        $hasSessionError = $request->hasSession()
            && ($request->session()->has('errors') || $request->session()->has('error'));

        if ($response->getStatusCode() >= 400 || $hasSessionError || ! $response->isRedirection()) {
            Cache::forget($key);
        } else {
            Cache::put($key, $response->getTargetUrl(), 300);
        }

        return $response;
    }
}
