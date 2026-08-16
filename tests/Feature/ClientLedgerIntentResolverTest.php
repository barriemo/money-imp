<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Conversation\Intent\ClientLedgerIntentResolver;
use Tests\TestCase;

class ClientLedgerIntentResolverTest extends TestCase
{
    public function test_invoice_follow_up_resolves_to_invoice_evidence(): void
    {
        $intent =
            app(
                ClientLedgerIntentResolver::class
            )->resolve(
                'Show me the invoices'
            );

        $this->assertSame(
            'show_invoices',
            $intent
        );
    }

    public function test_bank_follow_up_resolves_to_bank_receipts(): void
    {
        $intent =
            app(
                ClientLedgerIntentResolver::class
            )->resolve(
                'Show me the bank receipts'
            );

        $this->assertSame(
            'show_bank_receipts',
            $intent
        );
    }

    public function test_assertion_can_be_sent_for_evidence_verification(): void
    {
        $intent =
            app(
                ClientLedgerIntentResolver::class
            )->resolve(
                'Test that against the evidence'
            );

        $this->assertSame(
            'verify_assertion',
            $intent
        );
    }

    public function test_missing_evidence_follow_up_is_resolved(): void
    {
        $this->assertSame(
            'show_missing_evidence',
            app(
                ClientLedgerIntentResolver::class
            )->resolve(
                'What evidence is still missing?'
            )
        );
    }

    public function test_supporting_evidence_follow_up_is_resolved(): void
    {
        $this->assertSame(
            'show_supporting_evidence',
            app(
                ClientLedgerIntentResolver::class
            )->resolve(
                'Show me what supports my assertion'
            )
        );
    }

    public function test_contradicting_evidence_follow_up_is_resolved(): void
    {
        $this->assertSame(
            'show_contradicting_evidence',
            app(
                ClientLedgerIntentResolver::class
            )->resolve(
                'Show me what contradicts my assertion'
            )
        );
    }
}
