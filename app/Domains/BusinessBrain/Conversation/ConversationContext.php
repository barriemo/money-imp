<?php

namespace App\Domains\BusinessBrain\Conversation;

class ConversationContext
{
    public function __construct(
        public ?string $subjectType = null,

        public ?string $subjectId = null,

        public ?string $subjectName = null,

        public ?string $issue = null,

        public ?string $hypothesis = null,

        public array $evidenceIds = [],

        public array $pendingActions = [],

        public array $unresolvedQuestions = [],

        public array $investigation = [],

        public ?string $investigationCaseId = null
    ) {}

    public function toArray(): array
    {
        return [
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'subject_name' => $this->subjectName,
            'issue' => $this->issue,
            'hypothesis' => $this->hypothesis,
            'evidence_ids' => $this->evidenceIds,
            'pending_actions' => $this->pendingActions,
            'unresolved_questions' => $this->unresolvedQuestions,
            'investigation' => $this->investigation,
            'investigation_case_id' => $this->investigationCaseId,
        ];
    }

    public static function fromArray(
        array $data
    ): self {
        return new self(
            subjectType: $data['subject_type'] ?? null,

            subjectId: $data['subject_id'] ?? null,

            subjectName: $data['subject_name'] ?? null,

            issue: $data['issue'] ?? null,

            hypothesis: $data['hypothesis'] ?? null,

            evidenceIds: $data['evidence_ids'] ?? [],

            pendingActions: $data['pending_actions'] ?? [],

            unresolvedQuestions: $data['unresolved_questions'] ?? [],

            investigation: $data['investigation'] ?? [],

            investigationCaseId: $data['investigation_case_id'] ?? null
        );
    }
}
