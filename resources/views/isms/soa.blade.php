{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : soa.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Statement of Applicability (Feature 044, MVP 1): druckbare Read-Only-
  Tabelle aller Controls. Standalone-HTML mit Print-CSS
  (Muster: diary/case-file.blade.php — „Fallakte").
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('isms.soa.document_title') }} — {{ $organizationName }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 11px; color: #111; margin: 24px auto; max-width: 1100px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .meta { color: #555; font-size: 10px; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { padding: 5px 8px; border: 1px solid #ddd; vertical-align: top; text-align: left; }
        th { background: #f5f5f5; font-weight: 600; white-space: nowrap; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        tr:nth-child(even) td { background: #fafafa; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; white-space: nowrap; }
        .muted { color: #777; }
        .center { text-align: center; }
        .na td { color: #888; }
        .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 12px; }
        .toolbar a, .toolbar button { padding: 6px 14px; border: 1px solid #555; background: #fff; color: #111; text-decoration: none; cursor: pointer; font-size: 12px; border-radius: 3px; }
        .toolbar .primary { background: #2563eb; color: #fff; border-color: #2563eb; }
        @media print { .toolbar { display: none !important; } body { margin: 0; } }
        @page { size: A4 landscape; margin: 10mm; }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('isms.controls.index') }}">← {{ __('isms.action.back') }}</a>
        <button type="button" class="primary" onclick="window.print()">{{ __('isms.action.print') }}</button>
    </div>

    <h1>{{ __('isms.soa.heading') }}</h1>
    <p class="meta">
        {{ $organizationName }}
        · {{ __('isms.soa.generated_at') }}: {{ $generatedAt->format('d.m.Y H:i') }}
        · {{ __('isms.soa.control_count', ['count' => $controls->count()]) }}
    </p>

    <table>
        <thead>
            <tr>
                <th style="width: 60px;">{{ __('isms.field.code') }}</th>
                <th>{{ __('isms.field.title') }}</th>
                <th style="width: 70px;" class="center">{{ __('isms.field.applicable') }}</th>
                <th style="width: 24%;">{{ __('isms.field.justification') }}</th>
                <th style="width: 110px;">{{ __('isms.field.implementation_status') }}</th>
                <th style="width: 16%;">{{ __('isms.field.evidence_note') }}</th>
                <th style="width: 12%;">{{ __('isms.field.risks') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($controls as $control)
                <tr @class(['na' => ! $control->applicable])>
                    <td class="mono">{{ $control->code }}</td>
                    <td>
                        {{ $control->title }}
                        @if ($control->owner !== null)
                            <span class="muted"> · {{ $control->owner->name }}</span>
                        @endif
                    </td>
                    <td class="center">{{ $control->applicable ? __('isms.soa.yes') : __('isms.soa.no') }}</td>
                    <td>{{ $control->justification ?? '—' }}</td>
                    <td>{{ $control->implementation_status->label() }}</td>
                    <td>{{ $control->evidence_note ?? '—' }}</td>
                    <td>
                        @if ($control->risks->isEmpty())
                            <span class="muted">—</span>
                        @else
                            ({{ $control->risks->count() }})
                            <span class="mono">{{ $control->risks->map(fn($r) => $r->displayNo())->implode(', ') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">{{ __('isms.empty_controls') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="meta">{{ __('isms.soa.disclaimer') }}</p>
</body>
</html>
