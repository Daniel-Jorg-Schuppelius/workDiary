{{--
  Created on   : Sat May 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _page.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Gemeinsames Gerüst der Fehlerseiten (041-P0, MVP-053): standalone
     ohne App-Layout (funktioniert auch bei kaputter Session/DB), zeigt
     Request-ID für Support-Zuordnung und bietet — sobald das
     Fehlermeldesystem aktiv ist — den „Problem melden"-Einstieg an.

     Optionale Parameter (Vollaudit 2026-07, N42):
       safe        true = weder Request-ID-Lookup noch auth()-Checks
                   (DB-down-Fall: jeder Session-/DB-Zugriff würde erneut werfen)
       reportable  false = „Problem melden"-Button unterdrücken
       extraNote   Zusatzzeile unter der Message (kleiner, gedämpfter Text)
       details     Inhalt eines aufklappbaren Debug-Blocks (Aufrufer gated
                   selbst auf config('app.debug'))
       actions     eigene Buttons statt Zurück/Startseite:
                   list<array{label: string, href?: string, tone?: string,
                   icon?: string, reload?: bool}> — reload=true rendert den
                   data-reload-Button (Seite neu laden statt Navigation) --}}
@php
    $safe = $safe ?? false;
    $reportable = $reportable ?? true;
    $requestId = (! $safe && app()->bound(\App\Http\Middleware\AssignRequestId::CONTAINER_KEY))
        ? app(\App\Http\Middleware\AssignRequestId::CONTAINER_KEY)
        : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Anti-Flash-Theme (ein Partial statt 17 Kopien; Vollaudit 2026-07, M51). --}}
    @include('partials.theme-bootstrap')
    <title>{{ $title }} – {{ config('app.name', 'WorkDiary') }}</title>
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
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-lg rounded-3xl border border-base-300 bg-base-100 p-8 text-center shadow-lg">
            <img src="{{ asset('img/logo/workdiary-logo-512.png') }}" alt="WorkDiary"
                 class="mx-auto mb-6 h-12 w-auto object-contain">
            <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-{{ $tone ?? 'primary' }}/15 text-{{ $tone ?? 'primary' }}">
                <span class="material-symbols-outlined" style="font-size: 2rem; font-variation-settings: 'FILL' 1, 'wght' 500;">{{ $icon }}</span>
            </div>
            <h1 class="mb-2 text-2xl font-semibold" style="font-family: 'Space Grotesk', sans-serif;">
                {{ $title }}
            </h1>
            <p class="text-sm leading-relaxed text-base-content/75">
                {{ $message }}
            </p>
            @if (! empty($extraNote))
                <p class="mt-3 text-xs text-muted">
                    {{ $extraNote }}
                </p>
            @endif
            @if ($requestId !== null)
                <p class="mt-3 text-xs text-muted">
                    {{ __('errors.request_id') }}: <span class="font-mono select-all">{{ $requestId }}</span>
                </p>
            @endif
            @if (! empty($actions))
                <x-button-group center class="mt-6">
                    @foreach ($actions as $action)
                        @if (! empty($action['reload']))
                            <x-button type="button" data-reload tone="{{ $action['tone'] ?? 'primary' }}" size="sm" class="gap-1" icon="{{ $action['icon'] ?? 'refresh' }}">{{ $action['label'] }}</x-button>
                        @else
                            <x-button href="{{ $action['href'] ?? url('/') }}" tone="{{ $action['tone'] ?? 'primary' }}" size="sm" class="gap-1" icon="{{ $action['icon'] ?? 'home' }}">{{ $action['label'] }}</x-button>
                        @endif
                    @endforeach
                </x-button-group>
            @else
                <x-button-group center class="mt-6">
                    <x-button href="{{ url()->previous() }}" tone="ghost" size="sm" class="gap-1" icon="arrow_back">{{ __('Zurück') }}</x-button>
                    <x-button href="{{ url('/') }}" tone="primary" size="sm" class="gap-1" icon="home">{{ __('Zur Startseite') }}</x-button>
                    @if (! $safe && $reportable && auth()->check() && \Illuminate\Support\Facades\Route::has('problem-reports.create'))
                        {{-- rid: Request-ID des FEHLGESCHLAGENEN Requests — nur damit findet
                             der Diagnose-Auszug die zugehörigen Logzeilen wieder. --}}
                        <x-button href="{{ route('problem-reports.create', ['context' => 'error', 'code' => $code ?? null, 'rid' => $requestId]) }}" tone="warning" size="sm" class="gap-1" icon="flag">{{ __('errors.report_problem') }}</x-button>
                    @endif
                </x-button-group>
            @endif
            @if (! empty($details))
                <details class="mt-6 text-left text-xs">
                    <summary class="cursor-pointer text-muted">{{ __('Technische Details') }}</summary>
                    <pre class="mt-2 overflow-auto rounded-md bg-base-200 p-3 text-[0.7rem]">{{ $details }}</pre>
                </details>
            @endif
        </div>
    </div>
</body>
</html>
