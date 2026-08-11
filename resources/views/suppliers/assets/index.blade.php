<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title>
        Infrastructure Review · Money Imp
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
            max-width: 1400px;
            margin: auto;
        }

        a {
            color: #eee;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin: 25px 0;
        }

        .card {
            background: #191919;
            border: 1px solid #333;
            border-radius: 14px;
            padding: 20px;
        }

        .value {
            font-size: 30px;
            font-weight: 800;
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

        .asset {
            margin: 16px 0;
        }

        .asset-grid {
            display: grid;
            grid-template-columns:
                220px
                160px
                minmax(500px, 1fr);
            gap: 20px;
            align-items: start;
        }

        form {
            display: grid;
            grid-template-columns:
                160px
                220px
                120px
                140px
                minmax(180px, 1fr)
                80px;
            gap: 8px;
        }

        select,
        input,
        button {
            padding: 9px;
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

    <h1>Infrastructure Review</h1>

    <p class="muted">
        Every server, hosting package and
        infrastructure add-on should have an owner.
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

    <div class="grid">
        <article class="card">
            <div>
                Current infrastructure cost
            </div>

            <div class="value">
                £{{ number_format(
                    $monthlyCost,
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>
                Cost marked billable
            </div>

            <div class="value good">
                £{{ number_format(
                    $billableCost,
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>
                Expected client recovery
            </div>

            <div class="value good">
                £{{ number_format(
                    $expectedRecovery,
                    2
                ) }}
            </div>
        </article>

        <article class="card">
            <div>
                Assets still unexplained
            </div>

            <div class="value warning">
                {{ $unassignedCount }}
            </div>
        </article>
    </div>

    @forelse ($assets as $asset)
        <article class="card asset">
            <div class="asset-grid">
                <div>
                    <strong>
                        {{ $asset->name }}
                    </strong>

                    <div class="muted">
                        {{ $asset->supplier
                            ?->supplier_name }}

                        ·

                        {{ $asset->asset_type }}
                    </div>

                    <div class="muted">
                        {{ $asset->asset_key }}
                    </div>
                </div>

                <div>
                    <strong>
                        £{{ number_format(
                            (float)
                            $asset->observed_cost,
                            2
                        ) }}
                    </strong>

                    <div class="muted">
                        Current known cost
                    </div>
                </div>

                <div>
                    <form
                        method="POST"
                        action="{{ route(
                            'suppliers.assets.update',
                            $asset
                        ) }}"
                    >
                        @csrf

                        <select
                            name="purpose"
                            required
                        >
                            <option value="">
                                What is it?
                            </option>

                            @foreach ([
                                'client' => 'Client',
                                'internal' => 'Internal',
                                'shared' => 'Shared',
                                'dead' => 'Dead',
                                'cancel' => 'Cancel',
                                'unknown' => 'Unknown',
                            ] as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    @selected(
                                        $asset->purpose
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

                            @foreach (
                                $clients
                                as $client
                            )
                                <option
                                    value="{{ $client->id }}"
                                    @selected(
                                        $asset->client_id
                                        === $client->id
                                    )
                                >
                                    {{ $client->name }}
                                </option>
                            @endforeach
                        </select>

                        <label>
                            <input
                                type="checkbox"
                                name="billable"
                                value="1"
                                @checked(
                                    $asset->billable
                                )
                            >
                            Billable
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="expected_charge"
                            placeholder="Charge"
                            value="{{ $asset
                                ->expected_charge }}"
                        >

                        <input
                            type="text"
                            name="notes"
                            placeholder="Notes"
                            value="{{ $asset->notes }}"
                        >

                        <button type="submit">
                            Save
                        </button>
                    </form>
                </div>
            </div>
        </article>
    @empty
        <p>
            No infrastructure assets detected yet.
        </p>
    @endforelse
</main>
</body>
</html>
