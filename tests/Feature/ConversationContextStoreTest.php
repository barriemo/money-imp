<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Conversation\ConversationContextStore;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConversationContextStoreTest extends TestCase
{
    public function test_conversation_context_survives_between_requests(): void
    {
        Storage::fake('local');

        $store =
            app(
                ConversationContextStore::class
            );

        $store->save(
            new ConversationContext(
                subjectType: 'client',
                subjectId: 'walker',
                subjectName: 'Walker The Jeweller',
                issue: 'customer_payment_truth'
            )
        );

        $context =
            $store->current();

        $this->assertSame(
            'Walker The Jeweller',
            $context->subjectName
        );

        $this->assertSame(
            'customer_payment_truth',
            $context->issue
        );
    }
}
