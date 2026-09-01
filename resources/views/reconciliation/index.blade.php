<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reconciliation — Money Imp</title>

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
            font-size: 38px;
            margin-bottom: 8px;
        }

        .tabs {
            display: flex;
            gap: 12px;
            margin: 28px 0;
            flex-wrap: wrap;
        }

        .tabs a {
            padding: 10px 14px;
            border: 1px solid #333;
            border-radius: 8px;
            text-decoration: none;
        }

        .tabs a.active {
            background: white;
            color: #111;
        }

        .flash {
            margin: 20px 0;
            padding: 15px 18px;
            border-radius: 10px;
            background: #18351f;
            border: 1px solid #295b35;
        }

        .transaction {
            padding: 24px;
            margin-bottom: 14px;
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
        }

        .amount {
            font-size: 28px;
            font-weight: 800;
        }

        .meta {
            margin-top: 5px;
            color: #888;
        }

        .description {
            margin: 16px 0;
            font-family: monospace;
            color: #ccc;
        }

        .client {
            margin: 15px 0;
            font-size: 18px;
        }

        form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-top: 10px;
        }

        input,
        select,
        button {
            padding: 11px 13px;
            border-radius: 8px;
            border: 1px solid #333;
            font: inherit;
        }

        input,
        select {
            background: #222;
            color: white;
        }

        .client-search {
            min-width: 320px;
        }

        .invoice-select {
            min-width: 360px;
        }

        .amount-input {
            width: 140px;
        }

        button {
            cursor: pointer;
            font-weight: 700;
        }

        .ignore {
            background: transparent;
            color: #aaa;
        }

        .invoice {
            margin-top: 16px;
            padding: 15px;
            background: #131313;
            border-radius: 10px;
        }
    </style>
</head>

<body>
<main>
    <a href="{{ route('dashboard') }}">← Money Imp</a>

    <h1>Reconciliation Inbox</h1>

    @if (session('success'))
        <div class="flash">
            {{ session('success') }}
        </div>
    @endif

    <div class="tabs">
        @foreach ([
            'unknown' => 'Unknown',
            'known' => 'Client known',
            'ready' => 'Ready',
            'ignored' => 'Ignored',
        ] as $key => $label)
            <a
                class="{{ $tab === $key ? 'active' : '' }}"
                href="{{ route('reconciliation.index', ['tab' => $key]) }}"
            >
                {{ $label }} {{ $counts[$key] }}
            </a>
        @endforeach

        <a
            href="{{ route('reconciliation.commercial.index') }}"
        >
            Commercial services
        </a>
    </div>

    <datalist id="money-imp-clients">
        @foreach ($clients as $client)
            <option value="{{ $client->name }}"></option>
        @endforeach
    </datalist>

    @foreach ($transactions as $transaction)
        <article class="transaction">
            <div class="amount">
                £{{ number_format((float) $transaction->amount, 2) }}
            </div>

            <div class="meta">
                {{ $transaction->transaction_date->format('d M Y') }}
                · {{ $transaction->bankAccount->name }}
            </div>

            <div class="description">
                {{ $transaction->description }}
            </div>

            @if ($transaction->client)
                <div class="client">
                    Client:
                    <strong>{{ $transaction->client->name }}</strong>
                </div>
            @endif

            @if ($tab === 'unknown' || $tab === 'known')
                <form
                    method="POST"
                    action="{{ route('reconciliation.assign-client', $transaction) }}"
                    data-client-picker
                >
                    @csrf

                    <input
                        class="client-search"
                        type="text"
                        list="money-imp-clients"
                        placeholder="Start typing client name…"
                        value="{{ $transaction->client?->name }}"
                        autocomplete="off"
                        data-client-name
                    >

                    <input
                        type="hidden"
                        name="client_id"
                        value="{{ $transaction->client_id }}"
                        data-client-id
                    >

                    <label>
                        <input
                            type="checkbox"
                            name="remember_identity"
                            value="1"
                            checked
                        >
                        Remember payer
                    </label>

                    <button type="submit">
                        Assign client
                    </button>
                </form>
            @endif

            @if (
                $transaction->client
                && $transaction->client->invoices->isNotEmpty()
                && $tab === 'known'
            )
                <div class="invoice">
                    <strong>Allocate payment</strong>

                    <form
                        method="POST"
                        action="{{ route('reconciliation.allocate-invoice', $transaction) }}"
                    >
                        @csrf

                        <select
                            class="invoice-select"
                            name="invoice_id"
                            required
                        >
                            <option value="">
                                Choose outstanding invoice…
                            </option>

                            @foreach ($transaction->client->invoices as $invoice)
                                <option value="{{ $invoice->id }}">
                                    {{ $invoice->invoice_number }}
                                    · £{{ number_format((float) $invoice->outstanding_amount, 2) }}
                                    · due {{ optional($invoice->due_date)->format('d M Y') ?? 'n/a' }}
                                </option>
                            @endforeach
                        </select>

                        <input
                            class="amount-input"
                            name="amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            value="{{ number_format((float) $transaction->amount, 2, '.', '') }}"
                            required
                        >

                        <button type="submit">
                            Allocate
                        </button>
                    </form>
                </div>
            @endif

            @if ($tab !== 'ignored')
                <form
                    method="POST"
                    action="{{ route('reconciliation.ignore', $transaction) }}"
                >
                    @csrf

                    <button class="ignore" type="submit">
                        Not client income
                    </button>
                </form>
            @endif
        </article>
    @endforeach

    {{ $transactions->links() }}
</main>

<script>
    const clients = @json(
        $clients->map(fn ($client) => [
            'id' => $client->id,
            'name' => $client->name,
        ])->values()
    );

    document.querySelectorAll('[data-client-picker]').forEach((form) => {
        const nameInput = form.querySelector('[data-client-name]');
        const idInput = form.querySelector('[data-client-id]');

        const resolveClient = () => {
            const value = nameInput.value.trim().toLowerCase();

            const match = clients.find(
                (client) => client.name.toLowerCase() === value
            );

            idInput.value = match ? match.id : '';

            return match;
        };

        nameInput.addEventListener('input', resolveClient);
        nameInput.addEventListener('change', resolveClient);

        form.addEventListener('submit', (event) => {
            if (!resolveClient()) {
                event.preventDefault();
                nameInput.focus();
                alert('Choose a client from the Money Imp client list.');
            }
        });
    });
</script>
</body>
</html>
