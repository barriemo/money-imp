<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Unbilled Work — Money Imp</title>

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

        a { color: inherit; }

        h1 {
            margin: 18px 0 6px;
            font-size: 44px;
        }

        .muted { color: #888; }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 28px 0;
        }

        .stat,
        .client {
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
        }

        .stat {
            padding: 20px;
        }

        .stat strong {
            display: block;
            font-size: 28px;
        }

        .client {
            margin-bottom: 18px;
            padding: 22px;
        }

        .client-head {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 16px;
        }

        .client-head strong {
            font-size: 22px;
        }

        .entry {
            padding: 16px 0;
            border-top: 1px solid #292929;
        }

        .entry-top {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        form {
            display: grid;
            grid-template-columns:
                1fr
                1fr
                auto;
            gap: 10px;
            margin-top: 12px;
        }

        select,
        input,
        button {
            min-height: 42px;
            border-radius: 8px;
            border: 1px solid #333;
            padding: 9px 12px;
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

        .success {
            margin: 18px 0;
            padding: 14px;
            border-radius: 10px;
            background: #173c23;
        }

        @media (max-width: 750px) {
            .summary {
                grid-template-columns: 1fr;
            }

            form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<main>
    <a href="{{ route('dashboard') }}">
        ← Money Imp
    </a>

    <h1>Unbilled Work</h1>

    <p class="muted">
        Decide what becomes money
        and what we deliberately give away.
    </p>

    @if (session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    <section class="summary">
        <div class="stat">
            <strong>
                {{ $summary['entries'] }}
            </strong>

            <span>Items to review</span>
        </div>

        <div class="stat">
            <strong>
                {{ number_format(
                    $summary['minutes'] / 60,
                    1
                ) }}h
            </strong>

            <span>Logged time</span>
        </div>

        <div class="stat">
            <strong>
                £{{ number_format(
                    $summary['value'],
                    2
                ) }}
            </strong>

            <span>Potential value</span>
        </div>
    </section>

    @forelse ($clients as $group)
        <article class="client">
            <div class="client-head">
                <div>
                    <strong>
                        {{ $group['client']->name }}
                    </strong>

                    <div class="muted">
                        {{ $group['count'] }}
                        item(s)
                        ·
                        {{ number_format(
                            $group['minutes'] / 60,
                            1
                        ) }}h
                    </div>
                </div>

                <strong>
                    £{{ number_format(
                        $group['value'],
                        2
                    ) }}
                </strong>
            </div>

            @if (
                $group['logs']
                    ->where(
                        'commercial_status',
                        'invoice'
                    )
                    ->isNotEmpty()
            )
                <form
                    method="POST"
                    action="{{ route(
                        'work-review.invoice-draft',
                        $group['client']
                    ) }}"
                    style="margin-bottom:16px;"
                >
                    @csrf

                    <button type="submit">
                        Create invoice draft from approved work
                    </button>
                </form>
            @endif

            @foreach ($group['logs'] as $log)
                <div class="entry">
                    <div class="entry-top">
                        <div>
                            <strong>
                                {{ $log->description }}
                            </strong>

                            <div class="muted">
                                {{ $log->user->name }}
                                ·
                                {{ $log->minutes }} mins
                                ·
                                {{ $log->performed_at->format(
                                    'd M Y'
                                ) }}
                                ·
                                {{ ucfirst(
                                    $log->billing_hint
                                ) }}
                            </div>
                        </div>

                        <strong>
                            £{{ number_format(
                                (float)
                                $log->commercial_value,
                                2
                            ) }}
                        </strong>
                    </div>

                    <form
                        method="POST"
                        action="{{ route(
                            'work-review.update',
                            $log
                        ) }}"
                    >
                        @csrf

                        <select
                            name="commercial_status"
                            required
                        >
                            <option
                                value="invoice"
                                @selected(
                                    $log->commercial_status
                                    === 'invoice'
                                )
                            >
                                Invoice
                            </option>

                            <option value="retainer">
                                Included in retainer
                            </option>

                            <option value="goodwill">
                                Goodwill
                            </option>

                            <option value="internal">
                                Internal
                            </option>

                            <option value="written_off">
                                Write off
                            </option>
                        </select>

                        <input
                            name="commercial_notes"
                            type="text"
                            placeholder="Optional note..."
                        >

                        <button type="submit">
                            Save
                        </button>
                    </form>
                </div>
            @endforeach
        </article>
    @empty
        <p class="muted">
            Nothing waiting for review.
        </p>
    @endforelse
</main>
</body>
</html>
