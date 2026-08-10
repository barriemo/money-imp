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
        * {
            box-sizing: border-box;
        }

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
            max-width: 1100px;
            margin: auto;
            padding: 48px 24px 80px;
        }

        h1 {
            margin: 0;
            font-size: 54px;
            letter-spacing: -2px;
        }

        .intro {
            margin: 6px 0 34px;
            color: #888;
            font-size: 18px;
        }

        .section-title {
            margin-top: 38px;
            color: #888;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 14px;
            margin-top: 12px;
        }

        .card {
            display: block;
            min-height: 145px;
            padding: 22px;
            border-radius: 14px;
            border: 1px solid #292929;
            background: #181818;
            color: inherit;
            text-decoration: none;
        }

        .card:hover {
            border-color: #555;
        }

        .card strong {
            display: block;
            margin-bottom: 8px;
            font-size: 21px;
        }

        .card span {
            color: #8d8d8d;
            line-height: 1.5;
        }

        .primary {
            background: #f5f5f5;
            color: #111;
        }

        .primary span {
            color: #555;
        }

        @media (max-width: 800px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<main>
    <h1>Money Imp</h1>

    <p class="intro">
        Your money. Minus the bullshit.
    </p>

    <div class="section-title">
        Everyday
    </div>

    <section class="grid">
        <a
            class="card primary"
            href="{{ route('work-log.index') }}"
        >
            <strong>Log Work</strong>

            <span>
                Client. Time. What you did.
                Ten seconds and done.
            </span>
        </a>

        <a
            class="card"
            href="{{ route('reconciliation.index') }}"
        >
            <strong>Reconciliation</strong>

            <span>
                Match incoming money to clients
                and invoices.
            </span>
        </a>

        <a
            class="card"
            href="{{ route('money-out.index') }}"
        >
            <strong>Money Out</strong>

            <span>
                Review spending and teach
                supplier attribution.
            </span>
        </a>
    </section>

    <div class="section-title">
        Billing & cash
    </div>

    <section class="grid">
        <a
            class="card"
            href="{{ route('billing.index') }}"
        >
            <strong>Billing</strong>

            <span>
                Find missing monthly billing
                and create drafts.
            </span>
        </a>

        <a
            class="card"
            href="{{ route('billing.review') }}"
        >
            <strong>Draft Review</strong>

            <span>
                Approve and send FreeAgent
                invoices.
            </span>
        </a>

        <a
            class="card"
            href="{{ route('debtors.index') }}"
        >
            <strong>Who Owes Us Money?</strong>

            <span>
                Outstanding invoices and
                overdue balances.
            </span>
        </a>

        <a
            class="card"
            href="{{ route('chase.index') }}"
        >
            <strong>Chase Queue</strong>

            <span>
                Decide who needs chased today.
            </span>
        </a>
    </section>

    <div class="section-title">
        Imports
    </div>

    <section class="grid">
        <a
            class="card"
            href="{{ route('imports.index') }}"
        >
            <strong>Import Inbox</strong>

            <span>
                All statement imports and
                their status.
            </span>
        </a>

        <a
            class="card"
            href="{{ route('money-out.import.index') }}"
        >
            <strong>Import Statement</strong>

            <span>
                Drop in a bank or card
                statement.
            </span>
        </a>
    </section>

    <div class="section-title">
        Connections
    </div>

    <section class="grid">
        <a
            class="card"
            href="{{ route(
                'integrations.freeagent.health'
            ) }}"
        >
            <strong>FreeAgent</strong>

            <span>
                Connection health and accounting
                system of record.
            </span>
        </a>
    </section>
</main>
</body>
</html>
