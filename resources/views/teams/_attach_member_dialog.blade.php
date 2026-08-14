{{--
  Created on   : Thu Jun 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _attach_member_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Mitglied einem Team hinzufügen. --}}
@php
    /** @var \App\Models\Team $team */
    /** @var \Illuminate\Database\Eloquent\Collection $addableUsers */
@endphp
<x-modal
    :title="__('Mitglied hinzufügen')"
    :eyebrow="$team->name"
    icon="person_add"
    tone="primary"
    size="md"
    :action="route('teams.members.attach', $team)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Hinzufügen')"
>
    @if ($addableUsers->isEmpty())
        <p class="text-sm text-base-content/60">{{ __('Alle Benutzer sind bereits Mitglied.') }}</p>
    @else
        <x-form-group :label="__('Mitglied')" name="user_id" required>
            <x-user-select name="user_id" :users="$addableUsers" value-key="sqid" required />
        </x-form-group>
    @endif
</x-modal>
