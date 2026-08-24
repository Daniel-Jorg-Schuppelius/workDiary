{{--
  Created on   : Mon Jul 20 2026
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
    <meta name="robots" content="{{ ($embed ?? false) ? 'noindex, nofollow' : 'index, follow' }}">
    <title>@yield('title', __('Karriere')) · {{ $organization->name }}</title>
    <style>
        :root { color-scheme: light dark; --fg:#1f2937; --muted:#6b7280; --bg:#f8fafc; --card:#ffffff; --border:#e5e7eb; --accent:#2563eb; --accent-fg:#ffffff; --ok:#065f46; --okbg:#ecfdf5; --err:#b91c1c; --errbg:#fef2f2; }
        @media (prefers-color-scheme: dark) { :root { --fg:#e5e7eb; --muted:#9ca3af; --bg:#0b1220; --card:#111827; --border:#1f2937; --accent:#3b82f6; --ok:#a7f3d0; --okbg:#052e2b; --err:#fecaca; --errbg:#3f1d1d; } }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:var(--fg); background:{{ ($embed ?? false) ? 'transparent' : 'var(--bg)' }}; line-height:1.55; }
        .wrap { max-width: 720px; margin: 0 auto; padding: {{ ($embed ?? false) ? '12px' : '28px 16px 64px' }}; }
        header.masthead { margin-bottom: 20px; }
        header.masthead h1 { font-size: 1.35rem; margin:0 0 2px; }
        header.masthead p { color:var(--muted); margin:0; font-size:.9rem; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:18px 20px; margin-bottom:14px; }
        .card h2 { margin:0 0 6px; font-size:1.1rem; }
        .meta { color:var(--muted); font-size:.85rem; margin:0 0 4px; }
        .section h3 { font-size:.95rem; margin:16px 0 4px; }
        .section p { margin:0 0 8px; white-space:pre-line; }
        a { color:var(--accent); }
        .btn { display:inline-block; background:var(--accent); color:var(--accent-fg); border:0; border-radius:8px; padding:10px 16px; font-size:.95rem; cursor:pointer; text-decoration:none; }
        .btn:disabled { opacity:.5; cursor:not-allowed; }
        label { display:block; font-size:.9rem; margin:10px 0 3px; font-weight:600; }
        input[type=text], input[type=email], input[type=tel], textarea, input[type=file] { width:100%; padding:9px 11px; border:1px solid var(--border); border-radius:8px; background:var(--card); color:var(--fg); font:inherit; }
        textarea { min-height:110px; resize:vertical; }
        .hp { position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden; }
        .check { display:flex; gap:8px; align-items:flex-start; margin:12px 0; font-weight:400; }
        .check input { margin-top:4px; }
        .alert { border-radius:8px; padding:10px 12px; margin:0 0 14px; font-size:.9rem; }
        .alert.err { background:var(--errbg); color:var(--err); }
        .alert.ok { background:var(--okbg); color:var(--ok); }
        .muted { color:var(--muted); font-size:.85rem; }
        ul.errs { margin:0; padding-left:18px; }
        footer.foot { color:var(--muted); font-size:.8rem; margin-top:28px; }
    </style>
</head>
<body>
    <div class="wrap">
        @unless($embed ?? false)
            <header class="masthead">
                <h1>{{ $organization->name }} · {{ __('Karriere') }}</h1>
                <p>{{ __('Offene Stellen und Bewerbung') }}</p>
            </header>
        @endunless

        @if(session('error'))
            <div role="alert" class="alert err">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert err"><ul class="errs">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @yield('content')

        @unless($embed ?? false)
            <footer class="foot">{{ __('Bewerbungen werden vertraulich und ausschließlich für das Auswahlverfahren verarbeitet.') }}</footer>
        @endunless
    </div>
</body>
</html>
