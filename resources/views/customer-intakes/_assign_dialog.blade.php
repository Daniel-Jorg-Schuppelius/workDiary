{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _assign_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Zuständigkeit (MVP-1075). --}}
<x-modal
    :title="__('customer_intake.action.assign')"
    :eyebrow="$intake->number"
    icon="person_add"
    :action="route('customer-intakes.assign', $intake)"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    <x-form-group :legend="__('customer_intake.field.assignee')" icon="person" cols="1">
        <x-select-field name="assigned_user_id" :label="__('customer_intake.field.assignee')">
            <option value="">{{ __('customer_intake.assign.nobody') }}</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected((string) old('assigned_user_id', \App\Support\Sqid::encode(\App\Models\Platform\User::class, $intake->assigned_user_id)) === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-validation-errors />
</x-modal>
