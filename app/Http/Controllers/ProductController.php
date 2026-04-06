<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
// تم الإضافة: استخدام الكاش لمسح عداد المنتجات في لوحة التحكم بعد التعديلات
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const TYPES = ['product', 'service', 'vaccination'];

    public function __construct(private readonly StockService $stockService) {}

    public function index(Request $request): View
    {
        $query = Product::query();

        if ($search = trim((string) $request->string('q'))) {
            $query->where('name', '=', '%'.$search.'%');
        }

        $type = $request->string('type')->toString();
        if (in_array($type, self::TYPES, true)) {
            $query->where('type', $type);
        }

        $status = $request->string('status')->toString();
        if ($status === 'active') {
            $query->where('is_active', true);
        }
        if ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $products = $query
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'filters' => [
                'q' => $search,
                'type' => $type,
                'status' => $status,
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->string('q'));

        $products = Product::query()
            ->active()
            ->where('type', '!=', 'vaccination')
            ->when($q, fn($query) => $query->where('name', 'like', '%' . $q . '%'))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'price', 'stock_status', 'quantity', 'track_stock']);

        return response()->json($products);
    }

    public function create(): View
    {
        return view('products.create', [
            'product' => new Product([
                'type' => 'product',
                'price' => 0,
                'quantity' => 0,
                'track_stock' => true,
                'stock_status' => 'available',
                'low_stock_threshold' => 0,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = Product::create($this->prepareProductData($request->validated()));

        if ($product->type === 'vaccination') {
            $this->stockService->recalculateVaccineStock($product);
        }

        // تم الإضافة: تحديث كاش إجمالي المنتجات بعد إنشاء منتج جديد
        Cache::forget('dashboard.total_products');

        return redirect()
            ->route('products.index')
            ->with('success', __('products.messages.created'));
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($this->prepareProductData($request->validated()));

        if ($product->type === 'vaccination') {
            $this->stockService->recalculateVaccineStock($product->fresh());
        }

        // تم الإضافة: تحديث كاش إجمالي المنتجات بعد تعديل بيانات منتج قائم
        Cache::forget('dashboard.total_products');

        return redirect()
            ->route('products.index')
            ->with('success', __('products.messages.updated'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->isReferenced()) {
            $product->update(['is_active' => false]);

            return redirect()
                ->route('products.index')
                ->with('warning', __('products.messages.deactivated_instead_of_delete'));
        }

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', __('products.messages.deleted'));
    }

    public function toggleActive(Product $product): RedirectResponse
    {
        $product->update([
            'is_active' => ! $product->is_active,
        ]);

        // تم الإضافة: تحديث كاش إجمالي المنتجات بعد تغيير حالة التفعيل
        Cache::forget('dashboard.total_products');

        return redirect()
            ->route('products.index')
            ->with('success', __('products.messages.toggled'));
    }

    private function prepareProductData(array $data): array
    {
        $type = $data['type'];

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        if ($type === 'service') {
            $data['track_stock'] = false;
            $data['quantity'] = 0;
            $data['low_stock_threshold'] = 0;
            $data['stock_status'] = 'available';

            return $data;
        }

        if ($type === 'vaccination') {
            // Authoritative vaccine stock is always derived from vaccine batches.
            $data['track_stock'] = true;
            $data['quantity'] = 0;
            $data['stock_status'] = 'out_of_stock';

            return $data;
        }

        $data['track_stock'] = true;
        $quantity = (float) ($data['quantity'] ?? 0);
        $threshold = (float) ($data['low_stock_threshold'] ?? 0);
        $data['stock_status'] = $this->resolveStockStatus($quantity, $threshold);

        return $data;
    }

    private function resolveStockStatus(float $quantity, float $threshold): string
    {
        if ($quantity <= 0) {
            return 'out_of_stock';
        }

        if ($quantity <= $threshold) {
            return 'low';
        }

        return 'available';
    }
}
