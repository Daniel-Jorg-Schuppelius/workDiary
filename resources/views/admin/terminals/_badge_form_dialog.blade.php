{{--
  Created on   : Mon Aug 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _badge_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: RFID-/NFC-Badge einem Nutzer zuordnen (Feature 061). --}}
@php
    /** @var \Illuminate\Support\Collection $users */
@endphp
<x-modal
    :title="__('terminal.action.assign')"
    :eyebrow="__('terminal.badges_heading')"
    icon="badge"
    tone="primary"
    :action="route('admin.terminals.badges.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('terminal.action.assign')">

    <x-form-group :label="__('terminal.badge.user')" name="user">
        <select name="user" class="select select-bordered w-full" required>
            @foreach ($users as $user)
                <option value="{{ $user['sqid'] }}">{{ $user['name'] }}</option>
            @endforeach
        </select>
    </x-form-group>
    <x-form-group :label="__('terminal.badge.uid')" name="badge_uid">
        <input type="text" name="badge_uid" value="{{ old('badge_uid') }}" placeholder="{{ __('terminal.badge.uid_placeholder') }}" class="input input-bordered w-full" required>
        <span class="label-text-alt text-muted">{{ __('terminal.badge.uid_help') }}</span>
    </x-form-group>
    <x-form-group :label="__('terminal.badge.label')" name="label">
        <input type="text" name="label" value="{{ old('label') }}" class="input input-bordered w-full">
    </x-form-group>
    <x-date-range layout="split" form-control
                  from-name="valid_from" to-name="valid_until" type="date"
                  :from="old('valid_from')" :to="old('valid_until')"
                  :from-label="__('terminal.badge.valid_from')"
                  :to-label="__('terminal.badge.valid_until')" />
</x-modal>
