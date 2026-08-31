<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Supplier Payments — Money Imp</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #111;
            color: #f5f5f5;
            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        main {
            max-width: 1200px;
            margin: auto;
            padding: 40px 24px 80px;
        }

        a {
            color: inherit;
        }

        h1 {
            margin-bottom: 6px;
            font-size: 42px;
        }

        .muted {
            color: #888;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin: 28px 0;
        }

        .stat,
        .allocation {
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
        }

        .stat {
            padding: 18px;
        }

        .stat strong {
            display: block;
            font-size: 26px;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
        }

        .button,
        button {
            display: inline-block;
            padding: 12px 16px;
            border: 0;
            border-radius: 8px;
            background: #fff;
            color: #111;
            text-decoration: none;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
        }

        .success {
            margin: 20px 0;
            padding: 14px 16px;
            border: 1px solid #285d34;
            border-radius: 10px;
            background: #132419;
            color: #9ce9ac;
        }

        .errors {
            margin: 20px 0;
            padding: 14px 16px;
            border: 1px solid #713434;
            border-radius: 10px;
            background: #2a1616;
            color: #ffaaaa;
        }

        .allocation {
            margin-bottom: 14px;
            padding: 20px;
        }

        .allocation-top {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .supplier {
            font-size: 22px;
            font-weight: 800;
        }

        .amount {
            font-size: 24px;
            font-weight: 800;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-top: 20px;
        }

        .detail strong {
            display: block;
            margin-bottom: 4px;
        }

        .reason {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #292929;
            color: #bbb;
        }

        .allocation-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .reject {
            background: #2a1616;
            color: #ffaaaa;
            border: 1px solid #713434;
        }

        @media (max-width: 850px) {
            .summary,
            .details {
                grid-template-columns: 1fr 1fr;
            }

            .allocation-top {
                display: block;
            }
        }
    </style>
</head>

<body>
<main>
    <a href="{{ route('dashboard') }}">← Money Imp</a>

    <h1>Supplier Payments</h1>

    <p class="muted">
        Review suggested payments against outstanding supplier bills.
    </p>

    @if (session('success'))
        <div class="success">
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

    <section class="summary">
        <div class="stat">
            <strong>{{ $summary['suggested'] }}</strong>
            <span>Awaiting review</span>
        </div>

        <div class="stat">
            <strong>£{{ number_format($summary['suggested_value'], 2) }}</strong>
            <span>Suggested value</span>
        </div>

        <div class="stat">
            <strong>{{ $summary['approved'] }}</strong>
            <span>Approved</span>
        </div>

        <div class="stat">
            <strong>{{ $summary['rejected'] }}</strong>
            <span>Rejected</span>
        </div>
    </section>

    <div class="actions">
        <form
            method="POST"
            action="{{ route('supplier-payments.generate') }}"
        >
            @csrf

            <button type="submit">
                Generate Payment Suggestions
            </button>
        </form>

        <a
            class="button"
            href="{{ route('money-out.index') }}"
        >
            Money Out
        </a>
    </div>

    @forelse ($allocations as $allocation)
        <article class="allocation">
            <div class="allocation-top">
                <div>
                    <div class="supplier">
                        {{ $allocation->bill?->supplier?->name ?? 'Unknown supplier' }}
                    </div>

                    <div class="muted">
                        {{ $allocation->transaction?->transaction_date?->format('d M Y') }}

                        @if ($allocation->transaction?->bankAccount)
                            · {{ $allocation->transaction->bankAccount->name }}
                        @endif
                    </div>
                </div>

                <div class="amount">
                    £{{ number_format((float) $allocation->amount, 2) }}
                </div>
            </div>

            <div class="details">
                <div class="detail">
                    <strong>Payment</strong>
                    <span>
                        £{{ number_format(abs((float) $allocation->transaction->amount), 2) }}
                    </span>
                </div>

                <div class="detail">
                    <strong>Bill</strong>
                    <span>
                        £{{ number_format((float) $allocation->bill->gross_amount, 2) }}
                    </span>
                </div>

                <div class="detail">
                    <strong>Outstanding</strong>
                    <span>
                        £{{ number_format((float) $allocation->bill->outstanding_amount, 2) }}
                    </span>
                </div>

                <div class="detail">
                    <strong>Confidence</strong>
                    <span>
                        {{ $allocation->confidence !== null
                            ? number_format((float) $allocation->confidence, 0) . '%'
                            : '—'
                        }}
                    </span>
                </div>
            </div>

            <div class="reason">
                <strong>Bank description</strong><br>

                {{ $allocation->transaction->description ?: 'No description' }}

                @if ($allocation->reason)
                    <br><br>
                    <strong>Why Money Imp suggested it</strong><br>
                    {{ $allocation->reason }}
                @endif

                @if ($allocation->match_method)
                    <br><br>
                    <span class="muted">
                        Match method:
                        {{ str_replace('_', ' ', $allocation->match_method) }}
                    </span>
                @endif
            </div>

            <div class="allocation-actions">
                <form
                    method="POST"
                    action="{{ route('supplier-payments.approve', $allocation) }}"
                >
                    @csrf

                    <button type="submit">
                        Approve
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route('supplier-payments.reject', $allocation) }}"
                >
                    @csrf

                    <button
                        type="submit"
                        class="reject"
                    >
                        Reject
                    </button>
                </form>
            </div>
        </article>
    @empty
        <div class="allocation">
            <strong>No supplier payment suggestions.</strong>

            <p class="muted">
                Generate suggestions from the current bank transactions
                and outstanding supplier bills.
            </p>
        </div>
    @endforelse

    {{ $allocations->links() }}
</main>
</body>
</html>
