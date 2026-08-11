<?php

namespace App\Domains\CheerfulCharlie\Review;

use Illuminate\Support\Str;

class CharlieFindingFingerprint
{
    public function make(
        array $finding
    ): string {
        return hash(
            'sha256',
            implode('|', [
                $this->normalise(
                    $finding['category']
                    ?? ''
                ),

                $this->normalise(
                    $finding['title']
                    ?? ''
                ),

                $this->normalise(
                    $finding['description']
                    ?? ''
                ),
            ])
        );
    }

    private function normalise(
        string $value
    ): string {
        return Str::of($value)
            ->lower()
            ->squish()
            ->trim()
            ->toString();
    }
}
