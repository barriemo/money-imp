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

    <p>
        <a href="{{ route(
            'suppliers.rules.index'
        ) }}">
            View learned rules
        </a>

        &nbsp; · &nbsp;

        <a href="{{ route(
            'suppliers.assets.index'
        ) }}">
            Review infrastructure
        </a>
    </p>

    <p class="muted">
        Why is this money going out,
        and who should be paying for it?
    </p>

    <div class="grid">
        <article class="card">
            <div>Client attributed</div>

            <div class="value good">
                £{{ number_format(
                    $clientSpend,
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>Internal overhead</div>

            <div class="value">
                £{{ number_format(
                    $internalSpend,
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>Shared cost</div>

            <div class="value">
                £{{ number_format(
                    $sharedSpend,
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>Waste / cancel</div>

            <div class="value warning">
                £{{ number_format(
                    $wasteSpend,
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>Still unknown</div>

            <div class="value warning">
                £{{ number_format(
                    $unknownSpend,
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>Potential recovery</div>

            <div class="value warning">
                £{{ number_format(
                    $potentialRecovery,
                    2
                ) }}
            </div>

            <div class="muted">
                Recoverable suppliers only,
                pending review.
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
            <th>Transactions</th>
            <th>Total</th>
            <th>Client</th>
            <th>Internal</th>
            <th>Shared</th>
            <th>Waste</th>
            <th>Unknown</th>
            <th>Recurring</th>
            <th>Action</th>
        </tr>
        </thead>

        <tbody>
        @forelse ($suppliers as $item)
            <tr>
                <td>
                    <strong>
                        {{ $item->supplier
                            ->supplier_name }}
                    </strong>

                    <div class="muted">
                        {{ $item->supplier
                            ->category ?? '—' }}
                    </div>
                </td>

                <td>
                    {{ $item->transactionCount }}
                </td>

                <td>
                    £{{ number_format(
                        $item->totalSpend,
                        2
                    ) }}
                </td>

                <td class="good">
                    £{{ number_format(
                        $item->clientSpend,
                        2
                    ) }}
                </td>

                <td>
                    £{{ number_format(
                        $item->internalSpend,
                        2
                    ) }}
                </td>

                <td>
                    £{{ number_format(
                        $item->sharedSpend,
                        2
                    ) }}
                </td>

                <td class="warning">
                    £{{ number_format(
                        $item->wasteSpend,
                        2
                    ) }}
                </td>

                <td class="warning">
                    £{{ number_format(
                        $item->unknownSpend,
                        2
                    ) }}
                </td>

                <td>
                    {{ $item->recurring
                        ? 'YES'
                        : 'NO' }}
                </td>

                <td>
                    <a href="{{ route(
                        'suppliers.transactions.index',
                        $item->supplier
                    ) }}">
                        Review
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="10">
                    No suppliers configured.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</main>
</body>
</html>
