<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Chase Queue — Money Imp</title>

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
            font-size: 42px;
            margin-bottom: 6px;
        }

        .muted {
            color: #888;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin: 28px 0;
        }

        .stat {
            padding: 20px;
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
        }

        .stat strong {
            display: block;
            font-size: 28px;
        }

        .stat span {
            color: #888;
        }

        .client {
            margin-bottom: 16px;
            padding: 24px;
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .name {
            font-size: 22px;
            font-weight: 800;
        }

        .amount {
            font-size: 27px;
            font-weight: 800;
        }

        .meta {
            margin-top: 6px;
            color: #888;
        }

        .actions {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .button {
            display: inline-block;
            padding: 10px 14px;
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

        .invoice-list {
            margin-top: 20px;
            border-top: 1px solid #292929;
            padding-top: 14px;
        }

        .invoice-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 8px 0;
            color: #bbb;
        }

        textarea {
            width: 100%;
            min-height: 190px;
            margin-top: 18px;
            padding: 14px;
            border-radius: 10px;
            border: 1px solid #333;
            background: #121212;
            color: white;
            font: inherit;
        }

        @media (max-width: 800px) {
            .summary {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
<main>
    <a href="{{ route('dashboard') }}">← Money Imp</a>

    <h1>Chase Queue</h1>

    <p class="muted">
        The money is overdue. These are the people to chase first.
    </p>

    <section class="summary">
        <div class="stat">
            <strong>{{ $summary['clients'] }}</strong>
            <span>Clients to chase</span>
        </div>

        <div class="stat">
            <strong>
                £{{ number_format($summary['total'], 2) }}
            </strong>
            <span>Overdue cash</span>
        </div>

        <div class="stat">
            <strong>{{ $summary['high_priority'] }}</strong>
            <span>High priority</span>
        </div>
    </section>

    @foreach ($clients as $row)
        @php
            $subject = 'Outstanding invoices';

            $body = "Hi,\n\n"
                ."Just a quick note regarding the outstanding balance on your account.\n\n";

            foreach ($row['invoices'] as $invoiceRow) {
                $body .= "Invoice "
                    .$invoiceRow['invoice']->invoice_number
                    ." — £"
                    .number_format($invoiceRow['outstanding'], 2)
                    ." — "
                    .$invoiceRow['days_overdue']
                    ." days overdue\n";
            }

            $body .= "\nTotal outstanding: £"
                .number_format($row['total_outstanding'], 2)
                ."\n\nCould you please confirm when payment will be made?\n\nThanks,\nBarrie";
        @endphp

        <article class="client">
            <div class="header">
                <div>
                    <div class="name">
                        {{ $row['client']->name }}
                    </div>

                    <div class="meta">
                        {{ $row['invoice_count'] }} overdue invoice(s)
                        · oldest {{ $row['oldest_days_overdue'] }} days
                    </div>
                </div>

                <div class="amount">
                    £{{ number_format($row['total_outstanding'], 2) }}
                </div>
            </div>

            <div class="invoice-list">
                @foreach ($row['invoices'] as $invoiceRow)
                    <div class="invoice-row">
                        <span>
                            {{ $invoiceRow['invoice']->invoice_number }}
                            · {{ $invoiceRow['days_overdue'] }} days overdue
                        </span>

                        <strong>
                            £{{ number_format($invoiceRow['outstanding'], 2) }}
                        </strong>
                    </div>
                @endforeach
            </div>

            <textarea readonly>{{ $body }}</textarea>

            <div class="actions">
                @if ($row['client']->email)
                    <a
                        class="button primary"
                        href="mailto:{{ $row['client']->email }}?subject={{ urlencode($subject) }}&body={{ urlencode($body) }}"
                    >
                        Open chase email
                    </a>
                @endif

                <a
                    class="button"
                    href="{{ route('debtors.index') }}"
                >
                    View debtors
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
