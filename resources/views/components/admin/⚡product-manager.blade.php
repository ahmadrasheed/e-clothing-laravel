<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    public $showModal = false;

    public $editingId = null;

    public $name = '';

    public $category_id = '';

    public $description = '';

    public $base_price = '';

    public $image_url = '';

    /** @var array<int, array{id: ?int, size: string, color: string, stock_quantity: int}> */
    public array $variants = [];

    public string $search = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'image_url' => 'nullable|url',
            'variants' => 'array',
            'variants.*.size' => 'required|in:S,M,L,XL',
            'variants.*.color' => 'required|string|max:50',
            'variants.*.stock_quantity' => 'required|integer|min:0',
        ];
    }

    public function with(): array
    {
        return [
            'products' => Product::query()
                ->with(['category', 'variants'])
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->latest()
                ->get(),
            'categories' => Category::orderBy('name')->get(),
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->addVariant();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $product = Product::with('variants')->findOrFail($id);

        $this->editingId = $product->id;
        $this->name = $product->name;
        $this->category_id = $product->category_id;
        $this->description = $product->description;
        $this->base_price = $product->base_price;
        $this->image_url = $product->image_url;
        $this->variants = $product->variants->map(fn (ProductVariant $v) => [
            'id' => $v->id,
            'size' => $v->size,
            'color' => $v->color,
            'stock_quantity' => $v->stock_quantity,
        ])->toArray();

        $this->showModal = true;
    }

    public function addVariant(): void
    {
        $this->variants[] = ['id' => null, 'size' => 'S', 'color' => '', 'stock_quantity' => 0];
    }

    public function removeVariant(int $index): void
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants);
    }

    public function save(): void
    {
        $validated = $this->validate();

        $product = Product::updateOrCreate(
            ['id' => $this->editingId],
            [
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'base_price' => $validated['base_price'],
                'image_url' => $validated['image_url'],
            ]
        );

        $keepIds = [];

        foreach ($validated['variants'] as $variant) {
            $saved = $product->variants()->updateOrCreate(
                ['id' => $variant['id'] ?? null],
                [
                    'size' => $variant['size'],
                    'color' => $variant['color'],
                    'stock_quantity' => $variant['stock_quantity'],
                ]
            );
            $keepIds[] = $saved->id;
        }

        $product->variants()->whereNotIn('id', $keepIds)->delete();

        $this->showModal = false;
        $this->resetForm();

        session()->flash('message', 'Product saved successfully.');
    }

    public function delete(int $id): void
    {
        Product::findOrFail($id)->delete();
        session()->flash('message', 'Product deleted.');
    }

    public function updateStock(int $variantId, int $delta): void
    {
        $variant = ProductVariant::findOrFail($variantId);
        $variant->stock_quantity = max(0, $variant->stock_quantity + $delta);
        $variant->save();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'category_id', 'description', 'base_price', 'image_url', 'variants']);
        $this->resetValidation();
    }
};
?>

<div class="space-y-6">
    @if (session('message'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 transition duration-300">
            {{ session('message') }}
        </div>
    @endif

    <div class="flex items-center justify-between gap-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search products..."
            class="w-full max-w-xs rounded-2xl border border-gray-200 px-4 py-2 text-sm outline-none transition duration-300 focus:border-gray-900"
        >
        <button
            wire:click="create"
            class="shrink-0 rounded-2xl bg-gray-900 px-5 py-2 text-sm font-medium text-white transition duration-300 hover:bg-gray-700"
        >
            + Add Product
        </button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Product</th>
                    <th class="px-5 py-3">Category</th>
                    <th class="px-5 py-3">Price</th>
                    <th class="px-5 py-3">Variants / Stock</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($products as $product)
                    <tr class="transition duration-300 hover:bg-gray-50">
                        <td class="px-5 py-4 font-medium text-gray-900">{{ $product->name }}</td>
                        <td class="px-5 py-4 text-gray-500">{{ $product->category?->name }}</td>
                        <td class="px-5 py-4 text-gray-500">${{ number_format($product->base_price, 2) }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                @foreach ($product->variants as $variant)
                                    <span class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-2 py-1 text-xs">
                                        {{ $variant->size }} / {{ $variant->color }}
                                        <button wire:click="updateStock({{ $variant->id }}, -1)" class="text-gray-400 transition duration-300 hover:text-gray-900">-</button>
                                        <span class="font-semibold {{ $variant->stock_quantity === 0 ? 'text-red-500' : 'text-gray-700' }}">{{ $variant->stock_quantity }}</span>
                                        <button wire:click="updateStock({{ $variant->id }}, 1)" class="text-gray-400 transition duration-300 hover:text-gray-900">+</button>
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <button wire:click="edit({{ $product->id }})" class="text-sm font-medium text-gray-500 transition duration-300 hover:text-gray-900">Edit</button>
                            <button
                                wire:click="delete({{ $product->id }})"
                                wire:confirm="Delete this product?"
                                class="ml-3 text-sm font-medium text-red-500 transition duration-300 hover:text-red-700"
                            >Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-8 text-center text-gray-400">No products found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl transition duration-300">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">
                    {{ $editingId ? 'Edit Product' : 'New Product' }}
                </h2>

                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">Name</label>
                        <input type="text" wire:model="name" class="w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm outline-none transition duration-300 focus:border-gray-900">
                        @error('name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500">Category</label>
                            <select wire:model="category_id" class="w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm outline-none transition duration-300 focus:border-gray-900">
                                <option value="">Select...</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500">Base Price</label>
                            <input type="number" step="0.01" wire:model="base_price" class="w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm outline-none transition duration-300 focus:border-gray-900">
                            @error('base_price') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">Image URL</label>
                        <input type="text" wire:model="image_url" class="w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm outline-none transition duration-300 focus:border-gray-900">
                        @error('image_url') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500">Description</label>
                        <textarea wire:model="description" rows="3" class="w-full rounded-2xl border border-gray-200 px-4 py-2 text-sm outline-none transition duration-300 focus:border-gray-900"></textarea>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-xs font-medium text-gray-500">Variants</label>
                            <button type="button" wire:click="addVariant" class="text-xs font-medium text-gray-900 underline">+ Add variant</button>
                        </div>
                        <div class="space-y-2">
                            @foreach ($variants as $i => $variant)
                                <div class="grid grid-cols-12 items-center gap-2">
                                    <select wire:model="variants.{{ $i }}.size" class="col-span-3 rounded-xl border border-gray-200 px-2 py-1.5 text-sm">
                                        @foreach (['S', 'M', 'L', 'XL'] as $size)
                                            <option value="{{ $size }}">{{ $size }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" placeholder="Color" wire:model="variants.{{ $i }}.color" class="col-span-4 rounded-xl border border-gray-200 px-2 py-1.5 text-sm">
                                    <input type="number" placeholder="Stock" wire:model="variants.{{ $i }}.stock_quantity" class="col-span-3 rounded-xl border border-gray-200 px-2 py-1.5 text-sm">
                                    <button type="button" wire:click="removeVariant({{ $i }})" class="col-span-2 text-xs font-medium text-red-500">Remove</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="closeModal" class="rounded-2xl border border-gray-200 px-5 py-2 text-sm font-medium transition duration-300 hover:bg-gray-50">Cancel</button>
                    <button wire:click="save" class="rounded-2xl bg-gray-900 px-5 py-2 text-sm font-medium text-white transition duration-300 hover:bg-gray-700">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
