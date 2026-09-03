<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reconciliation — Money Imp</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #111;
            color: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            max-width: 1200px;
            margin: auto;
            padding: 40px 24px 80px;
        }

        a { color: inherit; }

        h1 {
            font-size: 38px;
            margin-bottom: 8px;
        }

        .tabs {
            display: flex;
            gap: 12px;
            margin: 28px 0;
            flex-wrap: wrap;
        }

        .tabs a {
            padding: 10px 14px;
            border: 1px solid #333;
            border-radius: 8px;
            text-decoration: none;
        }

        .tabs a.active {
            background: white;
            color: #111;
        }

        .flash {
            margin: 20px 0;
            padding: 15px 18px;
            border-radius: 10px;
            background: #18351f;
            border: 1px solid #295b35;
        }

        .transaction {
            padding: 24px;
            margin-bottom: 14px;
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
        }

        .amount {
            font-size: 28px;
            font-weight: 800;
        }

        .meta {
            margin-top: 5px;
            color: #888;
        }

        .description {
            margin: 16px 0;
            font-family: monospace;
            color: #ccc;
        }

        .client {
            margin: 15px 0;
            font-size: 18px;
        }

        form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 10px;
        }

        input,
        select,
        button {
            padding: 11px 13px;
            border-radius: 8px;
            border: 1px solid #333;
            font: inherit;
        }

        input,
        select {
            background: #222;
            color: white;
        }

        .client-search {
            min-width: 320px;
        }

        .invoice-select {
            min-width: 360px;
        }

        .amount-input {
            width: 140px;
        }

        button {
            cursor: pointer;
            font-weight: 700;
        }

        .ignore {
            background: transparent;
            color: #aaa;
        }

        .review-note {
            margin: 0 0 20px;
            padding: 16px 18px;
            border: 1px solid #333;
            border-radius: 10px;
            color: #bbb;
            background: #151515;
        }

        .review-bands {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 24px;
        }

        .review-band {
            padding: 9px 12px;
            border: 1px solid #333;
            border-radius: 999px;
            color: #ccc;
        }

        .priority {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 14px;
        }

        .priority-badge {
            padding: 6px 9px;
            border-radius: 7px;
            background: #eee;
            color: #111;
            font-weight: 800;
        }

        .priority-score {
            color: #aaa;
            font-size: 14px;
        }

        .evidence {
            margin-top: 16px;
            padding: 15px;
            background: #131313;
            border-radius: 10px;
        }

        .evidence ul,
        .warning-box ul {
            margin: 10px 0 0;
            padding-left: 21px;
        }

        .warning-box {
            margin-top: 14px;
            padding: 14px;
            background: #321d12;
            border: 1px solid #684025;
            border-radius: 10px;
        }

        .stale-box {
            margin-top: 14px;
            padding: 14px;
            background: #282828;
            border: 1px solid #444;
            border-radius: 10px;
            color: #bbb;
        }

        .review-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }

        .review-actions form {
            margin-top: 0;
        }

        .approve {
            background: #f2f2f2;
            color: #111;
        }

        .reject {
            background: transparent;
            color: #bbb;
        }

        .resolution-options {
            margin-top: 16px;
            padding: 16px;
            background: #10151a;
            border: 1px solid #253545;
            border-radius: 10px;
        }

        .resolution-option {
            margin-top: 12px;
            padding: 13px;
            border: 1px solid #2c3945;
            border-radius: 9px;
        }

        .resolution-option:first-of-type {
            margin-top: 10px;
        }

        .resolution-option form {
            display: inline-flex;
            margin-right: 8px;
        }

        .historical {
            background: #1c2b24;
            border-color: #31503e;
        }

        .historical-label {
            display: inline-block;
            margin-top: 8px;
            padding: 5px 8px;
            border-radius: 6px;
            background: #203329;
            color: #bfe1ca;
            font-size: 13px;
            font-weight: 700;
        }

        .invoice {
            margin-top: 16px;
            padding: 15px;
            background: #131313;
            border-radius: 10px;
        }
    </style>
