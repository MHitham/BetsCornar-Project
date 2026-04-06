<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVaccineBatchRequest;
use App\Http\Requests\UpdateVaccineBatchRequest;
use App\Models\Product;
use App\Models\VaccineBatch;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
// تم الإضافة: استخدام الكاش لمسح تنبيهات صلاحية التشغيلات بعد تعديلها
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use RuntimeException;

class VaccineBatchController extends Controller
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    public function index(Request $request): View
    {
        $query = VaccineBatch::query()->with('product');

        $search = trim((string) $request->string('q'));
        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery->where('batch_code', 'like', '%' . $search . '%')
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        $productId = (int) $request->integer('product_id');
        if ($productId > 0) {
            $query->where('product_id', $productId);
        }

        $batches = $query
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('vaccine_batches.index', [
            'batches' => $batches,
            'vaccines' => Product::query()->selectable()->vaccinations()->get(['id', 'name']),
            'filters' => [
                'q' => $search,
                'product_id' => $productId,
            ],
        ]);
    }

    public function create(): View
    {
        return view('vaccine_batches.create', [
            'batch' => new VaccineBatch([
                'received_date' => now()->toDateString(),
                'expiry_date' => now()->addMonth()->toDateString(),
                'quantity_received' => 0,
                'quantity_remaining' => 0,
            ]),
            'vaccines' => Product::query()->selectable()->vaccinations()->get(['id', 'name']),
        ]);
    }

    public function store(StoreVaccineBatchRequest $request): RedirectResponse
    {
        try {
            $this->stockService->createVaccineBatch($request->validated());

            // تم الإضافة: تحديث كاش تنبيهات صلاحية التشغيلات بعد إضافة تشغيله جديدة
            Cache::forget(dashboardKey('batch_expiry'));

            return redirect()
                ->route('vaccine-batches.index')
                ->with('success', __('vaccine_batches.messages.created'));
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }
    }

    public function edit(VaccineBatch $vaccineBatch): View
    {
        $vaccines = Product::query()
            ->vaccinations()
            ->where(function ($query) use ($vaccineBatch) {
                $query->where('is_active', true)
                    ->orWhere('id', $vaccineBatch->product_id);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('vaccine_batches.edit', [
            'batch' => $vaccineBatch,
            'vaccines' => $vaccines,
        ]);
    }

    public function update(UpdateVaccineBatchRequest $request, VaccineBatch $vaccineBatch): RedirectResponse
    {
        try {
            $this->stockService->updateVaccineBatch($vaccineBatch, $request->validated());

            // تم الإضافة: تحديث كاش تنبيهات صلاحية التشغيلات بعد تعديل بيانات التشغيله
            Cache::forget(dashboardKey('batch_expiry'));

            return redirect()
                ->route('vaccine-batches.index')
                ->with('success', __('vaccine_batches.messages.updated'));
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }
    }

    public function destroy(VaccineBatch $vaccineBatch): RedirectResponse
    {
        try {
            $this->stockService->deleteVaccineBatch($vaccineBatch);

            return redirect()
                ->route('vaccine-batches.index')
                ->with('success', __('vaccine_batches.messages.deleted'));
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('vaccine-batches.index')
                ->with('error', __('vaccine_batches.messages.delete_referenced_error'));
        }
    }
}