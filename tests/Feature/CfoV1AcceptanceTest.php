<?php

namespace Tests\Feature;

use App\Domains\Cfo\Decision\CfoDecision;
use App\Domains\Cfo\Decision\CfoDecisionContext;
use App\Domains\Cfo\Decision\CfoDecisionPolicy;
use App\Domains\Cfo\Decision\CfoDecisionRequest;
use App\Domains\Cfo\Decision\CfoDecisionService;
use App\Domains\Cfo\Decision\DiscretionarySpendDecisionPolicy;
use Illuminate\Support\Facades\Artisan;
use ReflectionClass;
use Tests\Support\CfoV1AcceptanceCatalog;
use Tests\TestCase;

class CfoV1AcceptanceTest extends TestCase
{
    public function test_cfo_v1_acceptance_contains_exactly_seven_unique_financial_decision_questions(): void
    {
        $questions =
            collect(
                CfoV1AcceptanceCatalog::questions()
            );

        $this->assertCount(
            7,
            $questions
        );

        $this->assertSame(
            [
                'CFO01',
                'CFO02',
                'CFO03',
                'CFO04',
                'CFO05',
                'CFO06',
                'CFO07',
            ],
            $questions
                ->pluck(
                    'id'
                )
                ->all()
        );

        $this->assertSame(
            7,
            $questions
                ->pluck(
                    'id'
                )
                ->unique()
                ->count()
        );

        $this->assertSame(
            7,
            $questions
                ->pluck(
                    'question'
                )
                ->unique()
                ->count()
        );
    }

    public function test_every_accepted_question_maps_only_to_the_authoritative_v1_policy(): void
    {
        $questions =
            collect(
                CfoV1AcceptanceCatalog::questions()
            );

        $this->assertSame(
            [
                DiscretionarySpendDecisionPolicy::KEY,
            ],
            $questions
                ->pluck(
                    'policy'
                )
                ->unique()
                ->values()
                ->all()
        );
    }

    public function test_every_accepted_question_is_backed_by_real_public_cfo_decision_contracts(): void
    {
        foreach (
            CfoV1AcceptanceCatalog::questions() as $question
        ) {
            $this->assertNotEmpty(
                $question[
                    'contracts'
                ]
            );

            foreach (
                $question[
                    'contracts'
                ] as $contract
            ) {
                $reflection =
                    new ReflectionClass(
                        $contract[
                            'class'
                        ]
                    );

                $this->assertTrue(
                    $reflection->hasProperty(
                        $contract[
                            'property'
                        ]
                    )
                );

                $this->assertTrue(
                    $reflection
                        ->getProperty(
                            $contract[
                                'property'
                            ]
                        )
                        ->isPublic()
                );
            }
        }
    }

    public function test_cfo_v1_accepts_established_guidance_and_deferral_but_does_not_claim_conditional_policy_support(): void
    {
        $this->assertSame(
            [
                CfoDecision::RECOMMENDED,
                CfoDecision::DEFERRED,
            ],
            CfoV1AcceptanceCatalog::acceptedStatuses()
        );

        /*
         * CONDITIONAL remains a valid 6.1 decision-contract state.
         *
         * CFO v1 simply does not yet contain an authoritative policy
         * that produces it.
         */
        $this->assertContains(
            CfoDecision::CONDITIONAL,
            CfoDecision::STATUSES
        );

        $policySource =
            file_get_contents(
                app_path(
                    'Domains/Cfo/Decision/DiscretionarySpendDecisionPolicy.php'
                )
            );

        $this->assertIsString(
            $policySource
        );

        $this->assertStringNotContainsString(
            'CfoDecision::CONDITIONAL',
            $policySource
        );
    }

    public function test_cfo_v1_surface_is_registered(): void
    {
        $exit =
            Artisan::call(
                'list'
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exit
        );

        $this->assertStringContainsString(
            'cfo:decide-spend',
            $output
        );
    }

    public function test_cfo_v1_boundary_explicitly_stops_before_priority_execution_forecasting_and_other_decision_types(): void
    {
        $this->assertSame(
            [
                'Which CFO recommendation should we prioritise next?',
                'Execute this recommendation.',
                'What will our cash position be next month?',
                'Can we hire another employee?',
                'Should we take additional credit?',
            ],
            CfoV1AcceptanceCatalog::boundaryQuestions()
        );

        foreach (
            [
                CfoDecision::class,
                CfoDecisionRequest::class,
                CfoDecisionContext::class,
            ] as $class
        ) {
            $reflection =
                new ReflectionClass(
                    $class
                );

            foreach (
                [
                    'priority',
                    'score',
                    'urgency',
                    'execution',
                    'executedAt',
                ] as $forbidden
            ) {
                $this->assertFalse(
                    $reflection->hasProperty(
                        $forbidden
                    )
                );
            }
        }
    }

    public function test_cfo_v1_has_one_policy_contract_and_one_authoritative_service_surface(): void
    {
        $policy =
            new ReflectionClass(
                DiscretionarySpendDecisionPolicy::class
            );

        $this->assertTrue(
            $policy->implementsInterface(
                CfoDecisionPolicy::class
            )
        );

        $service =
            new ReflectionClass(
                CfoDecisionService::class
            );

        $this->assertTrue(
            $service->hasMethod(
                'decide'
            )
        );

        $method =
            $service->getMethod(
                'decide'
            );

        $this->assertTrue(
            $method->isPublic()
        );

        $this->assertSame(
            CfoDecision::class,
            (string) $method
                ->getReturnType()
        );
    }

    public function test_acceptance_catalog_contains_no_priority_execution_or_empty_contract_metadata(): void
    {
        foreach (
            CfoV1AcceptanceCatalog::questions() as $question
        ) {
            $this->assertNotSame(
                '',
                trim(
                    $question[
                        'question'
                    ]
                )
            );

            $this->assertNotSame(
                '',
                trim(
                    $question[
                        'answer_shape'
                    ]
                )
            );

            $this->assertArrayNotHasKey(
                'priority',
                $question
            );

            $this->assertArrayNotHasKey(
                'score',
                $question
            );

            $this->assertArrayNotHasKey(
                'urgency',
                $question
            );

            $this->assertArrayNotHasKey(
                'execution',
                $question
            );

            $this->assertArrayNotHasKey(
                'action',
                $question
            );

            foreach (
                $question[
                    'contracts'
                ] as $contract
            ) {
                $this->assertNotSame(
                    '',
                    trim(
                        $contract[
                            'class'
                        ]
                    )
                );

                $this->assertNotSame(
                    '',
                    trim(
                        $contract[
                            'property'
                        ]
                    )
                );
            }
        }
    }
}
