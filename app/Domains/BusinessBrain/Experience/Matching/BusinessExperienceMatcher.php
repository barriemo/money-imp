<?php

namespace App\Domains\BusinessBrain\Experience\Matching;

use App\Models\BusinessExperience;
use App\Models\InvestigationCase;
use Illuminate\Support\Collection;

class BusinessExperienceMatcher
{
    /**
     * @return Collection<int, ExperienceMatch>
     */
    public function forInvestigation(
        InvestigationCase $case,
        int $limit = 5
    ): Collection {
        return BusinessExperience::query()
            ->where(
                'source_investigation_case_id',
                '!=',
                $case->id
            )
            ->get()
            ->map(
                fn (BusinessExperience $experience) => $this->score(
                    $case,
                    $experience
                )
            )
            ->filter(
                fn (ExperienceMatch $match) => $match->score > 0
            )
            ->sortByDesc(
                fn (ExperienceMatch $match) => $match->score
            )
            ->take(
                $limit
            )
            ->values();
    }

    private function score(
        InvestigationCase $case,
        BusinessExperience $experience
    ): ExperienceMatch {
        $score = 0;
        $reasons = [];

        if ($experience->type === $case->type) {
            $score += 30;

            $reasons[] =
                'Same investigation type.';
        }

        if (
            $experience->subject_type !== null
            && $experience->subject_type
                === $case->subject_type
        ) {
            $score += 10;

            $reasons[] =
                'Same subject type.';
        }

        $hypothesisSimilarity =
            $this->textSimilarity(
                $case->current_hypothesis,
                $experience->hypothesis
            );

        if ($hypothesisSimilarity >= 0.60) {
            $points =
                (int) round(
                    35 * $hypothesisSimilarity
                );

            $score += $points;

            $reasons[] =
                sprintf(
                    'Similar hypothesis language (%d%%).',
                    (int) round(
                        $hypothesisSimilarity * 100
                    )
                );
        }

        $caseTerms =
            $this->terms(
                implode(
                    ' ',
                    array_filter([
                        $case->title,
                        $case->question,
                        $case->current_hypothesis,
                        $case->verdict,
                    ])
                )
            );

        $experienceTerms =
            $this->terms(
                implode(
                    ' ',
                    array_filter([
                        $experience->title,
                        $experience->summary,
                        $experience->outcome,
                        $experience->hypothesis,
                        json_encode(
                            $experience->lessons
                            ?? []
                        ),
                    ])
                )
            );

        $overlap =
            $caseTerms
                ->intersect(
                    $experienceTerms
                )
                ->unique()
                ->values();

        if ($overlap->isNotEmpty()) {
            $points =
                min(
                    20,
                    $overlap->count() * 4
                );

            $score += $points;

            $reasons[] =
                sprintf(
                    'Shared concepts: %s.',
                    $overlap
                        ->take(5)
                        ->implode(', ')
                );
        }

        $score =
            min(
                100,
                $score
            );

        return new ExperienceMatch(
            experience: $experience,
            score: $score,
            reasons: $reasons
        );
    }

    private function textSimilarity(
        ?string $left,
        ?string $right
    ): float {
        if (
            blank($left)
            || blank($right)
        ) {
            return 0;
        }

        $leftTerms =
            $this->terms(
                $left
            );

        $rightTerms =
            $this->terms(
                $right
            );

        if (
            $leftTerms->isEmpty()
            || $rightTerms->isEmpty()
        ) {
            return 0;
        }

        $intersection =
            $leftTerms
                ->intersect(
                    $rightTerms
                )
                ->unique()
                ->count();

        $union =
            $leftTerms
                ->merge(
                    $rightTerms
                )
                ->unique()
                ->count();

        if ($union === 0) {
            return 0;
        }

        return $intersection / $union;
    }

    private function terms(
        ?string $text
    ): Collection {
        if (blank($text)) {
            return collect();
        }

        $stopWords = [
            'the',
            'a',
            'an',
            'and',
            'or',
            'to',
            'of',
            'in',
            'on',
            'for',
            'with',
            'our',
            'this',
            'that',
            'those',
            'were',
            'was',
            'is',
            'are',
            'into',
            'from',
        ];

        return collect(
            preg_split(
                '/[^a-z0-9]+/',
                strtolower(
                    $text
                )
            )
        )
            ->filter(
                fn ($term) => $term !== ''
                    && strlen($term) >= 3
                    && ! in_array(
                        $term,
                        $stopWords,
                        true
                    )
            )
            ->unique()
            ->values();
    }
}
