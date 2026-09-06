<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Condomínio', 'type' => 'bill', 'color' => '#6366f1'],
            ['name' => 'Água', 'type' => 'bill', 'color' => '#3b82f6'],
            ['name' => 'Energia', 'type' => 'bill', 'color' => '#f59e0b'],
            ['name' => 'Internet', 'type' => 'bill', 'color' => '#8b5cf6'],
            ['name' => 'Gás', 'type' => 'bill', 'color' => '#ef4444'],
            ['name' => 'Mobília', 'type' => 'expense', 'color' => '#14b8a6'],
            ['name' => 'Reformas', 'type' => 'expense', 'color' => '#f97316'],
            ['name' => 'Eletrodomésticos', 'type' => 'expense', 'color' => '#ec4899'],
            ['name' => 'Mercado', 'type' => 'expense', 'color' => '#10b981'],
            ['name' => 'Manutenção', 'type' => 'both', 'color' => '#64748b'],
            ['name' => 'Outros', 'type' => 'both', 'color' => '#94a3b8'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name' => $category['name']],
                ['type' => $category['type'], 'color' => $category['color']],
            );
        }
    }
}
