{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _paper.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Papierlayout eines ausgefüllten Formulars (Feature 203, MVP-1651): Kopftabelle,
  je Abschnitt eine Tabelle „Thema / Angaben“, Auswahl als ☐/☑ (x-field-display
  paper). Geteilt von Druckansicht (show) und PDF; immer gegen den eingefrorenen
  fields_snapshot. Erwartet: $submission, $subjectLabel (?string).
--}}
@php
    /** @var \App\Models\Form\FormSubmission $submission */
    $paperSchema = \App\Services\Fields\FieldSchema::fromArray($submission->fields_snapshot);
    $paperValues = \App\Services\Fields\FieldValues::fromArray($submission->values);
    $paperVisible = $paperSchema->visibleFor((array) $submission->values);
@endphp
<table class="paper-head">
    <tr>
        <th scope="row">{{ __('form.field.template') }}</th>
        <td>{{ optional($submission->template)->name ?? __('form.title.submission') }}</td>
        <th scope="row">{{ __('form.field.status') }}</th>
        <td>{{ $submission->status->label() }}</td>
    </tr>
    <tr>
        <th scope="row">{{ __('form.field.submitted_by') }}</th>
        <td>{{ optional($submission->submitter)->name ?? '—' }}</td>
        <th scope="row">{{ __('form.field.submitted_at') }}</th>
        <td>{{ $submission->submitted_at?->fdatetime() ?? "—" }}</td>
    </tr>
    <tr>
        <th scope="row">{{ __('form.field.subject') }}</th>
        <td colspan="3">{{ $subjectLabel ?? '—' }}</td>
    </tr>
</table>

@foreach ($paperVisible->sections() as $paperIndex => $paperSection)
    @if ($paperSection['fields'] === [])
        @continue
    @endif
    <h2 class="paper-section-title">{{ $paperIndex + 1 }}. {{ $paperSection['field']?->label ?? __('fields.form.general') }}</h2>
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
                        <x-field-display :field="$paperField" :values="$paperValues" :attachment="$submission->attachmentByMeta('field:' . $paperField->key)" paper />
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endforeach
