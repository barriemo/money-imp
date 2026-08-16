<?php

namespace Database\Factories;

use App\Models\ClientRequest;
use App\Models\ClientRequestClassification;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientRequestClassificationFactory extends Factory
{
    protected $model = ClientRequestClassification::class;

    public function definition(): array
    {
        return [
            'client_request_id' => ClientRequest::factory(),

            'classification' => 'delivery_request',

            'confidence' => 80,

            'reason' => 'Existing delivery work detected.',
        ];
    }
}
