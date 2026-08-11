<?php

namespace App\Domains\BusinessMemory\Enums;

enum BusinessContextType: string
{
    case StrategicGoal = 'strategic_goal';
    case GrowthPlan = 'growth_plan';

    case DecisionMaker = 'decision_maker';
    case Stakeholder = 'stakeholder';

    case Budget = 'budget';
    case PricingSensitivity = 'pricing_sensitivity';
    case RiskAppetite = 'risk_appetite';

    case PreferredCommunication = 'preferred_communication';

    case KnownFrustration = 'known_frustration';
    case KnownBlocker = 'known_blocker';

    case CurrentSupplier = 'current_supplier';
    case Competitor = 'competitor';

    case CommercialPreference = 'commercial_preference';
    case ServiceExpectation = 'service_expectation';

    case DoNotRecommend = 'do_not_recommend';

    case Background = 'background';
}
