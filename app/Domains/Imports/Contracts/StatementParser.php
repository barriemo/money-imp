<?php

namespace App\Domains\Imports\Contracts;

interface StatementParser
{
    public function provider(): string;

    public function parse(string $path): iterable;
}
