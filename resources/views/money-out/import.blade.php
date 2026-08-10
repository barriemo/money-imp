<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Import Money Out — Money Imp</title>

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
            max-width: 1100px;
            margin: auto;
            padding: 40px 24px 80px;
        }

        h1 {
            font-size: 42px;
            margin-bottom: 6px;
        }

        a { color: inherit; }

        .muted { color: #888; }

        .panel {
            margin-top: 24px;
            padding: 24px;
            border: 1px solid #292929;
            background: #181818;
            border-radius: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #bbb;
            font-size: 14px;
        }

        select,
        input,
        button {
            width: 100%;
            min-height: 44px;
            border-radius: 8px;
            border: 1px solid #333;
            padding: 10px 12px;
            font: inherit;
        }

        select,
        input {
            background: #202020;
            color: #fff;
        }

        button {
            cursor: pointer;
            font-weight: 800;
        }

        .primary {
            background: #fff;
            color: #111;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 18px;
        }

        .stat {
            padding: 18px;
            border-radius: 12px;
            background: #121212;
            border: 1px solid #292929;
        }

        .stat strong {
            display: block;
            font-size: 26px;
        }

        table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 11px 8px;
            border-bottom: 1px solid #292929;
            text-align: left;
        }

        .duplicate {
            color: #888;
        }

        .new {
            color: #8ee8a1;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .actions form {
            flex: 1;
        }

        .error {
            margin-top: 20px;
            padding: 14px;
            background: #351818;
            border: 1px solid #5b2929;
            border-radius: 10px;
        }

        @media (max-width: 750px) {
            .grid,
            .summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<main>
    <a href="{{ route('money-out.index') }}">
        ← Money Out
    </a>

    <h1>Import Statement</h1>

    <p class="muted">
        Analyse first. Import only new transactions.
    </p>

    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    @if (! $preview)
        <section class="panel">
            <form
                method="POST"
                action="{{ route('money-out.import.preview') }}"
                enctype="multipart/form-data"
            >
                @csrf

                <div class="grid">
                    <div>
                        <label for="bank_account_id">
                            Account
                        </label>

                        <select
                            id="bank_account_id"
                            name="bank_account_id"
                            required
                        >
                            <option value="">
                                Select account...
                            </option>

                            @foreach ($accounts as $account)
                                <option
                                    value="{{ $account->id }}"
                                >
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="provider">
                            Provider
                        </label>

                        <select
                            id="provider"
                            name="provider"
                            required
                        >
                            <option value="amex">
                                American Express
                            </option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <label for="statement">
                        CSV statement
                    </label>

                    <input
                        id="statement"
                        name="statement"
                        type="file"
                        accept=".csv,text/csv"
                        required
                    >
                </div>

                <button
                    class="primary"
                    type="submit"
                    style="margin-top:18px;"
                >
                    Analyse Statement
                </button>
            </form>
        </section>
    @else
        <section class="panel">
            <h2>
                {{ $preview['original_filename'] }}
            </h2>

            <p class="muted">
                {{ $preview['bank_account_name'] }}
                · American Express
            </p>

            <div class="summary">
                <div class="stat">
                    <strong>
                        {{ $preview['rows_seen'] }}
                    </strong>

                    <span>Rows found</span>
                </div>

                <div class="stat">
                    <strong>
                        {{ $preview['duplicates'] }}
                    </strong>

                    <span>Already known</span>
                </div>

                <div class="stat">
                    <strong>
                        {{ $preview['new_rows'] }}
                    </strong>

                    <span>New transactions</span>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                @foreach ($preview['rows'] as $row)
                    <tr>
                        <td>
                            {{ $row['date'] }}
                        </td>

                        <td>
                            {{ $row['description'] }}
                        </td>

                        <td>
                            £{{ number_format(
                                abs((float) $row['amount']),
                                2
                            ) }}
                        </td>

                        <td>
                            @if ($row['duplicate'])
                                <span class="duplicate">
                                    Already known
                                </span>
                            @else
                                <span class="new">
                                    New
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="actions">
                <form
                    method="POST"
                    action="{{ route('money-out.import.cancel') }}"
                >
                    @csrf

                    <button type="submit">
                        Cancel
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route('money-out.import.confirm') }}"
                >
                    @csrf

                    <button
                        class="primary"
                        type="submit"
                        @disabled($preview['new_rows'] === 0)
                    >
                        Import {{ $preview['new_rows'] }} New
                    </button>
                </form>
            </div>
        </section>
    @endif
</main>
</body>
</html>
