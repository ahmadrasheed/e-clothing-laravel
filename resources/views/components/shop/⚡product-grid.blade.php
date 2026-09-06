<?php

use App\Models\Category;
use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public array $selectedCategories = [];

    public array $selectedSizes = [];

    public array $selectedColors = [];

    public function updated($property): void
    {
        if (in_array($property, ['selectedCategories', 'selectedSizes', 'selectedColors'], true) || str_starts_with($property, 'selected')) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['selectedCategories', 'selectedSizes', 'selectedColors']);
        $this->resetPage();
    }

    public function with(): array
    {
        $products = Product::query()
            ->with(['category', 'variants'])
            ->when($this->selectedCategories, fn ($q) => $q->whereIn('category_id', $this->selectedCategories))
            ->when($this->selectedSizes, function ($q) {
                $q->whereHas('variants', fn ($v) => $v->whereIn('size', $this->selectedSizes));
            })
            ->when($this->selectedColors, function ($q) {
                $q->whereHas('variants', fn ($v) => $v->whereIn('color', $this->selectedColors));
            })
            ->latest()
            ->paginate(12);

        return [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'sizes' => ['S', 'M', 'L', 'XL'],
            'colors' => \App\Models\ProductVariant::query()->select('color')->distinct()->orderBy('color')->pluck('color'),
        ];
    }
};
?>

<div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-6 py-10 lg:grid-cols-[240px_1fr]">
    <aside class="space-y-8">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-900">Filters</h2>
            <button wire:click="clearFilters" class="text-xs font-medium text-gray-400 underline transition duration-300 hover:text-gray-900">Clear</button>
        </div>

        <div>
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Category</h3>
            <div class="space-y-2">
                @foreach ($categories as $category)
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" wire:model.live="selectedCategories" value="{{ $category->id }}" class="rounded border-gray-300">
                        {{ $category->name }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Size</h3>
            <div class="flex flex-wrap gap-2">
                @foreach ($sizes as $size)
                    <label class="cursor-pointer">
                        <input type="checkbox" wire:model.live="selectedSizes" value="{{ $size }}" class="peer sr-only">
                        <span class="inline-block rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 transition duration-300 peer-checked:border-gray-900 peer-checked:bg-gray-900 peer-checked:text-white">
                            {{ $size }}
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Color</h3>
            <div class="space-y-2">
                @foreach ($colors as $color)
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" wire:model.live="selectedColors" value="{{ $color }}" class="rounded border-gray-300">
                        {{ $color }}
                    </label>
                @endforeach
            </div>
        </div>
    </aside>

    <section>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold tracking-tight">Shop</h1>
            <span class="text-sm text-gray-500">{{ $products->total() }} items</span>
        </div>

        <div class="grid grid-cols-2 gap-6 sm:grid-cols-3 xl:grid-cols-4">
            @forelse ($products as $product)
                <div class="group overflow-hidden rounded-2xl border border-gray-100 bg-white transition duration-300 hover:shadow-lg">
                    <div class="aspect-[3/4] overflow-hidden bg-gray-100">
                        @if ($product->image_url)
                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                        @endif
                    </div>
                    <div class="p-4">
                        <p class="text-xs uppercase tracking-wide text-gray-400">{{ $product->category?->name }}</p>
                        <h3 class="mt-1 truncate text-sm font-medium text-gray-900">{{ $product->name }}</h3>
                        <p class="mt-1 text-sm font-semibold text-gray-900">${{ number_format($product->base_price, 2) }}</p>

                        <div class="mt-3 flex flex-wrap gap-1">
                            @foreach ($product->variants->pluck('size')->unique() as $size)
                                <span class="rounded-lg border border-gray-200 px-2 py-0.5 text-[11px] text-gray-500">{{ $size }}</span>
                            @endforeach
                        </div>

                        <div class="mt-3 flex gap-1">
                            @foreach ($product->variants->pluck('color')->unique() as $color)
                                <span
                                    class="h-4 w-4 rounded-full border border-gray-200"
                                    style="background-color: {{ strtolower($color) }};"
                                    title="{{ $color }}"
                                ></span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <p class="col-span-full py-16 text-center text-gray-400">No products match these filters.</p>
            @endforelse
        </div>

        <div class="mt-8">
            {{ $products->links() }}
        </div>
    </section>
</div>
