<?php

namespace App\Domains\Imports\Services;

use App\Domains\Imports\Detection\DocumentTypeDetector;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\Storage;

class PendingImportReclassifier
{
    public function __construct(
        private DocumentTypeDetector $detector
    ) {}

    public function run(): array
    {
        $summary = [];

        ImportBatch::query()
            ->where('status', 'pending_review')
            ->whereNotNull('storage_path')
            ->chunkById(
                100,
                function ($batches) use (&$summary): void {
                    foreach ($batches as $batch) {
                        if (
                            ! Storage::exists(
                                $batch->storage_path
                            )
                        ) {
                            continue;
                        }

                        $extension = strtolower(
                            pathinfo(
                                $batch->original_filename ?? '',
                                PATHINFO_EXTENSION
                            )
                        );

                        try {
                            $classification =
                                $this->detector->detect(
                                    Storage::path(
                                        $batch->storage_path
                                    ),
                                    $extension
                                );
                        } catch (\Throwable $exception) {
                            $classification = [
                                'type' => 'unknown',
                                'provider' => null,
                                'supplier' => null,
                                'confidence' => 0,
                            ];
                        }

                        $batch->update([
                            'source_type' => $classification['type'],

                            'provider' => $classification['provider'],

                            'metadata' => [
                                ...($batch->metadata ?? []),

                                'supplier' => $classification['supplier'],

                                'classification_confidence' => $classification['confidence']
                                    ?? 0,

                                'reclassified_at' => now()->toIso8601String(),
                            ],
                        ]);

                        $key =
                            $classification['type']
                            .' | '
                            .(
                                $classification['provider']
                                ?? $classification['supplier']
                                ?? 'unknown'
                            );

                        $summary[$key] =
                            ($summary[$key] ?? 0) + 1;
                    }
                }
            );

        arsort($summary);

        return $summary;
    }
}
