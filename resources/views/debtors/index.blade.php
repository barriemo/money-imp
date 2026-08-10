<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Debtors — Money Imp</title>

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
            gap: 14px;
            margin: 30px 0 16px;
        }

        .bands {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 32px;
        }

        .stat,
        .band {
            padding: 20px;
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
        }

        .stat strong,
        .band strong {
            display: block;
            font-size: 28px;
        }

        .stat span,
        .band span {
            color: #888;
        }

        .danger {
            color: #ff8e8e;
        }

        .warning {
            color: #ffc46b;
        }

        .debtor {
            margin-bottom: 16px;
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
            overflow: hidden;
        }

        .debtor-header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 22px;
        }

        .client-name {
            font-size: 22px;
            font-weight: 800;
        }

        .amount {
            font-size: 25px;
            font-weight: 800;
            text-align: right;
        }

        .meta {
            margin-top: 5px;
            color: #888;
        }

        .actions {
            margin-top: 14px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-block;
            padding: 9px 12px;
            border-radius: 8px;
            text-decoration: none;
            border: 1px solid #333;
            background: #222;
        }

        .button.primary {
            background: white;
            color: #111;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #141414;
        }

        th,
        td {
            padding: 13px 22px;
            text-align: left;
            border-top: 1px solid #292929;
        }

        th {
            color: #777;
            font-size: 13px;
            text-transform: uppercase;
        }

        td:last-child,
        th:last-child {
            text-align: right;
        }

        .overdue {
            color: #ff8e8e;
            font-weight: 700;
        }

        @media (max-width: 900px) {
            .summary {
                grid-template-columns: 1fr 1fr;
            }

            .bands {
                grid-template-columns: 1fr 1fr;
            }

            .debtor-header {
                flex-direction: column;
            }

            .amount {
                text-align: left;
            }
        }
    </style>
</head>

<body>
<main>
    <a href="{{ route('dashboard') }}">← Money Imp</a>

    <h1>Who Owes Us Money?</h1>

    <p class="muted">
        Operational balances after approved Money Imp payment allocations.
    </p>

    <section class="summary">
        <div class="stat">
            <strong>{{ $summary['clients'] }}</strong>
            <span>Clients owing</span>
        </div>

        <div class="stat">
            <strong>{{ $summary['invoices'] }}</strong>
            <span>Open invoices</span>
        </div>

        <div class="stat">
            <strong>
                £{{ number_format($summary['total'], 2) }}
            </strong>
            <span>Total outstanding</span>
        </div>

        <div class="stat">
            <strong class="danger">
                £{{ number_format($summary['overdue_total'], 2) }}
            </strong>
            <span>
                {{ $summary['overdue_invoices'] }} overdue invoices
            </span>
        </div>
    </section>

    <section class="bands">
        <div class="band">
            <strong>
                £{{ number_format($summary['bands']['current'], 2) }}
            </strong>
            <span>Current</span>
        </div>

        <div class="band">
            <strong>
                £{{ number_format($summary['bands']['1_30'], 2) }}
            </strong>
            <span>1–30 days</span>
        </div>

        <div class="band">
            <strong class="warning">
                £{{ number_format($summary['bands']['31_60'], 2) }}
            </strong>
            <span>31–60 days</span>
        </div>

        <div class="band">
            <strong class="warning">
                £{{ number_format($summary['bands']['61_90'], 2) }}
            </strong>
            <span>61–90 days</span>
        </div>

        <div class="band">
            <strong class="danger">
                £{{ number_format($summary['bands']['90_plus'], 2) }}
            </strong>
            <span>90+ days</span>
        </div>
    </section>

    @foreach ($clients as $row)
        <article class="debtor">
            <div class="debtor-header">
                <div>
                    <div class="client-name">
                        {{ $row['client']->name }}
                    </div>

                    <div class="meta">
                        {{ $row['invoice_count'] }} invoice(s)
                        · {{ $row['overdue_count'] }} overdue

                        @if ($row['oldest_days_overdue'] > 0)
                            · oldest
                            {{ $row['oldest_days_overdue'] }}
                            days overdue
                        @endif
                    </div>

                    <div class="actions">
                        <a
                            class="button primary"
                            href="mailto:{{ $row['client']->email }}"
                        >
                            Chase now
                        </a>

                        <a
                            class="button"
                            href="{{ route('reconciliation.index', ['tab' => 'known']) }}"
                        >
                            Check payments
                        </a>
                    </div>
                </div>

                <div class="amount">
                    £{{ number_format($row['total_outstanding'], 2) }}
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Issued</th>
                        <th>Due</th>
                        <th>Age</th>
                        <th>Outstanding</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($row['invoices'] as $invoiceRow)
                        <tr>
                            <td>
                                {{ $invoiceRow['invoice']->invoice_number }}
                            </td>

                            <td>
                                {{ optional($invoiceRow['invoice']->invoice_date)->format('d M Y') ?? '—' }}
                            </td>

                            <td>
                                {{ optional($invoiceRow['invoice']->due_date)->format('d M Y') ?? '—' }}
                            </td>

                            <td>
                                @if ($invoiceRow['days_overdue'] > 0)
                                    <span class="overdue">
                                        {{ $invoiceRow['days_overdue'] }}
                                        days
                                    </span>
                                @else
                                    Current
                                @endif
                            </td>

                            <td>
                                £{{ number_format($invoiceRow['outstanding'], 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </article>
    @endforeach
</main>
</body>
</html>
