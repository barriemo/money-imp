<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Billing Queue — Money Imp</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #111;
            color: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            max-width: 1250px;
            margin: auto;
            padding: 40px 24px 80px;
        }

        a { color: inherit; }

        h1 {
            margin-bottom: 6px;
            font-size: 42px;
        }

        .muted { color: #888; }

        .summary {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
            margin: 30px 0;
        }

        .stat {
            padding: 20px;
            border: 1px solid #292929;
            border-radius: 14px;
            background: #181818;
        }

        .stat strong {
            display: block;
            font-size: 27px;
        }

        .stat span {
            color: #888;
        }

        .danger { color: #ff8e8e; }
        .warning { color: #ffc46b; }

        .client {
            margin-bottom: 14px;
            padding: 22px;
            border: 1px solid #292929;
            border-radius: 14px;
            background: #181818;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .name {
            font-size: 21px;
            font-weight: 800;
        }

        .status {
            font-weight: 800;
            text-transform: uppercase;
            font-size: 13px;
        }

        .money {
            text-align: right;
        }

        .money strong {
            display: block;
            font-size: 25px;
        }

        .history {
            margin-top: 15px;
            color: #aaa;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
        }

        .actions {
            margin-top: 18px;
            display: flex;
            gap: 10px;
        }

        .button {
            display: inline-block;
            padding: 10px 13px;
            border: 1px solid #333;
            border-radius: 8px;
            text-decoration: none;
            background: #222;
        }

        @media (max-width: 900px) {
            .summary {
                grid-template-columns: 1fr 1fr;
            }

            .header {
                flex-direction: column;
            }

            .money {
                text-align: left;
            }
        }
    </style>
</head>

<body>
<main>
    <a href="{{ route('dashboard') }}">← Money Imp</a>

    <h1>{{ $month->format('F Y') }} Billing</h1>

    <p class="muted">
        What Money Imp expected from recent billing history
        versus what actually exists in FreeAgent.
    </p>

    @if (session('success'))
        <div style="margin:20px 0;padding:15px 18px;background:#18351f;border:1px solid #295b35;border-radius:10px;">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->has('billing'))
        <div style="margin:20px 0;padding:15px 18px;background:#351818;border:1px solid #5b2929;border-radius:10px;">
            {{ $errors->first('billing') }}
        </div>
    @endif

    <section class="summary">
        <div class="stat">
            <strong>{{ $summary['expected_clients'] }}</strong>
            <span>Recurring clients detected</span>
        </div>

        <div class="stat">
            <strong>{{ $summary['issued'] }}</strong>
            <span>Looks invoiced</span>
        </div>

        <div class="stat">
            <strong class="danger">
                {{ $summary['missing'] }}
            </strong>
            <span>Missing</span>
        </div>

        <div class="stat">
            <strong>
                {{ $summary['drafts'] ?? 0 }}
            </strong>
            <span>Drafted</span>
        </div>

        <div class="stat">
            <strong class="warning">
                {{ $summary['underbilled'] }}
            </strong>
            <span>Needs review</span>
        </div>

        <div class="stat">
            <strong class="danger">
                £{{ number_format($summary['potential_missing_value'], 2) }}
            </strong>
            <span>Potential unbilled</span>
        </div>
    </section>

    <form
        method="POST"
        action="{{ route('billing.create-bulk-drafts') }}"
        id="bulk-billing-form"
    >
        @csrf
    </form>

        <div
            style="
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:20px;
                padding:18px 20px;
                margin-bottom:18px;
                background:#181818;
                border:1px solid #292929;
                border-radius:14px;
            "
        >
            <div>
                <strong id="selected-count">0 selected</strong>
                <div class="muted">
                    Draft only. Nothing will be emailed or sent.
                </div>
            </div>

            <div style="display:flex;gap:10px;">
                <button
                    type="button"
                    class="button"
                    id="select-all-missing"
                >
                    Select all missing
                </button>

                <button
                    type="submit"
                    form="bulk-billing-form"
                    class="button"
                    style="background:white;color:#111;font-weight:800;cursor:pointer;"
                    onclick="return confirm('Create FreeAgent draft invoices for the selected clients? Nothing will be sent.');"
                >
                    Create Selected Drafts
                </button>
            </div>
        </div>

    @foreach ($rows as $row)
        <article class="client">
            <div class="header">
                <div>
                    <div class="name">
                        @if ($row['status'] === 'missing')
                            <label
                                style="
                                    display:inline-flex;
                                    align-items:center;
                                    gap:10px;
                                "
                            >
                                <input
                                    type="checkbox"
                                    name="clients[]"
                                    value="{{ $row['client']->id }}"
                                    data-billing-client
                                    form="bulk-billing-form"
                                >

                                <span>{{ $row['client']->name }}</span>
                            </label>
                        @else
                            {{ $row['client']->name }}
                        @endif
                    </div>

                    <div class="muted">
                        Seen in {{ $row['history_months'] }}
                        of the previous 4 months
                    </div>

                    <div class="history">
                        @foreach ($row['history'] as $period => $amount)
                            <span>
                                {{ $period }}
                                £{{ number_format($amount, 2) }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div class="money">
                    @if ($row['status'] === 'missing')
                        <span class="status danger">
                            Missing
                        </span>
                    @elseif ($row['status'] === 'underbilled')
                        <span class="status warning">
                            Review
                        </span>
                    @else
                        <span class="status">
                            Issued ✓
                        </span>
                    @endif

                    <strong>
                        Expected £{{ number_format($row['expected_amount'], 2) }}
                    </strong>

                    <div class="muted">
                        July actual:
                        £{{ number_format($row['actual_amount'], 2) }}
                    </div>

                    @if ($row['potential_missing_amount'] > 0)
                        <div class="danger">
                            Potentially missing
                            £{{ number_format($row['potential_missing_amount'], 2) }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="actions">
                @if ($row['status'] === 'missing')
                    <form
                        method="POST"
                        action="{{ route('billing.create-draft', $row['client']) }}"
                    >
                        @csrf

                        <button
                            class="button"
                            type="submit"
                            style="background:white;color:#111;font-weight:800;cursor:pointer;"
                        >
                            Create FreeAgent Draft
                        </button>
                    </form>
                @elseif ($row['status'] === 'draft')
                    <span
                        class="button"
                        style="border-color:#295b35;color:#8ee8a1;"
                    >
                        Draft created ✓
                    </span>
                @endif

                <a
                    class="button"
                    href="{{ route('debtors.index') }}"
                >
                    Check account
                </a>

                <a
                    class="button"
                    href="{{ route('reconciliation.index', ['tab' => 'known']) }}"
                >
                    Check payments
                </a>
            </div>
        </article>
    @endforeach
</main>
</body>
</html>
