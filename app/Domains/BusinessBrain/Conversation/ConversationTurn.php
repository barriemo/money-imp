<?php

namespace App\Domains\BusinessBrain\Conversation;

class ConversationTurn
{
    public function __construct(
        public string $role,

        public string $message,

        public ?string $subjectType = null,

        public ?string $subjectId = null,

        public array $metadata = []
    ) {}
}
