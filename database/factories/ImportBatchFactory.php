<?php

namespace Database\Factories;

use App\Models\ImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    public function definition(): array
    {
        return [
            'source_type' => 'transaction_file',
            'provider' => 'test',
            'original_filename' => fake()->uuid().'.csv',
            'file_hash' => hash(
                'sha256',
                fake()->uuid()
            ),
            'status' => 'completed',
            'rows_seen' => 0,
            'rows_imported' => 0,
            'rows_skipped' => 0,
            'rows_failed' => 0,
            'metadata' => [],
            'started_at' => now(),
            'finished_at' => now(),
        ];
    }
}
