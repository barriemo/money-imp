<?php

namespace Tests\Feature;

use App\Domains\Imports\Services\ArchiveExtractor;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class UniversalImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_zip_can_be_expanded_and_statement_detected(): void
    {
        $user = User::factory()->create();

        $zipPath = tempnam(
            sys_get_temp_dir(),
            'money-imp-'
        );

        $zip = new ZipArchive;

        $this->assertTrue(
            $zip->open(
                $zipPath,
                ZipArchive::CREATE
                | ZipArchive::OVERWRITE
            )
        );

        $zip->addFromString(
            'bank/starling.csv',
            implode("\n", [
                'Date,Counter Party,Reference,Type,Amount (GBP),Balance (GBP)',
                '10/08/2026,Test Client,ABC123,FASTER PAYMENT,100.00,1000.00',
            ])
        );

        $zip->addFromString(
            '__MACOSX/.DS_Store',
            'junk'
        );

        $zip->close();

        $file = new UploadedFile(
            $zipPath,
            'bank-download.zip',
            'application/zip',
            null,
            true
        );

        $response = $this
            ->actingAs($user)
            ->post(
                route('imports.drop'),
                [
                    'files' => [$file],
                ]
            );

        $response->assertRedirect(
            route('imports.index')
        );

        $this->assertDatabaseCount(
            'import_batches',
            1
        );

        $batch = ImportBatch::firstOrFail();

        $this->assertSame(
            'statement',
            $batch->source_type
        );

        $this->assertSame(
            'starling_csv',
            $batch->provider
        );

        $this->assertSame(
            'starling.csv',
            $batch->original_filename
        );

        $this->assertSame(
            'pending_review',
            $batch->status
        );

        $this->assertSame(
            'bank-download.zip',
            $batch->metadata[
                'archive_filename'
            ]
        );

        @unlink($zipPath);
    }

    public function test_unsupported_file_inside_zip_is_ignored(): void
    {
        $zipPath = tempnam(
            sys_get_temp_dir(),
            'money-imp-'
        );

        $zip = new ZipArchive;

        $zip->open(
            $zipPath,
            ZipArchive::CREATE
            | ZipArchive::OVERWRITE
        );

        $zip->addFromString(
            'virus.exe',
            'not really an executable'
        );

        $zip->addFromString(
            '.DS_Store',
            'junk'
        );

        $zip->close();

        $extractor = app(
            ArchiveExtractor::class
        );

        $destination = storage_path(
            'framework/testing/'
            .uniqid('zip-', true)
        );

        $files = $extractor->extract(
            $zipPath,
            $destination
        );

        $this->assertSame(
            [],
            $files
        );

        @unlink($zipPath);

        if (is_dir($destination)) {
            File::deleteDirectory(
                $destination
            );
        }
    }
}
