<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script @cspNonce>
        (function () {
            try {
                var savedTheme = localStorage.getItem('workDiaryTheme');
                var prefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;
                var theme = savedTheme || (prefersLight ? 'corporate' : 'dim');
                document.documentElement.setAttribute('data-theme', theme);
                document.documentElement.style.colorScheme = theme === 'corporate' ? 'light' : 'dark';
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'corporate');
            }
        })();
        // Kein app.js auf dieser Seite: [data-reload]-Button per Delegation binden
        // (Inline-onclick wäre unter CSP Stufe 1 blockiert).
        document.addEventListener('click', function (event) {
            if (event.target instanceof Element && event.target.closest('[data-reload]')) {
                window.location.reload();
            }
        });
    </script>
    <title>{{ __('Datenbank nicht erreichbar') }} – {{ config('app.name', 'WorkDiary') }}</title>
    <style>
        .material-symbols-outlined {
            font-family: 'Material Symbols Outlined';
            font-weight: normal;
            font-style: normal;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            -webkit-font-feature-settings: 'liga';
            -webkit-font-smoothing: antialiased;
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
    </style>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="min-h-screen bg-gradient-to-b from-base-200 to-base-300 text-base-content"
      style="font-family: 'IBM Plex Sans', system-ui, sans-serif;">
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-lg rounded-3xl border border-base-300 bg-base-100 p-8 text-center shadow-lg">
            <img src="{{ asset('img/logo/workdiary-logo-512.png') }}" alt="WorkDiary"
                 class="mx-auto mb-6 h-12 w-auto object-contain">
            <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-warning/15 text-warning">
                <span class="material-symbols-outlined" style="font-size: 2rem; font-variation-settings: 'FILL' 1, 'wght' 500;">database_off</span>
            </div>
            <h1 class="mb-2 text-2xl font-semibold" style="font-family: 'Space Grotesk', sans-serif;">
                {{ __('Datenbank vorübergehend nicht erreichbar') }}
            </h1>
            <p class="text-sm leading-relaxed text-base-content/75">
                {{ __('Wir können die Datenbank gerade nicht erreichen. Bitte versuche es in wenigen Augenblicken erneut. Falls das Problem bestehen bleibt, wende dich an deine Administration.') }}
            </p>
            <div class="mt-6 flex justify-center gap-2">
                <x-button type="button" data-reload tone="primary" size="sm" class="gap-1" icon="refresh">{{ __('Erneut versuchen') }}</x-button>
            </div>
            @if (config('app.debug') && ! empty($exceptionMessage))
                <details class="mt-6 text-left text-xs">
                    <summary class="cursor-pointer text-base-content/60">{{ __('Technische Details') }}</summary>
                    <pre class="mt-2 overflow-auto rounded-md bg-base-200 p-3 text-[0.7rem]">{{ $exceptionMessage }}</pre>
                </details>
            @endif
        </div>
    </div>
</body>
</html>
