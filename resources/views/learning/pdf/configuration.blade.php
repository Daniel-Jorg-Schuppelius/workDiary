{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : configuration.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Konfigurationsausdruck der Lernplattform (Feature 149 §26, MVP-1271).
  Variablen: $organization, $printedAt, $version, $rows, $courses, $retention.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('learning.config.title') }} — {{ $printedAt->translatedFormat('d.m.Y H:i') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 19px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 20px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #111; text-transform: uppercase; letter-spacing: 0.06em; }
        .meta { color: #555; font-size: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { padding: 4px 7px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { background: #f5f5f5; font-weight: 600; }
        .kv th { width: 240px; }
        .note { margin-top: 16px; font-size: 10px; color: #555; }
    </style>
</head>
<body>
    <h1>{{ __('learning.config.title') }}</h1>
    <div class="meta">
        {{ $organization->name }} — {{ __('learning.config.as_of') }}: <strong>{{ $printedAt->translatedFormat('d.m.Y H:i') }}</strong>
        — {{ __('learning.config.version') }}: <strong>{{ $version }}</strong>
    </div>

    <h2>{{ __('learning.config.organization') }}</h2>
    <table class="kv">
        @foreach ($rows as $label => $value)
            <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
        @endforeach
    </table>

    <h2>{{ __('learning.config.courses') }}</h2>
    <table>
        <tr>
            <th>{{ __('learning.field.title') }}</th>
            <th>{{ __('learning.field.status') }}</th>
            <th>{{ __('learning.field.version') }}</th>
            <th>{{ __('learning.field.time_policy') }}</th>
            <th>{{ __('learning.config.sequential') }}</th>
            <th>{{ __('learning.field.access_days') }}</th>
            <th>{{ __('learning.field.validity_months') }}</th>
        </tr>
        @forelse ($courses as $course)
            <tr>
                <td>{{ $course->title }}</td>
                <td>{{ $course->status->label() }}</td>
                <td>{{ $course->versions->first() ? 'v' . $course->versions->first()->version : '—' }}</td>
                <td>{{ $course->time_policy->label() }}</td>
                <td>{{ $course->sequential ? __('learning.field.yes') : __('learning.field.no') }}</td>
                <td>{{ $course->access_days ?? '—' }}</td>
                <td>{{ $course->validity_months ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7">{{ __('learning.config.no_courses') }}</td></tr>
        @endforelse
    </table>

    <h2>{{ __('learning.config.retention') }}</h2>
    <table>
        <tr>
            <th>{{ __('learning.config.retention_area') }}</th>
            <th>{{ __('learning.config.retention_years') }}</th>
            <th>{{ __('learning.config.retention_basis') }}</th>
        </tr>
        @foreach ($retention as $entry)
            <tr>
                <td>{{ $entry['label'] }}</td>
                <td>{{ $entry['years'] ?? '—' }}</td>
                <td>{{ $entry['basis'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>

    <p class="note">{{ __('learning.config.note') }}</p>
</body>
</html>
