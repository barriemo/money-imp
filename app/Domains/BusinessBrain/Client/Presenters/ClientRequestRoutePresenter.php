<?php

namespace App\Domains\BusinessBrain\Client\Presenters;

class ClientRequestRoutePresenter
{
    public function present(
        mixed $data
    ): array {
        return [
            'data' => $data,
        ];
    }
}
