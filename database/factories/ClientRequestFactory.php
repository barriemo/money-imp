<?php

namespace Database\Factories;

use App\Models\ClientRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientRequestFactory extends Factory
{
    protected $model = ClientRequest::class;

    public function definition(): array
    {
        return [
            'client_name' => fake()->company(),

            'request' => fake()->sentence(),

            'source' => 'email',

            'status' => 'received',

            'classification' => null,
        ];
    }
}
