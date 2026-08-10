<?php

namespace App\Domains\Imports\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class ArchiveExtractor
{
    private const MAX_FILES = 100;

    private const MAX_EXTRACTED_BYTES = 100 * 1024 * 1024;

    private const ALLOWED_EXTENSIONS = [
        'pdf',
        'csv',
        'txt',
    ];

    public function extract(
        string $archivePath,
        string $destination
    ): array {
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException(
                'Money Imp could not open this ZIP archive.'
            );
        }

        File::ensureDirectoryExists($destination);

        $files = [];

        $extractedBytes = 0;

        try {
            if ($zip->numFiles > self::MAX_FILES) {
                throw new RuntimeException(
                    'ZIP contains too many files.'
                );
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if (! is_string($name)) {
                    continue;
                }

                $name = str_replace('\\', '/', $name);

                if (
                    str_ends_with($name, '/')
                    || str_contains($name, '__MACOSX/')
                    || basename($name) === '.DS_Store'
                ) {
                    continue;
                }

                /*
                 * Prevent ZIP slip.
                 */
                if (
                    str_contains($name, '../')
                    || str_starts_with($name, '/')
                ) {
                    continue;
                }

                $extension = strtolower(
                    pathinfo(
                        $name,
                        PATHINFO_EXTENSION
                    )
                );

                if (
                    ! in_array(
                        $extension,
                        self::ALLOWED_EXTENSIONS,
                        true
                    )
                ) {
                    continue;
                }

                $contents = $zip->getFromIndex(
                    $index
                );

                if ($contents === false) {
                    continue;
                }

                $extractedBytes += strlen(
                    $contents
                );

                if (
                    $extractedBytes
                    > self::MAX_EXTRACTED_BYTES
                ) {
                    throw new RuntimeException(
                        'ZIP expands beyond the allowed size.'
                    );
                }

                $filename = basename($name);

                $path = $destination
                    .DIRECTORY_SEPARATOR
                    .uniqid('', true)
                    .'-'
                    .$filename;

                file_put_contents(
                    $path,
                    $contents
                );

                $files[] = [
                    'path' => $path,
                    'original_filename' => $filename,
                    'extension' => $extension,
                ];
            }
        } finally {
            $zip->close();
        }

        return $files;
    }
}
