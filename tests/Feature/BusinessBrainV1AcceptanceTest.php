<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessQuestion;
use App\Domains\BusinessBrain\BusinessState\BusinessStateProjection;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeReport;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanation;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationReport;
use Illuminate\Support\Facades\Artisan;
use ReflectionClass;
use Tests\Support\BusinessBrainV1AcceptanceCatalog;
use Tests\TestCase;

class BusinessBrainV1AcceptanceTest extends TestCase
{
    public function test_canonical_acceptance_set_contains_exactly_28_unique_executive_questions(): void
    {
        $questions =
            collect(
                BusinessBrainV1AcceptanceCatalog::questions()
            );

        $this->assertCount(
            28,
            $questions
        );

        $this->assertSame(
            array_map(
                fn (int $number): string => sprintf(
                    'Q%02d',
                    $number
                ),
                range(
                    1,
                    28
                )
            ),
            $questions
                ->pluck(
                    'id'
                )
                ->all()
        );

        $this->assertSame(
            28,
            $questions
                ->pluck(
                    'id'
                )
                ->unique()
                ->count()
        );

        $this->assertSame(
            28,
            $questions
                ->pluck(
                    'question'
                )
                ->unique()
                ->count()
        );

        $this->assertTrue(
            $questions
                ->every(
                    fn (array $question): bool => trim(
                        $question['question']
                    ) !== ''
                )
        );
    }

    public function test_every_question_maps_to_exactly_one_existing_business_brain_layer(): void
    {
        $questions =
            collect(
                BusinessBrainV1AcceptanceCatalog::questions()
            );

        $this->assertTrue(
            $questions
                ->every(
                    fn (array $question): bool => in_array(
                        $question['layer'],
                        BusinessBrainV1AcceptanceCatalog::LAYERS,
                        true
                    )
                )
        );

        $this->assertSame(
            [
                BusinessBrainV1AcceptanceCatalog::STATE => 22,

                BusinessBrainV1AcceptanceCatalog::CHANGE => 2,

                BusinessBrainV1AcceptanceCatalog::ATTENTION => 1,

                BusinessBrainV1AcceptanceCatalog::EXPLANATION => 3,
            ],
            $questions
                ->countBy(
                    'layer'
                )
                ->all()
        );
    }

    public function test_every_accepted_question_is_backed_by_a_real_public_production_contract(): void
    {
        foreach (
            BusinessBrainV1AcceptanceCatalog::questions() as $question
        ) {
            $this->assertNotEmpty(
                $question['contracts'],
                $question['id']
                    .' has no authoritative contract.'
            );

            foreach (
                $question['contracts'] as $contract
            ) {
                $reflection =
                    new ReflectionClass(
                        $contract['class']
                    );

                $this->assertTrue(
                    $reflection->hasProperty(
                        $contract['property']
                    ),
                    sprintf(
                        '%s expects missing property %s::%s.',
                        $question['id'],
                        $contract['class'],
                        $contract['property']
                    )
                );

                $property =
                    $reflection->getProperty(
                        $contract['property']
                    );

                $this->assertTrue(
                    $property->isPublic(),
                    sprintf(
                        '%s requires %s::%s to remain an explicit public truth contract.',
                        $question['id'],
                        $contract['class'],
                        $contract['property']
                    )
                );
            }
        }
    }

    public function test_acceptance_questions_cover_every_temporal_business_state_metric(): void
    {
        $covered =
            collect(
                BusinessBrainV1AcceptanceCatalog::questions()
            )
                ->flatMap(
                    fn (array $question): array => $question['metrics']
                )
                ->unique()
                ->sort()
                ->values()
                ->all();

        $expected =
            BusinessStateMetricCatalog::ALL;

        sort(
            $expected
        );

        $this->assertSame(
            $expected,
            $covered
        );

        $this->assertCount(
            19,
            $covered
        );
    }

