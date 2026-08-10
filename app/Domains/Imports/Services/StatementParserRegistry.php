<?php

namespace App\Domains\Imports\Services;

use App\Domains\Imports\Contracts\StatementParser;
use RuntimeException;

class StatementParserRegistry
{
    /**
     * @param  iterable<StatementParser>  $parsers
     */
    public function __construct(
        private readonly iterable $parsers
    ) {}

    public function for(string $provider): StatementParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->provider() === $provider) {
                return $parser;
            }
        }

        throw new RuntimeException(
            "No statement parser registered for {$provider}."
        );
    }
}
