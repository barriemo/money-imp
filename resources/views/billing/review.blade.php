<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Draft Review — Money Imp</title>

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
            font-size: 42px;
            margin-bottom: 6px;
        }

        .muted { color: #888; }

        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin: 28px 0;
        }

        .stat,
        .invoice {
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

        .invoice {
            padding: 22px;
            margin-bottom: 14px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .name {
            font-size: 22px;
            font-weight: 800;
        }

        .amount {
            font-size: 26px;
            font-weight: 800;
        }

        .items {
            margin-top: 16px;
            border-top: 1px solid #292929;
            padding-top: 12px;
        }

        .item {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 6px 0;
            color: #bbb;
        }

        .actions {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        button {
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #333;
            cursor: pointer;
            font: inherit;
            font-weight: 800;
        }

        .approved {
            color: #8ee8a1;
            font-weight: 800;
        }

        .flash {
            margin: 20px 0;
            padding: 15px 18px;
            border-radius: 10px;
            background: #18351f;
            border: 1px solid #295b35;
        }
    </style>
</head>

<body>
<main>
    <a href="{{ route('billing.index') }}">← July Billing</a>

    <h1>Draft Review</h1>
    <p class="muted">
        Review July drafts before Money Imp is allowed to send anything.
    </p>

    @if (session('success'))
        <div class="flash">
            {{ session('success') }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('billing.review.send-approved') }}"
        id="send-approved-form"
    >
        @csrf
    </form>

    <section class="summary">
        <div class="stat">
            <strong>{{ $summary['drafts'] }}</strong>
            <span>Drafts</span>
        </div>

        <div class="stat">
            <strong>{{ $summary['pending'] }}</strong>
            <span>Pending review</span>
        </div>

        <div class="stat">
            <strong>{{ $summary['approved'] }}</strong>
            <span>Approved</span>
        </div>

        <div class="stat">
            <strong>£{{ number_format($summary['value'], 2) }}</strong>
            <span>Draft value</span>
        </div>
    </section>

    <form
        method="POST"
        action="{{ route('billing.review.approve-bulk') }}"
    >
        @csrf

        <div
            style="
                margin-bottom:18px;
                display:flex;
                gap:10px;
                flex-wrap:wrap;
            "
        >
            <button type="submit">
                Approve Selected
            </button>

            <button
                type="submit"
                form="send-approved-form"
                onclick="return confirm('EMAIL the selected approved invoices to customers through FreeAgent?');"
                style="background:#fff;color:#111;"
            >
                Send Selected Approved
            </button>
        </div>

        @foreach ($invoices as $invoice)
            <article class="invoice">
                <div class="header">
                    <div>
                        <div class="name">
                            @if ($invoice->billingReview?->status !== 'approved')
                                <input
                                    type="checkbox"
                                    name="invoices[]"
                                    value="{{ $invoice->id }}"
                                >
                            @endif

                            {{ $invoice->client->name }}
                        </div>

                        <div class="muted">
                            Invoice {{ $invoice->invoice_number }}
                            · {{ $invoice->invoice_date?->format('d M Y') }}
                            · due {{ $invoice->due_date?->format('d M Y') }}
                        </div>
                    </div>

                    <div class="amount">
                        £{{ number_format((float) $invoice->gross_amount, 2) }}
                    </div>
                </div>

                @if ($invoice->items->isNotEmpty())
                    <div class="items">
                        @foreach ($invoice->items as $item)
                            <div class="item">
                                <span>{{ $item->description }}</span>
                                <strong>
                                    £{{ number_format((float) $item->gross_amount, 2) }}
                                </strong>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="actions">
                    @if ($invoice->billingReview?->status === 'approved')
                        <label class="approved">
                            <input
                                type="checkbox"
                                name="invoices[]"
                                value="{{ $invoice->id }}"
                                form="send-approved-form"
                            >
                            Approved ✓ — select to send
                        </label>
                    @else
                        <button
                            type="submit"
                            formaction="{{ route('billing.review.approve', $invoice) }}"
                            formmethod="POST"
                            name="_single"
                            value="1"
                        >
                            Approve
                        </button>
                    @endif
                </div>
            </article>
        @endforeach
    </form>
</main>
</body>
</html>
