{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Spielplan importieren (in #entry-modal geladen). Variablen: $teams, $preselectedTeam (int|null), $formTz. --}}
<x-modal
    :title="__('club.matches.action.import')"
    :eyebrow="__('club.matches.title.proposals')"
    icon="upload_file"
    tone="primary"
    size="wide"
    :action="route('club.matches.proposals.import')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.matches.action.import')">
    <x-form-group :legend="__('club.matches.card.import')" icon="upload_file" tone="primary" cols="2" :description="__('club.matches.hint.import')">
        <x-select-field name="club_group_id" :label="__('club.teams.field.team')" required>
            <option value="">–</option>
            @foreach ($teams as $team)
                <option value="{{ $team->sqid }}" @selected(old('club_group_id', $preselectedTeam !== null ? \App\Support\Sqid::encode(\App\Models\Club\ClubGroup::class, $preselectedTeam) : '') === $team->sqid)>{{ $team->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="source" :label="__('club.matches.field.source')" required>
            @foreach (\App\Enums\Club\ClubMatchProposalSource::cases() as $source)
                <option value="{{ $source->value }}" @selected(old('source', 'csv') === $source->value)>{{ $source->label() }}</option>
            @endforeach
        </x-select-field>
        <label class="form-control md:col-span-2">
            <span class="label-text">{{ __('club.matches.field.file') }}</span>
            <input type="file" name="file" class="file-input file-input-bordered w-full" accept=".csv,.txt,.ics,.ical">
            <span class="label-text-alt mt-1 text-muted">{{ __('club.matches.hint.import_file') }}</span>
        </label>
        <x-textarea-field name="content" :label="__('club.matches.field.content')" rows="6" maxlength="200000" span="2" :value="old('content')" :hint="__('club.matches.hint.import_columns')" />
        <input type="hidden" name="timezone" value="{{ old('timezone', $formTz) }}">
    </x-form-group>
</x-modal>
