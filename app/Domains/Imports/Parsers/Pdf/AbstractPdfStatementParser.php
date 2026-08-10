<?php

namespace App\Domains\Imports\Parsers\Pdf;

use Smalot\PdfParser\Parser;

abstract class AbstractPdfStatementParser
{
    protected function text(string $path): string
    {
        return (new Parser)
            ->parseFile($path)
            ->getText();
    }

    protected function lines(string $path): array
    {
        return array_values(
            array_filter(
                array_map(
                    'trim',
                    preg_split(
                        '/\R/',
                        $this->text($path)
                    ) ?: []
                )
            )
        );
    }
}
