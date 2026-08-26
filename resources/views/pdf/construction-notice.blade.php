{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : construction-notice.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    /** @var \App\Models\Construction\ConstructionNotice $notice */
    /** @var \App\Models\Organization|null $organization */
    /** @var \Illuminate\Support\Carbon $generatedAt */
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $notice->kind->label() }} {{ $notice->displayNo() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #111; }
        h1 { font-size: 16pt; margin: 0 0 2mm 0; }
        h2 { font-size: 12pt; margin: 6mm 0 2mm 0; border-bottom: 1px solid #888; }
        .addr { margin: 6mm 0 8mm 0; line-height: 1.4; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .meta th { text-align: left; width: 45mm; padding: 1mm 2mm; background: #f3f3f3; }
        .meta td { padding: 1mm 2mm; }
        .body { white-space: pre-line; }
        .muted { color: #555; font-size: 9pt; }
        .note { border: 1px solid #888; padding: 2mm; margin-top: 4mm; font-size: 10pt; }
        .footer { position: fixed; bottom: 8mm; left: 8mm; right: 8mm; font-size: 8pt; color: #555; border-top: 1px solid #ccc; padding-top: 1mm; }
    </style>
</head>
<body>
<div class="addr">
    {{ $notice->recipient_name ?: ($notice->customer->name ?? '—') }}<br>
    @if ($notice->site !== null)
        {{ $notice->site->address_street }}<br>
        {{ $notice->site->address_zip }} {{ $notice->site->address_city }}
    @endif
</div>

<h1>{{ $notice->kind->label() }}</h1>
<div class="muted">{{ $organization?->name }}</div>

<table class="meta">
    <tr><th>{{ __('construction.pdf.number') }}</th><td>{{ $notice->displayNo() }}</td></tr>
    <tr><th>{{ __('construction.pdf.subject') }}</th><td>{{ $notice->subject }}</td></tr>
    <tr><th>{{ __('construction.pdf.occurred_on') }}</th><td>{{ $notice->occurred_on?->format('d.m.Y') }}</td></tr>
    @if ($notice->project !== null)
        <tr><th>{{ __('construction.pdf.project') }}</th><td>{{ $notice->project->name }}</td></tr>
    @endif
    @if ($notice->site !== null)
        <tr><th>{{ __('construction.pdf.site') }}</th><td>{{ $notice->site->name }}</td></tr>
    @endif
    @if ($notice->legal_reference)
        <tr><th>{{ __('construction.pdf.legal_reference') }}</th><td>{{ $notice->legal_reference }}</td></tr>
    @endif
</table>

<h2>{{ __('construction.pdf.facts') }}</h2>
<div class="body">{{ $notice->facts }}</div>

@if ($notice->impact_schedule)
    <h2>{{ __('construction.pdf.impact_schedule') }}</h2>
    <div class="body">{{ $notice->impact_schedule }}</div>
@endif

@if ($notice->impact_cost)
    <h2>{{ __('construction.pdf.impact_cost') }}</h2>
    <div class="body">{{ $notice->impact_cost }}</div>
@endif

@if ($notice->weatherSnapshot !== null)
    <h2>{{ __('construction.pdf.weather') }}</h2>
    <table class="meta">
        <tr>
            <th>{{ __('construction.pdf.weather_values') }}</th>
            <td>
                {{ $notice->weatherSnapshot->temp_min }} – {{ $notice->weatherSnapshot->temp_max }} °C,
                {{ $notice->weatherSnapshot->precipitation_mm }} mm,
                {{ $notice->weatherSnapshot->wind_gust_kmh }} km/h
            </td>
        </tr>
        <tr>
            <th>{{ __('construction.pdf.weather_source') }}</th>
            <td>{{ $notice->weatherSnapshot->provider }} — {{ $notice->weatherSnapshot->fetched_at?->format('d.m.Y H:i') }}</td>
        </tr>
    </table>
@endif

@if ($notice->claims_time_extension)
    <div class="note">
        <strong>{{ __('construction.pdf.time_extension') }}</strong><br>
        {{ __('construction.pdf.time_extension_text') }}
    </div>
@endif

<div class="note muted">{{ __('construction.pdf.disclaimer') }}</div>

<div class="footer">
    {{ $notice->displayNo() }} · {{ $organization?->name }} · {{ $generatedAt->format('d.m.Y H:i') }}
</div>
</body>
</html>
