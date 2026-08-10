<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Import Inbox — Money Imp</title>

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
            max-width: 1200px;
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
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin: 28px 0;
        }

        .stat,
        .batch {
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
        }

        .stat {
            padding: 18px;
        }

        .stat strong {
            display: block;
            font-size: 26px;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
        }

        .button {
            display: inline-block;
            padding: 12px 16px;
            border-radius: 8px;
            background: #fff;
            color: #111;
            text-decoration: none;
            font-weight: 800;
        }

        .batch {
            margin-bottom: 14px;
            padding: 20px;
        }

        .batch-top {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .provider {
            font-size: 20px;
            font-weight: 800;
        }

        .status {
            font-weight: 800;
        }

        .completed {
            color: #8ee8a1;
        }

        .failed {
            color: #ff8f8f;
        }

        .metrics {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin-top: 14px;
            color: #bbb;
        }

        @media (max-width: 850px) {
            .summary {
                grid-template-columns: 1fr 1fr;
            }

            .batch-top {
                display: block;
            }
        }
    </style>
</head>

<body>
<main>
    <a href="/dashboard">
        ← Money Imp
    </a>

    <h1>Import Inbox</h1>

    <p class="muted">
        Every statement and transaction import in one place.
    </p>

    <section class="summary">
        <div class="stat">
            <strong>{{ $summary['total'] }}</strong>
            <span>Total imports</span>
        </div>

        <div class="stat">
            <strong>{{ $summary['completed'] }}</strong>
            <span>Completed</span>
        </div>

        <div class="stat">
            <strong>{{ $summary['failed'] }}</strong>
            <span>Failed</span>
        </div>

        <div class="stat">
            <strong>{{ $summary['rows_imported'] }}</strong>
            <span>Rows imported</span>
        </div>

        <div class="stat">
            <strong>{{ $summary['rows_skipped'] }}</strong>
            <span>Duplicates skipped</span>
        </div>
    </section>

    <div class="actions">
        <a
            class="button"
            href="{{ route('money-out.import.index') }}"
        >
            Import Statement
        </a>

        <a
            class="button"
            href="{{ route('money-out.index') }}"
        >
            Review Money Out
        </a>
    </div>

    @forelse ($batches as $batch)
        <article class="batch">
            <div class="batch-top">
                <div>
                    <div class="provider">
                        {{ str_replace(
                            '_',
                            ' ',
                            strtoupper($batch->provider)
                        ) }}
                    </div>

                    <div class="muted">
                        {{ $batch->original_filename }}

                        @if ($batch->bankAccount)
                            · {{ $batch->bankAccount->name }}
                        @endif
                    </div>
                </div>

                <div
                    class="
                        status
                        {{ $batch->status === 'completed'
                            ? 'completed'
                            : ($batch->status === 'failed'
                                ? 'failed'
                                : '')
                        }}
                    "
                >
                    {{ ucfirst(
                        str_replace(
                            '_',
                            ' ',
                            $batch->status
                        )
                    ) }}
                </div>
            </div>

            <div class="metrics">
                <span>
                    Seen: {{ $batch->rows_seen }}
                </span>

                <span>
                    Imported: {{ $batch->rows_imported }}
                </span>

                <span>
                    Skipped: {{ $batch->rows_skipped }}
                </span>

                <span>
                    Failed: {{ $batch->rows_failed }}
                </span>

                <span>
                    {{ $batch->created_at?->format(
                        'd M Y H:i'
                    ) }}
                </span>
            </div>
        </article>
    @empty
        <p>
            No imports yet.
        </p>
    @endforelse

    {{ $batches->links() }}
</main>
</body>
</html>
