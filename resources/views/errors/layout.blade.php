{{--
    Error page shell. Self-contained (no database, no built assets) so it still
    renders when the error is the database or a missing build.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="turbo-visit-control" content="reload">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <style>
        :root { --ink: #0e2730; --soft: #4a626b; --canopy: #05303b; --amber: #ffb21e; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
               font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--ink);
               background: radial-gradient(1200px 600px at 50% -10%, #d9eef2, #edf3f5 60%); }
        .box { width: 100%; max-width: 460px; text-align: center; background: #fff; border-radius: 22px; padding: 40px 32px;
               box-shadow: 0 30px 70px -30px rgba(3, 31, 39, .55), 0 0 0 1px #d6e2e6; }
        .stripe { height: 5px; border-radius: 99px; margin: -16px auto 28px; width: 120px;
                  background: linear-gradient(90deg, #12a66a 0 25%, #2f6fe4 25% 50%, #e0294f 50% 75%, #ffb21e 75%); }
        .code { display: inline-grid; place-items: center; width: 84px; height: 84px; border-radius: 24px; font-size: 30px; font-weight: 800;
                background: linear-gradient(160deg, #0a5163, var(--canopy)); color: var(--amber); box-shadow: 0 12px 24px -12px rgba(3, 31, 39, .8); }
        h1 { font-size: 22px; margin: 22px 0 8px; }
        p { color: var(--soft); line-height: 1.55; margin: 0 0 26px; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        a, button { font: inherit; font-weight: 600; text-decoration: none; padding: 11px 20px; border-radius: 12px; border: 0; cursor: pointer; }
        .primary { background: var(--amber); color: #031f27; box-shadow: 0 4px 0 #e58e00; }
        .ghost { background: #eef4f6; color: var(--ink); }
    </style>
</head>
<body>
    <main class="box">
        <div class="stripe"></div>
        <div class="code">{{ $code }}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            <a href="{{ url('/') }}" class="primary">Go to dashboard</a>
            <button type="button" class="ghost" onclick="history.back()">Go back</button>
        </div>
    </main>
</body>
</html>
