<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Log Work — Money Imp</title>

    <style>
        * {
            box-sizing: border-box;
        }

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
            max-width: 900px;
            margin: auto;
            padding: 40px 24px 80px;
        }

        a {
            color: inherit;
        }

        h1 {
            margin: 20px 0 6px;
            font-size: 44px;
        }

        .muted {
            color: #8d8d8d;
        }

        .panel {
            margin-top: 28px;
            padding: 24px;
            background: #181818;
            border: 1px solid #292929;
            border-radius: 16px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            color: #aaa;
        }

        select,
        textarea,
        input,
        button {
            width: 100%;
            font: inherit;
        }

        select,
        textarea,
        input {
            padding: 12px;
            color: #fff;
            background: #202020;
            border: 1px solid #333;
            border-radius: 9px;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .time-options {
            display: grid;
            grid-template-columns:
                repeat(6, 1fr);
            gap: 8px;
        }

        .time-option input {
            display: none;
        }

        .time-option span {
            display: block;
            padding: 11px 6px;
            text-align: center;
            border: 1px solid #333;
            border-radius: 8px;
            background: #202020;
            cursor: pointer;
        }

        .time-option input:checked + span {
            background: #fff;
            color: #111;
        }

        .billing {
            display: grid;
            grid-template-columns:
                repeat(4, 1fr);
            gap: 8px;
        }

        .billing input {
            display: none;
        }

        .billing span {
            display: block;
            padding: 12px;
            text-align: center;
            border: 1px solid #333;
            border-radius: 8px;
            cursor: pointer;
        }

        .billing input:checked + span {
            background: #fff;
            color: #111;
        }

        button {
            margin-top: 20px;
            padding: 14px;
            border: 0;
            border-radius: 9px;
            background: #fff;
            color: #111;
            font-weight: 800;
            cursor: pointer;
        }

        .success {
            margin-top: 20px;
            padding: 14px;
            border-radius: 10px;
            background: #173c23;
        }

        .recent {
            margin-top: 40px;
        }

        .recent-item {
            padding: 16px 0;
            border-bottom: 1px solid #292929;
        }

        .recent-top {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        @media (max-width: 700px) {
            .grid,
            .billing {
                grid-template-columns: 1fr;
            }

            .time-options {
                grid-template-columns:
                    repeat(3, 1fr);
            }
        }
    </style>
</head>

<body>
<main>
    <a href="{{ route('dashboard') }}">
        ← Money Imp
    </a>

    <h1>Log Work</h1>

    <p class="muted">
        Took longer than five minutes?
        Log the bastard.
    </p>

    @if (session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    <section class="panel">
        <form
            method="POST"
            action="{{ route('work-log.store') }}"
        >
            @csrf

            <div class="grid">
                <div>
                    <label for="client_id">
                        Client
                    </label>

                    <select
                        id="client_id"
                        name="client_id"
                        required
                    >
                        <option value="">
                            Select client...
                        </option>

                        @foreach ($clients as $client)
                            <option
                                value="{{ $client->id }}"
                            >
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="user_id">
                        Who did it?
                    </label>

                    <select
                        id="user_id"
                        name="user_id"
                        required
                    >
                        @foreach ($users as $user)
                            <option
                                value="{{ $user->id }}"
                                @selected(
                                    auth()->id()
                                    === $user->id
                                )
                            >
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="margin-top:18px;">
                <label>
                    Time
                </label>

                <div class="time-options">
                    @foreach ([
                        5 => '5m',
                        15 => '15m',
                        30 => '30m',
                        45 => '45m',
                        60 => '1h',
                        120 => '2h',
                    ] as $minutes => $label)
                        <label class="time-option">
                            <input
                                type="radio"
                                name="minutes"
                                value="{{ $minutes }}"
                                @checked(
                                    $minutes === 30
                                )
                            >

                            <span>
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div style="margin-top:18px;">
                <label for="description">
                    What did you do?
                </label>

                <textarea
                    id="description"
                    name="description"
                    placeholder="Updated homepage copy, fixed the enquiry form..."
                    required
                ></textarea>
            </div>

            <div
                class="grid"
                style="margin-top:18px;"
            >
                <div>
                    <label for="performed_at">
                        Date
                    </label>

                    <input
                        id="performed_at"
                        name="performed_at"
                        type="date"
                        value="{{ now()->toDateString() }}"
                        required
                    >
                </div>
            </div>

            <div style="margin-top:18px;">
                <label>
                    What do you reckon?
                </label>

                <div class="billing">
                    @foreach ([
                        'billable' =>
                            'Billable',

                        'retainer' =>
                            'Retainer',

                        'goodwill' =>
                            'Goodwill',

                        'unsure' =>
                            'Not sure',
                    ] as $value => $label)
                        <label>
                            <input
                                type="radio"
                                name="billing_hint"
                                value="{{ $value }}"
                                @checked(
                                    $value === 'unsure'
                                )
                            >

                            <span>
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <button type="submit">
                Log it
            </button>
        </form>
    </section>

    <section class="recent">
        <h2>Recently logged</h2>

        @forelse ($recent as $entry)
            <div class="recent-item">
                <div class="recent-top">
                    <strong>
                        {{ $entry->client->name }}
                    </strong>

                    <strong>
                        {{ $entry->minutes }} mins
                    </strong>
                </div>

                <div>
                    {{ $entry->description }}
                </div>

                <div class="muted">
                    {{ $entry->user->name }}
                    ·
                    {{ $entry->performed_at->format(
                        'd M Y'
                    ) }}
                    ·
                    {{ ucfirst(
                        $entry->billing_hint
                    ) }}
                </div>
            </div>
        @empty
            <p class="muted">
                Nothing logged yet.
            </p>
        @endforelse
    </section>
</main>
</body>
</html>
