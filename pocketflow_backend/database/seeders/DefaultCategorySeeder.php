<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DefaultCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Makanan', 'type' => 'expense', 'icon' => '🍽️'],
            ['name' => 'Transportasi', 'type' => 'expense', 'icon' => '🚌'],
            ['name' => 'Belanja', 'type' => 'expense', 'icon' => '🛒'],
            ['name' => 'Tagihan', 'type' => 'expense', 'icon' => '🧾'],
            ['name' => 'Pendidikan', 'type' => 'expense', 'icon' => '🎓'],
            ['name' => 'Kesehatan', 'type' => 'expense', 'icon' => '🏥'],
            ['name' => 'Hiburan', 'type' => 'expense', 'icon' => '🎮'],
            ['name' => 'Lainnya', 'type' => 'expense', 'icon' => '📦'],
            ['name' => 'Gaji', 'type' => 'income', 'icon' => '💼'],
            ['name' => 'Bonus', 'type' => 'income', 'icon' => '🎁'],
            ['name' => 'Transfer Masuk', 'type' => 'income', 'icon' => '🏦'],
            ['name' => 'Freelance', 'type' => 'income', 'icon' => '💻'],
            ['name' => 'Uang Jajan', 'type' => 'income', 'icon' => '💰'],
            ['name' => 'Lainnya', 'type' => 'income', 'icon' => '✨'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                [
                    'user_id' => null,
                    'name' => $category['name'],
                    'type' => $category['type'],
                ],
                [
                    'icon' => $category['icon'],
                    'is_default' => true,
                ]
            );
        }
    }
}
