<?php

namespace App\Domains\BusinessBrain\MorningBrief\Presenters;

use App\Domains\BusinessBrain\MorningBrief\MorningBrief;

class MorningBriefConsolePresenter
{
    public function present(
        MorningBrief $brief
    ): string {
        if ($brief->signals->isEmpty()) {
            return 'No signals requiring attention.';
        }

        $lines = [
            'MORNING BUSINESS BRIEF',
            '======================',
            '',
            'Priority Signals: '.$brief->signals->count(),
            '',
        ];

        foreach ($brief->signals as $signal) {
            $lines[] = strtoupper(
                str_replace(
                    '_',
                    ' ',
                    $signal->type
                )
            );

            $lines[] = str_repeat('-', 20);
            $lines[] = 'Value: £'.number_format(
                $signal->value
            );
            $lines[] = 'Reason: '.$signal->reason;
            $lines[] = '';
        }

        return implode(
            PHP_EOL,
            $lines
        );
    }
}
