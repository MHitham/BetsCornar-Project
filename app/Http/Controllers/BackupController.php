<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class BackupController extends Controller
{
    // حقن الخدمة عبر الـ constructor
    public function __construct(private readonly BackupService $backupService)
    {
    }

    /**
     * عرض صفحة النسخ الاحتياطية مع قائمة النسخ المحفوظة.
     */
    public function index(): View
    {
        $backups = $this->backupService->list();

        return view('backup.index', compact('backups'));
    }

    /**
     * إنشاء نسخة احتياطية جديدة الآن.
     */
    public function store(): RedirectResponse
    {
        try {
            $this->backupService->create();

            return redirect()->back()->with('success', 'تم إنشاء النسخة الاحتياطية بنجاح');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'فشل إنشاء النسخة الاحتياطية: ' . $e->getMessage());
        }
    }

    /**
     * حذف نسخة احتياطية محددة بعد التحقق من صحة اسم الملف.
     */
    public function destroy(string $filename): RedirectResponse
    {
        // التحقق من صيغة اسم الملف لمنع أي حذف خارج المسموح به
        if (! preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}\.sqlite$/', $filename)) {
            return redirect()->back()->with('error', 'اسم الملف غير صحيح');
        }

        try {
            $this->backupService->delete($filename);

            return redirect()->back()->with('success', 'تم حذف النسخة الاحتياطية بنجاح');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'فشل حذف النسخة الاحتياطية: ' . $e->getMessage());
        }
    }

    /**
     * فتح مجلد النسخ الاحتياطية عبر Shell الخاص بـ NativePHP.
     */
    public function openFolder(): JsonResponse
    {
        // تم تعطيل هذه الميزة في نسخة الويب
        return back()->with('info', 'هذه الميزة غير متاحة في نسخة الويب.');
    }

    /**
     * استعادة نسخة احتياطية محددة واستبدال قاعدة البيانات الحالية بها.
     */
    public function restoreBackup(string $filename): RedirectResponse
    {
        // التحقق من صيغة اسم الملف لمنع أي وصول خارج المسموح به
        if (! preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}\.sqlite$/', $filename)) {
            return redirect()->back()->with('error', 'اسم الملف غير صحيح');
        }

        try {
            $this->backupService->restore($filename);

            return redirect()->route('backup.index')
                ->with('success', 'تمت استعادة النسخة الاحتياطية بنجاح. يُرجى إعادة تشغيل التطبيق.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'فشل استعادة النسخة الاحتياطية: ' . $e->getMessage());
        }
    }
}
