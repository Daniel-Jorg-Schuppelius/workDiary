{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : audit-package.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Öffentliche Prüfer-Webansicht eines finalisierten ISMS-Auditpakets
  (Feature 046, „Live-Prüferzugang"). Rendert den EINGEFRORENEN Snapshot der
  Paketdatei — nie die laufenden Register. Die Feldschlüssel der Zeilen
  bleiben bewusst die JSON-Schlüssel der Datei: Ansicht und Download müssen
  wortgleich dasselbe belegen, eine Übersetzungsschicht könnte driften.
  Variablen: $package (IsmsAuditPackage), $snapshot (array), $token
--}}
@php
    /** @var array<string, mixed> $snapshot */
    $meta = is_array($snapshot['meta'] ?? null) ? $snapshot['meta'] : [];
    $sections = [
        'soa' => __('isms.package.section.soa'),
        'risks' => __('isms.package.section.risks'),
        'controls' => __('isms.package.section.controls'),
        'conformity' => __('isms.package.section.conformity'),
        'audits' => __('isms.package.section.audits'),
        'reviews' => __('isms.package.section.reviews'),
        'software' => __('isms.package.section.software'),
    ];
    $scalar = static fn ($v): string => is_bool($v) ? ($v ? __('Ja') : __('Nein')) : (is_scalar($v) ? (string) $v : '');
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ __('Auditpaket :no', ['no' => $package->displayNo()]) }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200">
<main class="mx-auto max-w-5xl p-4 space-y-4">
    <div class="rounded-box bg-base-100 p-6 shadow">
        <div class="mb-1 text-xs uppercase tracking-wide text-muted">{{ __('Auditpaket (finalisiert)') }}</div>
        <h1 class="text-xl font-semibold">{{ $meta['title'] ?? $package->title }}</h1>
        <dl class="mt-4 grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
            <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Organisation') }}</dt><dd>{{ $meta['organization'] ?? '—' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Geltungsbereich') }}</dt><dd>{{ $meta['scope'] ?? '—' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Norm') }}</dt><dd>{{ $meta['norm_filter'] ?? '—' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Paket-Nr.') }}</dt><dd class="font-mono">{{ $meta['package_no'] ?? $package->displayNo() }}</dd></div>
            {{-- Berichtsstichtag und Datenstand sind bewusst getrennt: der eine
                 ist dokumentiert, der andere der Zeitpunkt des Einfrierens. --}}
            <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Berichtsstichtag') }}</dt><dd>{{ $meta['as_of_date'] ?? '—' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Datenstand (eingefroren)') }}</dt><dd>{{ $meta['data_captured_at'] ?? '—' }}</dd></div>
        </dl>
        <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-muted">
            {{-- Der Hash macht Ansicht und Datei gegeneinander prüfbar. --}}
            <span class="font-mono break-all">SHA-256: {{ $package->file_hash ?? '—' }}</span>
            <a class="btn btn-primary btn-xs" href="{{ route('audit-packages.public-download', $token) }}">{{ __('Paketdatei herunterladen (JSON)') }}</a>
        </div>
    </div>

    @foreach ($sections as $key => $label)
        @php($rows = is_array($snapshot[$key] ?? null) ? $snapshot[$key] : [])
        <div class="rounded-box bg-base-100 p-6 shadow">
            <h2 class="mb-1 font-semibold">{{ $label }} <span class="text-sm font-normal text-muted">({{ count($rows) }})</span></h2>
            @if ($rows === [])
                <p class="text-sm text-muted">{{ __('Keine Einträge in diesem Abschnitt.') }}</p>
            @else
                <div class="space-y-3">
                    @foreach ($rows as $row)
                        <dl class="rounded-lg border border-base-300 p-3 text-sm grid gap-x-6 gap-y-0.5 sm:grid-cols-2">
                            @foreach ((array) $row as $field => $value)
                                @if (is_scalar($value) || $value === null)
                                    <div class="flex justify-between gap-3">
                                        <dt class="font-mono text-xs text-muted">{{ $field }}</dt>
                                        <dd class="text-right wrap-break-word">{{ $scalar($value) !== '' ? $scalar($value) : '—' }}</dd>
                                    </div>
                                @elseif (is_array($value) && array_is_list($value) && $value !== [] && is_scalar($value[0] ?? null))
                                    <div class="flex justify-between gap-3 sm:col-span-2">
                                        <dt class="font-mono text-xs text-muted">{{ $field }}</dt>
                                        <dd class="text-right">{{ implode(', ', array_map($scalar, $value)) }}</dd>
                                    </div>
                                @elseif (is_array($value) && $value !== [])
                                    <div class="sm:col-span-2">
                                        <dt class="font-mono text-xs text-muted">{{ $field }} ({{ count($value) }})</dt>
                                        <dd class="mt-1 space-y-1">
                                            @foreach ($value as $sub)
                                                <div class="rounded bg-base-200/60 px-2 py-1 text-xs">
                                                    @foreach ((array) $sub as $subField => $subValue)
                                                        @if (is_scalar($subValue) || $subValue === null)
                                                            <span class="mr-3"><span class="font-mono text-muted">{{ $subField }}:</span> {{ $scalar($subValue) !== '' ? $scalar($subValue) : '—' }}</span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    <p class="pb-6 text-center text-xs text-muted">
        {{ __('Diese Ansicht zeigt den bei der Finalisierung eingefrorenen Datenstand — identisch mit der herunterladbaren Paketdatei, prüfbar über den SHA-256-Hash.') }}
    </p>
</main>
</body>
</html>
