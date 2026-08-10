<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Money Imp</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #111;
            color: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .login {
            width: min(420px, calc(100% - 40px));
        }

        .brand {
            margin-bottom: 32px;
        }

        .brand h1 {
            margin: 0;
            font-size: 42px;
            letter-spacing: -2px;
        }

        .brand p {
            margin: 8px 0 0;
            color: #999;
        }

        label {
            display: block;
            margin: 20px 0 8px;
            color: #bbb;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #333;
            border-radius: 10px;
            background: #191919;
            color: white;
            font: inherit;
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 15px;
            border: 0;
            border-radius: 10px;
            background: white;
            color: #111;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .error {
            margin-top: 16px;
            padding: 12px 14px;
            border: 1px solid #633;
            border-radius: 10px;
            background: #211;
        }
    </style>
</head>

<body>
    <main class="login">
        <div class="brand">
            <h1>Money Imp</h1>
            <p>Your money. Minus the bullshit.</p>
        </div>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label for="email">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="email"
                required
                autofocus
            >

            <label for="password">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">
                Enter Money Imp
            </button>
        </form>
    </main>
</body>
</html>
