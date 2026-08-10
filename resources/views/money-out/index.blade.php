<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Money Out — Money Imp</title>

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

        a { color: inherit; }

        h1 {
            margin-bottom: 6px;
            font-size: 42px;
        }

        .muted { color: #888; }

        .summary {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 28px 0;
        }

        .stat,
        .expense {
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

        .expense {
            margin-bottom: 14px;
            padding: 22px;
        }

        .expense-top {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .merchant {
            font-size: 20px;
            font-weight: 800;
        }

        .amount {
            font-size: 24px;
            font-weight: 800;
        }

        form.review {
            display: grid;
            grid-template-columns:
                1fr
                1fr
                1fr
                auto
                auto;
            gap: 10px;
            margin-top: 18px;
            align-items: center;
        }

        select,
        button {
            min-height: 42px;
            border-radius: 8px;
            border: 1px solid #333;
            padding: 9px 12px;
            font: inherit;
        }

        select {
            background: #202020;
            color: #fff;
        }

        button {
            cursor: pointer;
            font-weight: 800;
        }

        .suggested {
            color: #f6c86b;
            margin-top: 10px;
        }

        .flash {
            padding: 14px;
            margin: 18px 0;
            border: 1px solid #295b35;
            background: #18351f;
            border-radius: 10px;
        }

        @media (max-width: 850px) {
            form.review {
                grid-template-columns: 1fr;
            }

            .summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<main>
    <a href="/dashboard">← Money Imp</a>

    <h1>Money Out</h1>

    <p class="muted">
        Teach Money Imp where every pound went.
    </p>

    @if (session('success'))
        <div class="flash">
            {{ session('success') }}
        </div>
    @endif

    <section class="summary">
        <div class="stat">
            <strong>
                {{ $summary['needs_review'] }}
            </strong>

            <span>Needs review</span>
        </div>

        <div class="stat">
            <strong>
                {{ $summary['reviewed'] }}
            </strong>

            <span>Reviewed</span>
        </div>
    </section>

    <form
        method="POST"
        action="{{ route('money-out.categorise') }}"
        style="margin-bottom:24px;"
    >
        @csrf

        <button type="submit">
            Run categorisation
        </button>
    </form>

    @forelse ($rows as $row)
        <article class="expense">
            <div class="expense-top">
                <div>
                    <div class="merchant">
                        {{ $row->merchant ?: $row->description }}
                    </div>

                    <div class="muted">
                        {{ $row->transaction_date?->format('d M Y') }}

                        @if ($row->reference)
                            · {{ $row->reference }}
                        @endif
                    </div>
                </div>

                <div class="amount">
                    £{{ number_format(abs((float) $row->amount), 2) }}
                </div>
            </div>

            @if ($row->supplier)
                <div class="suggested">
                    Suggested:
                    {{ $row->supplier->name }}

                    @if ($row->classification_confidence)
                        · {{ number_format((float) $row->classification_confidence, 0) }}%
                    @endif
                </div>
            @endif

            <form
                class="review"
                method="POST"
                action="{{ route('money-out.review', $row) }}"
            >
                @csrf

                <select name="supplier_id" required>
                    <option value="">
                        Supplier...
                    </option>

                    @foreach ($suppliers as $supplier)
                        <option
                            value="{{ $supplier->id }}"
                            @selected(
                                $row->supplier_id === $supplier->id
                            )
                        >
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>

                <select
                    name="expense_category_id"
                    required
                >
                    <option value="">
                        Category...
                    </option>

                    @foreach ($categories as $category)
                        <option
                            value="{{ $category->id }}"
                            @selected(
                                $row->expense_category_id === $category->id
                            )
                        >
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>

                <select name="client_id">
                    <option value="">
                        Internal / no client
                    </option>

                    @foreach ($clients as $client)
                        <option
                            value="{{ $client->id }}"
                            @selected(
                                $row->client_id === $client->id
                            )
                        >
                            {{ $client->name }}
                        </option>
                    @endforeach
                </select>

                <label>
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        checked
                    >

                    Remember
                </label>

                <button type="submit">
                    Confirm
                </button>
            </form>
        </article>
    @empty
        <p>
            Nothing needs reviewed. Lovely.
        </p>
    @endforelse

    {{ $rows->links() }}
</main>
</body>
</html>
