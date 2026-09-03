<?php

namespace App\Domains\BusinessBrain\Signals;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Domains\BusinessMemory\Extraction\BusinessMemoryExtractionService;
use App\Models\BusinessMemory;
use App\Models\BusinessMemoryEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CeoSignalCaptureService
{
    public const INBOX_TITLE =
        'CEO Signal Inbox';

    public function __construct(
        private readonly AddBusinessMemoryEntry $entries,
        private readonly BusinessMemoryExtractionService $extraction,
        private readonly InvestigationCaseService $investigations,

        private readonly CeoSignalRoutingService $routing,
    ) {}

    public function capture(
        User $submittedBy,
        string $rawInput
    ): BusinessMemoryEntry {
        $content =
            trim(
                $rawInput
            );

        if ($content === '') {
            throw new InvalidArgumentException(
                'CEO signal cannot be empty.'
            );
        }

        return DB::transaction(
            function () use (
                $submittedBy,
                $content
            ): BusinessMemoryEntry {
                /*
                 * Business-wide inbox.
                 *
                 * Do not force the authenticated user into the
                 * UUID polymorphic subject columns. Human identity
                 * is retained as source metadata instead.
                 */
                $memory =
                    BusinessMemory::query()
                        ->firstOrCreate(
                            [
                                'subject_type' => null,

                                'subject_id' => null,

                                'title' => self::INBOX_TITLE,
                            ],
                            [
                                'status' => 'active',

                                'metadata' => [
                                    'scope' => 'business',

                                    'purpose' => 'human_signal_inbox',
                                ],
                            ]
                        );

                /*
                 * The entry proves only that the human supplied
                 * this signal.
                 *
                 * It does NOT verify the underlying business claim.
                 */
                $entry =
                    $this->entries
                        ->execute(
                            memory: $memory,

                            type: BusinessMemoryEntryType::Note,

                            content: $content,

                            source: 'ceo_signal_box',

                            sourceReference: 'dashboard',

                            confidence: 100,

                            verified: false,

                            metadata: [
                                'input_channel' => 'ceo_signal_box',

                                'submitted_by_user_id' => $submittedBy->id,

                                'submitted_by_name' => $submittedBy->name,

                                'truth_status' => 'unverified',

                                'requires_investigation_before_truth' => true,
                            ]
                        );

                /*
                 * Reuse the existing Business Memory extraction
                 * pipeline so questions, concerns, risks,
                 * requirements and other signals are classified
                 * without the CEO having to select a category.
                 */
                $this->extraction
                    ->extract(
                        $entry
                    );

                /*
                 * Open the existing investigation container.
                 *
                 * Opening an investigation is NOT promotion to
                 * truth and is NOT creation of an executive action.
                 *
                 * Confidence starts at zero. Existing investigation
                 * machinery must gather/assess evidence before a
                 * conclusion can be verified.
                 */
                $humanSignalCase =
                    $this->investigations
                        ->open(
                            type: 'human_signal',

                            title: 'CEO signal: '
                                .Str::limit(
                                    $content,
                                    120,
                                    ''
                                ),

                            question: $content,

                            subjectType: 'business_memory_entry',

                            subjectId: $entry->id,

                            subjectName: 'CEO Signal',

                            metadata: [
                                'business_memory_id' => $memory->id,

                                'business_memory_entry_id' => $entry->id,

                                'source' => 'ceo_signal_box',

                                'submitted_by_user_id' => $submittedBy->id,

                                'truth_status' => 'unverified',

                                'requires_evidence' => true,
                            ]
                        );

                /*
                 * Resolve the signal into existing Brain domains.
                 *
                 * Routing may link/open an investigation and capture
                 * an evidence snapshot, but it does not verify the
                 * human claim or create an executive action.
                 */
                $this->routing
                    ->route(
                        entry: $entry,

                        humanSignalCase: $humanSignalCase
                    );

                return $entry
                    ->fresh([
                        'observations',
                    ]);
            }
        );
    }
}
