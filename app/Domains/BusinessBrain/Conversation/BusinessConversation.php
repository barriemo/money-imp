<?php

namespace App\Domains\BusinessBrain\Conversation;

use Illuminate\Support\Collection;

class BusinessConversation
{
    /**
     * @param  Collection<int, ConversationTurn>|null  $turns
     */
    public function __construct(
        public ConversationContext $context,

        public ?Collection $turns = null
    ) {
        $this->turns ??= collect();
    }

    public function add(
        ConversationTurn $turn
    ): void {
        $this->turns->push(
            $turn
        );
    }

    public function lastTurn(): ?ConversationTurn
    {
        return $this->turns
            ->last();
    }
}
