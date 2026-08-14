{{--
  Created on   : Thu Jun 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Arbeits-Team anlegen oder bearbeiten. --}}
@php
    /** @var \App\Models\Team $team */
    /** @var \Illuminate\Database\Eloquent\Collection $orgUsers */
    /** @var list<int> $assignedMemberIds */
    $isEdit = $isEdit ?? (bool) ($team->id ?? false);
@endphp
<x-modal
    :title="$isEdit ? __('Team bearbeiten') : __('Team anlegen')"
    :eyebrow="__('Team-Verwaltung')"
    icon="groups"
    tone="primary"
    size="xl"
    :action="$isEdit ? route('teams.update', $team) : route('teams.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')"
>
    <x-slot:headerActions>
        <x-dialog-status-controls :name="null" :color="$team->color ?? '#6b7280'" />
    </x-slot:headerActions>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <x-form-group :label="__('Teamname')" name="name" class="md:col-span-2" required>
            <input type="text" name="name" value="{{ old('name', $team->name) }}"
                   class="input input-bordered w-full" maxlength="120" required autofocus />
        </x-form-group>

        <x-form-group :label="__('Teamleiter')" name="lead_user_id">
            @php($leadSqid = (string) old('lead_user_id', $team->lead_user_id ? \App\Support\Sqid::encode(\App\Models\User::class, $team->lead_user_id) : ''))
            <select name="lead_user_id" class="select select-bordered w-full">
                <option value="">{{ __('— kein Teamleiter —') }}</option>
                @foreach ($orgUsers as $u)
                    <option value="{{ $u->sqid }}" @selected($leadSqid === $u->sqid)>{{ $u->name }}</option>
                @endforeach
            </select>
        </x-form-group>

        <x-form-group :label="__('Beschreibung')" name="description" class="md:col-span-3">
            <textarea name="description" rows="2" maxlength="500"
                      class="textarea textarea-bordered w-full">{{ old('description', $team->description) }}</textarea>
        </x-form-group>
    </div>

    <section class="card bg-base-200/40 mt-3">
        <div class="card-body space-y-2">
            <h3 class="card-title text-base">{{ __('Mitglieder') }}</h3>
            <p class="text-xs text-base-content/60">{{ __('Der Teamleiter wird automatisch als Mitglied geführt.') }}</p>

            @php($selectedMembers = (array) old('member_ids', array_map(fn($id) => \App\Support\Sqid::encode(\App\Models\User::class, $id), $assignedMemberIds)))
            <x-user-checklist
                name="member_ids"
                :users="$orgUsers"
                :selected="$selectedMembers"
                :placeholder="__('Mitarbeiter suchen…')"
                :empty-text="__('Keine Benutzer vorhanden.')" />
        </div>
    </section>
</x-modal>
