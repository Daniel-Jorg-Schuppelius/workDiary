{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : attendance-list.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Teilnehmerliste eines Präsenztermins (Feature 149, MVP-741). QR-Code für
  den Selbst-Check-in und Unterschriftenspalte als Papier-Rückfall — auf
  einer Baustelle sind Netz und Handy keine Selbstverständlichkeit.
  Die Liste ist ein Arbeitsmittel, kein Nachweis.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('learning.pdf.attendance_title') }} — {{ $unit->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .meta { color: #555; font-size: 10px; margin-bottom: 12px; }
        .head { width: 100%; }
        .head td { vertical-align: top; border: 0; padding: 0; }
        .qr { width: 150px; text-align: right; }
        .qr img { width: 130px; height: 130px; }
        .qr .caption { font-size: 8px; color: #666; }
        table.list { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.list th, table.list td { padding: 7px 8px; border: 1px solid #ccc; text-align: left; }
        table.list th { background: #f5f5f5; font-weight: 600; }
        .sig { width: 42%; }
        .status { width: 90px; }
        .note { margin-top: 14px; font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td>
                <h1>{{ $unit->title }}</h1>
                <div class="meta">
                    {{ $event->title ?? '' }}<br>
                    {{ $event->started_at?->translatedFormat('d.m.Y H:i') }}
                    @if ($event->ended_at) – {{ $event->ended_at->translatedFormat('H:i') }} @endif
                    @if ($event->topic) · {{ $event->topic }} @endif
                </div>
            </td>
            <td class="qr">
                <img src="{{ $qrImage }}" alt="{{ __('learning.pdf.checkin_qr') }}">
                <div class="caption">{{ __('learning.pdf.checkin_qr') }}</div>
            </td>
        </tr>
    </table>

    <table class="list">
        <thead>
            <tr>
                <th>{{ __('learning.field.person') }}</th>
                <th class="status">{{ __('learning.field.status') }}</th>
                <th class="sig">{{ __('learning.pdf.signature') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($participants as $participant)
                <tr>
                    <td>{{ $participant->user?->name ?? '—' }}</td>
                    <td>{{ $participant->status->label() }}</td>
                    <td></td>
                </tr>
            @empty
                <tr><td colspan="3">{{ __('learning.empty.participants') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="note">{{ __('learning.pdf.attendance_note') }}</p>
</body>
</html>
