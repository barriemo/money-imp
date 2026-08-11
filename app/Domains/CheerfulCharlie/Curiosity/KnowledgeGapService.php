<?php

namespace App\Domains\CheerfulCharlie\Curiosity;

use App\Domains\BusinessMemory\Enums\BusinessContextType;
use App\Models\BusinessContext;
use App\Models\BusinessMemory;
use Illuminate\Support\Collection;

class KnowledgeGapService
{
    public function gaps(
        BusinessMemory $memory
    ): Collection {
        $contexts = $memory
            ->hasMany(
                BusinessContext::class
            )
            ->get();

        $known = $contexts
            ->map(
                fn ($context) => $context->context_type->value
                    .'|'
                    .$context->key
            )
            ->all();

        $definitions = collect([
            [
                'type' => BusinessContextType::DecisionMaker,
                'key' => 'primary_decision_maker',
                'question' => 'Who is the main decision maker?',
                'reason' => 'Knowing who makes buying decisions improves commercial recommendations.',
                'priority' => 90,
            ],
            [
                'type' => BusinessContextType::CurrentSupplier,
                'key' => 'internet_provider',
                'question' => 'Who provides their internet connection?',
                'reason' => 'Connectivity ownership affects operational risk and service opportunity.',
                'priority' => 70,
            ],
            [
                'type' => BusinessContextType::CurrentSupplier,
                'key' => 'backup_provider',
                'question' => 'Who currently looks after their backups?',
                'reason' => 'Backup ownership affects service completeness and operational risk.',
                'priority' => 95,
            ],
            [
                'type' => BusinessContextType::CurrentSupplier,
                'key' => 'telephony_provider',
                'question' => 'Who provides their phone system?',
                'reason' => 'Telephony may expose service gaps or commercial opportunities.',
                'priority' => 55,
            ],
            [
                'type' => BusinessContextType::Background,
                'key' => 'mfa_status',
                'question' => 'Do they use MFA across their main accounts?',
                'reason' => 'MFA materially affects security risk.',
                'priority' => 85,
            ],
        ]);

        return $definitions
            ->reject(
                fn (array $gap) => in_array(
                    $gap['type']->value
                    .'|'
                    .$gap['key'],
                    $known,
                    true
                )
            )
            ->sortByDesc('priority')
            ->values();
    }
}
