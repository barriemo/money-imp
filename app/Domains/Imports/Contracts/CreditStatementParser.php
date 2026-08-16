<?php

namespace App\Domains\Imports\Contracts;

use App\Domains\Imports\DTOs\CreditStatementSummary;

interface CreditStatementParser
{
    public function statementSummary(
        string $path
    ): CreditStatementSummary;
}
