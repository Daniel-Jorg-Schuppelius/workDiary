{{--
  Created on   : Fri Aug 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : certificate.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zertifikat als PDF (Feature 149, MVP-740). Der Ausdruck ist eine Kopie —
  maßgeblich bleibt der Datensatz mit seinem Prüfcode, deshalb steht die
  Prüfadresse auf dem Blatt. Ein Widerruf wird sichtbar gedruckt, nicht
  verschwiegen.
--}}
@php
    /** @var \App\Models\Learning\LearningCertificate $certificate */
    $course = $certificate->course;
    $revoked = $certificate->revoked_at !== null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('learning.pdf.certificate_kind') }} {{ $certificate->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .sheet { border: 3px double #333; padding: 30px 34px; }
        .kind { font-size: 11px; letter-spacing: 0.22em; text-transform: uppercase; color: #555; }
        h1 { font-size: 26px; margin: 6px 0 20px; }
        .holder { font-size: 20px; font-weight: 700; margin: 4px 0 2px; }
        .lead { color: #444; margin: 0 0 4px; }
        .course { font-size: 16px; font-weight: 600; margin: 14px 0 2px; }
        table.meta { width: 100%; border-collapse: collapse; margin-top: 26px; }
        table.meta th, table.meta td { padding: 5px 8px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        table.meta th { background: #f5f5f5; width: 190px; font-weight: 600; }
        .verify { margin-top: 22px; font-size: 10px; color: #555; }
        .verify code { font-family: DejaVu Sans Mono, monospace; }
        .revoked { margin: 0 0 18px; padding: 10px 12px; border: 2px solid #a00; color: #a00; font-weight: 700; }
    </style>
</head>
<body>
<div class="sheet">
    @if ($revoked)
        {{-- Nie stillschweigend weglassen: ein spurlos verschwundener
             Widerruf verschleiert genau das, was er anzeigen soll. --}}
        <p class="revoked">
            {{ __('learning.pdf.revoked_on', ['date' => $certificate->revoked_at?->translatedFormat('d.m.Y')]) }}
            @if ($certificate->revoked_reason) — {{ $certificate->revoked_reason }} @endif
        </p>
    @endif

    <div class="kind">{{ __('learning.pdf.certificate_kind') }}</div>
    <h1>{{ __('learning.pdf.certificate_headline') }}</h1>

    <p class="lead">{{ __('learning.pdf.holder_intro') }}</p>
    <p class="holder">{{ $certificate->holder_name }}</p>

    <p class="lead">{{ __('learning.pdf.course_intro') }}</p>
    <p class="course">{{ $course?->title ?? '—' }}</p>
    @if ($course?->subtitle)
        <p class="lead">{{ $course->subtitle }}</p>
    @endif

    <table class="meta">
        <tr>
            <th>{{ __('learning.field.certificate_number') }}</th>
            <td>{{ $certificate->number }}</td>
        </tr>
        <tr>
            <th>{{ __('learning.field.issued_on') }}</th>
            <td>{{ $certificate->issued_on?->translatedFormat('d.m.Y') }}</td>
        </tr>
        @if ($certificate->valid_until)
            <tr>
                <th>{{ __('learning.field.valid_until') }}</th>
                <td>{{ $certificate->valid_until->translatedFormat('d.m.Y') }}</td>
            </tr>
        @endif
        @if ($certificate->score_percent !== null)
            <tr>
                <th>{{ __('learning.field.score') }}</th>
                <td>{{ $certificate->score_percent }} %</td>
            </tr>
        @endif
        @if ($course?->duration_minutes)
            <tr>
                <th>{{ __('learning.field.duration_minutes') }}</th>
                <td>{{ $course->duration_minutes }} {{ __('learning.field.minutes_short') }}</td>
            </tr>
        @endif
    </table>

    <p class="verify">
        {{ __('learning.pdf.verify_hint') }}<br>
        <code>{{ $verifyUrl }}</code><br>
        {{ __('learning.field.verification_code') }}: <code>{{ $certificate->verification_code }}</code>
    </p>
</div>
</body>
</html>
