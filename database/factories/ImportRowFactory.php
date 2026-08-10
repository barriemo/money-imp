<?php

namespace Database\Factories;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRow>
 */
class ImportRowFactory extends Factory
{
    protected $model = ImportRow::class;

    public function definition(): array
    {
        $description = fake()->company();

        return [
            'import_batch_id' => ImportBatch::factory(),
            'row_number' => fake()->numberBetween(1, 1000),
            'transaction_date' => fake()->date(),
            'amount' => -fake()->randomFloat(2, 1, 500),
            'currency' => 'GBP',
            'description' => $description,
            'merchant' => $description,
            'reference' => fake()->optional()->bothify('REF-####'),
            'row_hash' => hash(
                'sha256',
                fake()->uuid()
            ),
            'status' => 'imported',
            'classification_status' => 'unclassified',
            'raw_payload' => [],
        ];
    }
}
