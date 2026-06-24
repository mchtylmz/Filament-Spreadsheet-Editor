<?php

namespace Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mivento\FilamentSpreadsheetEditor\Tests\Fixtures\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'sku' => fake()->unique()->bothify('SKU-###'),
            'name' => str($name)->headline()->toString(),
            'price' => fake()->randomFloat(2, 5, 500),
            'stock' => fake()->numberBetween(0, 250),
            'active' => fake()->boolean(85),
            'available_on' => fake()->dateTimeBetween('-1 month', '+3 months')->format('Y-m-d'),
            'category' => fake()->randomElement(['Furniture', 'Lighting', 'Office', 'Storage']),
            'internal_cost' => fake()->randomFloat(2, 2, 250),
        ];
    }
}
