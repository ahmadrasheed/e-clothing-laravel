<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();

        $sizes = ['S', 'M', 'L', 'XL'];
        $colors = ['Black', 'White', 'Navy'];

        $categories->each(function (Category $category) use ($sizes, $colors) {
            Product::factory()
                ->count(4)
                ->for($category)
                ->create()
                ->each(function (Product $product) use ($sizes, $colors) {
                    foreach (array_slice($sizes, 0, 3) as $i => $size) {
                        \App\Models\ProductVariant::factory()->create([
                            'product_id' => $product->id,
                            'size' => $size,
                            'color' => $colors[$i],
                        ]);
                    }
                });
        });
    }
}
