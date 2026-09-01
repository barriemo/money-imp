<?php

namespace App\Domains\Accounting\Contracts;

use Illuminate\Support\Collection;

interface ExpenseEvidenceProvider
{
    /**
     * @return Collection<int, object>
     */
    public function current(): Collection;
}
