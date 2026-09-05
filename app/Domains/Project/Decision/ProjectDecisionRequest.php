<?php

namespace App\Domains\Project\Decision;

use InvalidArgumentException;

class ProjectDecisionRequest
{
    public function __construct(
        public string $key,
        public string $question,
        public int $projectId,
        public array $parameters = [],
    ) {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException(
                'Project decision request key cannot be empty.'
            );
        }

        if (trim($this->question) === '') {
            throw new InvalidArgumentException(
                'Project decision request question cannot be empty.'
            );
        }

        if ($this->projectId <= 0) {
            throw new InvalidArgumentException(
                'Project decision request project id must be positive.'
            );
        }

        foreach ($this->parameters as $name => $value) {
            if (
                ! is_string($name)
                || trim($name) === ''
            ) {
                throw new InvalidArgumentException(
                    'Project decision request parameter names must be non-empty strings.'
                );
            }

            if (
                $value !== null
                && ! is_scalar($value)
            ) {
                throw new InvalidArgumentException(
                    'Project decision request parameters must contain only scalar or null values.'
                );
            }

            if (
                is_float($value)
                && ! is_finite($value)
            ) {
                throw new InvalidArgumentException(
                    'Project decision request numeric parameters must be finite.'
                );
            }
        }
    }
}
