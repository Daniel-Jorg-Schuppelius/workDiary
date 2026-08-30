{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dossier.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Nachweismappe als PDF (Feature 149, MVP-750). Aggregiert ist die Vorgabe;
  die namentliche Ausprägung erscheint nur, wenn sie mit Anlass angefordert
  wurde. Stichtag und Prüfhash stehen auf dem Blatt — ohne sie ist die
  Aussage nicht nachvollziehbar.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('learning.pdf.dossier_title') }} — {{ $asOf->translatedFormat('d.m.Y') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 19px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 20px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #111; text-transform: uppercase; letter-spacing: 0.06em; }
        h3 { font-size: 12px; margin: 14px 0 4px; }
        .meta { color: #555; font-size: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { padding: 4px 7px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { background: #f5f5f5; font-weight: 600; }
        .kv th { width: 220px; }
        .ok { color: #146c2e; font-weight: 600; }
        .bad { color: #a00; font-weight: 600; }
        .note { margin-top: 16px; font-size: 10px; color: #555; }
        .hash { font-family: DejaVu Sans Mono, monospace; font-size: 9px; word-break: break-all; }
    </style>
</head>
<body>
    <h1>{{ __('learning.pdf.dossier_title') }}</h1>
    <div class="meta">
        {{ $organization->name }} — {{ __('learning.field.as_of') }}: <strong>{{ $asOf->translatedFormat('d.m.Y') }}</strong>
        @if ($named) — {{ __('learning.pdf.named_reason') }}: {{ $reason }} @endif
    </div>

    <h2>{{ __('learning.pdf.coverage') }}</h2>
    <table class="kv">
        <tr><th>{{ __('learning.field.people') }}</th><td>{{ $summary['people'] }}</td></tr>
        <tr>
            <th>{{ __('learning.field.ready') }}</th>
            <td class="{{ $summary['ready'] === $summary['people'] ? 'ok' : '' }}">{{ $summary['ready'] }}</td>
        </tr>
        <tr>
            <th>{{ __('learning.field.expired') }}</th>
            <td class="{{ $summary['expired'] > 0 ? 'bad' : '' }}">{{ $summary['expired'] }}</td>
        </tr>
        <tr><th>{{ __('learning.field.open_obligations') }}</th><td>{{ $summary['open_obligations'] }}</td></tr>
        <tr><th>{{ __('learning.field.earliest_expiry') }}</th><td>{{ $summary['earliest_expiry'] ?? '—' }}</td></tr>
    </table>

    @if ($named)
        <h2>{{ __('learning.pdf.by_person') }}</h2>
        @foreach ($rows as $row)
            <h3>{{ $row['user']->name }}</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 22%">{{ __('learning.field.proof_kind') }}</th>
                        <th>{{ __('learning.field.title') }}</th>
                        <th style="width: 18%">{{ __('learning.field.valid_until') }}</th>
                        <th style="width: 18%">{{ __('learning.field.valid_on_date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($row['qualifications'] as $entry)
                        <tr>
                            <td>{{ __('learning.field.qualification') }}</td>
                            <td>{{ $entry['name'] }}</td>
                            <td>{{ $entry['valid_until'] ?? '—' }}</td>
                            <td class="{{ $entry['valid_on'] ? 'ok' : 'bad' }}">{{ $entry['valid_on'] ? __('learning.field.yes') : __('learning.field.no') }}</td>
                        </tr>
                    @endforeach
                    @foreach ($row['instructions'] as $entry)
                        <tr>
                            <td>{{ __('learning.field.instruction') }}</td>
                            <td>{{ $entry['topic'] }} @if ($entry['held_on']) ({{ $entry['held_on'] }}) @endif</td>
                            <td>{{ $entry['next_due_on'] ?? '—' }}</td>
                            <td class="{{ $entry['valid_on'] ? 'ok' : 'bad' }}">{{ $entry['valid_on'] ? __('learning.field.yes') : __('learning.field.no') }}</td>
                        </tr>
                    @endforeach
                    @foreach ($row['certificates'] as $entry)
                        <tr>
                            <td>{{ __('learning.field.certificate') }}</td>
                            <td>{{ $entry['course'] }} ({{ $entry['number'] }})</td>
                            <td>{{ $entry['valid_until'] ?? '—' }}</td>
                            <td class="{{ $entry['valid_on'] ? 'ok' : 'bad' }}">{{ $entry['valid_on'] ? __('learning.field.yes') : __('learning.field.no') }}</td>
                        </tr>
                    @endforeach
                    @if ($row['qualifications'] === [] && $row['instructions'] === [] && $row['certificates'] === [])
                        <tr><td colspan="4">{{ __('learning.empty.proofs') }}</td></tr>
                    @endif
                </tbody>
            </table>
            @if ($row['open_obligations'] > 0)
                <p class="bad">{{ __('learning.field.open_obligations') }}: {{ $row['open_obligations'] }}</p>
            @endif
        @endforeach
    @else
        <p class="note">{{ __('learning.pdf.aggregated_note') }}</p>
    @endif

    <p class="note">
        {{ __('learning.pdf.hash_note') }}<br>
        <span class="hash">{{ $hash }}</span>
    </p>
</body>
</html>
