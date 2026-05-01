<?php

namespace Database\Factories;

use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShoppingListItemFactory extends Factory
{
    protected $model = ShoppingListItem::class;

    public function definition(): array
    {
        return [
            'shopping_list_id' => ShoppingList::factory(),
            'name' => $this->faker->word(),
            'quantity' => $this->faker->numberBetween(1, 10),
            'unit' => $this->faker->randomElement(['piece', 'kg', 'l', 'ml']),
            'barcode' => null,
            'nutrition' => null,
            'checked' => false,
        ];
    }
}
