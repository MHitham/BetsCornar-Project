{{-- استخدم البيانات المحمّلة مسبقًا واعرض خمسة أسماء مختلفة بحد أقصى. --}}
@php
    $itemNames = $invoice->items->pluck('product.name')->filter(fn ($name) => filled($name))->uniqueStrict();
    $remainingNames = $itemNames->count() - 5;
@endphp
<div class="small" data-invoice-item-names>
    <div>الأصناف</div>
    <div class="d-flex flex-wrap gap-1">
        @forelse ($itemNames->take(5) as $name)
            <span class="badge bg-light text-dark border">{{ $name }}</span>
        @empty
            <span>—</span>
        @endforelse
        @if ($remainingNames > 0)
            <span class="badge bg-light text-dark border"><bdi dir="ltr">+{{ $remainingNames }}</bdi> أخرى</span>
        @endif
    </div>
</div>
