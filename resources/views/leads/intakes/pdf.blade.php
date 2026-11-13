{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  PDF der Aufnahmeakte (Feature 200, MVP-1644) im Papierlayout aus Feature 203:
  Kopftabelle, je Abschnitt „Thema / Angaben“, Auswahl als ☐/☑, jedes Prüfpaket
  auf eigener Seite, Fußzeile Stand · Kunde · Seite x von y. Eng sichtbare
  Angaben, die die Person nicht sehen darf ($hidden), stehen als „eingeschränkt“.
  Variablen: $intake, $schema, $values, $hidden (Schlüssel → Art), $reference, $pages.
--}}
@php
    /** @var \App\Models\Sales\LeadIntake $intake */
    $pages ??= null;
    $visible = $schema->visibleFor($intake->fieldDocumentValues()->toArray());
    $lastPackage = null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('lead_intake.title') }} — {{ $reference }}</title>
    <style>
        @page { margin: 16mm 14mm 20mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111; }
        h1 { font-size: 17px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        .paper-head { margin-bottom: 10px; }
        .paper-head th, .paper-head td, .paper-section th, .paper-section td { border: 1px solid #8f99a3; padding: 4px 6px; vertical-align: top; text-align: left; }
        .paper-head th { width: 17%; background: #f1f3f5; font-weight: bold; }
        .paper-section-title { font-size: 11.5px; margin: 14px 0 4px; padding: 3px 6px; background: #e9edf1; border-left: 3px solid #333; page-break-after: avoid; }
        .paper-package { page-break-before: always; font-size: 13px; margin: 0 0 6px; }
        .paper-section thead th { background: #f1f3f5; font-size: 9.5px; }
        .paper-section td.paper-topic { width: 38%; font-weight: bold; }
        .paper-section tr { page-break-inside: avoid; }
        .muted { color: #555; }
        .pre { white-space: pre-wrap; }
        .wd-ballot__mark { margin-right: 10px; white-space: nowrap; }
        .wd-field-note-display { color: #333; font-style: italic; }
        .paper-foot { position: fixed; bottom: -12mm; left: 0; right: 0; padding-top: 3px; border-top: 1px solid #8f99a3; font-size: 8.5px; color: #444; }
        .paper-page:after { content: counter(page); }
        .dd-pagenum { display: none; }
    </style>
</head>
<body>
    <div class="paper-foot">
        {{ __('fields.paper.as_of') }}: {{ ($intake->decided_at ?? $intake->updated_at)?->orgTz()->format('d.m.Y H:i') ?? '—' }}
        · {{ __('lead_intake.pdf.customer') }}: {{ $reference }}
        · <span class="paper-page">{{ __('fields.paper.page') }} </span>@if ($pages !== null) {{ __('fields.paper.of_pages', ['pages' => $pages]) }}@endif
    </div>

    <h1>{{ __('lead_intake.title') }}</h1>

    <table class="paper-head">
        <tr>
            <th scope="row">{{ __('lead_intake.pdf.customer') }}</th>
            <td>{{ $reference }}</td>
            <th scope="row">{{ __('lead_intake.field.stage') }}</th>
            <td>{{ $intake->stage->label() }} · {{ __('lead_intake.revision', ['revision' => $intake->revision]) }}</td>
        </tr>
        <tr>
            <th scope="row">{{ __('lead_intake.field.packages') }}</th>
            <td>{{ implode(', ', array_map(static fn ($p): string => $p->label, $intake->packageSnapshots())) ?: __('lead_intake.packages.none') }}</td>
            <th scope="row">{{ __('lead_intake.field.decision') }}</th>
            <td>{{ $intake->decision?->label() ?? '—' }}</td>
        </tr>
        <tr>
            <th scope="row">{{ __('lead_intake.field.decided_by') }}</th>
            <td>{{ $intake->decider?->name ?? '—' }} · {{ $intake->decided_at?->fdatetime() ?? '—' }}</td>
            <th scope="row">{{ __('lead_intake.field.second_review') }}</th>
            <td>{{ $intake->secondReviewer?->name ?? '—' }}</td>
        </tr>
        @if ($intake->decision_reason !== null)
            <tr>
                <th scope="row">{{ __('lead_intake.field.decision_reason') }}</th>
                <td colspan="3" class="pre">{{ $intake->decision_reason }}</td>
            </tr>
        @endif
    </table>

    @foreach ($visible->sections() as $paperIndex => $paperSection)
        @continue($paperSection['fields'] === [])
        @php($package = \App\Services\Sales\Intake\IntakeKeys::packageOf($paperSection['key']))
        @if ($package !== null && $package !== $lastPackage)
            @php($lastPackage = $package)
            <h2 class="paper-package">{{ collect($intake->packageSnapshots())->firstWhere('key', $package)?->label ?? $package }}</h2>
        @endif
        <h2 class="paper-section-title">{{ $paperIndex + 1 }}. {{ $paperSection['field']?->label ?? __('lead_intake.form.section_base') }}</h2>
        <table class="paper-section">
            <thead>
                <tr>
                    <th scope="col" class="paper-topic">{{ __('fields.paper.topic') }}</th>
                    <th scope="col">{{ __('fields.paper.details') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($paperSection['fields'] as $paperField)
                    <tr>
                        <td class="paper-topic">
                            {{ $paperField->label }}
                            @if ($paperField->unit !== null)
                                <span class="muted">({{ $paperField->unit }})</span>
                            @endif
                        </td>
                        <td class="pre">
                            @if (isset($hidden[$paperField->key]))
                                <span class="muted">{{ __('lead_intake.pdf.restricted', ['kind' => $hidden[$paperField->key]->label()]) }}</span>
                            @else
                                <x-field-display :field="$paperField" :values="$values" paper />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
