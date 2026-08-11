<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Financial Truth · Money Imp</title>

    <style>
        body {
            margin: 0;
            padding: 40px;
            background: #111;
            color: #eee;
            font-family: system-ui, sans-serif;
        }

        main {
            max-width: 1180px;
            margin: auto;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }

        .card {
            padding: 22px;
            background: #191919;
            border: 1px solid #333;
            border-radius: 14px;
        }

        .value {
            margin-top: 8px;
            font-size: 34px;
            font-weight: 800;
        }

        .warning {
            color: #ffbd75;
        }

        .good {
            color: #9ae6a8;
        }

        .muted {
            color: #999;
        }

        table {
            width: 100%;
            margin-top: 24px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px;
            border-bottom: 1px solid #333;
            text-align: left;
        }
    </style>
</head>
<body>
<main>
    <h1>Financial Truth</h1>

    <p class="muted">
        Money Imp only calls a number true
        when the underlying source has been verified.
    </p>

    <div class="grid">
        <article class="card">
            <div>Verified cash</div>

            <div class="value">
                £{{ number_format(
                    $truth['cash']['available'],
                    2
                ) }}
            </div>

            <div class="muted">
                Confidence:
                {{ $truth['cash']['confidence'] }}%
            </div>
        </article>

        <article class="card">
            <div>Credit card debt</div>

            <div class="value warning">
                £{{ number_format(
                    $truth['cash']['credit_card_debt'],
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>Known liabilities</div>

            <div class="value warning">
                £{{ number_format(
                    $truth['cash']['known_liabilities'],
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>Net cash position</div>

            <div class="value">
                £{{ number_format(
                    $truth['cash']['net_position'],
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>Invoice ledger outstanding</div>

            <div class="value">
                £{{ number_format(
                    $truth['receivables']
                        ['ledger_outstanding'],
                    2
                ) }}
            </div>

            <div class="warning">
                NOT YET VERIFIED DEBTORS
            </div>
        </article>

        <article class="card">
            <div>VAT</div>

            <div class="value warning">
                £{{ number_format(
                    $truth['liabilities']['vat'],
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>PAYE</div>

            <div class="value warning">
                £{{ number_format(
                    $truth['liabilities']['paye'],
                    2
                ) }}
            </div>
        </article>
    </div>


    @if (session('success'))
        <div class="card good" style="margin: 24px 0;">
            {{ session('success') }}
        </div>
    @endif

    <h2>Verify today's truth</h2>

    <div class="grid">
        <article class="card">
            <h3>Account balance</h3>

            <form
                method="POST"
                action="{{ route(
                    'financial-truth.balance.store'
                ) }}"
            >
                @csrf

                <p>
                    <select
                        name="bank_account_id"
                        required
                        style="width: 100%; padding: 12px;"
                    >
                        @foreach ($bankAccounts as $account)
                            <option value="{{ $account->id }}">
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </p>

                <p>
                    <input
                        type="number"
                        step="0.01"
                        name="balance"
                        placeholder="Actual balance"
                        required
                        style="width: 100%; padding: 12px;"
                    >
                </p>

                <p>
                    <input
                        type="datetime-local"
                        name="balance_at"
                        value="{{ now()->format('Y-m-d\TH:i') }}"
                        required
                        style="width: 100%; padding: 12px;"
                    >
                </p>

                <p>
                    <input
                        type="text"
                        name="notes"
                        placeholder="Checked in RBS app, statement, etc."
                        style="width: 100%; padding: 12px;"
                    >
                </p>

                <button type="submit">
                    Verify balance
                </button>
            </form>
        </article>

        <article class="card">
            <h3>Known liability</h3>

            <form
                method="POST"
                action="{{ route(
                    'financial-truth.liability.store'
                ) }}"
            >
                @csrf

                <p>
                    <select
                        name="type"
                        required
                        style="width: 100%; padding: 12px;"
                    >
                        <option value="vat">VAT</option>
                        <option value="paye">PAYE</option>
                        <option value="corporation_tax">
                            Corporation Tax
                        </option>
                        <option value="loan">Loan</option>
                        <option value="creditor">
                            Creditor
                        </option>
                        <option value="other">Other</option>
                    </select>
                </p>

                <p>
                    <input
                        type="text"
                        name="name"
                        placeholder="e.g. HMRC VAT"
                        required
                        style="width: 100%; padding: 12px;"
                    >
                </p>

                <p>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="amount"
                        placeholder="Amount still owed"
                        required
                        style="width: 100%; padding: 12px;"
                    >
                </p>

                <p>
                    <input
                        type="date"
                        name="due_date"
                        style="width: 100%; padding: 12px;"
                    >
                </p>

                <p>
                    <input
                        type="text"
                        name="notes"
                        placeholder="Notes / payment arrangement"
                        style="width: 100%; padding: 12px;"
                    >
                </p>

                <button type="submit">
                    Verify liability
                </button>
            </form>
        </article>
    </div>

    <h2>Accounts</h2>

    <table>
        <thead>
        <tr>
            <th>Account</th>
            <th>Balance</th>
            <th>As at</th>
            <th>Source</th>
            <th>Confidence</th>
        </tr>
        </thead>

        <tbody>
        @foreach ($truth['accounts'] as $account)
            <tr>
                <td>{{ $account['name'] }}</td>

                <td>
                    @if ($account['verified'])
                        £{{ number_format(
                            $account['balance'],
                            2
                        ) }}
                    @else
                        <span class="warning">
                            UNVERIFIED
                        </span>
                    @endif
                </td>

                <td>
                    {{ $account['balance_at']
                        ?->format('d M Y H:i')
                        ?? '—' }}
                </td>

                <td>
                    {{ $account['source'] ?? '—' }}
                </td>

                <td>
                    {{ $account['confidence'] }}%
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</main>
</body>
</html>
