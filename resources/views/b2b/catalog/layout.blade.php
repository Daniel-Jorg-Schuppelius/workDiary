<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('b2b_catalog.public.title')) · {{ $organization->name }}</title>
    <style>
        :root { color-scheme: light dark; --fg:#1f2937; --muted:#6b7280; --bg:#f8fafc; --card:#ffffff; --border:#e5e7eb; --accent:#2563eb; --accent-fg:#ffffff; --err:#b91c1c; --errbg:#fef2f2; }
        @media (prefers-color-scheme: dark) { :root { --fg:#e5e7eb; --muted:#9ca3af; --bg:#0b1220; --card:#111827; --border:#1f2937; --accent:#3b82f6; --err:#fecaca; --errbg:#3f1d1d; } }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:var(--fg); background:var(--bg); line-height:1.55; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 24px 16px 64px; }
        header.masthead { margin-bottom: 18px; display:flex; justify-content:space-between; align-items:baseline; gap:12px; flex-wrap:wrap; }
        header.masthead h1 { font-size: 1.25rem; margin:0; }
        header.masthead p { color:var(--muted); margin:0; font-size:.85rem; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:16px 18px; margin-bottom:14px; }
        table { width:100%; border-collapse:collapse; font-size:.92rem; }
        th, td { text-align:left; padding:8px 10px; border-bottom:1px solid var(--border); vertical-align:middle; }
        th { color:var(--muted); font-weight:600; font-size:.8rem; }
        td.num, th.num { text-align:right; }
        .btn { display:inline-block; background:var(--accent); color:var(--accent-fg); border:0; border-radius:8px; padding:9px 16px; font-size:.95rem; cursor:pointer; text-decoration:none; }
        .btn.secondary { background:transparent; color:var(--accent); border:1px solid var(--border); }
        input[type=text], input[type=search] { padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:var(--card); color:var(--fg); font:inherit; }
        input[type=number] { width:6.5rem; padding:6px 8px; border:1px solid var(--border); border-radius:8px; background:var(--card); color:var(--fg); font:inherit; text-align:right; }
        .alert.err { background:var(--errbg); color:var(--err); border-radius:8px; padding:10px 12px; margin:0 0 14px; font-size:.9rem; }
        .muted { color:var(--muted); font-size:.85rem; }
        .toolbar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:space-between; margin-bottom:12px; }
        footer.foot { color:var(--muted); font-size:.8rem; margin-top:24px; }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="masthead">
            <h1>{{ $organization->name }} · {{ __('b2b_catalog.public.title') }}</h1>
            <p>@yield('subtitle')</p>
        </header>

        @yield('content')

        <footer class="foot">{{ __('b2b_catalog.public.footer') }}</footer>
    </div>
</body>
</html>
