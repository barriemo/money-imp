<?php

namespace App\Domains\BusinessBrain\Responses;

use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Insights\BusinessInsight;

class BusinessResponse
{
    public function __construct(
        public string $answer,

        public ?BusinessInsight $insight = null,

        public ?ConversationContext $context = null,

        public array $evidence = [],

        public array $questions = [],

        public array $proposedActions = []
    ) {}
}
