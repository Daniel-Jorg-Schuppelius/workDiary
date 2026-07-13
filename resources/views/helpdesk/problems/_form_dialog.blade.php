{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Modal (Feature 065, MVP-156): Problem anlegen/bearbeiten. Anlage optional
  mit vorbelegten Incidents (aus dem Verknüpfungs-Widget des Tickets) —
  die Verknüpfung läuft über ProblemService::openFromIncidents(). Erwartet:
  $problem, $isEdit, $incidentOptions, $selectedIncidents (Sqids).
--}}
@php
    /** @var \App\Models\Problem $problem */
    /** @var bool $isEdit */
    $action = $isEdit ? route('servicedesk.problems.update', $problem) : route('servicedesk.problems.store');
    $method = $isEdit ? 'PATCH' : 'POST';
    $title = $isEdit ? __('Problem bearbeiten') : __('Neues Problem');
    $selected = collect(old('incidents', $selectedIncidents ?? []))->map(fn($s) => (string) $s)->all();
@endphp

<x-modal
    :title="$title"
    :eyebrow="__('Service Desk')"
    icon="troubleshoot"
    tone="primary"
    size="md"
    :action="$action"
    :method="$method"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-form-group :legend="__('Problem')" icon="troubleshoot" tone="primary" cols="1">
        <x-input-field name="title" :label="__('Titel')" required minlength="3" maxlength="255" :value="old('title', $problem->title)" />
        <x-textarea-field name="description" :label="__('Beschreibung')" rows="3" maxlength="10000" :value="old('description', $problem->description)" />

        @if (! $isEdit)
            <x-select-field name="incidents[]" :label="__('Incidents verknüpfen')" multiple error="incidents"
                            :hint="__('Mehrfachauswahl möglich — das Problem entsteht als Ursachenobjekt hinter den gewählten Incidents.')">
                @foreach ($incidentOptions as $incident)
                    <option value="{{ $incident->sqid }}" @selected(in_array($incident->sqid, $selected, true))>
                        {{ $incident->ticket_no }} — {{ \Illuminate\Support\Str::limit($incident->title, 60) }}
                    </option>
                @endforeach
            </x-select-field>
        @endif
    </x-form-group>

    @if ($isEdit)
        <x-form-group :legend="__('Ursachenanalyse')" icon="science" tone="primary" cols="1">
            <x-textarea-field name="root_cause" :label="__('Ursache')" rows="2" maxlength="10000" :value="old('root_cause', $problem->root_cause)" />
            <x-textarea-field name="evidence" :label="__('Evidenz')" rows="2" maxlength="10000" :value="old('evidence', $problem->evidence)" />
            <x-textarea-field name="workaround" :label="__('Workaround')" rows="2" maxlength="10000" :value="old('workaround', $problem->workaround)" />
            <x-textarea-field name="permanent_fix" :label="__('Dauerhafte Lösung')" rows="2" maxlength="10000" :value="old('permanent_fix', $problem->permanent_fix)" />

            <x-select-field name="visibility" :label="__('Sichtbarkeit')" required
                            :hint="__('Kundenportal zeigt Known Errors (Titel + Workaround) read-only an.')">
                <option value="internal" @selected(old('visibility', $problem->visibility) === 'internal')>{{ __('Intern') }}</option>
                <option value="customer" @selected(old('visibility', $problem->visibility) === 'customer')>{{ __('Kundenportal') }}</option>
            </x-select-field>
        </x-form-group>
    @endif
</x-modal>
