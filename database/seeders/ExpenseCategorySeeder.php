<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Advertising',
            'Bank Charges',
            'Domains',
            'Entertainment',
            'Freelancers',
            'Hardware',
            'Hosting',
            'Insurance',
            'Office',
            'Payroll',
            'Professional Fees',
            'Software',
            'Subscriptions',
            'Tax',
            'Travel',
            'Utilities',
            'Other',
        ];

        foreach ($categories as $index => $name) {
            ExpenseCategory::updateOrCreate(
                [
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => $name,
                    'active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
