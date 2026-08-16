<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\EvidenceCollector;
use App\Domains\BusinessBrain\Investigation\EvidenceItem;
use App\Domains\BusinessBrain\Investigation\Hypothesis;
use App\Domains\BusinessBrain\Investigation\HypothesisVerificationService;
use Tests\TestCase;

class HypothesisVerificationServiceTest extends TestCase
{
    public function test_supported_evidence_with_missing_source_is_plausible(): void
    {
        $hypothesis =
            new Hypothesis(
                statement: 'The invoices were paid into the old HSBC account.',
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'Peak Renewables'
            );

        $collector =
            new class implements EvidenceCollector
            {
                public function collect(
                    Hypothesis $hypothesis
                ): array {
                    return [
                        new EvidenceItem(
                            source: 'accounting',
                            description: 'Accounting records the relevant invoices as paid.',
                            position: 'supports',
                            confidence: 90
                        ),

                        new EvidenceItem(
                            source: 'bank',
                            description: 'Historic HSBC bank evidence is not currently available.',
                            position: 'missing',
                            confidence: 100
                        ),
                    ];
                }
            };

        $result =
            app(
                HypothesisVerificationService::class
            )->verify(
                $hypothesis,
                [
                    $collector,
                ]
            );

        $this->assertSame(
            'plausible',
            $result->status
        );

        $this->assertNotEmpty(
            $result->missingEvidence
        );
    }

    public function test_support_without_missing_or_contradiction_is_verified(): void
    {
        $hypothesis =
            new Hypothesis(
                statement: 'Payment was received.',
                subjectType: 'invoice',
                subjectId: 'invoice-1'
            );

        $collector =
            new class implements EvidenceCollector
            {
                public function collect(
                    Hypothesis $hypothesis
                ): array {
                    return [
                        new EvidenceItem(
                            source: 'bank',
                            description: 'Matching bank receipt found.',
                            position: 'supports',
                            confidence: 100
                        ),
                    ];
                }
            };

        $result =
            app(
                HypothesisVerificationService::class
            )->verify(
                $hypothesis,
                [
                    $collector,
                ]
            );

        $this->assertSame(
            'verified',
            $result->status
        );
    }

    public function test_contradictory_evidence_is_contradicted(): void
    {
        $hypothesis =
            new Hypothesis(
                statement: 'Payment was received.',
                subjectType: 'invoice',
                subjectId: 'invoice-1'
            );

        $collector =
            new class implements EvidenceCollector
            {
                public function collect(
                    Hypothesis $hypothesis
                ): array {
                    return [
                        new EvidenceItem(
                            source: 'bank',
                            description: 'No matching receipt exists in complete bank evidence.',
                            position: 'contradicts',
                            confidence: 95
                        ),
                    ];
                }
            };

        $result =
            app(
                HypothesisVerificationService::class
            )->verify(
                $hypothesis,
                [
                    $collector,
                ]
            );

        $this->assertSame(
            'contradicted',
            $result->status
        );
    }

    public function test_no_directional_evidence_is_unknown(): void
    {
        $hypothesis =
            new Hypothesis(
                statement: 'Something happened.',
                subjectType: 'client',
                subjectId: 'client-1'
            );

        $collector =
            new class implements EvidenceCollector
            {
                public function collect(
                    Hypothesis $hypothesis
                ): array {
                    return [
                        new EvidenceItem(
                            source: 'accounting',
                            description: 'Evidence is relevant but does not support either conclusion.',
                            position: 'neutral',
                            confidence: 80
                        ),
                    ];
                }
            };

        $result =
            app(
                HypothesisVerificationService::class
            )->verify(
                $hypothesis,
                [
                    $collector,
                ]
            );

        $this->assertSame(
            'unknown',
            $result->status
        );
    }
}
