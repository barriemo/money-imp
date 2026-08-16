<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Actions\GetPaymentTruthAction;
use App\Domains\BusinessBrain\Questions\BusinessQuestionRegistry;
use Tests\TestCase;

class BusinessQuestionRegistryTest extends TestCase
{
    public function test_customer_payment_questions_resolve_to_payment_truth(): void
    {
        $action =
            app(
                BusinessQuestionRegistry::class
            )->resolve(
                'How are customer payments looking?'
            );

        $this->assertSame(
            GetPaymentTruthAction::class,
            $action
        );
    }

    public function test_unknown_questions_are_not_resolved(): void
    {
        $action =
            app(
                BusinessQuestionRegistry::class
            )->resolve(
                'What colour is the office wall?'
            );

        $this->assertNull(
            $action
        );
    }
}
