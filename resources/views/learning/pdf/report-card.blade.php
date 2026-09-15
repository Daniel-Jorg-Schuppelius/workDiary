{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report-card.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zeugnis je Einschreibung (Feature 149, MVP-790) — gerendert über das
  Dokumentdesign wie das Zertifikat. Variablen: $enrollment, $result.
--}}
@php
    /** @var \App\Models\Learning\LearningEnrollment $enrollment */
    $course = $enrollment->course;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('learning.pdf.report_card_kind') }} {{ $course?->code }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .sheet { border: 3px double #333; padding: 30px 34px; }
        .kind { font-size: 11px; letter-spacing: 0.22em; text-transform: uppercase; color: #555; }
        h1 { font-size: 24px; margin: 6px 0 18px; }
        .holder { font-size: 18px; font-weight: 700; margin: 4px 0 2px; }
        .lead { color: #444; margin: 0 0 4px; }
        .course { font-size: 15px; font-weight: 600; margin: 12px 0 2px; }
        table.parts { width: 100%; border-collapse: collapse; margin-top: 22px; }
        table.parts th, table.parts td { padding: 5px 8px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        table.parts th { background: #f5f5f5; font-weight: 600; }
        table.parts td.num, table.parts th.num { text-align: right; }
        .total { margin-top: 16px; font-size: 14px; font-weight: 700; }
        .pending { margin: 0 0 14px; padding: 8px 10px; border: 2px solid #a60; color: #a60; font-weight: 700; }
        .note { margin-top: 18px; font-size: 10px; color: #555; }
    </style>
</head>
<body>
<div class="sheet">
    @if ($result['pending'])
        <p class="pending">{{ __('learning.pdf.report_card_pending') }}</p>
    @endif

    <div class="kind">{{ __('learning.pdf.report_card_kind') }}</div>
    <h1>{{ __('learning.pdf.report_card_headline') }}</h1>

    <p class="lead">{{ __('learning.pdf.report_card_intro') }}</p>
    <p class="holder">{{ $enrollment->learnerName() }}</p>

    <p class="lead">{{ __('learning.pdf.course_intro') }}</p>
    <p class="course">{{ $course?->title ?? '—' }}</p>

    <table class="parts">
        <thead>
            <tr>
                <th>{{ __('learning.field.component') }}</th>
                <th class="num">{{ __('learning.field.score') }}</th>
                <th class="num">%</th>
                @if ($result['weighted'])
                    <th class="num">{{ __('learning.field.weight') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($result['components'] as $part)
                <tr>
                    <td>{{ $part['title'] }}</td>
                    <td class="num">{{ $part['pending'] ? '–' : $part['points'] . ' / ' . $part['max'] }}</td>
                    <td class="num">{{ $part['pending'] ? '–' : $part['percent'] }}</td>
                    @if ($result['weighted'])
                        <td class="num">{{ $part['weight'] }} %</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="total">
        {{ __('learning.field.total') }}: {{ $result['pending'] ? '–' : $result['percent'] . ' %' }}
        @if ($result['grade'] !== null) · {{ __('learning.field.grade') }}: {{ $result['grade'] }} @endif
    </p>

    <p class="note">{{ __('learning.pdf.report_card_note') }} · {{ now()->translatedFormat('d.m.Y') }}</p>
</div>
</body>
</html>
