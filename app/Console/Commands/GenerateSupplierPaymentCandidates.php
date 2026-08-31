<?php

namespace App\Console\Commands;

use App\Domains\Suppliers\Payments\Services\SupplierPaymentCandidateService;
use Illuminate\Console\Command;

class GenerateSupplierPaymentCandidates extends Command
{
    protected $signature = 'money-imp:supplier-payment-candidates';

    protected $description = 'Generate supplier payment reconciliation suggestions';

    public function handle(
        SupplierPaymentCandidateService $service
    ): int {
        $this->info('Generating supplier payment candidates...');

        $stats = $service->generate();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Considered', $stats['considered']],
                ['Supplier matches', $stats['supplier_matches']],
                ['Bill matches', $stats['bill_matches']],
                ['Ambiguous', $stats['ambiguous']],
                ['Unmatched', $stats['unmatched']],
            ]
        );

        return self::SUCCESS;
    }
}
