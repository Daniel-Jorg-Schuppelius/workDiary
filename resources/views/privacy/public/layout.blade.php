{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : layout.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('dsar.portal.title'))</title>
    <style>
        :root { color-scheme: light dark; --fg:#1f2937; --muted:#6b7280; --bg:#f8fafc; --card:#ffffff; --border:#e5e7eb; --accent:#2563eb; --accent-fg:#ffffff; --err:#b91c1c; --errbg:#fef2f2; --okbg:#ecfdf5; --ok:#065f46; }
        @media (prefers-color-scheme: dark) { :root { --fg:#e5e7eb; --muted:#9ca3af; --bg:#0b1220; --card:#111827; --border:#1f2937; --accent:#3b82f6; --err:#fecaca; --errbg:#3f1d1d; --okbg:#052e2b; --ok:#a7f3d0; } }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:var(--fg); background:var(--bg); line-height:1.55; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 28px 16px 64px; }
        header.masthead { margin-bottom: 20px; }
        header.masthead h1 { font-size: 1.35rem; margin:0 0 2px; }
        header.masthead p { color:var(--muted); margin:0; font-size:.9rem; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:18px 20px; margin-bottom:14px; }
        .card h2 { margin:0 0 6px; font-size:1.1rem; }
        .card p { margin:0 0 8px; }
        .muted { color:var(--muted); font-size:.85rem; }
        a { color:var(--accent); }
        .btn { display:inline-block; background:var(--accent); color:var(--accent-fg); border:0; border-radius:8px; padding:10px 16px; font-size:.95rem; cursor:pointer; text-decoration:none; }
        label { display:block; font-size:.9rem; margin:10px 0 3px; font-weight:600; }
        input[type=text], input[type=email], select, textarea, input[type=file] { width:100%; padding:9px 11px; border:1px solid var(--border); border-radius:8px; background:var(--card); color:var(--fg); font:inherit; }
        textarea { min-height:130px; resize:vertical; }
        .hp { position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden; }
        .check { display:flex; gap:8px; align-items:flex-start; margin:12px 0; font-weight:400; }
        .check input { margin-top:4px; }
        .alert { border-radius:8px; padding:10px 12px; margin:0 0 14px; font-size:.9rem; }
        .alert.err { background:var(--errbg); color:var(--err); }
        .alert.ok { background:var(--okbg); color:var(--ok); }
        ul { margin:0 0 8px; padding-left:20px; }
        footer.legal { color:var(--muted); font-size:.8rem; margin-top:24px; }
    </style>
</head>
<body>
    <main class="wrap">
        <header class="masthead">
            <h1>{{ ($portal ?? null)?->organization?->name ?? __('dsar.portal.title') }}</h1>
            <p>{{ __('dsar.portal.subtitle') }}</p>
        </header>

        @yield('content')

        <footer class="legal">
            <p>{{ __('dsar.portal.footer') }}</p>
        </footer>
    </main>
</body>
</html>
