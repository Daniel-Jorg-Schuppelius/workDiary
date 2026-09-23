{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _season_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Saison anlegen (in #entry-modal geladen). Variablen: $latest (ClubSeason|null) als Vorschlag für die nächste Saison. --}}
@php
    $suggestStart = $latest ? $latest->ends_on->addDay() : \Carbon\CarbonImmutable::today()->month(7)->day(1);
    $suggestEnd = $suggestStart->addYear()->subDay();
    $suggestName = $suggestStart->format('Y') . '/' . $suggestEnd->format('y');
@endphp
<x-modal
    :title="__('club.teams.action.create_season')"
    :eyebrow="__('club.teams.title.teams')"
    icon="calendar_month"
    tone="primary"
    :action="route('club.seasons.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">
    <input type="hidden" name="return_to" value="{{ url()->previous() }}">
    <x-form-group :legend="__('club.teams.field.season')" icon="calendar_month" tone="primary" cols="1" :description="__('club.teams.hint.season')">
        <x-input-field name="name" :label="__('club.field.name')" required maxlength="60" :value="old('name', $suggestName)" />
        <x-date-range layout="split" form-control required from-name="starts_on" to-name="ends_on"
                      :from-label="__('club.field.valid_from')" :to-label="__('club.field.valid_to')"
                      :from="old('starts_on', $suggestStart->toDateString())" :to="old('ends_on', $suggestEnd->toDateString())" />
    </x-form-group>
</x-modal>
