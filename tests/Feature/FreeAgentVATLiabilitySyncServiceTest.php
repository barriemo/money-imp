<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\RevenueTruth\FreeAgentVATLiabilitySyncService;
use App\Domains\FinancialTruth\Services\FinancialTruthService;
use App\Models\ExternalConnection;
use App\Models\Liability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FreeAgentVATLiabilitySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_freeagent_vat_is_reported_evidence_not_verified_liability(): void
    {
        $connection = $this->connection();

        Http::fake([
            '*' => Http::response([
                'vat_returns' => [
                    [
                        'url' => 'https://api.freeagent.com/v2/vat_returns/2026-07-31',
                        'period_starts_on' => '2026-05-01',
                        'period_ends_on' => '2026-07-31',
                        'filing_due_on' => '2026-09-07',
                        'filing_status' => 'unfiled',
                        'payments' => [
                            [
                                'label' => 'Payment Due',
                                'amount_due' => '5777.67',
                                'due_on' => '2026-09-07',
                                'status' => 'unpaid',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = app(
            FreeAgentVATLiabilitySyncService::class
        )->sync($connection);

        $this->assertSame(1, $result['returns']);
        $this->assertSame(1, $result['payments_seen']);
        $this->assertSame(1, $result['open']);

        $liability = Liability::query()
            ->where('type', 'vat')
            ->firstOrFail();

        $this->assertSame(
            '5777.67',
            (string) $liability->amount
        );

        $this->assertSame('open', $liability->status);
        $this->assertFalse($liability->verified);
        $this->assertSame(90, $liability->confidence);

        $this->assertTrue(
            (bool) $liability->metadata[
                'reported_by_freeagent'
            ]
        );

        $this->assertFalse(
            (bool) $liability->metadata[
                'settlement_verified'
            ]
        );

        $this->assertSame(
            'unfiled',
            $liability->metadata['filing_status']
        );

        /*
         * Reported accounting evidence must not become
         * verified Financial Truth by itself.
         */
        $truth = app(
            FinancialTruthService::class
        )->build();

        $this->assertSame(
            0.0,
            $truth['liabilities']['vat']
        );

        $this->assertSame(
            0.0,
            $truth['liabilities']['total']
        );

        $this->assertSame(
            0,
            $truth['confidence']['liabilities']
        );

        $this->assertSame(
            1,
            $truth['liabilities']['coverage'][
                'record_count'
            ]
        );

        $this->assertSame(
            0,
            $truth['liabilities']['coverage'][
                'verified_record_count'
            ]
        );
    }

    public function test_sync_downgrades_legacy_freeagent_false_verification(): void
    {
        $connection = $this->connection();

        Liability::create([
            'type' => 'vat',
            'name' => 'FreeAgent VAT 2025-10-31',
            'amount' => 3562.81,
            'due_date' => '2025-12-07',
            'status' => 'open',
            'source' => 'freeagent_vat_return',
            'verified' => true,
            'confidence' => 100,
            'notes' => 'Payment Due',
            'metadata' => [
                'period_ends_on' => '2025-10-31',
                'payment_status' => 'unpaid',
            ],
        ]);

        Http::fake([
            '*' => Http::response([
                'vat_returns' => [
                    [
                        'url' => 'https://api.freeagent.com/v2/vat_returns/2025-10-31',
                        'period_starts_on' => '2025-08-01',
                        'period_ends_on' => '2025-10-31',
                        'filing_status' => 'unfiled',
                        'payments' => [
                            [
                                'label' => 'Payment Due',
                                'amount_due' => '3562.81',
                                'due_on' => '2025-12-07',
                                'status' => 'unpaid',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        app(
            FreeAgentVATLiabilitySyncService::class
        )->sync($connection);

        $liability = Liability::query()
            ->where(
                'name',
                'FreeAgent VAT 2025-10-31'
            )
            ->firstOrFail();

        $this->assertFalse($liability->verified);
        $this->assertSame(90, $liability->confidence);

        $this->assertFalse(
            (bool) $liability->metadata[
                'settlement_verified'
            ]
        );
    }

    private function connection(): ExternalConnection
    {
        return ExternalConnection::create([
            'name' => 'FreeAgent',
            'provider' => 'freeagent',
            'status' => 'connected',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
        ]);
    }
}
