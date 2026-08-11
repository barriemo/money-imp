<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title>
        {{ $supplier->supplier_name }}
        · Money Imp
    </title>

    <style>
        body {
            margin: 0;
            padding: 40px;
            background: #111;
            color: #eee;
            font-family: system-ui, sans-serif;
        }

        main {
            max-width: 1300px;
            margin: auto;
        }

        a {
            color: #eee;
        }

        .muted {
            color: #999;
        }

        .warning {
            color: #ffbd75;
        }

        .good {
            color: #9ae6a8;
        }

        .card {
            margin: 14px 0;
            padding: 20px;
            background: #191919;
            border: 1px solid #333;
            border-radius: 14px;
        }

        .transaction {
            display: grid;
            grid-template-columns:
                120px
                minmax(280px, 1fr)
                120px
                minmax(430px, 1fr);
            gap: 18px;
            align-items: center;
        }

        select,
        button {
            padding: 9px;
        }

        form {
            display: flex;
            gap: 8px;
            align-items: center;
        }
    </style>
</head>

<body>
<main>
    <p>
        <a href="{{ route('suppliers.index') }}">
            ← Supplier Intelligence
        </a>
    </p>

    <h1>
        {{ $supplier->supplier_name }}
    </h1>

    <p class="muted">
        Review every cost once.
        Money Imp will remember what it belongs to.
    </p>

    @if (session('success'))
        <div class="card good">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="card warning">
            {{ $errors->first() }}
        </div>
    @endif

    @forelse ($transactions as $transaction)
        <article class="card transaction">
            <div>
                {{ $transaction
                    ->transaction_date
                    ?->format('d M Y') }}
            </div>

            <div>
                <strong>
                    {{ $transaction->description }}
                </strong>

                @if ($transaction->reference)
                    <div class="muted">
                        {{ $transaction->reference }}
                    </div>
                @endif

                <div class="muted">
                    Status:
                    {{ $transaction
                        ->cost_review_status
                        ?? 'unreviewed' }}

                    · Purpose:
                    {{ $transaction
                        ->cost_purpose
                        ?? 'unknown' }}
                </div>
            </div>

            <div>
                <strong>
                    £{{ number_format(
                        abs(
                            (float)
                            $transaction->amount
                        ),
                        2
                    ) }}
                </strong>
            </div>

            <div>
                <form
                    method="POST"
                    action="{{ route(
                        'suppliers.transactions.update',
                        [
                            $supplier,
                            $transaction,
                        ]
                    ) }}"
                >
                    @csrf

                    <select name="purpose">
                        @foreach ([
                            'client' => 'Client',
                            'internal' => 'Internal',
                            'shared' => 'Shared',
                            'cancel' => 'Cancel / Waste',
                            'unknown' => 'Still Unknown',
                        ] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(
                                    $transaction
                                        ->cost_purpose
                                    === $value
                                )
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>

                    <select name="client_id">
                        <option value="">
                            No client
                        </option>

                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit">
                        Save
                    </button>
                </form>
            </div>
        </article>
    @empty
        <p>
            No transactions found for this supplier.
        </p>
    @endforelse
</main>
</body>
</html>
