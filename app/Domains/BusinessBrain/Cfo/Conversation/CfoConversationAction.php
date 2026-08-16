<?php

namespace App\Domains\BusinessBrain\Cfo\Conversation;

use App\Domains\BusinessBrain\Cfo\Briefing\CfoBriefService;
use App\Domains\BusinessBrain\Cfo\Conversation\Intent\CfoIntentResolver;
use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Responses\BusinessResponse;

class CfoConversationAction
{
    public function __construct(
        private CfoIntentResolver $intentResolver,

        private CfoBriefService $brief
    ) {}

    public function execute(
        string $question,
        ConversationContext $context
    ): ?BusinessResponse {
        $intent =
            $this->intentResolver
                ->resolve(
                    $question
                );

        if ($intent === null) {
            return null;
        }

        $brief =
            $this->brief
                ->current();

        return match ($intent) {
            'explain_uncertainty' => $this->uncertainty(
                $brief,
                $context
            ),

            'cash_position' => $this->cash(
                $brief,
                $context
            ),

            'biggest_risk' => $this->risk(
                $brief,
                $context
            ),

            'today_priority' => $this->priority(
                $brief,
                $context
            ),

            default => null,
        };
    }

    private function uncertainty(
        $brief,
        ConversationContext $context
    ): BusinessResponse {
        return new BusinessResponse(
            answer: "My current financial position is uncertain because I do not yet have enough verified evidence.\n\n"
                .implode(
                    "\n",
                    $brief->unknowns
                ),
            context: $context,
            questions: [
                'How much cash is actually safe to spend?',
                'What is the biggest financial risk?',
            ]
        );
    }

    private function cash(
        $brief,
        ConversationContext $context
    ): BusinessResponse {
        return new BusinessResponse(
            answer: "I cannot currently confirm safe available cash.\n\n"
                ."The highest-value evidence action is:\n"
                .(
                    $brief->bestNextVerification
                        ? '- '.$brief->bestNextVerification->subject
                        .' (£'
                        .number_format(
                            $brief->bestNextVerification->amount,
                            2
                        )
                        .')'
                        : '- No verification action identified.'
                ),
            context: $context,
            questions: [
                'Why are you uncertain?',
                'What should I focus on today?',
            ]
        );
    }

    private function risk(
        $brief,
        ConversationContext $context
    ): BusinessResponse {
        return new BusinessResponse(
            answer: "The biggest current financial risks are:\n\n"
                .implode(
                    "\n",
                    $brief->risks
                ),
            context: $context,
            questions: [
                'What should I focus on today?',
                'How do we improve confidence?',
            ]
        );
    }

    private function priority(
        $brief,
        ConversationContext $context
    ): BusinessResponse {
        return new BusinessResponse(
            answer: "Today's priorities:\n\n"
                .implode(
                    "\n",
                    $brief->priorities
                ),
            context: $context,
            questions: [
                'Why are we uncertain?',
                'What is our biggest risk?',
            ]
        );
    }
}
