<?php

namespace App\Domains\BusinessBrain\Client\Presenters;

class ClientAdvocacyPresenter
{
    public function present(
        mixed $data
    ): array {
        return [
            'data' => $data,
        ];
    }
}
