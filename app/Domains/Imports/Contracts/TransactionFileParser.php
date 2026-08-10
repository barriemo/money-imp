<?php

namespace App\Domains\Imports\Contracts;

use App\Domains\Imports\DTOs\ImportedTransaction;

interface TransactionFileParser
{
    public function supports(
        string $provider,
        string $extension
    ): bool;

    /**
     * @return iterable<ImportedTransaction>
     */
    public function parse(string $path): iterable;
}
