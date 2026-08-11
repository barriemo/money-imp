<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Supplier Intelligence · Money Imp</title>

    <style>
        body {
            margin: 0;
            padding: 40px;
            background: #111;
            color: #eee;
            font-family: system-ui, sans-serif;
        }

        main {
            max-width: 1250px;
            margin: auto;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .card {
            background: #191919;
            border: 1px solid #333;
            border-radius: 14px;
            padding: 20px;
        }

        .value {
            font-size: 30px;
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
            margin-top: 30px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #333;
            text-align: left;
        }

        input,
        button {
            padding: 10px;
        }
    </style>
</head>

<body>
<main>
    <h1>Supplier Intelligence</h1>

    <p class="muted">
        Why is this money going out,
        and who should be paying for it?
    </p>

    <div class="grid">
        <article class="card">
            <div>Average monthly spend</div>

            <div class="value">
                £{{ number_format(
                    $totalMonthly,
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>Annualised spend</div>

            <div class="value">
                £{{ number_format(
                    $totalAnnual,
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>Unallocated spend</div>

            <div class="value warning">
                £{{ number_format(
                    $totalUnallocated,
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>Recoverable leakage</div>

            <div class="value warning">
                £{{ number_format(
                    $recoverableLeakage,
                    2
                ) }}
            </div>
        </article>
    </div>

    <h2>Add supplier</h2>

    <form
        method="POST"
        action="{{ route('suppliers.store') }}"
    >
        @csrf

        <input
            name="supplier_name"
            placeholder="20i / EUKhost / Adobe"
            required
        >

        <input
            name="category"
            placeholder="hosting / software / domain"
        >

        <label>
            <input
                type="checkbox"
                name="recoverable"
                value="1"
            >
            Recoverable from clients
        </label>

        <button type="submit">
            Add supplier
        </button>
    </form>

    <table>
        <thead>
        <tr>
            <th>Supplier</th>
            <th>Category</th>
            <th>Transactions</th>
            <th>Avg monthly</th>
            <th>Annualised</th>
            <th>Allocated</th>
            <th>Unknown</th>
            <th>Recurring</th>
            <th>Last seen</th>
        </tr>
        </thead>

        <tbody>
        @forelse ($suppliers as $item)
            <tr>
                <td>
                    {{ $item->supplier
                        ->supplier_name }}
                </td>

                <td>
                    {{ $item->supplier
                        ->category ?? '—' }}
                </td>

                <td>
                    {{ $item->transactionCount }}
                </td>

                <td>
                    £{{ number_format(
                        $item->averageMonthlySpend,
                        2
                    ) }}
                </td>

                <td>
                    £{{ number_format(
                        $item->annualisedSpend,
                        2
                    ) }}
                </td>

                <td class="good">
                    £{{ number_format(
                        $item->allocatedSpend,
                        2
                    ) }}
                </td>

                <td class="warning">
                    £{{ number_format(
                        $item->unallocatedSpend,
                        2
                    ) }}
                </td>

                <td>
                    {{ $item->recurring
                        ? 'YES'
                        : 'NO' }}
                </td>

                <td>
                    {{ $item->lastSeen ?? '—' }}

                    <div style="margin-top: 6px;">
                        <a href="{{ route(
                            'suppliers.transactions.index',
                            $item->supplier
                        ) }}">
                            View transactions
                        </a>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9">
                    No suppliers configured yet.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</main>
</body>
</html>
