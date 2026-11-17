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

        // تسجيل الرمز ذريًا — بدون أي انتظار: إذا فشل add فالرمز مستخدم بالفعل.
        if (! Cache::add($key, 'processing', 300)) {
            $cached = Cache::get($key);

            // إذا كان الرمز يحمل URL سابق → أعد توجيه المستخدم إليه.
            if (is_string($cached) && $cached !== 'processing' && $cached !== 'done') {
                return redirect($cached)->with('info', 'تم حفظ الطلب بالفعل.');
            }

            // إذا كان الرمز "done" (استجابة JSON ناجحة سابقة) → 409 JSON أو back.
            if ($cached === 'done') {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'الطلب قيد التنفيذ'], 409);
                }

                return back()->with('info', 'الطلب قيد التنفيذ، من فضلك لا تضغط مرة أخرى.');
            }

            // الرمز لا يزال في حالة "processing" → رد فوري بدون أي انتظار.
            if ($request->expectsJson()) {
                return response()->json(['message' => 'الطلب قيد التنفيذ'], 409);
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

        // لا نحجز الرمز إذا كانت الاستجابة فشلًا أو تحتوي على أخطاء.
        $hasSessionError = $request->hasSession()
            && ($request->session()->has('errors') || $request->session()->has('error'));

        if ($response->getStatusCode() >= 400 || $hasSessionError) {
            // فشل — نحرر الرمز حتى يستطيع المستخدم إعادة المحاولة.
            Cache::forget($key);
        } elseif ($response->isRedirection()) {
            // نجاح مع redirect — نخزن الـ URL المستهدف لإعادة توجيه الطلبات المكررة.
            Cache::put($key, $response->getTargetUrl(), 300);
        } else {
            // نجاح بدون redirect (JSON أو عرض مباشر) — نضع علامة "done" لمنع التكرار.
            Cache::put($key, 'done', 300);
        }

        return $response;
    }
}
