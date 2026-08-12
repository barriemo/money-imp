<?php

namespace App\Domains\CommercialTruth\Recovery;

use App\Models\WorkLog;

class WorkRecoveryReasoner
{
    public function assess(
        WorkLog $workLog
    ): WorkRecoveryAssessment {
        if (
            $workLog->commercial_value > 0
            &&
            $workLog->accounting_invoice_id === null
        ) {
            return new WorkRecoveryAssessment(
                state: 'recovery_required',
                value: (float) $workLog->commercial_value,
                confidence: 95,
                reason: 'Work created measurable commercial value but no invoice evidence exists.'
            );
        }

        return new WorkRecoveryAssessment(
            state: 'recovered',
            value: (float) $workLog->commercial_value,
            confidence: 95,
            reason: 'No unrecovered commercial value identified.'
        );
    }
}
