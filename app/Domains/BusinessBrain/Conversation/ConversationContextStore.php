<?php

namespace App\Domains\BusinessBrain\Conversation;

use Illuminate\Support\Facades\Storage;

class ConversationContextStore
{
    private const PATH =
        'business-brain/conversation-context.json';

    public function current(): ConversationContext
    {
        if (
            ! Storage::disk('local')
                ->exists(self::PATH)
        ) {
            return new ConversationContext;
        }

        $data =
            json_decode(
                Storage::disk('local')
                    ->get(self::PATH),
                true
            );

        if (! is_array($data)) {
            return new ConversationContext;
        }

        return ConversationContext::fromArray(
            $data
        );
    }

    public function save(
        ConversationContext $context
    ): void {
        Storage::disk('local')
            ->put(
                self::PATH,
                json_encode(
                    $context->toArray(),
                    JSON_PRETTY_PRINT
                    | JSON_THROW_ON_ERROR
                )
            );
    }

    public function forget(): void
    {
        Storage::disk('local')
            ->delete(
                self::PATH
            );
    }
}
