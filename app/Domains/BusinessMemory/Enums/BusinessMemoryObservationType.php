<?php

namespace App\Domains\BusinessMemory\Enums;

enum BusinessMemoryObservationType: string
{
    case Fact = 'fact';
    case Risk = 'risk';
    case Opportunity = 'opportunity';
    case Promise = 'promise';
    case Question = 'question';
    case Decision = 'decision';
    case Requirement = 'requirement';
    case Concern = 'concern';
}
