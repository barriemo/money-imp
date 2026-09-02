<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Commercial Reconciliation — Money Imp</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #111;
            color: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            max-width: 1280px;
            margin: auto;
            padding: 40px 24px 80px;
        }

        a { color: inherit; }

        h1 {
            font-size: 38px;
            margin-bottom: 8px;
        }

        h2 {
            margin: 0 0 6px;
        }

        .intro,
        .muted {
            color: #999;
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

        .flash,
        .errors {
            margin: 20px 0;
            padding: 15px 18px;
            border-radius: 10px;
        }

        .flash {
            background: #18351f;
            border: 1px solid #295b35;
        }

        .errors {
            background: #3d1717;
            border: 1px solid #6d2929;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(180px, 1fr)
            );
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat,
        .candidate {
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
        }

        .stat {
            padding: 18px;
        }

        .stat strong {
            display: block;
            font-size: 25px;
        }

        .candidate {
            padding: 24px;
            margin-bottom: 16px;
        }

        .candidate-top {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .service-type {
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: 12px;
            color: #aaa;
            font-weight: 800;
            margin-bottom: 7px;
        }

        .value {
            font-size: 30px;
            font-weight: 800;
            text-align: right;
        }

        .value-label {
            color: #888;
            text-align: right;
            margin-top: 3px;
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin: 16px 0;
        }

        .badge {
            border: 1px solid #383838;
            border-radius: 999px;
            padding: 6px 9px;
            font-size: 13px;
            color: #ccc;
        }

        .badge.current {
            border-color: #315b3c;
            color: #9ddcab;
        }

        .badge.recently_observed {
            border-color: #685c28;
            color: #ead47d;
        }

        .truth-boundary {
            margin: 18px 0;
            padding: 13px 15px;
            background: #131313;
            border-left: 3px solid #555;
            color: #bbb;
        }

        .actions {
            margin-top: 20px;
            display: grid;
            gap: 12px;
        }

        form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        input,
        select,
        textarea,
        button {
            padding: 11px 13px;
            border-radius: 8px;
            border: 1px solid #333;
            font: inherit;
        }

        input,
        select,
        textarea {
            background: #222;
            color: white;
        }

        .service-name {
            min-width: 280px;
        }

        .service-select {
            min-width: 300px;
        }

        .reason {
            min-width: 280px;
            flex: 1;
        }

        button {
            cursor: pointer;
            font-weight: 700;
        }

        .secondary {
            background: #222;
            color: #ddd;
        }

        .defer {
            background: transparent;
            color: #aaa;
        }

        .reject {
            background: #351717;
            color: #f0b3b3;
            border-color: #662b2b;
        }

        details {
            margin-top: 18px;
            padding: 13px 15px;
            background: #131313;
            border-radius: 10px;
        }

        summary {
            cursor: pointer;
            font-weight: 700;
        }

        .evidence-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .evidence-table th,
        .evidence-table td {
            text-align: left;
            padding: 9px 7px;
            border-bottom: 1px solid #262626;
            vertical-align: top;
        }

        .evidence-table th {
            color: #888;
            font-size: 12px;
            text-transform: uppercase;
        }

        .money {
            white-space: nowrap;
        }

        .empty {
            padding: 28px;
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
        }

        @media (max-width: 700px) {
            .value,
            .value-label {
                text-align: left;
            }

            .service-name,
            .service-select,
            .reason {
                min-width: 100%;
                width: 100%;
            }

            .evidence-table {
                font-size: 13px;
            }
        }
    </style>
</head>

<body>
<main>
    <a href="{{ route('dashboard') }}">
        ← Money Imp
    </a>

    <h1>Reconciliation Inbox</h1>

    <p class="intro">
        Human review of commercial truth.
        Invoice history is evidence — it is not a contract.
    </p>

    @if (session('success'))
        <div class="flash">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="errors">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="tabs">
        <a href="{{ route('reconciliation.index') }}">
            Payments
        </a>

        <a
            class="{{ $queue === 'services' ? 'active' : '' }}"
            href="{{ route(
                'reconciliation.commercial.index',
                ['queue' => 'services']
            ) }}"
        >
            Commercial services {{ $counts['services'] }}
        </a>

        <a
            class="{{ $queue === 'composite' ? 'active' : '' }}"
            href="{{ route(
                'reconciliation.commercial.index',
                ['queue' => 'composite']
            ) }}"
        >
            Composite evidence {{ $counts['composite'] }}
        </a>

        <a
            class="{{ $queue === 'attribution' ? 'active' : '' }}"
            href="{{ route(
                'reconciliation.commercial.index',
                ['queue' => 'attribution']
            ) }}"
        >
            Service attribution {{ $counts['attribution'] }}
        </a>

        <a
            class="{{ $queue === 'status' ? 'active' : '' }}"
            href="{{ route(
                'reconciliation.commercial.index',
                ['queue' => 'status']
            ) }}"
        >
            Service status {{ $counts['status'] }}
        </a>
    </div>

    <section class="summary">
        <div class="stat">
            <strong>{{ $counts['services'] }}</strong>
            <span>Service-existence reviews</span>
        </div>

        <div class="stat">
            <strong>{{ $counts['composite'] }}</strong>
            <span>Composite evidence reviews</span>
        </div>

        <div class="stat">
            <strong>{{ $counts['attribution'] }}</strong>
            <span>Invoice attribution reviews</span>
        </div>

        <div class="stat">
            <strong>{{ $counts['status'] }}</strong>
            <span>Service-status reviews</span>
        </div>

        <div class="stat">
            <strong>{{ $asOf->format('d M Y') }}</strong>
            <span>Evidence assessed as of</span>
        </div>
    </section>

    @if ($queue === 'services')
        @forelse ($serviceCandidates as $assessment)
            @php
                $candidate = $assessment->candidate;

                $evidenceItems = collect(
                    $candidate->invoiceItemIds
                )
                    ->map(
                        fn ($id) => $serviceEvidence->get(
                            (string) $id
                        )
                    )
                    ->filter()
                    ->sortByDesc(
                        fn ($item) =>
                            $item->invoice?->invoice_date?->timestamp
                            ?? 0
                    )
                    ->values();

                $clientServices =
                    $existingServices->get(
                        (string) $candidate->clientId,
                        collect()
                    );
            @endphp

            <article class="candidate">
                <div class="candidate-top">
                    <div>
                        <div class="service-type">
                            {{ str_replace(
                                '_',
                                ' ',
                                $candidate->serviceType
                            ) }}
                        </div>

                        <h2>
                            {{ $candidate->clientName }}
                        </h2>

                        @if ($candidate->serviceHint)
                            <div class="muted">
                                Hint:
                                {{ $candidate->serviceHint }}
                            </div>
                        @endif
                    </div>

                    <div>
                        @if (
                            $assessment
                                ->currentMonthlyEquivalent
                            !== null
                        )
                            <div class="value">
                                £{{ number_format(
                                    $assessment
                                        ->currentMonthlyEquivalent,
                                    2
                                ) }}
                            </div>

                            <div class="value-label">
                                current observed monthly equivalent
                            </div>
                        @else
                            <div class="value">
                                Not established
                            </div>

                            <div class="value-label">
                                current monthly equivalent
                            </div>

                            @if (
                                $candidate
                                    ->monthlyEquivalent
                                > 0
                            )
                                <div class="muted">
                                    Last observed cadence equivalent:
                                    £{{ number_format(
                                        $candidate
                                            ->monthlyEquivalent,
                                        2
                                    ) }}
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="badges">
                    <span
                        class="badge {{ $assessment->freshness }}"
                    >
                        {{ str_replace(
                            '_',
                            ' ',
                            ucfirst(
                                $assessment->freshness
                            )
                        ) }}
                    </span>

                    <span class="badge">
                        {{ ucfirst($candidate->cadence) }}
                    </span>

                    <span class="badge">
                        Cadence confidence
                        {{ $candidate->cadenceConfidence }}%
                    </span>

                    <span class="badge">
                        Classification confidence
                        {{ $candidate->classificationConfidence }}%
                    </span>

                    <span class="badge">
                        {{ $candidate->evidenceCount }}
                        evidence item(s)
                    </span>

                    <span class="badge">
                        {{ $candidate->firstObservedOn ?? 'unknown' }}
                        →
                        {{ $candidate->lastObservedOn ?? 'unknown' }}
                    </span>
                </div>

                <div class="truth-boundary">
                    @if (
                        $assessment->freshness
                        === 'recently_observed'
                    )
                        Money Imp has established recurring
                        invoice history for this service, but
                        current active status is not established.
                        A human may preserve it as historical
                        canonical service truth without treating
                        it as current recurring value.
                    @else
                        Money Imp has observed recurring invoice
                        history consistent with this service.
                        A human decision is required before this
                        becomes canonical ClientService truth.
                        This is not contracted MRR.
                    @endif
                </div>

                <details>
                    <summary>
                        Review exact invoice evidence
                        ({{ $evidenceItems->count() }})
                    </summary>

                    <table class="evidence-table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Description</th>
                            <th>Net</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach ($evidenceItems as $item)
                            <tr>
                                <td>
                                    {{
                                        $item
                                            ->invoice
                                            ?->invoice_date
                                            ?->format('d M Y')
                                        ?? '—'
                                    }}
                                </td>

                                <td>
                                    {{
                                        $item
                                            ->invoice
                                            ?->invoice_number
                                        ?? '—'
                                    }}
                                </td>

                                <td>
                                    {{ $item->description }}
                                </td>

                                <td class="money">
                                    £{{ number_format(
                                        (float) $item
                                            ->net_amount,
                                        2
                                    ) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </details>

                <div class="actions">
                    @if (
                        $assessment->freshness
                        === 'current'
                    )
                        <form
                            method="POST"
                            action="{{ route(
                                'reconciliation.commercial.confirm',
                                [
                                    'clientId' =>
                                        $candidate->clientId,
                                    'candidateFingerprint' =>
                                        $candidate->fingerprint,
                                ]
                            ) }}"
                        >
                            @csrf

                            <input
                                class="service-name"
                                type="text"
                                name="service_name"
                                placeholder="Canonical service name…"
                                required
                            >

                            <input
                                class="reason"
                                type="text"
                                name="reason"
                                placeholder="Optional review note…"
                            >

                            <button type="submit">
                                Confirm New Service
                            </button>
                        </form>

                        @if ($clientServices->isNotEmpty())
                            <form
                                method="POST"
                                action="{{ route(
                                    'reconciliation.commercial.merge',
                                    [
                                        'clientId' =>
                                            $candidate->clientId,
                                        'candidateFingerprint' =>
                                            $candidate->fingerprint,
                                    ]
                                ) }}"
                            >
                                @csrf

                                <select
                                    class="service-select"
                                    name="client_service_id"
                                    required
                                >
                                    <option value="">
                                        Merge into existing service…
                                    </option>

                                    @foreach ($clientServices as $service)
                                        <option value="{{ $service->id }}">
                                            {{ $service->name }}
                                        </option>
                                    @endforeach
                                </select>

                                <input
                                    class="reason"
                                    type="text"
                                    name="reason"
                                    placeholder="Optional review note…"
                                >

                                <button
                                    class="secondary"
                                    type="submit"
                                >
                                    Merge Into Existing
                                </button>
                            </form>
                        @endif
                    @elseif (
                        $assessment->freshness
                        === 'recently_observed'
                    )
                        <form
                            method="POST"
                            action="{{ route(
                                'reconciliation.commercial.historical',
                                [
                                    'clientId' =>
                                        $candidate->clientId,
                                    'candidateFingerprint' =>
                                        $candidate->fingerprint,
                                ]
                            ) }}"
                        >
                            @csrf

                            <input
                                class="service-name"
                                type="text"
                                name="service_name"
                                placeholder="Historical canonical service name…"
                                required
                            >

                            <input
                                class="reason"
                                type="text"
                                name="reason"
                                placeholder="Historical status evidence note…"
                            >

                            <button
                                class="secondary"
                                type="submit"
                            >
                                Confirm Historical Service
                            </button>
                        </form>
                    @endif

                    <form
                        method="POST"
                        action="{{ route(
                            'reconciliation.commercial.defer',
                            [
                                'clientId' =>
                                    $candidate->clientId,
                                'candidateFingerprint' =>
                                    $candidate->fingerprint,
                            ]
                        ) }}"
                    >
                        @csrf

                        <input
                            class="reason"
                            type="text"
                            name="reason"
                            placeholder="Why defer? Optional…"
                        >

                        <button
                            class="defer"
                            type="submit"
                        >
                            Defer
                        </button>
                    </form>

                    <form
                        method="POST"
                        action="{{ route(
                            'reconciliation.commercial.reject',
                            [
                                'clientId' =>
                                    $candidate->clientId,
                                'candidateFingerprint' =>
                                    $candidate->fingerprint,
                            ]
                        ) }}"
                    >
                        @csrf

                        <input
                            class="reason"
                            type="text"
                            name="reason"
                            placeholder="Reason for rejection…"
                        >

                        <button
                            class="reject"
                            type="submit"
                        >
                            Reject Evidence
                        </button>
                    </form>
                </div>
            </article>
        @empty
            <div class="empty">
                <strong>
                    No commercial service candidates
                    currently need human review.
                </strong>
            </div>
        @endforelse
    @elseif ($queue === 'composite')
        @forelse ($compositeCandidates as $assessment)
            @php
                $candidate = $assessment->candidate;

                $evidenceItems = collect(
                    $candidate->invoiceItemIds
                )
                    ->map(
                        fn ($id) =>
                            $compositeEvidence
                                ->get(
                                    (string) $id
                                )
                    )
                    ->filter()
                    ->sortByDesc(
                        fn ($item) =>
                            $item
                                ->invoice
                                ?->invoice_date
                                ?->timestamp
                            ?? 0
                    )
                    ->values();
            @endphp

            <article class="candidate">
                <div class="candidate-top">
                    <div>
                        <div class="service-type">
                            Composite commercial evidence
                        </div>

                        <h2>
                            {{ $candidate->clientName }}
                        </h2>

                        @if ($candidate->serviceHint)
                            <div class="muted">
                                Hint:
                                {{ $candidate->serviceHint }}
                            </div>
                        @endif
                    </div>

                    <div>
                        <div class="value">
                            £{{ number_format(
                                (float) $candidate
                                    ->signedObservedNet,
                                2
                            ) }}
                        </div>

                        <div class="value-label">
                            source invoice evidence
                        </div>
                    </div>
                </div>

                <div class="badges">
                    <span class="badge recently_observed">
                        Needs commercial review
                    </span>

                    <span class="badge">
                        {{ $candidate->evidenceCount }}
                        evidence item(s)
                    </span>

                    @foreach (
                        $candidate->commercialComponents
                        as $component
                    )
                        <span class="badge">
                            {{ str_replace(
                                '_',
                                ' ',
                                $component
                            ) }}
                        </span>
                    @endforeach
                </div>

                <div class="truth-boundary">
                    This source evidence names multiple commercial
                    activities. It may represent one bundled service
                    or it may require monetary decomposition. The
                    classifier cannot decide that commercial structure
                    safely. It is excluded from supported current
                    monthly-equivalent billing and cannot create,
                    merge or update canonical truth until a human
                    commercial review resolves the evidence.
                </div>

                <details>
                    <summary>
                        Review exact composite invoice evidence
                        ({{ $evidenceItems->count() }})
                    </summary>

                    <table class="evidence-table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Qty</th>
                            <th>Unit</th>
                            <th>Net</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach ($evidenceItems as $item)
                            <tr>
                                <td>
                                    {{ $item
                                        ->invoice
                                        ?->invoice_date
                                        ?->format('d M Y')
                                        ?? '—'
                                    }}
                                </td>

                                <td>
                                    {{ $item->description }}
                                </td>

                                <td>
                                    {{ number_format(
                                        (float) $item->quantity,
                                        2
                                    ) }}
                                </td>

                                <td class="money">
                                    £{{ number_format(
                                        (float) $item->unit_price,
                                        2
                                    ) }}
                                </td>

                                <td class="money">
                                    £{{ number_format(
                                        (float) $item->net_amount,
                                        2
                                    ) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </details>

                <div class="truth-boundary">
                    Read-only review surface.
                    No allocation or reconciliation action is
                    available in this patch.
                </div>
            </article>
        @empty
            <div class="empty">
                <strong>
                    No composite commercial evidence currently
                    requires human commercial review.
                </strong>
            </div>
        @endforelse
    @elseif ($queue === 'attribution')
        @forelse ($attributionCandidates as $candidate)
            @php
                $evidenceItems = collect(
                    $candidate->invoiceItemIds
                )
                    ->map(
                        fn ($id) =>
                            $attributionEvidence
                                ->get(
                                    (string) $id
                                )
                    )
                    ->filter()
                    ->sortByDesc(
                        fn ($item) =>
                            $item
                                ->invoice
                                ?->invoice_date
                                ?->timestamp
                            ?? 0
                    )
                    ->values();
            @endphp

            <article class="candidate">
                <div class="candidate-top">
                    <div>
                        <div class="service-type">
                            Attribution review
                        </div>

                        <h2>
                            {{ $candidate->clientName }}
                        </h2>

                        <div class="muted">
                            Proposed canonical service:
                            <strong>
                                {{ $candidate->clientServiceName }}
                            </strong>
                        </div>
                    </div>

                    <div>
                        <div class="value">
                            £{{ number_format(
                                (float) $candidate
                                    ->signedObservedNet,
                                2
                            ) }}
                        </div>

                        <div class="value-label">
                            exact unattributed evidence
                        </div>
                    </div>
                </div>

                <div class="badges">
                    <span class="badge current">
                        Unique human-backed match
                    </span>

                    <span class="badge">
                        {{ str_replace(
                            '_',
                            ' ',
                            $candidate->serviceType
                        ) }}
                    </span>

                    <span class="badge">
                        {{ $candidate->evidenceCount }}
                        evidence item(s)
                    </span>

                    <span class="badge">
                        {{ $candidate->firstObservedOn ?? 'unknown' }}
                        →
                        {{ $candidate->lastObservedOn ?? 'unknown' }}
                    </span>
                </div>

                <div class="truth-boundary">
                    Earlier human reconciliation established
                    the canonical service mapping.
                    These new invoice items remain unattributed
                    until a human approves this exact evidence set.
                </div>

                <details>
                    <summary>
                        Review exact new invoice evidence
                        ({{ $evidenceItems->count() }})
                    </summary>

                    <table class="evidence-table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Description</th>
                            <th>Net</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach ($evidenceItems as $item)
                            <tr>
                                <td>
                                    {{
                                        $item
                                            ->invoice
                                            ?->invoice_date
                                            ?->format('d M Y')
                                        ?? '—'
                                    }}
                                </td>

                                <td>
                                    {{
                                        $item
                                            ->invoice
                                            ?->invoice_number
                                        ?? '—'
                                    }}
                                </td>

                                <td>
                                    {{ $item->description }}
                                </td>

                                <td class="money">
                                    £{{ number_format(
                                        (float) $item
                                            ->net_amount,
                                        2
                                    ) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </details>

                <div class="actions">
                    <form
                        method="POST"
                        action="{{ route(
                            'reconciliation.commercial.attribution.approve',
                            [
                                'clientId' =>
                                    $candidate->clientId,
                                'candidateFingerprint' =>
                                    $candidate
                                        ->candidateFingerprint,
                            ]
                        ) }}"
                    >
                        @csrf

                        <input
                            class="reason"
                            type="text"
                            name="reason"
                            placeholder="Optional review note…"
                        >

                        <button type="submit">
                            Approve Attribution
                        </button>
                    </form>

                    <form
                        method="POST"
                        action="{{ route(
                            'reconciliation.commercial.attribution.reject',
                            [
                                'clientId' =>
                                    $candidate->clientId,
                                'candidateFingerprint' =>
                                    $candidate
                                        ->candidateFingerprint,
                            ]
                        ) }}"
                    >
                        @csrf

                        <input
                            class="reason"
                            type="text"
                            name="reason"
                            placeholder="Reason for rejection…"
                        >

                        <button
                            class="reject"
                            type="submit"
                        >
                            Reject Attribution
                        </button>
                    </form>
                </div>
            </article>
        @empty
            <div class="empty">
                <strong>
                    No new invoice attribution decisions
                    currently need human review.
                </strong>
            </div>
        @endforelse
    @elseif ($queue === 'status')
        @forelse ($statusCandidates as $candidate)
            @php
                $evidenceItems = collect(
                    $candidate->invoiceItemIds
                )
                    ->map(
                        fn ($id) =>
                            $statusEvidence
                                ->get(
                                    (string) $id
                                )
                    )
                    ->filter()
                    ->sortByDesc(
                        fn ($item) =>
                            $item
                                ->invoice
                                ?->invoice_date
                                ?->timestamp
                            ?? 0
                    )
                    ->values();
            @endphp

            <article class="candidate">
                <div class="candidate-top">
                    <div>
                        <div class="service-type">
                            Non-active service status review
                        </div>

                        <h2>
                            {{ $candidate->clientName }}
                        </h2>

                        @if ($candidate->serviceHint)
                            <div class="muted">
                                Hint:
                                {{ $candidate->serviceHint }}
                            </div>
                        @endif
                    </div>

                    <div>
                        <div class="value">
                            £{{ number_format(
                                (float) $candidate
                                    ->signedObservedNet,
                                2
                            ) }}
                        </div>

                        <div class="value-label">
                            new unattributed evidence
                        </div>
                    </div>
                </div>

                <div class="badges">
                    <span class="badge recently_observed">
                        Inactive canonical target
                    </span>

                    <span class="badge">
                        {{ str_replace(
                            '_',
                            ' ',
                            $candidate->serviceType
                        ) }}
                    </span>

                    <span class="badge">
                        {{ $candidate->evidenceCount }}
                        evidence item(s)
                    </span>

                    <span class="badge">
                        {{ $candidate->firstObservedOn ?? 'unknown' }}
                        →
                        {{ $candidate->lastObservedOn ?? 'unknown' }}
                    </span>
                </div>

                <div class="truth-boundary">
                    New invoice evidence matches a previously
                    reconciled canonical service which is not
                    currently active. The evidence remains
                    unattributed. Money Imp will not reactivate
                    the service or treat this billing as current
                    canonical truth without an explicit human
                    service-status decision.
                </div>

                <details>
                    <summary>
                        Review exact new invoice evidence
                        ({{ $evidenceItems->count() }})
                    </summary>

                    <table class="evidence-table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Description</th>
                            <th>Net</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach ($evidenceItems as $item)
                            <tr>
                                <td>
                                    {{
                                        $item
                                            ->invoice
                                            ?->invoice_date
                                            ?->format('d M Y')
                                        ?? '—'
                                    }}
                                </td>

                                <td>
                                    {{
                                        $item
                                            ->invoice
                                            ?->invoice_number
                                        ?? '—'
                                    }}
                                </td>

                                <td>
                                    {{ $item->description }}
                                </td>

                                <td class="money">
                                    £{{ number_format(
                                        (float) $item
                                            ->net_amount,
                                        2
                                    ) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </details>
            </article>
        @empty
            <div class="empty">
                <strong>
                    No non-active services currently have
                    new evidence requiring status review.
                </strong>
            </div>
        @endforelse
    @endif
</main>
</body>
</html>
