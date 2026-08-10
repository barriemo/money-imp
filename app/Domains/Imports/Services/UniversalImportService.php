<?php

namespace App\Domains\Imports\Services;

use App\Domains\Imports\Detection\DocumentTypeDetector;
use App\Models\ImportBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class UniversalImportService
{
    public function __construct(
        private DocumentTypeDetector $detector,
        private ArchiveExtractor $archives
    ) {}

    public function ingest(
        UploadedFile $file,
        ?int $userId = null
    ): array {
        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        if ($extension === 'zip') {
            return $this->ingestZip(
                $file,
                $userId
            );
        }

        $storedPath = $file->store(
            'imports/inbox'
        );

        return [
            $this->classifyStoredFile(
                Storage::path($storedPath),
                $file->getClientOriginalName(),
                $storedPath,
                $userId
            ),
        ];
    }

    private function ingestZip(
        UploadedFile $file,
        ?int $userId
    ): array {
        $archiveStoredPath = $file->store(
            'imports/archives'
        );

        $archivePath = Storage::path(
            $archiveStoredPath
        );

        $directory = storage_path(
            'app/private/imports/extracted/'
            .uniqid('', true)
        );

        $extracted = $this->archives->extract(
            $archivePath,
            $directory
        );

        $results = [];

        try {
            foreach ($extracted as $item) {
                $storedPath = 'imports/inbox/'
                    .uniqid('', true)
                    .'-'
                    .$item['original_filename'];

                Storage::put(
                    $storedPath,
                    file_get_contents(
                        $item['path']
                    )
                );

                $results[] =
                    $this->classifyStoredFile(
                        Storage::path(
                            $storedPath
                        ),
                        $item[
                            'original_filename'
                        ],
                        $storedPath,
                        $userId,
                        [
                            'archive_filename' => $file
                                ->getClientOriginalName(),
                        ]
                    );
            }
        } finally {
            File::deleteDirectory(
                $directory
            );
        }

        return $results;
    }

    private function classifyStoredFile(
        string $absolutePath,
        string $originalFilename,
        string $storedPath,
        ?int $userId,
        array $metadata = []
    ): array {
        $extension = strtolower(
            pathinfo(
                $originalFilename,
                PATHINFO_EXTENSION
            )
        );

        try {
            $classification =
                $this->detector->detect(
                    $absolutePath,
                    $extension
                );

            $status = 'pending_review';
        } catch (\Throwable $exception) {
            $classification = [
                'type' => 'unknown',
                'provider' => null,
                'supplier' => null,
            ];

            $status = 'failed';

            $metadata['error'] =
                $exception->getMessage();
        }

        $batch = ImportBatch::create([
            'source_type' => $classification['type'],

            'provider' => $classification['provider'],

            'original_filename' => $originalFilename,

            'storage_path' => $storedPath,

            'file_hash' => hash_file(
                'sha256',
                $absolutePath
            ),

            'status' => $status,

            'metadata' => [
                ...$metadata,

                'supplier' => $classification['supplier'],

                'extension' => $extension,
            ],

            'created_by' => $userId,
        ]);

        return [
            'batch_id' => $batch->id,
            'filename' => $originalFilename,
            'type' => $classification['type'],
            'provider' => $classification['provider'],
            'supplier' => $classification['supplier'],
            'status' => $status,
        ];
    }
}
