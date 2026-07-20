{{-- „Problem melden" als standalone Vollseite (Feature 041, MVP-053).
     Wird geöffnet, wenn der Einstieg NICHT per Dialog-Host erfolgt — v. a.
     von den standalone Fehlerseiten (errors/_page), die weder App-Layout
     noch Modal-JS besitzen. Gleiches CSS-only-Gerüst wie die Fehlerseiten,
     damit die Seite auch bei kaputter Session/DB gestylt bleibt. Die Felder
     teilt sie sich mit dem Modal-Dialog (problem-reports._fields). --}}
@php
    $cancelUrl = ! empty($context['url']) ? $context['url'] : url('/');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Anti-Flash-Theme (ein Partial statt 17 Kopien; Vollaudit 2026-07, M51). --}}
    @include('partials.theme-bootstrap')
    <title>{{ __('problemreport.title.create') }} – {{ config('app.name', 'WorkDiary') }}</title>
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
<body class="min-h-screen bg-linear-to-b from-base-200 to-base-300 text-base-content"
      style="font-family: 'IBM Plex Sans', system-ui, sans-serif;">
    <div class="flex min-h-screen items-start justify-center px-4 py-8">
        <div class="w-full max-w-2xl overflow-hidden rounded-3xl border border-base-300 bg-base-100 shadow-lg">
            <header class="flex items-start gap-3 border-b border-base-300 bg-linear-to-br from-warning/15 via-warning/5 to-transparent px-6 py-5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-box bg-warning/15 text-warning">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1, 'wght' 500;">flag</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.2em] text-base-content/60">{{ __('problemreport.title.eyebrow') }}</p>
                    <h1 class="mt-1 text-xl font-bold text-base-content" style="font-family: 'Space Grotesk', sans-serif;">
                        {{ __('problemreport.title.create') }}
                    </h1>
                </div>
            </header>

            <form method="POST" action="{{ route('problem-reports.store') }}"
                  enctype="multipart/form-data" autocomplete="on">
                @csrf
                <div class="space-y-4 px-6 py-5">
                    @if ($errors->any())
                        <div class="rounded-lg border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">
                            {{ __('js.dialog.check_input') }}
                        </div>
                    @endif

                    @include('problem-reports._fields', [
                        'context' => $context,
                        'diagnosticsMode' => $diagnosticsMode,
                        'diagnosticsPreview' => $diagnosticsPreview,
                    ])
                </div>

                <footer class="flex items-center justify-end gap-2 border-t border-base-300 bg-base-200/40 px-6 py-4">
                    <x-button :href="$cancelUrl" tone="ghost" icon="close">{{ __('Abbrechen') }}</x-button>
                    <x-button type="submit" tone="warning" icon="check">{{ __('problemreport.action.submit') }}</x-button>
                </footer>
            </form>
        </div>
    </div>
</body>
</html>
