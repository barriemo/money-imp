<?php

namespace App\Domains\BusinessBrain\Client\Services;

use App\Models\ClientRequest;
use App\Models\ClientRequestClassification;

class ClientRequestClassificationService
{
    public function __construct(
        protected ClientRequestClassifier $classifier
    ) {}

    public function classify(
        ClientRequest $request
    ): ClientRequestClassification {
        $result = $this->classifier->classify(
            $request
        );

        return $request->classifications()->create([
            'classification' => $result['classification'],

            'confidence' => $result['confidence'],

            'reason' => $result['reason'],
        ]);
    }
}