</head>

<body>
<main>
    <a href="{{ route('dashboard') }}">← Money Imp</a>

    <h1>Reconciliation Inbox</h1>

    @if (session('success'))
        <div class="flash">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="warning-box">
            {{ session('error') }}
        </div>
    @endif

    <div class="tabs">
        @foreach ([
            'unknown' => 'Unknown',
            'known' => 'Client known',
            'ready' => 'Ready',
            'historical' => 'Historical evidence',
            'ignored' => 'Ignored',
        ] as $key => $label)
            <a
                class="{{ $tab === $key ? 'active' : '' }}"
                href="{{ route('reconciliation.index', ['tab' => $key]) }}"
            >
                {{ $label }} {{ $counts[$key] }}
            </a>
        @endforeach

        <a
            href="{{ route('reconciliation.commercial.index') }}"
        >
            Commercial services
        </a>
    </div>

    <datalist id="money-imp-clients">
        @foreach ($clients as $client)
            <option value="{{ $client->name }}"></option>
        @endforeach
    </datalist>

    @if ($tab === 'ready')
        <div class="review-note">
            <strong>Human review queue.</strong>
            Review priority orders the work; it is not payment truth,
            investigation confidence, or an auto-approval threshold.
            Every approval still requires a human decision.
        </div>

        <div class="review-bands">
            @foreach ([
                'review_first' => 'Review first',
                'strong_review' => 'Strong review',
                'normal_review' => 'Normal review',
                'needs_care' => 'Needs care',
                'stale' => 'Stale',
            ] as $band => $label)
                <div class="review-band">
                    <strong>{{ $reviewBandCounts[$band] ?? 0 }}</strong>
                    {{ $label }}
                </div>
            @endforeach
        </div>

        @forelse ($reviewItems as $review)
            @php
                $allocation = $review->allocation;
                $transaction = $allocation->transaction;
                $invoice = $allocation->invoice;
            @endphp

            <article class="transaction">
                <div class="priority">
                    <span class="priority-badge">
                        {{ $review->bandLabel }}
                    </span>

                    <span class="priority-score">
                        Review priority {{ $review->score }}/100
                    </span>
                </div>

                <div class="amount">
                    £{{ number_format((float) $allocation->amount, 2) }}
                </div>

                <div class="meta">
                    Suggested allocation from a
                    £{{ number_format((float) $transaction->amount, 2) }}
                    receipt

                    @if ($transaction->transaction_date)
                        · {{ $transaction->transaction_date->format('d M Y') }}
                    @endif

                    @if ($transaction->bankAccount)
                        · {{ $transaction->bankAccount->name }}
                    @endif
                </div>

                <div class="description">
                    {{ $transaction->description }}
                </div>

                <div class="client">
                    Client:
                    <strong>
                        {{ $transaction->client?->name ?? 'Unknown' }}
                    </strong>
                </div>

                <div class="invoice">
                    <strong>
                        Invoice {{ $invoice->invoice_number ?? 'unknown' }}
                    </strong>

                    <div class="meta">
                        Source outstanding:
                        £{{ number_format($review->sourceOutstanding, 2) }}

                        · Current allocatable balance:
                        £{{ number_format($review->invoiceBalance, 2) }}

                        · Approval would allocate:
                        £{{ number_format($review->effectiveApprovalAmount, 2) }}
                    </div>
                </div>

                <div class="evidence">
                    <strong>Why this is in the queue</strong>

                    <ul>
                        @foreach ($review->reasons as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                </div>

                @if ($review->warnings !== [])
                    <div class="warning-box">
                        <strong>Review warning</strong>

                        <ul>
                            @foreach ($review->warnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="meta">
                    Match method:
                    {{ $allocation->match_method ?? 'unknown' }}

                    · Engine confidence:
                    {{ number_format((float) $allocation->confidence, 0) }}%

                    · Engine confidence is shown as source metadata,
                    not as Money Imp truth confidence.
                </div>

                @if (
                    in_array(
                        $review->band,
                        ['needs_care', 'stale'],
                        true
                    )
                )
                    @php
                        $candidates =
                            $resolutionCandidates[
                                $allocation->id
                            ] ?? [];

                        $classification =
                            $historicalClassifications[
                                $allocation->id
                            ] ?? null;
                    @endphp

                    <div class="resolution-options">
                        <strong>
                            {{ $review->band === 'needs_care'
                                ? 'Resolve recurring receipt'
                                : 'Resolve historical receipt' }}
                        </strong>

                        <div class="meta">
                            Date proximity and same-value invoices are
                            review context only. They are not proof of
                            invoice attribution.
                        </div>

                        @if (
                            $classification
                            && $classification['classification']
                                === 'historical_corroboration_candidate'
                        )
                            <div class="historical-label">
                                Strong historical corroboration candidate:
                                invoice reference + exact amount + source paid
                            </div>
                        @elseif ($classification)
                            <div class="warning-box">
                                Historical match requires manual judgement.
                                The current bank evidence does not contain
                                enough structure to label this a strong
                                corroboration candidate.
                            </div>
                        @endif

                        @forelse ($candidates as $candidate)
                            @php
                                $candidateInvoice =
                                    $candidate['invoice'];
                            @endphp

                            <div
                                class="resolution-option
                                    {{ $candidate['historical_eligible']
                                        ? 'historical'
                                        : '' }}"
                            >
                                <strong>
                                    Invoice
                                    {{ $candidateInvoice->invoice_number }}
                                </strong>

                                @if ($candidate['current_target'])
                                    · current suggestion
                                @endif

                                <div class="meta">
                                    Invoice
                                    {{ optional(
                                        $candidateInvoice->invoice_date
                                    )->format('d M Y') ?? 'date unknown' }}

                                    ·
                                    {{ number_format(
                                        (float) $candidate[
                                            'days_from_receipt'
                                        ],
                                        0
                                    ) }}
                                    day(s) from receipt

                                    · source status
                                    {{ $candidateInvoice->status }}

                                    · source outstanding
                                    £{{ number_format(
                                        (float) $candidateInvoice
                                            ->outstanding_amount,
                                        2
                                    ) }}
                                </div>

                                @if (
                                    $candidate[
                                        'explicit_invoice_reference'
                                    ]
                                )
                                    <div class="historical-label">
                                        Invoice reference appears in bank evidence
                                    </div>
                                @endif

                                @if (
                                    $candidate[
                                        'historical_eligible'
                                    ]
                                )
                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'reconciliation.suggestions.resolve-historical',
                                            $allocation
                                        ) }}"
                                    >
                                        @csrf

                                        <input
                                            type="hidden"
                                            name="invoice_id"
                                            value="{{ $candidateInvoice->id }}"
                                        >

                                        <button type="submit">
                                            Record historical match
                                        </button>
                                    </form>
                                @endif

                                @if (
                                    $candidate[
                                        'approval_eligible'
                                    ]
                                )
                                    <form
                                        method="POST"
                                        action="{{ route(
                                            'reconciliation.suggestions.resolve-approved',
                                            $allocation
                                        ) }}"
                                    >
                                        @csrf

                                        <input
                                            type="hidden"
                                            name="invoice_id"
                                            value="{{ $candidateInvoice->id }}"
                                        >

                                        <button
                                            class="approve"
                                            type="submit"
                                        >
                                            Allocate receipt here
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <div class="warning-box">
                                No exact same-value invoice candidates
                                are available for this receipt.
                            </div>
                        @endforelse
                    </div>
                @endif

                @if (
                    $review->actionable
                    && $review->band !== 'needs_care'
                )
                    <div class="review-actions">
                        <form
                            method="POST"
                            action="{{ route(
                                'reconciliation.suggestions.approve',
                                $allocation
                            ) }}"
                        >
                            @csrf

                            <button
                                class="approve"
                                type="submit"
                            >
                                Approve
                                £{{ number_format(
                                    $review->effectiveApprovalAmount,
                                    2
                                ) }}
                            </button>
                        </form>

                        <form
                            method="POST"
                            action="{{ route(
                                'reconciliation.suggestions.reject',
                                $allocation
                            ) }}"
                        >
                            @csrf

                            <button
                                class="reject"
                                type="submit"
                            >
                                Reject suggestion
                            </button>
                        </form>
                    </div>
                @elseif ($review->band === 'needs_care')
                    <div class="stale-box">
                        Generic approval is blocked because competing
                        receipts target the same invoice. Resolve this
                        receipt against an explicit invoice above or
                        reject the suggestion.
                    </div>

                    <div class="review-actions">
                        <form
                            method="POST"
                            action="{{ route(
                                'reconciliation.suggestions.reject',
                                $allocation
                            ) }}"
                        >
                            @csrf

                            <button
                                class="reject"
                                type="submit"
                            >
                                Reject suggestion
                            </button>
                        </form>
                    </div>
                @else
                    <div class="stale-box">
                        This suggestion cannot be approved against its
                        current invoice balance. Preserve a reviewed
                        bank-to-invoice relationship as historical
                        corroboration where supported, choose another
                        invoice, or reject it.
                    </div>

                    <div class="review-actions">
                        <form
                            method="POST"
                            action="{{ route(
                                'reconciliation.suggestions.reject',
                                $allocation
                            ) }}"
                        >
                            @csrf

                            <button
                                class="reject"
                                type="submit"
                            >
                                Reject suggestion
                            </button>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <div class="review-note">
                No payment suggestions currently require review.
            </div>
        @endforelse

        {{ $reviewItems->links() }}
    @elseif ($tab === 'historical')
        <div class="review-note">
            <strong>Historical corroborating evidence.</strong>
            These records preserve a human-reviewed bank-to-invoice
            relationship where the accounting source already marks
            the invoice paid. They are not approved/imported invoice
            allocations and do not themselves create confirmed
            invoice-allocation truth.
        </div>

        @forelse ($historicalItems as $allocation)
            <article class="transaction">
                <div class="priority">
                    <span class="priority-badge">
                        Historical evidence
                    </span>
                </div>

                <div class="amount">
                    £{{ number_format(
                        (float) $allocation->amount,
                        2
                    ) }}
                </div>

                <div class="meta">
                    {{ optional(
                        $allocation
                            ->transaction
                            ?->transaction_date
                    )->format('d M Y') ?? 'date unknown' }}

                    @if (
                        $allocation
                            ->transaction
                            ?->bankAccount
                    )
                        ·
                        {{ $allocation
                            ->transaction
                            ->bankAccount
                            ->name }}
                    @endif
                </div>

                <div class="description">
                    {{ $allocation
                        ->transaction
                        ?->description }}
                </div>

                <div class="client">
                    Client:
                    <strong>
                        {{ $allocation
                            ->transaction
                            ?->client
                            ?->name
                            ?? $allocation
                                ->invoice
                                ?->client
                                ?->name
                            ?? 'Unknown' }}
                    </strong>
                </div>

                <div class="invoice">
                    Invoice
                    <strong>
                        {{ $allocation
                            ->invoice
                            ?->invoice_number
                            ?? 'unknown' }}
                    </strong>

                    <div class="meta">
                        Source status:
                        {{ $allocation
                            ->invoice
                            ?->status
                            ?? 'unknown' }}

                        · source paid:
                        £{{ number_format(
                            (float) (
                                $allocation
                                    ->invoice
                                    ?->paid_amount
                                ?? 0
                            ),
                            2
                        ) }}

                        · source outstanding:
                        £{{ number_format(
                            (float) (
                                $allocation
                                    ->invoice
                                    ?->outstanding_amount
                                ?? 0
                            ),
                            2
                        ) }}
                    </div>
                </div>

                <div class="historical-label">
                    Non-canonical invoice corroboration
                </div>

                <div class="meta">
                    {{ data_get(
                        $allocation->metadata,
                        'historical_corroboration.evidence_basis',
                        'human-reviewed historical evidence'
                    ) }}
                </div>
            </article>
        @empty
            <div class="review-note">
                No historical corroborations have been recorded yet.
            </div>
        @endforelse

        {{ $historicalItems->links() }}
    @else
    @foreach ($transactions as $transaction)
        <article class="transaction">
            <div class="amount">
                £{{ number_format((float) $transaction->amount, 2) }}
            </div>

            <div class="meta">
                {{ $transaction->transaction_date->format('d M Y') }}
                · {{ $transaction->bankAccount->name }}
            </div>

            <div class="description">
                {{ $transaction->description }}
            </div>

            @if ($transaction->client)
                <div class="client">
                    Client:
                    <strong>{{ $transaction->client->name }}</strong>
                </div>
            @endif

            @if ($tab === 'unknown' || $tab === 'known')
                <form
                    method="POST"
                    action="{{ route('reconciliation.assign-client', $transaction) }}"
                    data-client-picker
                >
                    @csrf

                    <input
                        class="client-search"
                        type="text"
                        list="money-imp-clients"
                        placeholder="Start typing client name…"
                        value="{{ $transaction->client?->name }}"
                        autocomplete="off"
                        data-client-name
                    >

                    <input
                        type="hidden"
                        name="client_id"
                        value="{{ $transaction->client_id }}"
                        data-client-id
                    >

                    <label>
                        <input
                            type="checkbox"
                            name="remember_identity"
                            value="1"
                            checked
                        >
                        Remember payer
                    </label>

                    <button type="submit">
                        Assign client
                    </button>
                </form>
            @endif

            @if (
                $transaction->client
                && $transaction->client->invoices->isNotEmpty()
                && $tab === 'known'
            )
                <div class="invoice">
                    <strong>Allocate payment</strong>

                    <form
                        method="POST"
                        action="{{ route('reconciliation.allocate-invoice', $transaction) }}"
                    >
                        @csrf

                        <select
                            class="invoice-select"
                            name="invoice_id"
                            required
                        >
                            <option value="">
                                Choose outstanding invoice…
                            </option>

                            @foreach ($transaction->client->invoices as $invoice)
                                <option value="{{ $invoice->id }}">
                                    {{ $invoice->invoice_number }}
                                    · £{{ number_format((float) $invoice->outstanding_amount, 2) }}
                                    · due {{ optional($invoice->due_date)->format('d M Y') ?? 'n/a' }}
                                </option>
                            @endforeach
                        </select>

                        <input
                            class="amount-input"
                            name="amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            value="{{ number_format((float) $transaction->amount, 2, '.', '') }}"
                            required
                        >

                        <button type="submit">
                            Allocate
                        </button>
                    </form>
                </div>
            @endif

            @if ($tab !== 'ignored')
                <form
                    method="POST"
                    action="{{ route('reconciliation.ignore', $transaction) }}"
                >
                    @csrf

                    <button class="ignore" type="submit">
                        Not client income
                    </button>
                </form>
            @endif
        </article>
    @endforeach

    {{ $transactions->links() }}
    @endif
</main>

<script>
    const clients = @json(
        $clients->map(fn ($client) => [
            'id' => $client->id,
            'name' => $client->name,
        ])->values()
    );

    document.querySelectorAll('[data-client-picker]').forEach((form) => {
        const nameInput = form.querySelector('[data-client-name]');
        const idInput = form.querySelector('[data-client-id]');

        const resolveClient = () => {
            const value = nameInput.value.trim().toLowerCase();

            const match = clients.find(
                (client) => client.name.toLowerCase() === value
            );

            idInput.value = match ? match.id : '';

            return match;
        };

        nameInput.addEventListener('input', resolveClient);
        nameInput.addEventListener('change', resolveClient);

        form.addEventListener('submit', (event) => {
            if (!resolveClient()) {
                event.preventDefault();
                nameInput.focus();
                alert('Choose a client from the Money Imp client list.');
            }
        });
    });
</script>
</body>
</html>
