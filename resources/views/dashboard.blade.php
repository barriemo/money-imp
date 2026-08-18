<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Money Imp</title>

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
            max-width: 1150px;
            margin: auto;
            padding: 48px 24px 80px;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        h1 {
            margin: 0;
            font-size: 54px;
            letter-spacing: -2px;
        }

        .intro {
            margin: 6px 0 32px;
            color: #888;
            font-size: 18px;
        }

        .metrics {
            display: grid;
            grid-template-columns:
                repeat(4, 1fr);
            gap: 12px;
        }

        .metric,
        .action,
        .nav-card {
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
        }

        .metric {
            padding: 20px;
        }

        .metric strong {
            display: block;
            margin-bottom: 4px;
            font-size: 28px;
        }

        .metric span {
            color: #888;
        }

        .section-title {
            margin-top: 38px;
            margin-bottom: 12px;
            color: #777;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .actions {
            display: grid;
            gap: 10px;
        }

        .action {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 18px;
        }

        .action:hover,
        .nav-card:hover {
            border-color: #555;
        }

        .action strong {
            display: block;
            font-size: 18px;
        }

        .action span {
            color: #888;
        }

        .dot {
            width: 10px;
            height: 10px;
            flex: 0 0 10px;
            margin-top: 7px;
            border-radius: 50%;
        }

        .red { background: #e85a5a; }
        .orange { background: #e9a23b; }
        .green { background: #67c77a; }

        .action-left {
            display: flex;
            gap: 12px;
        }

        .nav {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 12px;
        }

        .nav-card {
            min-height: 120px;
            padding: 20px;
        }

        .nav-card strong {
            display: block;
            margin-bottom: 7px;
            font-size: 18px;
        }

        .nav-card span {
            color: #888;
            line-height: 1.4;
        }

        .primary {
            background: #f5f5f5;
            color: #111;
        }

        .primary span {
            color: #555;
        }

        @media (max-width: 800px) {
            .metrics {
                grid-template-columns: 1fr 1fr;
            }

            .nav {
                grid-template-columns: 1fr;
            }

            .action {
                display: block;
            }
        }
    </style>
</head>

<body>
<main>
    <h1>
        Good afternoon, {{ auth()->user()->name }}.
    </h1>

    <p class="intro">
        Here's where the money is right now.
    </p>

    <div class="section-title">
        CFO Position
    </div>

    <section class="actions">
        <div class="action">
            <div class="action-left">
                <div class="dot orange"></div>

                <div>
                    <strong>
                        {{ strtoupper($cfo->overallStatus) }}
                    </strong>

                    <span>
                        Confidence:
                        {{ $cfo->overallConfidence }}%
                    </span>
                </div>
            </div>
        </div>
    </section>

    @if (count($cfo->risks))
        <div class="section-title">
            Risks
        </div>

        <section class="actions">
            @foreach ($cfo->risks as $risk)
                <div class="action">
                    <div class="action-left">
                        <div class="dot red"></div>

                        <div>
                            <span>{{ $risk }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>
    @endif


    <section class="metrics">
        <div class="metric">
            <strong>
                £{{ number_format(
                    $brief['cash']['bank'],
                    2
                ) }}
            </strong>

            <span>Cash in bank</span>
        </div>

        <div class="metric">
            <strong>
                £{{ number_format(
                    $brief['receivables']['outstanding'],
                    2
                ) }}
            </strong>

            <span>Outstanding sales</span>
        </div>

        <div class="metric">
            <strong>
                £{{ number_format(
                    $brief['work']['review_value']
                    + $brief['work']['ready_value'],
                    2
                ) }}
            </strong>

            <span>Potential unbilled work</span>
        </div>

        <div class="metric">
            <strong>
                {{ $brief['billing']['draft_count'] }}
            </strong>

            <span>Invoice drafts</span>
        </div>
    </section>

    <div class="section-title">
        Needs your attention
    </div>

    <section class="actions">
        @forelse ($brief['actions'] as $action)
            <a
                class="action"
                href="{{ route($action['route']) }}"
            >
                <div class="action-left">
                    <div
                        class="dot {{ $action['level'] }}"
                    ></div>

                    <div>
                        <strong>
                            {{ $action['title'] }}
                        </strong>

                        <span>
                            {{ $action['detail'] }}
                        </span>
                    </div>
                </div>

                <strong>→</strong>
            </a>
        @empty
            <div class="action">
                <div class="action-left">
                    <div class="dot green"></div>

                    <div>
                        <strong>Nothing urgent.</strong>

                        <span>
                            Money Imp has nothing
                            shouting for attention.
                        </span>
                    </div>
                </div>
            </div>
        @endforelse
    </section>

    <div class="section-title">
        Everyday
    </div>

    <section class="nav">
        <a
            class="nav-card primary"
            href="{{ route('work-log.index') }}"
        >
            <strong>Log Work</strong>

            <span>
                Client. Time. Description.
                Done.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('work-review.index') }}"
        >
            <strong>
                Unbilled Work
                @if ($brief['work']['review_count'] > 0)
                    ({{ $brief['work']['review_count'] }})
                @endif
            </strong>

            <span>
                Decide what becomes revenue.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('reconciliation.index') }}"
        >
            <strong>Reconciliation</strong>

            <span>
                Match incoming cash to invoices.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('billing.index') }}"
        >
            <strong>Billing</strong>

            <span>
                Find what we've forgotten
                to invoice.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('billing.review') }}"
        >
            <strong>
                Draft Review
                @if (
                    $brief['billing']['awaiting_approval']
                    > 0
                )
                    ({{ $brief['billing']['awaiting_approval'] }})
                @endif
            </strong>

            <span>
                Approve and send invoices.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('debtors.index') }}"
        >
            <strong>Who Owes Us Money?</strong>

            <span>
                £{{ number_format(
                    $brief['receivables']['outstanding'],
                    2
                ) }} outstanding.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('money-out.index') }}"
        >
            <strong>
                Money Out
                @if (
                    $brief['money_out']['review_count']
                    > 0
                )
                    ({{ $brief['money_out']['review_count'] }})
                @endif
            </strong>

            <span>
                Review spending and attribution.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('imports.index') }}"
        >
            <strong>Import Inbox</strong>

            <span>
                Statements and import history.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('chase.index') }}"
        >
            <strong>
                Chase Debtors
                @if (
                    $brief['receivables']['overdue_count']
                    > 0
                )
                    ({{ $brief['receivables']['overdue_count'] }})
                @endif
            </strong>

            <span>
                £{{ number_format(
                    $brief['receivables']['overdue_value'],
                    2
                ) }} overdue.
            </span>
        </a>
    </section>
</main>
</body>
</html>
