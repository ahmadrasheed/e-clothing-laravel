<x-layouts.app title="Admin · Inventory">
    <div class="mx-auto max-w-6xl px-6 py-10">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Inventory</h1>
                <p class="text-sm text-gray-500">Manage products, variants, and stock levels.</p>
            </div>
            <a href="{{ route('shop.index') }}" class="text-sm font-medium text-gray-500 underline transition duration-300 hover:text-gray-900">View storefront →</a>
        </div>

        <livewire:admin.product-manager />
    </div>
</x-layouts.app>
