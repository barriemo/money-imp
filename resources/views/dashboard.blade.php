<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Money Imp</title>

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
            max-width: 1150px;
            margin: auto;
            padding: 48px 24px 80px;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        h1 {
            margin: 0;
            font-size: 54px;
            letter-spacing: -2px;
        }

        .intro {
            margin: 6px 0 32px;
            color: #888;
            font-size: 18px;
        }

        .signal-box {
            margin: 0 0 32px;
            padding: 22px;
            background: #181818;
            border: 1px solid #343434;
            border-radius: 14px;
        }

        .signal-box h2 {
            margin: 0 0 5px;
            font-size: 24px;
            letter-spacing: -0.5px;
        }

        .signal-box > p {
            margin: 0 0 16px;
            color: #999;
            line-height: 1.5;
        }

        .signal-box textarea {
            display: block;
            width: 100%;
            min-height: 125px;
            padding: 15px;
            resize: vertical;
            background: #101010;
            color: #f5f5f5;
            border: 1px solid #333;
            border-radius: 10px;
            font: inherit;
            font-size: 16px;
            line-height: 1.5;
        }

        .signal-box textarea:focus {
            outline: none;
            border-color: #777;
        }

        .signal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-top: 12px;
        }

        .signal-footer span {
            max-width: 700px;
            color: #777;
            font-size: 13px;
            line-height: 1.4;
        }

        .signal-submit {
            flex: 0 0 auto;
            padding: 11px 17px;
            background: #f5f5f5;
            color: #111;
            border: 0;
            border-radius: 9px;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
        }

        .signal-submit:hover {
            background: #ddd;
        }

        .signal-success {
            margin-bottom: 14px;
            padding: 11px 13px;
            background: #162319;
            border: 1px solid #315439;
            border-radius: 9px;
            color: #bde5c5;
            line-height: 1.4;
        }

        .signal-finding {
            margin-bottom: 16px;
            padding: 16px;
            background: #171b20;
            border: 1px solid #39424d;
            border-radius: 10px;
        }

        .signal-finding strong {
            display: block;
            margin-bottom: 8px;
            font-size: 18px;
        }

        .signal-finding p {
            margin: 0 0 10px;
            color: #d3d7dc;
            line-height: 1.5;
        }

        .signal-finding-next {
            margin-top: 12px;
            color: #f2f2f2;
            line-height: 1.45;
        }

        .signal-finding-boundary {
            margin-top: 10px;
            color: #888;
            font-size: 13px;
            line-height: 1.45;
        }

        .brain-answers {
            display: grid;
            gap: 12px;
        }

        .brain-answer {
            padding: 20px;
            background: #181818;
            border: 1px solid #343434;
            border-radius: 14px;
        }

        .brain-answer-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 9px;
            margin-bottom: 10px;
            color: #777;
            font-size: 13px;
        }

        .brain-answer-status {
            padding: 4px 8px;
            background: #242424;
            border: 1px solid #3a3a3a;
            border-radius: 999px;
            color: #d8d8d8;
            font-weight: 700;
        }

        .brain-answer-question {
            margin-bottom: 13px;
            color: #999;
            font-size: 14px;
            line-height: 1.45;
        }

        .brain-answer h3 {
            margin: 0 0 9px;
            font-size: 20px;
            letter-spacing: -0.3px;
        }

        .brain-answer p {
            margin: 0;
            color: #d3d7dc;
            line-height: 1.55;
        }

        .brain-answer-next {
            margin-top: 13px;
            color: #f2f2f2;
            line-height: 1.45;
        }

        .brain-answer-boundary {
            margin-top: 10px;
            color: #777;
            font-size: 13px;
            line-height: 1.45;
        }

        .signal-error {
            margin-top: 8px;
            color: #e88c8c;
            font-size: 14px;
        }

        .metrics {
            display: grid;
            grid-template-columns:
                repeat(4, 1fr);
            gap: 12px;
        }

        .metric,
        .action,
        .nav-card {
            background: #181818;
            border: 1px solid #292929;
            border-radius: 14px;
        }

        .metric {
            padding: 20px;
        }

        .metric strong {
            display: block;
            margin-bottom: 4px;
            font-size: 28px;
        }

        .metric span {
            color: #888;
        }

        .section-title {
            margin-top: 38px;
            margin-bottom: 12px;
            color: #777;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .actions {
            display: grid;
            gap: 10px;
        }

        .action {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 18px;
        }

        .action:hover,
        .nav-card:hover {
            border-color: #555;
        }

        .action strong {
            display: block;
            font-size: 18px;
        }

        .action span {
            color: #888;
        }

        .dot {
            width: 10px;
            height: 10px;
            flex: 0 0 10px;
            margin-top: 7px;
            border-radius: 50%;
        }

        .red { background: #e85a5a; }
        .orange { background: #e9a23b; }
        .green { background: #67c77a; }

        .action-left {
            display: flex;
            gap: 12px;
        }

        .nav {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 12px;
        }

        .nav-card {
            min-height: 120px;
            padding: 20px;
        }

        .nav-card strong {
            display: block;
            margin-bottom: 7px;
            font-size: 18px;
        }

        .nav-card span {
            color: #888;
            line-height: 1.4;
        }

        .primary {
            background: #f5f5f5;
            color: #111;
        }

        .primary span {
            color: #555;
        }

        @media (max-width: 800px) {
            .metrics {
                grid-template-columns: 1fr 1fr;
            }

            .nav {
                grid-template-columns: 1fr;
            }

            .action {
                display: block;
            }

            .signal-footer {
                align-items: stretch;
                flex-direction: column;
            }

            .signal-submit {
                width: 100%;
            }
        }
    </style>
</head>

<body>
<main>
    <h1>
        Good afternoon, {{ auth()->user()->name }}.
    </h1>

    <p class="intro">
        Here's where the money is right now.
    </p>

    <section class="signal-box">
        <h2>What's on your mind?</h2>

        <p>
            Tell Money Imp what you're seeing,
            thinking, feeling, worrying about
            or questioning.
        </p>

        @if (
            session('ceo_signal_finding')
            && $ceoSignalAnswers->isNotEmpty()
        )
            <div class="signal-success">
                Captured. Money Imp has updated
                your current Brain answer below.
            </div>
        @elseif (session('ceo_signal_finding'))
            @php
                $finding =
                    session(
                        'ceo_signal_finding'
                    );
            @endphp

            <div class="signal-finding">
                <strong>
                    {{ $finding['headline'] }}
                </strong>

                <p>
                    {{ $finding['summary'] }}
                </p>

                <div class="signal-finding-next">
                    <b>Next:</b>
                    {{ $finding['next_step'] }}
                </div>

                <div class="signal-finding-boundary">
                    <b>Truth boundary:</b>
                    {{ $finding['truth_boundary'] }}
                </div>
            </div>
        @elseif (session('ceo_signal_success'))
            <div class="signal-success">
                {{ session('ceo_signal_success') }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('ceo-signal.store') }}"
        >
            @csrf

            <textarea
                name="signal"
                maxlength="5000"
                required
                placeholder="e.g. I think MML is swallowing far more time than we priced. Is that actually true?"
            >{{ old('signal') }}</textarea>

            @error('signal')
                <div class="signal-error">
                    {{ $message }}
                </div>
            @enderror

            <div class="signal-footer">
                <span>
                    Starts as unverified human input.
                    The Brain interrogates the evidence
                    before anything can become truth.
                </span>

                <button
                    class="signal-submit"
                    type="submit"
                >
                    Send to Brain
                </button>
            </div>
        </form>
    </section>

    @if ($ceoSignalAnswers->isNotEmpty())
        <div class="section-title">
            What you asked the Brain
        </div>

        <section class="brain-answers">
            @foreach ($ceoSignalAnswers as $answer)
                <article class="brain-answer">
                    <div class="brain-answer-meta">
                        <span class="brain-answer-status">
                            {{ $answer->statusLabel }}
                        </span>

                        <span>
                            Asked {{ $answer->askedAtLabel }}
                        </span>
                    </div>

                    <div class="brain-answer-question">
                        “{{ $answer->question }}”
                    </div>

                    <h3>
                        {{ $answer->headline }}
                    </h3>

                    <p>
                        {{ $answer->summary }}
                    </p>

                    <div class="brain-answer-next">
                        <b>Next:</b>
                        {{ $answer->nextStep }}
                    </div>

                    <div class="brain-answer-boundary">
                        <b>Truth boundary:</b>
                        {{ $answer->truthBoundary }}
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    <div class="section-title">
        CFO Position
    </div>

    <section class="actions">
        <div class="action">
            <div class="action-left">
                <div class="dot orange"></div>

                <div>
                    <strong>
                        {{ strtoupper($cfo->overallStatus) }}
                    </strong>

                    <span>
                        Confidence:
                        {{ $cfo->overallConfidence }}%
                    </span>
                </div>
            </div>
        </div>
    </section>

    @if (count($cfo->risks))
        <div class="section-title">
            Risks
        </div>

        <section class="actions">
            @foreach ($cfo->risks as $risk)
                <div class="action">
                    <div class="action-left">
                        <div class="dot red"></div>

                        <div>
                            <span>{{ $risk }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>
    @endif


    <section class="metrics">
        <div class="metric">
            <strong>
                £{{ number_format(
                    $brief['cash']['bank'],
                    2
                ) }}
            </strong>

            <span>Cash in bank</span>
        </div>

        <div class="metric">
            <strong>
                £{{ number_format(
                    $brief['receivables']['outstanding'],
                    2
                ) }}
            </strong>

            <span>Outstanding sales</span>
        </div>

        <div class="metric">
            <strong>
                £{{ number_format(
                    $brief['work']['review_value']
                    + $brief['work']['ready_value'],
                    2
                ) }}
            </strong>

            <span>Potential unbilled work</span>
        </div>

        <div class="metric">
            <strong>
                {{ $brief['billing']['draft_count'] }}
            </strong>

            <span>Invoice drafts</span>
        </div>
    </section>
<div class="section-title">
    Executive priorities
</div>

<section class="actions">
    @forelse ($executiveActions as $action)

        <div class="action">
            <div class="action-left">
                <div class="dot red"></div>

                <div>
                    <strong>
                        [{{ $action->score }}]
                        {{ $action->title }}
                    </strong>

                    <span>
                        £{{ number_format(
                            $action->estimated_financial_impact,
                            2
                        ) }}
                        ·
                        {{ $action->recommended_action }}
                    </span>
                </div>
            </div>
        </div>

    @empty

        <div class="action">
            <strong>
                No executive priorities.
            </strong>
        </div>

    @endforelse
</section>
    <div class="section-title">
        Needs your attention
    </div>

    <section class="actions">
        @forelse ($brief['actions'] as $action)
            <a
                class="action"
                href="{{ route($action['route']) }}"
            >
                <div class="action-left">
                    <div
                        class="dot {{ $action['level'] }}"
                    ></div>

                    <div>
                        <strong>
                            {{ $action['title'] }}
                        </strong>

                        <span>
                            {{ $action['detail'] }}
                        </span>
                    </div>
                </div>

                <strong>→</strong>
            </a>
        @empty
            <div class="action">
                <div class="action-left">
                    <div class="dot green"></div>

                    <div>
                        <strong>Nothing urgent.</strong>

                        <span>
                            Money Imp has nothing
                            shouting for attention.
                        </span>
                    </div>
                </div>
            </div>
        @endforelse
    </section>

    <div class="section-title">
        Everyday
    </div>

    <section class="nav">
        <a
            class="nav-card primary"
            href="{{ route('work-log.index') }}"
        >
            <strong>Log Work</strong>

            <span>
                Client. Time. Description.
                Done.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('work-review.index') }}"
        >
            <strong>
                Unbilled Work
                @if ($brief['work']['review_count'] > 0)
                    ({{ $brief['work']['review_count'] }})
                @endif
            </strong>

            <span>
                Decide what becomes revenue.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('reconciliation.index') }}"
        >
            <strong>Reconciliation</strong>

            <span>
                Match incoming cash to invoices.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('billing.index') }}"
        >
            <strong>Billing</strong>

            <span>
                Find what we've forgotten
                to invoice.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('billing.review') }}"
        >
            <strong>
                Draft Review
                @if (
                    $brief['billing']['awaiting_approval']
                    > 0
                )
                    ({{ $brief['billing']['awaiting_approval'] }})
                @endif
            </strong>

            <span>
                Approve and send invoices.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('debtors.index') }}"
        >
            <strong>Who Owes Us Money?</strong>

            <span>
                £{{ number_format(
                    $brief['receivables']['outstanding'],
                    2
                ) }} outstanding.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('money-out.index') }}"
        >
            <strong>
                Money Out
                @if (
                    $brief['money_out']['review_count']
                    > 0
                )
                    ({{ $brief['money_out']['review_count'] }})
                @endif
            </strong>

            <span>
                Review spending and attribution.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('imports.index') }}"
        >
            <strong>Import Inbox</strong>

            <span>
                Statements and import history.
            </span>
        </a>

        <a
            class="nav-card"
            href="{{ route('chase.index') }}"
        >
            <strong>
                Chase Debtors
                @if (
                    $brief['receivables']['overdue_count']
                    > 0
                )
                    ({{ $brief['receivables']['overdue_count'] }})
                @endif
            </strong>

            <span>
                £{{ number_format(
                    $brief['receivables']['overdue_value'],
                    2
                ) }} overdue.
            </span>
        </a>
    </section>
</main>
</body>
</html>
