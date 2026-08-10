<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Money Imp</title>

    <style>
        body {
            margin: 0;
            padding: 48px;
            background: #111;
            color: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            max-width: 900px;
            margin: auto;
        }

        h1 {
            font-size: 48px;
            letter-spacing: -2px;
            margin-bottom: 6px;
        }

        p {
            color: #999;
        }

        .card {
            margin-top: 40px;
            padding: 28px;
            border: 1px solid #292929;
            border-radius: 16px;
            background: #181818;
        }

        a {
            display: inline-block;
            margin-top: 18px;
            padding: 13px 18px;
            border-radius: 9px;
            background: white;
            color: #111;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <main>
        <h1>Money Imp</h1>
        <p>Your money. Minus the bullshit.</p>

        <section class="card">
            <h2>FreeAgent</h2>
            <p>Connect the accounting system of record.</p>

            <a href="{{ route('integrations.freeagent.connect') }}">
                Connect FreeAgent
            </a>
        </section>
    </main>
</body>
</html>
