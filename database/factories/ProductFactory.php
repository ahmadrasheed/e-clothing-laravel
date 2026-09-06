<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = ucwords($this->faker->words(3, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'description' => $this->faker->paragraph(),
            'base_price' => $this->faker->randomFloat(2, 15, 150),
            'image_url' => 'https://picsum.photos/seed/'.$this->faker->uuid().'/600/800',
        ];
    }
}
