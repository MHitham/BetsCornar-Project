<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backupService) {}

    public function index(): View
    {
        $backups = $this->backupService->list();

        return view('backup.index', compact('backups'));
    }

    public function store(): RedirectResponse
    {
        try {
            $this->backupService->create();

            return redirect()->back()->with('success', 'تم إنشاء النسخة الاحتياطية بنجاح');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'فشل إنشاء النسخة الاحتياطية: '.$e->getMessage());
        }
    }

    public function destroy(string $filename): RedirectResponse
    {

        if (! preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}\.sql$/', $filename)) {
            return redirect()->back()->with('error', 'اسم الملف غير صحيح');
        }

        try {
            $this->backupService->delete($filename);

            return redirect()->back()->with('success', 'تم حذف النسخة الاحتياطية بنجاح');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'فشل حذف النسخة الاحتياطية: '.$e->getMessage());
        }
    }

    public function openFolder(): RedirectResponse
    {
        return redirect()->back()->with('info', 'مسار النسخ الاحتياطية: '.config('backup.backup_path'));
    }

    public function restoreBackup(string $filename): RedirectResponse
    {

        if (! preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}\.sql$/', $filename)) {
            return redirect()->back()->with('error', 'اسم الملف غير صحيح');
        }

        try {
            $this->backupService->restore($filename);

            return redirect()->route('backup.index')
                ->with('success', 'تمت استعادة النسخة الاحتياطية بنجاح. يُرجى إعادة تشغيل التطبيق.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'فشل استعادة النسخة الاحتياطية: '.$e->getMessage());
        }
    }
}
