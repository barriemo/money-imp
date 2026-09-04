<?php

namespace App\Domains\BusinessBrain\Attention\Change;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use InvalidArgumentException;

class BusinessStateChangeAttention
{
    public const TRUTH_LOST =
        'truth_lost';

    public const FINANCIAL_POSITION_REDUCED =
        'financial_position_reduced';

    public const FINANCIAL_EXPOSURE_INCREASED =
        'financial_exposure_increased';

    public const COMMERCIAL_CONDITION_EXPANDED =
        'commercial_condition_expanded';

    public const RECORDED_WORK_EXPOSURE_INCREASED =
        'recorded_work_exposure_increased';

    public const EVIDENCE_COVERAGE_REDUCED =
        'evidence_coverage_reduced';

    public const TYPES = [
        self::TRUTH_LOST,
        self::FINANCIAL_POSITION_REDUCED,
        self::FINANCIAL_EXPOSURE_INCREASED,
        self::COMMERCIAL_CONDITION_EXPANDED,
        self::RECORDED_WORK_EXPOSURE_INCREASED,
        self::EVIDENCE_COVERAGE_REDUCED,
    ];

    public function __construct(
        public BusinessStateChange $change,
        public string $type,
        public string $reason,
    ) {
        if (
            ! in_array(
                $this->type,
                self::TYPES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported business state change attention type.'
            );
        }

        if (trim($this->reason) === '') {
            throw new InvalidArgumentException(
                'Business state change attention requires a reason.'
            );
        }
    }
}
