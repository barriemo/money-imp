<?php

namespace App\Domains\BusinessMemory\Enums;

enum BusinessMemoryInsightType: string
{
    case Opportunity = 'opportunity';
    case Risk = 'risk';
    case FollowUp = 'follow_up';
    case Recommendation = 'recommendation';
    case Question = 'question';
}
