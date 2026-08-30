{{--
  Created on   : Sat Aug 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : learning.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Auswertung der Lernplattform als PDF (Feature 149, MVP-747).
  **Quoten erst ab fünf Einschreibungen** — auch im Export. Sonst wäre die
  Datensparsamkeit eine Anzeigefrage statt einer Regel.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('learning.title.report') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 10px; }
        h2 { font-size: 13px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #111; text-transform: uppercase; letter-spacing: 0.06em; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { padding: 4px 7px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; font-weight: 600; }
        td.num { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .muted { color: #666; }
        .note { margin-top: 14px; font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <h1>{{ __('learning.title.report') }}</h1>

    <h2>{{ __('learning.field.courses') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('learning.field.course') }}</th>
                <th class="num">{{ __('learning.field.enrolled') }}</th>
                <th class="num">{{ __('learning.field.completed') }}</th>
                <th class="num">{{ __('learning.field.completion_rate') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($completion as $row)
                <tr>
                    <td>{{ $row['course']->title ?? '' }}</td>
                    <td class="num">{{ $row['enrolled'] }}</td>
                    <td class="num">{{ $row['completed'] }}</td>
                    <td class="num">
                        @if ($row['rate'] === null)
                            <span class="muted">{{ __('learning.field.suppressed') }}</span>
                        @else
                            {{ $row['rate'] }} %
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">{{ __('learning.empty.report') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="note">{{ __('learning.help.min_group', ['count' => $minGroup]) }}</p>
</body>
</html>
