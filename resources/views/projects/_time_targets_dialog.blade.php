{{--
  Created on   : Tue Nov 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _time_targets_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $project, $members, $default, $memberRows, $exceptions (ProjectTimeTarget mit Datum), $validFrom, $validUntil --}}
@php
    $days = [1 => __('Mo'), 2 => __('Di'), 3 => __('Mi'), 4 => __('Do'), 5 => __('Fr'), 6 => __('Sa'), 7 => __('So')];
    $clock = static fn (?int $minutes): string => $minutes === null ? '' : sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    // Drei leere Ausnahmezeilen reichen je Pflegegang; Bestand bleibt editierbar.
    $exceptionSlots = max(3, count($exceptions) + 3);
@endphp

<x-modal
    :title="__('Sollzeiten pflegen')"
    :eyebrow="$project->name"
    icon="flag"
    tone="primary"
    size="wide"
    :action="route('projects.time-targets.update', $project)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <div class="space-y-4" x-data="timeTargetGrid">
        <p class="text-sm text-muted">{{ __('Sollzeit je Tag als HH:MM. Die Projektvorgabe gilt für alle zuweisbaren Personen, eine Personenzeile geht vor, ein Datum vor dem Wochentag. Urlaub, Krankheit und Feiertage setzen das Soll auf null.') }}</p>

        <x-form-group :legend="__('Projektvorgabe')" icon="groups" tone="primary" cols="4">
            @foreach ($days as $i => $label)
                <x-input-field name="default[{{ $i }}]" :label="$label" placeholder="8:00" inputmode="numeric" maxlength="5"
                               :value="old('default.' . $i, $clock($default[$i] ?? null))" />
            @endforeach
        </x-form-group>

        <x-form-group :legend="__('Gültigkeit der Wochenvorgaben')" icon="event" cols="2">
            <x-input-field name="valid_from" type="date" :label="__('Gültig von')" :value="old('valid_from', $validFrom)" />
            <x-input-field name="valid_until" type="date" :label="__('Gültig bis')" :value="old('valid_until', $validUntil)" />
        </x-form-group>

        <x-form-group :legend="__('Personen')" icon="person" cols="1">
            <div class="flex flex-wrap items-end gap-2">
                <label class="fieldset">
                    <span class="fieldset-label">{{ __('Wert') }}</span>
                    <input type="text" data-ttg-value class="input input-bordered input-sm w-24" placeholder="8:00" inputmode="numeric" maxlength="5">
                </label>
                @foreach ($days as $i => $label)
                    <label class="label cursor-pointer gap-1">
                        <input type="checkbox" data-ttg-day="{{ $i }}" class="checkbox checkbox-sm" @checked($i <= 5)>
                        <span class="label-text">{{ $label }}</span>
                    </label>
                @endforeach
                <x-button type="button" tone="plain" size="sm" icon="done_all" x-on:click="applyToSelected()">{{ __('Auf ausgewählte Personen anwenden') }}</x-button>
            </div>
            @if ($members->isEmpty())
                <x-empty-state compact icon="person_off" :title="__('Dem Projekt sind noch keine Personen oder Teams zugewiesen.')" />
            @else
            <div class="max-h-[45vh] overflow-auto">
                {{-- raw-table-ok: Eingaberaster Person × Wochentag, keine Datenliste --}}
                <table class="table table-sm table-pin-rows">
                    <thead>
                        <tr>
                            <th><input type="checkbox" data-ttg-all class="checkbox checkbox-sm" aria-label="{{ __('Alle auswählen') }}" x-on:change="toggleAll()"></th>
                            <th>{{ __('Person') }}</th>
                            @foreach ($days as $label)
                                <th>{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            <tr data-ttg-row>
                                <td><input type="checkbox" data-ttg-member class="checkbox checkbox-sm" aria-label="{{ $member->name }}"></td>
                                <td class="whitespace-nowrap">{{ $member->name }}</td>
                                @foreach ($days as $i => $label)
                                    <td>
                                        <input type="text" name="members[{{ $member->sqid }}][{{ $i }}]" data-ttg-cell="{{ $i }}"
                                               value="{{ old('members.' . $member->sqid . '.' . $i, $clock($memberRows[$member->id][$i] ?? null)) }}"
                                               class="input input-bordered input-xs w-16" placeholder="—" inputmode="numeric" maxlength="5"
                                               aria-label="{{ $member->name }} {{ $label }}">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
            <p class="text-xs text-muted">{{ __('Leer = Projektvorgabe.') }}</p>
        </x-form-group>

        <x-form-group :legend="__('Ausnahmen an einem Datum')" icon="event_busy" cols="1">
            <div class="grid grid-cols-[1fr_auto_auto] items-end gap-2">
                @for ($n = 0; $n < $exceptionSlots; $n++)
                    @php
                        $row = $exceptions[$n] ?? null;
                        $selectedUser = old("exceptions.$n.user", $row?->user?->sqid ?? '');
                    @endphp
                    <x-select-field name="exceptions[{{ $n }}][user]" :label="$n === 0 ? __('Person') : null">
                        <option value="" @selected($selectedUser === '')>{{ __('Projektvorgabe') }}</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->sqid }}" @selected($selectedUser === $member->sqid)>{{ $member->name }}</option>
                        @endforeach
                    </x-select-field>
                    <x-input-field name="exceptions[{{ $n }}][date]" type="date" :label="$n === 0 ? __('Datum') : null"
                                   :value="old('exceptions.' . $n . '.date', $row?->specific_date?->toDateString())" />
                    <x-input-field name="exceptions[{{ $n }}][minutes]" :label="$n === 0 ? __('Soll') : null" placeholder="0:00" inputmode="numeric" maxlength="5"
                                   :value="old('exceptions.' . $n . '.minutes', $row !== null ? $clock((int) $row->target_minutes) : '')" />
                @endfor
            </div>
            <p class="text-xs text-muted">{{ __('0:00 setzt das Soll an diesem Tag aus; eine Zeile ohne Datum oder Soll wird nicht gespeichert.') }}</p>
        </x-form-group>
    </div>
</x-modal>
