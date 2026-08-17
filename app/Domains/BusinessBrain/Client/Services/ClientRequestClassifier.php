<?php

namespace App\Domains\BusinessBrain\Client\Services;

use App\Models\ClientRequest;

class ClientRequestClassifier
{
    public function classify(ClientRequest $request): array
    {
        $text = strtolower($request->request);

        if ($this->containsAny($text, [
            'recommend',
            'introduce',
            'know someone',
            'referral',
        ])) {
            return [
                'classification' => 'referral_opportunity',
                'confidence' => 90,
                'reason' => 'Referral intent detected.',
            ];
        }

        if ($this->containsAny($text, [
            'new website',
            'new platform',
            'campaign',
            'strategy',
            'build',
        ])) {
            return [
                'classification' => 'project_opportunity',
                'confidence' => 85,
                'reason' => 'New project opportunity detected.',
            ];
        }

        if ($this->containsAny($text, [
            'update',
            'change',
            'fix',
            'content',
            'edit',
        ])) {
            return [
                'classification' => 'delivery_request',
                'confidence' => 80,
                'reason' => 'Existing delivery work detected.',
            ];
        }

        return [
            'classification' => 'unknown',
            'confidence' => 0,
            'reason' => 'No matching request pattern detected.',
        ];
    }

    protected function containsAny(
        string $text,
        array $terms
    ): bool {
        foreach ($terms as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }

        return false;
    }
}
