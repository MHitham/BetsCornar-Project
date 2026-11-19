<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnlyAnimalDeletion
{
    public function handle(Request $request, Closure $next): Response
    {
        // حذف ملف الحيوان إجراء إداري ولا يسمح للموظفين بتنفيذه.
        abort_unless($request->user()?->hasRole('admin'), 403);

        return $next($request);
    }
}
