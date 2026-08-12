<?php

namespace App\Domains\Reasoning\Contracts;

use App\Domains\Reasoning\Answer;
use App\Domains\Reasoning\Question;

interface ReasoningStrategy
{
    public function supports(
        Question $question
    ): bool;

    public function answer(
        array $graph,
        Question $question
    ): Answer;
}
