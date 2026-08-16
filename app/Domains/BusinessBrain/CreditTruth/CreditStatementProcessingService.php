<?php

namespace App\Domains\BusinessBrain\CreditTruth;

use App\Domains\Imports\Contracts\CreditStatementParser;
use App\Domains\Imports\Services\StatementParserRegistry;
use App\Models\CreditStatementEvidence;
use App\Models\ImportBatch;
use RuntimeException;

class CreditStatementProcessingService
{
    public function __construct(
        private StatementParserRegistry $parsers,

        private CreditStatementImportService $imports
    ) {}

    public function supports(
        string $provider
    ): bool {
        try {
            return $this->parsers
                ->for(
                    $provider
                ) instanceof CreditStatementParser;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function process(
        ImportBatch $batch,
        string $path
    ): CreditStatementEvidence {
        $parser =
            $this->parsers
                ->for(
                    $batch->provider
                );

        if (! $parser instanceof CreditStatementParser) {
            throw new RuntimeException(
                sprintf(
                    '%s does not provide credit statement evidence.',
                    $batch->provider
                )
            );
        }

        $summary =
            $parser->statementSummary(
                $path
            );

        return $this->imports
            ->import(
                batch: $batch,

                statement: [
                    ...$summary->toEvidenceArray(),

                    'verified' => true,
                ]
            );
    }
}
