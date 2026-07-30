<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('b2b_catalog.public.error_title') }}</title>
    <style>
        :root { color-scheme: light dark; --fg:#1f2937; --bg:#f8fafc; --err:#b91c1c; --errbg:#fef2f2; }
        @media (prefers-color-scheme: dark) { :root { --fg:#e5e7eb; --bg:#0b1220; --err:#fecaca; --errbg:#3f1d1d; } }
        body { margin:0; font-family: system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:var(--fg); background:var(--bg); }
        .wrap { max-width:640px; margin:10vh auto 0; padding:0 16px; }
        .alert { background:var(--errbg); color:var(--err); border-radius:8px; padding:14px 16px; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>{{ __('b2b_catalog.public.error_title') }}</h1>
        <div class="alert">{{ $message }}</div>
    </div>
</body>
</html>
