{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Sportartenprofil anlegen/bearbeiten (in #entry-modal geladen). Variablen: $profile (ClubSportProfile|null). --}}
@php
    $isEdit = $profile !== null;
    $lines = static fn (?array $rows, string $key = 'label'): string => collect($rows ?? [])->map(fn ($r) => is_array($r) ? ($r['code'] . '=' . $r[$key] . (isset($r['unit']) && $r['unit'] !== null ? ';' . $r['unit'] . ';' . (($r['lower_is_better'] ?? false) ? 'ja' : 'nein') : '')) : (string) $r)->implode("\n");
@endphp
<x-modal
    :title="$isEdit ? __('club.action.edit') : __('club.teams.action.create_profile')"
    :eyebrow="__('club.teams.title.profiles')"
    icon="sports"
    tone="primary"
    size="wide"
    :action="$isEdit ? route('club.profiles.update', $profile) : route('club.profiles.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.card.master_data')" icon="sports" tone="primary" cols="2" :description="__('club.teams.hint.profile')">
        <x-input-field name="name" :label="__('club.field.name')" required maxlength="120" :value="old('name', $profile?->name)" />
        <x-select-field name="family" :label="__('club.teams.field.family')" required>
            @foreach (\App\Enums\Club\ClubSportFamily::cases() as $family)
                <option value="{{ $family->value }}" @selected(old('family', $profile?->family->value ?? 'team_ball') === $family->value)>{{ $family->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="result_format" :label="__('club.teams.field.result_format')" required :hint="__('club.teams.hint.result_format')">
            @foreach (\App\Enums\Club\ClubResultFormat::cases() as $format)
                <option value="{{ $format->value }}" @selected(old('result_format', $profile?->result_format->value ?? 'goals') === $format->value)>{{ $format->label() }}</option>
            @endforeach
        </x-select-field>
        <div class="grid grid-cols-2 gap-2">
            <x-input-field name="squad_size_field" type="number" min="1" max="99" :label="__('club.teams.field.squad_size_field')" :value="old('squad_size_field', $profile?->squad_size_field)" />
            <x-input-field name="squad_size_bench" type="number" min="0" max="99" :label="__('club.teams.field.squad_size_bench')" :value="old('squad_size_bench', $profile?->squad_size_bench)" />
        </div>
        <x-textarea-field name="positions" :label="__('club.teams.field.positions')" rows="4" maxlength="4000" :value="old('positions', $lines($profile?->positions))" :hint="__('club.teams.hint.positions')" />
        <x-textarea-field name="disciplines" :label="__('club.teams.field.disciplines')" rows="4" maxlength="4000" :value="old('disciplines', $lines($profile?->disciplines))" :hint="__('club.teams.hint.disciplines')" />
        <x-input-field name="age_cutoff" :label="__('club.teams.field.age_cutoff')" maxlength="5" placeholder="01-01" :value="old('age_cutoff', $profile?->age_cutoff)" :hint="__('club.teams.hint.age_cutoff')" />
        <x-input-field name="resource_types" :label="__('club.teams.field.resource_types')" maxlength="1000" :value="old('resource_types', implode(', ', $profile?->resource_types ?? []))" :hint="__('club.teams.hint.resource_types')" />
        <x-checkbox-field name="has_doubles" :label="__('club.teams.field.has_doubles')" :checked="(bool) old('has_doubles', $profile?->has_doubles ?? false)" />
        <x-checkbox-field name="is_active" :label="__('club.field.is_active')" :checked="(bool) old('is_active', $profile?->is_active ?? true)" />
        <x-textarea-field name="notes" :label="__('club.field.notes')" rows="2" maxlength="2000" span="2" :value="old('notes', $profile?->notes)" />
    </x-form-group>
</x-modal>
