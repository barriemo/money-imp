<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Supplier Rules · Money Imp</title>

    <style>
        body {
            margin: 0;
            padding: 40px;
            background: #111;
            color: #eee;
            font-family: system-ui, sans-serif;
        }

        main {
            max-width: 1200px;
            margin: auto;
        }

        a {
            color: #eee;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #333;
            text-align: left;
        }

        .muted {
            color: #999;
        }

        .good {
            color: #9ae6a8;
        }

        .warning {
            color: #ffbd75;
        }

        form {
            display: inline;
        }

        button {
            padding: 7px 10px;
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

    <h1>Learned Supplier Rules</h1>

    <p class="muted">
        These rules classify matching historical
        and future costs automatically.
    </p>

    <table>
        <thead>
        <tr>
            <th>Supplier</th>
            <th>Match</th>
            <th>Purpose</th>
            <th>Client</th>
            <th>Confidence</th>
            <th>Status</th>
            <th>Last applied</th>
            <th>Actions</th>
        </tr>
        </thead>

        <tbody>
        @forelse ($rules as $rule)
            <tr>
                <td>
                    {{ $rule->supplier
                        ?->supplier_name ?? '—' }}
                </td>

                <td>
                    {{ $rule->match_value }}
                </td>

                <td>
                    {{ $rule->purpose }}
                </td>

                <td>
                    {{ $rule->client
                        ?->name ?? '—' }}
                </td>

                <td>
                    {{ $rule->confidence }}%
                </td>

                <td>
                    <span class="{{ $rule->active
                        ? 'good'
                        : 'warning' }}">
                        {{ $rule->active
                            ? 'ACTIVE'
                            : 'OFF' }}
                    </span>
                </td>

                <td>
                    {{ $rule->last_applied_at
                        ?->format('d M Y H:i')
                        ?? '—' }}
                </td>

                <td>
                    <form
                        method="POST"
                        action="{{ route(
                            'suppliers.rules.toggle',
                            $rule
                        ) }}"
                    >
                        @csrf

                        <button type="submit">
                            {{ $rule->active
                                ? 'Disable'
                                : 'Enable' }}
                        </button>
                    </form>

                    <form
                        method="POST"
                        action="{{ route(
                            'suppliers.rules.destroy',
                            $rule
                        ) }}"
                    >
                        @csrf
                        @method('DELETE')

                        <button type="submit">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8">
                    No learned rules yet.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</main>
</body>
</html>