    public function test_change_attention_and_explanation_questions_are_backed_by_5_2_and_5_3_contracts(): void
    {
        $questions =
            collect(
                BusinessBrainV1AcceptanceCatalog::questions()
            )
                ->keyBy(
                    'id'
                );

        $this->assertSame(
            BusinessBrainV1AcceptanceCatalog::CHANGE,
            $questions['Q23']['layer']
        );

        $this->assertSame(
            BusinessStateChangeReport::class,
            $questions['Q23']['contracts'][0]['class']
        );

        $this->assertSame(
            'changes',
            $questions['Q23']['contracts'][0]['property']
        );

        $this->assertSame(
            BusinessStateChange::BECAME_KNOWN,
            'became_known'
        );

        $this->assertSame(
            BusinessStateChange::BECAME_UNKNOWN,
            'became_unknown'
        );

        $this->assertSame(
            BusinessBrainV1AcceptanceCatalog::ATTENTION,
            $questions['Q25']['layer']
        );

        $this->assertSame(
            'attention',
            $questions['Q25']['contracts'][0]['property']
        );

        $this->assertSame(
            BusinessBrainV1AcceptanceCatalog::EXPLANATION,
            $questions['Q26']['layer']
        );

        $this->assertSame(
            BusinessStateExplanationReport::class,
            $questions['Q26']['contracts'][0]['class']
        );

        $this->assertSame(
            BusinessStateExplanation::ESTABLISHED,
            'established'
        );

        $this->assertSame(
            BusinessStateExplanation::PARTIAL,
            'partial'
        );

        $this->assertSame(
            BusinessStateExplanation::UNESTABLISHED,
            'unestablished'
        );

        $this->assertSame(
            'missingTruth',
            $questions['Q27']['contracts'][0]['property']
        );

        $this->assertSame(
            'confidence',
            $questions['Q28']['contracts'][0]['property']
        );
    }

    public function test_state_acceptance_includes_unknowns_evidence_gaps_and_executive_conditions(): void
    {
        $questions =
            collect(
                BusinessBrainV1AcceptanceCatalog::questions()
            )
                ->keyBy(
                    'id'
                );

        $this->assertSame(
            BusinessStateProjection::class,
            $questions['Q16']['contracts'][0]['class']
        );

        $this->assertSame(
            'commercialConditions',
            $questions['Q16']['contracts'][0]['property']
        );

        $this->assertSame(
            'unknowns',
            $questions['Q21']['contracts'][0]['property']
        );

        $this->assertSame(
            'evidenceGaps',
            $questions['Q22']['contracts'][0]['property']
        );
    }

    public function test_v1_acceptance_does_not_use_legacy_priority_driven_business_questions(): void
    {
        foreach (
            BusinessBrainV1AcceptanceCatalog::questions() as $question
        ) {
            foreach (
                $question['contracts'] as $contract
            ) {
                $this->assertNotSame(
                    BusinessQuestion::class,
                    $contract['class']
                );
            }

            $this->assertArrayNotHasKey(
                'priority',
                $question
            );

            $this->assertArrayNotHasKey(
                'recommendation',
                $question
            );

            $this->assertArrayNotHasKey(
                'action',
                $question
            );

            $this->assertArrayNotHasKey(
                'decision',
                $question
            );
        }
    }

    public function test_business_brain_v1_explicitly_stops_before_recommendation_and_action_questions(): void
    {
        $this->assertSame(
            [
                'What should we do next?',
                'Which action should we prioritise?',
            ],
            BusinessBrainV1AcceptanceCatalog::decisionBoundaryQuestions()
        );

        $forbiddenProperties = [
            'priority',
            'score',
            'urgency',
            'recommendation',
            'action',
            'decision',
        ];

        foreach (
            [
                BusinessStateProjection::class,
                BusinessStateChangeReport::class,
                BusinessStateExplanationReport::class,
                BusinessStateExplanation::class,
            ] as $class
        ) {
            $reflection =
                new ReflectionClass(
                    $class
                );

            foreach (
                $forbiddenProperties as $property
            ) {
                $this->assertFalse(
                    $reflection->hasProperty(
                        $property
                    ),
                    sprintf(
                        '%s unexpectedly exposes downstream field %s.',
                        $class,
                        $property
                    )
                );
            }
        }
    }

    public function test_three_business_brain_v1_read_surfaces_are_registered(): void
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

        foreach (
            [
                'business:state',
                'business:changes',
                'business:explain',
            ] as $command
        ) {
            $this->assertStringContainsString(
                $command,
                $output
            );
        }
    }

    public function test_acceptance_catalog_contains_no_empty_answer_shape_or_contract_metadata(): void
    {
        foreach (
            BusinessBrainV1AcceptanceCatalog::questions() as $question
        ) {
            $this->assertNotSame(
                '',
                trim(
                    $question['answer_shape']
                )
            );

            foreach (
                $question['contracts'] as $contract
            ) {
                $this->assertNotSame(
                    '',
                    trim(
                        $contract['class']
                    )
                );

                $this->assertNotSame(
                    '',
                    trim(
                        $contract['property']
                    )
                );
            }
        }
    }
}
