{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : grade_certificate.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Graduierungsbescheinigung als PDF (Feature 159, MVP-847). Der Ausdruck ist
  eine Kopie — maßgeblich bleibt der Datensatz; ein Widerruf wird sichtbar
  gedruckt, nicht verschwiegen. Variablen: $memberGrade, $candidate|null
--}}
@php
    /** @var \App\Models\Club\ClubMemberGrade $memberGrade */
    $member = $memberGrade->member;
    $revoked = $memberGrade->isRevoked();
    $event = $candidate?->offer?->event;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('club.exams.pdf.kind') }} {{ $memberGrade->grade?->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .sheet { border: 3px double #333; padding: 30px 34px; }
        .kind { font-size: 11px; letter-spacing: 0.22em; text-transform: uppercase; color: #555; }
        h1 { font-size: 26px; margin: 6px 0 20px; }
        .holder { font-size: 20px; font-weight: 700; margin: 4px 0 2px; }
        .lead { color: #444; margin: 0 0 4px; }
        .grade { font-size: 18px; font-weight: 600; margin: 14px 0 2px; }
        table.meta { width: 100%; border-collapse: collapse; margin-top: 26px; }
        table.meta th, table.meta td { padding: 5px 8px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        table.meta th { background: #f5f5f5; width: 190px; font-weight: 600; }
        .revoked { margin: 0 0 18px; padding: 10px 12px; border: 2px solid #a00; color: #a00; font-weight: 700; }
        .note { margin-top: 22px; font-size: 10px; color: #555; }
    </style>
</head>
<body>
<div class="sheet">
    @if ($revoked)
        <p class="revoked">{{ __('club.exams.pdf.revoked_on', ['date' => $memberGrade->revoked_at?->translatedFormat('d.m.Y')]) }}@if ($memberGrade->revoke_reason) — {{ $memberGrade->revoke_reason }}@endif</p>
    @endif

    <div class="kind">{{ __('club.exams.pdf.kind') }}</div>
    <h1>{{ __('club.exams.pdf.headline') }}</h1>

    <p class="lead">{{ __('club.exams.pdf.holder_intro') }}</p>
    <p class="holder">{{ $member?->fullName() }}</p>

    <p class="lead">{{ __('club.exams.pdf.grade_intro') }}</p>
    <p class="grade">{{ $memberGrade->grade?->name }} <span style="font-weight: 400; color: #444;">· {{ $memberGrade->system?->name }} ({{ $memberGrade->system?->discipline }})</span></p>

    <table class="meta">
        <tr><th>{{ __('club.grading.field.obtained_on') }}</th><td>{{ $memberGrade->obtained_on->translatedFormat('d.m.Y') }}</td></tr>
        <tr><th>{{ __('club.exams.pdf.source') }}</th><td>{{ $memberGrade->source->label() }}@if ($event) — {{ $event->title }}, {{ $event->started_at->orgTz()->translatedFormat('d.m.Y') }}@endif</td></tr>
        @if ($memberGrade->evidence)
            <tr><th>{{ __('club.grading.field.evidence') }}</th><td>{{ $memberGrade->evidence }}</td></tr>
        @endif
        @if ($candidate?->resultBy)
            <tr><th>{{ __('club.exams.field.examiners') }}</th><td>{{ $candidate->resultBy->name }}</td></tr>
        @endif
        @if ($memberGrade->confirmedBy)
            <tr><th>{{ __('club.exams.pdf.confirmed_by') }}</th><td>{{ $memberGrade->confirmedBy->name }}</td></tr>
        @endif
        <tr><th>{{ __('club.field.member_no') }}</th><td>{{ $member?->displayNo() }}</td></tr>
    </table>

    <p class="note">{{ __('club.exams.pdf.note') }}</p>
</div>
</body>
</html>
